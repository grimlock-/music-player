<?php
//TODO - in the event a directory is removed from directories.txt I don't
//think the script will delete the DB entries for the things contained
//in the old directories. Should they be deleted?
//FIXME - Songs with multiple artists inside artist directories will only
//get a single artist in the DB

//Abandon execution if running via CGI
if(isset($_SERVER["REQUEST_SCHEME"]))
	exit();
//If running from API directory, move up one
if(strpos(getcwd(), "/api") !== false)
	chdir("..");

require("config.php");
require("util.php");
require("import.php");
require("getid3/getid3.php");
$debug = false;
$write_db = true;
$print_allowed = false;

function handle_mtm_updates($set, $proc_name)
{
	GLOBAL $db;
	GLOBAL $write_db;
	if(!$write_db)
		return;
	$c = 0;
	$n = count($set);
	foreach($set as $id => $newvalues)
	{
		commandline_print("\r".++$c."/$n");
		$q = "CALL $proc_name('$id','".$db->real_escape_string($newvalues)."');";
		if(!$db->query($q))
			import_error("Error calling $proc_name('$id', '$newvalues'): ".$db->error."\n");
	}
	commandline_print("\n");
}

function import_kill($message)
{
	GLOBAL $start_time;
	if(file_exists("library_scan.lock"))
		unlink("library_scan.lock");
	file_put_contents("log/".$start_time."_error.txt", $message);
	exit();
}

function commandline_print($message)
{
	GLOBAL $print_allowed;
	if($print_allowed)
		echo $message;
}


//Start
commandline_print("Starting import\n");
set_status("Scanning directories");
//Since cli_set_process_title wasn't working
if(file_exists("/proc/".getmypid()."/comm"))
	file_put_contents("/proc/".getmypid()."/comm", "music_scan");
$start_time = strtotime("now");
$dirs_file = "";
$import_modified_date = false;
$mediadirs = [];
$options = getopt("f:p", ["directories-file:", "import-by-last-modified", "debug", "no-db-write", "print"]);

//Command line args
if(isset($options["f"]))
	$dirs_file = $options["f"];
if(isset($options["directories-file"]))
	$dirs_file = $options["directories-file"];
if(isset($options["import-by-last-modified"]))
	$import_modified_date = true;
if(isset($options["debug"]))
{
	$debug = true;
	$write_db = false;
	$print_allowed = true;
}
if(isset($options["no-db-write"]))
	$write_db = false;
if(isset($options["p"]) || isset($options["print"]))
	$print_allowed = true;

if(!empty($dirs_file))
{
	$dirFile = file_get_contents($dirs_file);
	if($dirFile)
	{
		if(stripos($dirFile, "\n") !== false)
			$mediadirs = explode("\n", $dirFile);
		else
			$mediadirs[] = $dirFile;
	}
	else
	{
		import_log("directories file empty: $dirs_file\n");
	}
}

if(count($mediadirs) == 0)
	import_kill("No directories to scan");


//Setup libs
$id3 = new getID3;
$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
if($db->connect_errno)
	import_kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

//Initial scan
commandline_print("Starting initial scan\n");
$albumdirs = [];
$artistdirs = [];
$regulardirs = [];
$multiartist_files = [];
foreach($mediadirs as $dir)
{
	if(empty($dir))
		continue;
	if(!file_exists($dir))
	{
		import_error("Directory does not exist: $dir\n");
		continue;
	}
	$tmp = initialscan($dir);
	$albumdirs = array_merge($albumdirs, $tmp["albums"]);
	$artistdirs = array_merge($artistdirs, $tmp["artists"]);
	$regulardirs = array_merge($regulardirs, $tmp["dirs"]);
	$multiartist_files = array_merge($multiartist_files, $tmp["multiartists"]);
}
commandline_print("initial scan finished\n");
commandline_print("Album dirs: ".count($albumdirs)."\n");
commandline_print("Artist dirs: ".count($artistdirs)."\n");
commandline_print("Generic dirs: ".count($regulardirs)."\n");

$songintros = [];
//Prepared statement objects
$ps_artists = ["new" => [], "update" => [], "moved" => []];
$ps_albums = ["new" => [], "update" => [], "moved" => []];
$ps_songs = ["new" => [], "update" => [], "remove" => [], "moved" => []];
$ps_videos = ["new" => [], "update" => [], "remove" => [], "moved" => []];
$favorites = ["songs" => [], "videos" => [], "albums" => [], "artists" => []];
//many to many table data holders
$artistcountries = [];
$artistaliases = [];
$albumgenres = [];
$albumtags = [];
$albumaliases = [];
$songtags = [];
$songaliases = [];
$songartists = [];
$songartists_ids = [];
$songcovers = [];
$songremixes = [];
$songparodies = [];
$songrereleases = [];
$videoartists = [];
$videoartists_ids = [];
$videotags = [];

//Process files
set_status("Processing general directories");
commandline_print("Scaning directories\n");
foreach($regulardirs as $dir)
{
	import_directory($dir);
}
set_status("Processing album directories");
commandline_print("Scaning albums\n");
foreach($albumdirs as $dir)
{
	import_album($dir);
}
set_status("Processing artist directories");
commandline_print("Scaning artists\n");
foreach($artistdirs as $dir)
{
	import_artist($dir);
}
foreach($multiartist_files as $file)
{
	import_log("Scanning artist text file: $file\n");
	//$set = parse_multiartist_file($file);
	$configstr = "";
	$artists = [];
	foreach(file($file) as $line)
	{
		if(substr($line, 0, 5) == "name=" || substr($line, 0, 6) == "names=")
		{
			if(!empty($configstr))
			{
				$a = make_artist_obj(mb_trim($configstr));
				if($a !== false)
				{
					$a["directory"] = null;
					array_push($artists, $a);
				}
				$configstr = "";
			}
		}

		$configstr .= $line;
	}
	if(!empty($configstr))
	{
		$a = make_artist_obj(mb_trim($configstr));
		if($a !== false)
		{
			$a["directory"] = null;
			array_push($artists, $a);
		}
	}

	foreach($artists as $a)
	{
		$result = $db->query("SELECT GetArtistId_Count('".$db->real_escape_string($a["primaryname"])."');");
		if($result === false)
		{
			commandline_print("Error checking existance of artist ".$a["primaryname"].".");
			continue;
		}
		$n = $result->fetch_row()[0];
		if($n == 1)
		{
			$result = $db->query("SELECT id,hash FROM artists WHERE id = GetArtistId_Exact('".$db->real_escape_string($a["primaryname"])."');");
			if($result === false)
			{
				commandline_print("Error getting last hash for artist ".$a["primaryname"]);
				continue;
			}
			$vals = $result->fetch_assoc();
			if($a["hash"] != $vals["hash"])
			{
				$a["id"] = $vals["id"];
				log_changes("Updating artist ".$a["primaryname"]."\n");
				data_queueup_artistedit($a);
				data_assign_artistaliases($a["id"], $a["aliases"]);
				data_assign_artistcountries($a["id"], $a["countries"]);
			}
			if($a["favorite"])
				data_queueup_favoriteartist($a["id"], "");
		}
		else if($n == 0)
		{
			import_log("New artist: ".$a["primaryname"]."\n");
			log_changes("New artist: ".$a["primaryname"]."\n");
			$a["id"] = newid(10);
			data_queueup_artistadd($a);
			data_assign_artistaliases($a["id"], $a["aliases"]);
			data_assign_artistcountries($a["id"], $a["countries"]);
			if(isset($a["favorite"]))
				data_queueup_favoriteartist($a["id"], "");
		}
	}
}


//Run SQL stuff
commandline_print("Adding new artists\n");
set_status("Adding artists");
run_prepared_statement("$dbname.artists", $ps_artists["new"],
	"INSERT INTO *TABLE*(id,directory,hash,name,description,locations,external_links) VALUES(?,?,?,?,?,?,?);",
	function($data) {
		$name = explode("|", $data[3])[0];
		import_log("Added artist $name (".$data[0].") from ".$data[1]."\n");
	},
	function($data) use($db, &$favorites, &$ps_artists, &$artistcountries, &$artistaliases) {
		if(is_null($data))
		{
			foreach($ps_artists["new"] as $entry)
			{
				if(array_key_exists($entry[0], $artistcountries))
					unset($artistcountries[$entry[0]]);
				if(array_key_exists($entry[0], $artistaliases))
					unset($artistaliases[$entry[0]]);
				if(array_key_exists($entry[0], $favorites["artists"]))
					unset($favorites["artists"][$entry[0]]);
			}
		}
		else
		{
			$name = $data[3];
			$id = $data[0];
			import_error("Error ".$db->errno." adding artist $name ($id): ".$db->error."\n");
			if(array_key_exists($id, $artistcountries))
				unset($artistcountries[$id]);
			if(array_key_exists($id, $artistaliases))
				unset($artistaliases[$id]);
			if(array_key_exists($id, $favorites["artists"]))
				unset($favorites["artists"][$id]);
		}
	});
commandline_print("Updating artists\n");
run_prepared_statement("$dbname.artists", $ps_artists["update"],
	"UPDATE *TABLE* SET hash=?,name=?,description=?,locations=?,external_links=? WHERE id=?;",
	function($data) {
		import_log("Updated artist ".explode("|", $data[1])[0]." (".$data[5].")\n");
	},
	function($data) use($db) {
		if(!is_null($data))
			import_error("Error ".$db->errno." updating artist ".explode("|", $data[0])[0].": ".$db->error."\n");
	});
run_prepared_statement("$dbname.artists", $ps_artists["moved"],
	"UPDATE *TABLE* SET directory=? WHERE id=?;",
	function($data) {
		import_log("Updated artist ".explode("|", $data[0])[0]."\n");
	},
	function($data) use($db) {
		if(!is_null($data))
			import_error("Error ".$db->errno." updating artist ".explode("|", $data[0])[0].": ".$db->error."\n");
	});




commandline_print("Adding new albums\n");
set_status("Adding albums");
run_prepared_statement("$dbname.albums", $ps_albums["new"],
	"INSERT INTO *TABLE* (id,directory,hash,title,type,release_date,remaster_date,comment) VALUES(?,?,?,?,?,?,?,?);",
	function($data) {
		import_log("Added album ".explode(";", $data[1])[0]."\n");
	},
	function($data) use($db, &$favorites, &$ps_albums, &$albumgenres, &$albumtags, &$albumaliases) {
		if(is_null($data))
		{
			foreach($ps_albums["new"] as $entry)
			{
				if(array_key_exists($entry[0], $albumtags))
					unset($albumtags[$entry[0]]);
				if(array_key_exists($entry[0], $albumgenres))
					unset($albumgenres[$entry[0]]);
				if(array_key_exists($entry[0], $albumaliases))
					unset($albumaliases[$entry[0]]);
				if(array_key_exists($entry[0], $favorites["albums"]))
					unset($favorites["albums"][$entry[0]]);
			}
		}
		else
		{
			$name = $data[3];
			$id = $data[0];
			import_error("Error ".$db->errno." adding album $name ($id): ".$db->error."\n");
			if(array_key_exists($id, $albumtags))
				unset($albumtags[$id]);
			if(array_key_exists($id, $albumgenres))
				unset($albumgenres[$id]);
			if(array_key_exists($id, $albumaliases))
				unset($albumaliases[$id]);
			if(array_key_exists($id, $favorites["albums"]))
				unset($favorites["albums"][$id]);
		}
	});
commandline_print("Updating albums\n");
run_prepared_statement("$dbname.albums", $ps_albums["update"],
	"UPDATE *TABLE* SET directory=?,hash=?,title=?,type=?,release_date=?,remaster_date=?,comment=? WHERE id=?",
	function($data) {
		import_log("Updated album ".explode(";", $data[0])[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating albums UPDATE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." updating album ".explode(";", $data[0])[0].": ".$db->error."\n");
	});
run_prepared_statement("$dbname.albums", $ps_albums["moved"],
	"UPDATE *TABLE* SET directory=? WHERE id=?",
	function($data) {
		import_log("Updated album ".explode(";", $data[0])[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating albums UPDATE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." updating album ".explode(";", $data[0])[0].": ".$db->error."\n");
	});




commandline_print("Adding new songs\n");
set_status("Adding songs");
run_prepared_statement("$dbname.songs", $ps_songs["new"],
	"INSERT INTO *TABLE* (id,filepath,last_update,hash,album_id,title,genre,guest_artists,track_number,disc_number,release_date,comment,duration,import_date,true_import_date,art,embedded_art) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
	function($data) {
		import_log("Added song ".$data[1]."\n");
	},
	function($data) use($db, &$favorites, &$ps_songs, &$songtags, &$songaliases, &$songartists, &$songcovers, &$songremixes, &$songparodies, &$songrereleases) {
		if(is_null($data))
		{
			import_error("Error ".$db->errno." creating song INSERT prepared statement: ".$db->error."\n");
			foreach($ps_songs["new"] as $entry)
			{
				if(array_key_exists($entry[0], $songtags))
					unset($songtags[$entry[0]]);
				if(array_key_exists($entry[0], $songaliases))
					unset($songaliases[$entry[0]]);
				if(array_key_exists($entry[0], $songartists))
					unset($songartists[$entry[0]]);
				if(array_key_exists($entry[0], $songcovers))
					unset($songcovers[$entry[0]]);
				if(array_key_exists($entry[0], $songremixes))
					unset($songremixes[$entry[0]]);
				if(array_key_exists($entry[0], $songparodies))
					unset($songparodies[$entry[0]]);
				if(array_key_exists($entry[0], $songrereleases))
					unset($songrereleases[$entry[0]]);

				if(array_key_exists($entry[0], $favorites["songs"]))
					unset($favorites["songs"][$entry[0]]);
			}
		}
		else
		{
			$fpath = $data[1];
			$id = $data[0];
			import_error("Error ".$db->errno." adding $fpath ($id): ".$db->error."\n");
			if(array_key_exists($id, $songtags))
				unset($songtags[$id]);
			if(array_key_exists($id, $songaliases))
				unset($songaliases[$id]);
			if(array_key_exists($id, $songartists))
				unset($songartists[$id]);
			if(array_key_exists($id, $songcovers))
				unset($songcovers[$id]);
			if(array_key_exists($id, $songremixes))
				unset($songremixes[$id]);
			if(array_key_exists($id, $songparodies))
				unset($songparodies[$id]);
			if(array_key_exists($id, $songrereleases))
				unset($songrereleases[$id]);

			if(array_key_exists($id, $favorites["songs"]))
				unset($favorites["songs"][$id]);
		}
	});
commandline_print("Updating songs\n");
run_prepared_statement("$dbname.songs", $ps_songs["update"],
	"UPDATE *TABLE* SET last_update=?,hash=?,album_id=?,title=?,genre=?,guest_artists=?,track_number=?,disc_number=?,release_date=?,comment=?,duration=?,import_date=?,art=?,embedded_art=? WHERE id=?",
	function($data) {
		import_log("Updated song ".explode("|", $data[0])[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating songs UPDATE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." updating song ".explode("|", $data[0])[0].": ".$db->error."\n");
	});
run_prepared_statement("$dbname.songs", $ps_songs["moved"],
	"UPDATE *TABLE* SET filepath=? WHERE id=?",
	function($data) {
		import_log("Updated song ".explode("|", $data[0])[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating songs UPDATE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." updating song ".explode("|", $data[0])[0].": ".$db->error."\n");
	});

commandline_print("Deleting songs\n");
run_prepared_statement("$dbname.songs", $ps_songs["remove"],
	"DELETE FROM *TABLE* WHERE id=?",
	function($data) {
		import_log("Removed song ".$data[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating songs DELETE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." deleting song ".$data[0].": ".$db->error."\n");
	});




commandline_print("Adding new videos\n");
set_status("Adding videos");
run_prepared_statement("$dbname.videos", $ps_videos["new"],
	"INSERT INTO *TABLE* (id,filepath,last_update,hash,titles,genre,guest_artists,duration,release_date,import_date,true_import_date,type,comment) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
	function($data) {
		import_log("Added video ".$data[1]."\n");
	},
	function($data) use($db, &$favorites, &$ps_videos, &$videotags) {
		if(is_null($data))
		{
			import_error("Error ".$db->errno." creating videos INSERT prepared statement: ".$db->error."\n");
			foreach($ps_videos["new"] as $entry)
			{
				if(array_key_exists($entry[0], $videotags))
					unset($videotags[$entry[0]]);
				if(array_key_exists($entry[0], $favorites["videos"]))
					unset($favorites["videos"][$entry[0]]);
			}
		}
		else
		{
			$fpath = $data[1];
			$id = $data[0];
			import_error("Error ".$db->errno." adding $fpath ($id): ".$db->error."\n");
			if(array_key_exists($id, $videotags))
				unset($videotags[$id]);
			if(array_key_exists($id, $favorites["videos"]))
				unset($favorites["videos"][$id]);
		}
	});
commandline_print("Updating videos\n");
run_prepared_statement("$dbname.videos", $ps_videos["update"],
	"UPDATE *TABLE* SET last_update=?,hash=?,titles=?,genre=?,artists=?,guest_artists=?,duration=?,release_date=?,import_date=?,type=?,comment=? WHERE id=?",
	function($data) {
		import_log("Updated video ".$data[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating videos UPDATE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." updating video ".explode("|", $data[0])[0].": ".$db->error."\n");
	});
run_prepared_statement("$dbname.videos", $ps_videos["moved"],
	"UPDATE *TABLE* SET filepath=? WHERE id=?",
	function($data) {
		import_log("Updated video ".$data[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating videos UPDATE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." updating video ".explode("|", $data[0])[0].": ".$db->error."\n");
	});

commandline_print("Removing videos\n");
run_prepared_statement("$dbname.videos", $ps_videos["remove"],
	"DELETE FROM *TABLE* WHERE id=?",
	function($data) {
		import_log("Removed video ".$data[0]."\n");
	},
	function($data) use($db) {
		if(is_null($data))
			import_error("Error ".$db->errno." creating albums DELETE prepared statement: ".$db->error."\n");
		else
			import_error("Error ".$db->errno." deleting album ".explode("|", $data[0])[0].": ".$db->error."\n");
	});




//Favorites
$ps_newfavs = [];
$result = $db->query("SELECT id,type FROM favorites;");
if($result)
{
	$favs = $result->fetch_all(MYSQLI_ASSOC);
	foreach($favs as $key => $row)
	{
		if(array_key_exists($row["id"], $favorites[$row["type"]."s"]))
			unset($favorites[$row["type"]."s"][$row["id"]]);
	}
	foreach($favorites["artists"] as $artistid => $blah)
		$ps_newfavs[] = [$artistid, "artist"];
	foreach($favorites["albums"] as $artistid => $blah)
		$ps_newfavs[] = [$artistid, "album"];
	foreach($favorites["songs"] as $artistid => $blah)
		$ps_newfavs[] = [$artistid, "song"];
	foreach($favorites["videos"] as $artistid => $blah)
		$ps_newfavs[] = [$artistid, "video"];

	commandline_print("Adding favorites\n");
	set_status("Setting favorites");
	if(!empty($ps_newfavs))
	{
		run_prepared_statement("$dbname.favorites", $ps_newfavs,
			"INSERT INTO *TABLE* (id,type) VALUES(?,?);",
			function($data) {
				import_log("Added ".$data[1]." ".$data[0]." to favorites\n");
			},
			function($data) {
				if(!is_null($data))
					import_error("Error ".$db->errno." adding ".$data[1]." ".$data[2]." to favorites: ".$db->error."\n");
			});
	}
}




//artist countries
commandline_print("Artist countries\n");
set_status("Adding artist countries");
handle_mtm_updates($artistcountries, "update_artist_countries");
//artist aliases
commandline_print("Artist aliases\n");
set_status("Adding artist aliases");
handle_mtm_updates($artistaliases, "update_artist_aliases");

//album genres
commandline_print("Album genres\n");
set_status("Adding album genres");
handle_mtm_updates($albumgenres, "update_album_genres");
//album tags
commandline_print("Album tags\n");
set_status("Adding album tags");
handle_mtm_updates($albumtags, "update_album_tags");
//album aliases
commandline_print("Album Aliases\n");
set_status("Adding album aliases");
handle_mtm_updates($albumaliases, "update_album_aliases");

//song tags
commandline_print("Song tags\n");
set_status("Adding song tags");
handle_mtm_updates($songtags, "update_song_tags");
//song aliases
commandline_print("Song aliases\n");
set_status("Updating song aliases");
handle_mtm_updates($songaliases, "update_song_aliases");
//song artists
commandline_print("Song artists (by name)\n");
set_status("Updating song artists");
handle_mtm_updates($songartists, "update_song_artists");
//song artists (by ID)
commandline_print("Song artists (by ID)\n");
handle_mtm_updates($songartists_ids, "assign_song_artist");

//video artists (by ID)
commandline_print("Song artists (by ID)\n");
handle_mtm_updates($videoartists_ids, "assign_video_artist");
//video artists
commandline_print("Song artists\n");
handle_mtm_updates($videoartists, "update_video_artists");
//video tags
commandline_print("Video tags\n");
set_status("Adding video tags");
handle_mtm_updates($videotags, "update_video_tags");





//song intros
/*commandline_print("Song intros\n");
set_status("Updating song intros");
$q = "CALL add_intro_songs('";
foreach($songintros as $id)
{
	$q .= "$id,";
}
$q = substr($q, 0, strlen($q)-1);
$q .= ");";
if(!$db->query($q))
	import_error("Error calling add_intro_songs(): ".$db->error."\n");




//Resolve parodies covers and the like
$resolve_these = [];
commandline_print("Covers\n");
foreach($songcovers as $id => $subject)
{
}
commandline_print("Parodies\n");
foreach($songparodies as $id => $subject)
{
}
commandline_print("Remixes\n");
foreach($songremixes as $id => $subject)
{
}
commandline_print("Re-releases\n");
foreach($songrereleases as $id => $original)
{
}*/


if(file_exists("api/library_scan.lock"))
	unlink("api/library_scan.lock");
?>
