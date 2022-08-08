<?php
function import_log($message)
{
	GLOBAL $start_time;
	file_put_contents("log/$start_time.txt", $message, FILE_APPEND | LOCK_EX);
}
function import_warn($message)
{
	GLOBAL $start_time;
	file_put_contents("log/".$start_time."_warning.txt", $message, FILE_APPEND | LOCK_EX);
}
function import_error($message)
{
	GLOBAL $start_time;
	file_put_contents("log/".$start_time."_error.txt", $message, FILE_APPEND | LOCK_EX);
}
function log_changes($message)
{
	GLOBAL $debug;
	if($debug)
		echo $message;
}

//Recursively gets all child directories, organized into three arrays with the keys
//"albums", "artists" and "dirs" based on the presence of .nfo files in the dirs
function initialscan($dirstr)
{
	GLOBAL $artist_info_file;
	GLOBAL $album_info_file;

	if($dirstr[strlen($dirstr)-1] == "/")
		$dirstr = substr($dirstr, 0, strlen($dirstr)-1);

	$ret = ["artists" => [], "albums" => [], "dirs" => [], "multiartists" => []];

	if(file_exists("$dirstr/.nomedia"))
	{
		return $ret;
	}
	if(file_exists("$dirstr/artists.nfo"))
	{
		$ret["multiartists"][] = "$dirstr/artists.nfo";
	}
	if(file_exists("$dirstr/$artist_info_file"))
	{
		$ret["artists"][] = $dirstr;
		return $ret;
	}
	if(file_exists("$dirstr/$album_info_file"))
	{
		$ret["albums"][] = $dirstr;
		return $ret;
	}

	$cdirs = [];
	$hasFile = false;
	foreach(scandir($dirstr) as $item)
	{
		if($item == "." || $item == "..")
			continue;
		if(is_dir("$dirstr/$item"))
			$cdirs[] = $dirstr."/".$item;
		else
			$hasFile = true;
	}
	if($hasFile)
	{
		$ret["dirs"][] = $dirstr;
	}
	foreach($cdirs as $dir)
	{
		$results = initialscan($dir);
		$ret["artists"] = array_merge($ret["artists"], $results["artists"]);
		$ret["albums"] = array_merge($ret["albums"], $results["albums"]);
		$ret["dirs"] = array_merge($ret["dirs"], $results["dirs"]);
	}
	return $ret;
}

function import_artist($artistdir)
{
	GLOBAL $db;
	GLOBAL $artist_info_file;
	GLOBAL $album_info_file;

	$artistinfo = file_get_contents($artistdir."/$artist_info_file");
	if(!$artistinfo)
	{
		import_error("Empty $artist_info_file: $artistdir\n");
		return false;
	}
	$artist = make_artist_obj($artistinfo);
	if($artist === false)
	{
		import_directory($artistdir);
		return;
	}
	$artist["directory"] = $artistdir;

	//Queue up artist table changes
	//Also, get artist ID for other actions
	$q = "SELECT id,hash FROM artists WHERE directory = '".$db->real_escape_string($artistdir)."';";
	$result = $db->query($q);
	if($result)
	{
		$row = $result->fetch_assoc();
		if($result->num_rows == 0)
		{
			$q = "SELECT id,directory,hash FROM artists WHERE hash = '".$db->real_escape_string($artist["hash"])."';";
			$result = $db->query($q);
			if(!$result)
			{
				import_error("No query result trying to find artist: ".$artistdir." (using hash ".$artist["hash"].")\n");
				return;
			}

			if($result->num_rows == 0)
			{
				import_log("New artist: $artistdir\n");
				log_changes("New artist $artistdir\n");
				$artist["id"] = newid(10);

				data_queueup_artistadd($artist);

				data_assign_artistcountries($artist["id"], $artist["countries"]);
				if(!empty($artist["aliases"]))
					data_assign_artistaliases($artist["id"], $artist["aliases"]);
			}
			else
			{
				//making hash unique in the DB ensures num_rows will only ever be 1
				import_log("Artist update $artistdir\n");
				log_changes("Artist moved from ".$row["directory"]." to $artistdir\n");
				$row = $result->fetch_assoc();
				moved_artist($row["id"], $row["directory"], $artistdir);
				return;
			}
		}
		else if($artist["hash"] != $row["hash"])
		{
			log_changes("Artist update $artistdir\n");
			$artist["id"] = $row["id"];

			data_queueup_artistedit($artist);

			data_assign_artistcountries($artist["id"], $artist["countries"]);
			data_assign_artistaliases($artist["id"], $artist["aliases"]);
		}
		if($artist["favorite"])
			data_queueup_favoriteartist($artist["id"], $artist["directory"]);
	}
	else
	{
		import_error("No query result trying to find artist: ".$artistdir." (using directory)\n");
		return;
	}

	//Get all tracks in DB under artist dir
	$dbtracks = [];
	$where = "filepath LIKE '".$db->real_escape_string($artistdir)."%'";
	$result = $db->query("SELECT id,filepath,hash,last_update FROM songs WHERE $where UNION SELECT id,filepath,hash,last_update FROM videos WHERE $where;");
	if($result)
	{
		//Use filepaths as keys in the array
		while($row = $result->fetch_assoc())
		{
			$dbtracks[$row["filepath"]] = [ "id" => $row["id"], "hash" => $row["hash"], "last_update" => $row["last_update"] ];
		}
	}
	else
	{
		import_error("Error ".$db->error." getting tracks in database for $artistdir: ".$db->error."\n");
	}

	//Process files and album directories
	foreach(scandir($artistdir) as $item)
	{
		if($item == "." || $item == "..")
			continue;

		if(is_dir("$artistdir/$item"))
		{
			if(file_exists("$artistdir/$item/.nomedia"))
				continue;

			if(file_exists("$artistdir/$item/$album_info_file"))
				import_album("$artistdir/$item", $dbtracks, $artist);
			else
				import_directory($artistdir."/".$item, $dbtracks, false, $artist);
		}
		else
		{
			$blah = null;
			import_file($artistdir."/".$item, $dbtracks, $blah, $artist);
		}
	}
	//Any tracks still in query set have been deleted
	foreach($dbtracks as $fpath => $pair)
	{
		$type = audio_or_video($fpath);
		if($type == "audio")
		{
			log_changes("Song removed $fpath\n");
			data_queueup_songremove($pair["id"]);
		}
		else if($type == "video")
		{
			log_changes("Video removed $fpath\n");
			data_queueup_videoremove($pair["id"], $fpath);
		}
	}
}
function moved_artist($artistid, $olddir, $newdir)
{
	GLOBAL $db;
	data_queueup_artistmove($row["id"], $artistdir);

	foreach(scandir($artistdir) as $item)
	{
		if($item == "." || $item == "..")
			continue;

		if(is_dir("$artistdir/$item"))
		{
			if(file_exists("$artistdir/$item/.nomedia"))
				continue;

			if(file_exists("$artistdir/$item/$album_info_file"))
				moved_album($olddir, "$artistdir/$item");
			else
				import_directory($artistdir."/".$item, $dbtracks, false, $artist);
		}
		else
		{
			$f = "$artistdir/$item";
			$h = hash_file("sha1", $f);
			$type = audio_or_video($f);
			if($type == "audio")
				$q = "SELECT id FROM songs WHERE hash = ".$db->real_escape_string($h).";";
			else if($type == "video")
				$q = "SELECT id FROM videos WHERE hash = ".$db->real_escape_string($h).";";
			else
				continue;
			$result = $db->query($q);
			if($result->num_rows == 0)
			{
				import_error("Error getting ID for moved file: $f\n");
				continue;
			}
			$row = $result->fetch_assoc();
			if($type == "audio")
				data_queueup_songmove($row["id"], $f);
			else if($type == "video")
				data_queueup_videomove($row["id"], $f);
		}
	}
}

function import_album($albumdir, &$dbtracks = null, &$artistObj = null)
{
	GLOBAL $db;
	GLOBAL $album_info_file;
	$newalbum = false;
	$updated = false;
	$defer_dbtracks_removal = false;

	$album = make_album_obj($albumdir);
	if($album === false)
	{
		$blah = null;
		import_directory($albumdir, $blah, false, $artistObj);
		return;
	}

	$primaryname="";
	if(strpos($album["names"], ";") !== false)
		$primaryname = substr($album["names"], 0, strpos($album["names"], ";"));
	else
		$primaryname = $album["names"];

	//Get album ID and hash
	$oldhash;
	$q = "SELECT id,hash FROM albums WHERE directory = '".$db->real_escape_string($albumdir)."';";
	$result = $db->query($q);
	if(!$result)
	{
		import_error("No query result trying to find album: ".$albumdir." (using name ".$primaryname.")\n");
		return;
	}
	if($result->num_rows == 0)
	{
		$q = "SELECT id,directory FROM albums WHERE hash = '".$db->real_escape_string($album["hash"])."';";
		$result = $db->query($q);
		if($result === false)
		{
			import_error("Error ".$db->errno." searching for album by hash: ".$db->error."\n");
			return;
		}
		if($result->num_rows == 0)
		{
			//Set the ID now since songs need it, but defer checking update time since song updates force an album update
			log_changes("New album $albumdir\n");
			$newalbum = true;
			$album["id"] = newid(10);
		}
		else
		{
			//making hash unique in the DB ensures num_rows will only ever be 1
			$row = $result->fetch_assoc();
			import_log("Album update $albumdir\n");
			moved_album($row["directory"], $albumdir, $row["id"]);
			return;
		}
	}
	else
	{
		//Set the ID now since songs need it, but defer checking update time since song updates force an album update
		$row = $result->fetch_assoc();
		$album["id"] = $row["id"];
		$oldhash = $row["hash"];
		//TODO - When the album import date is updated, this forces an update on all the songs
	}
	import_log("Found album: $albumdir\n");

	//Get tracks in DB under album dir
	if(is_null($dbtracks))
	{
		$dbtracks = [];
		$where = "filepath LIKE '".$db->real_escape_string($albumdir."/")."%'";
		$q = "SELECT id,filepath,hash,last_update FROM songs WHERE $where UNION SELECT id,filepath,hash,last_update FROM videos WHERE $where;";
		$result = $db->query($q);
		if($result === false)
		{
			import_error("Error ".$db->errno." getting tracks in database for $albumdir: ".$db->error."\n");
			return;
		}

		//Use filepaths as keys in the array
		while($row = $result->fetch_assoc())
		{
			$dbtracks[$row["filepath"]] = [ "id" => $row["id"], "hash" => $row["hash"], "last_update" => $row["last_update"] ];
		}
	}
	else
	{
		$defer_dbtracks_removal = true;
	}

	//Process files
	foreach(scandir($albumdir) as $item)
	{
		if($item == "." || $item == ".." || is_dir($albumdir."/".$item))
			continue;

		import_file($albumdir."/".$item, $dbtracks, $album, $artistObj);
	}

	//Now check if the album's been updated
	if($newalbum)
	{
		//importing songs might clear the hash to force an album update, so reset the hash
		if(empty($album["hash"]))
			$album["hash"] = hash_file("sha1", "$albumdir/$album_info_file");
		data_queueup_albumadd($album);
	}
	else if($album["hash"] != $oldhash)
	{
		log_changes("Album update $albumdir\n");
		$updated = true;
		//importing songs might clear the hash to force an album update, so reset the hash
		if(empty($album["hash"]))
			$album["hash"] = hash_file("sha1", "$albumdir/$album_info_file");
		data_queueup_albumedit($album);
	}

	if($newalbum || $updated)
	{
		if($album["favorite"])
			data_queueup_favoritealbum($album["id"], $albumdir);

		if($newalbum)
		{
			if(!empty($album["tags"]))
				data_assign_albumtags($album["id"], $album["tags"]);
			if(!empty($album["genres"]))
				data_assign_albumgenres($album["id"], $album["genres"]);
			if(strpos($album["names"], ";") !== false)
				data_assign_albumaliases($album["id"], substr($album["names"], strpos($album["names"], ";")+1));
		}
		else
		{
			data_assign_albumtags($album["id"], $album["tags"]);
			data_assign_albumgenres($album["id"], $album["genres"]);
			data_assign_albumaliases($album["id"], substr($album["names"], strpos($album["names"], ";")+1));
		}

		if(!$defer_dbtracks_removal)
		{
			//Any tracks still in query set have been deleted
			foreach($dbtracks as $fpath => $pair)
			{
				$type = audio_or_video($fpath);
				if($type == "audio")
					data_queueup_songremove($pair["id"]);
				else if($type == "video")
					data_queueup_videoremove($pair["id"], $fpath);
			}
		}
	}
}
function moved_album($olddir, $newdir, $id = null)
{
	GLOBAL $db;
	GLOBAL $album_info_file;

	if(is_null($id))
	{
		$h = hash_file("sha1", "$newdir/$album_info_file");
		$q = "SELECT id FROM albums WHERE hash = '".$db->real_escape_string($h)."';";
		$result = $db->query($q);
		if($result === false || $result->num_rows == 0)
		{
			import_log("Error getting moved album's ID: $newdir\n");
			return;
		}
		$row = $result->fetch_assoc();
		$id = $row["id"];
	}
	data_queueup_albummove($id, $newdir);

	foreach(scandir($newdir) as $item)
	{
		if($item == "." || $item == "..")
			continue;

		if(is_dir("$newdir/$item"))
			continue;

		$f = "$newdir/$item";
		$h = hash_file("sha1", $f);
		$type = audio_or_video($f);
		if($type == "audio")
			$q = "SELECT id FROM songs WHERE hash = ".$db->real_escape_string($h).";";
		else if($type == "video")
			$q = "SELECT id FROM videos WHERE hash = ".$db->real_escape_string($h).";";
		else
			continue;
		$result = $db->query($q);
		if($result->num_rows == 0)
		{
			import_error("Error getting ID for moved file: $f\n");
			continue;
		}
		$row = $result->fetch_assoc();
		if($type == "audio")
			data_queueup_songmove($row["id"], $f);
		else if($type == "video")
			data_queueup_videomove($row["id"], $f);
	}
}

function import_directory($dirstr, &$dbtracks = null, $cleanDbTracks = true, &$artistObj = null)
{
	GLOBAL $db;
	import_log("directory: $dirstr\n");

	//Get tracks in DB in this dir
	if(is_null($dbtracks))
	{
		$dbtracks = [];
		$escaped_paren = str_ireplace(")", "\\\\)", str_ireplace("(", "\\\\(", $dirstr));
		$where = "filepath REGEXP '^".$db->real_escape_string($escaped_paren."/")."[^/]+$'";
		$q = "SELECT id,filepath,hash,last_update FROM songs WHERE $where UNION SELECT id,filepath,hash,last_update FROM videos WHERE $where;";
		$result = $db->query($q);
		if($result === false)
		{
			import_error("Error ".$db->errno." getting tracks in database for $dirstr: ".$db->error."\n");
			return;
		}

		if($result->num_rows == 0)
		{
			$olddir = directory_was_moved($dirstr);
			if(!empty($olddir))
			{
				log_changes("Content moved from ($olddir) to ($dirstr)\n");
				import_log("Content moved from $olddir\n");
				moved_directory($olddir, $dirstr);
				return;
			}
		}
		while($row = $result->fetch_assoc())
		{
			$dbtracks[$row["filepath"]] = [ "id" => $row["id"], "hash" => $row["hash"], "last_update" => $row["last_update"] ];
		}
	}

	//Process files
	foreach(scandir($dirstr) as $item)
	{
		if($item == "." || $item == ".." || is_dir($dirstr."/".$item))
			continue;

		$blah = null;
		import_file($dirstr."/".$item, $dbtracks, $blah, $artistObj);
	}

	//False when coming into this function from something like import_artist
	if($cleanDbTracks)
	{
		//Any tracks still in query set have been deleted
		foreach($dbtracks as $fpath => $pair)
		{
			$type = audio_or_video($fpath);
			if($type == "audio")
			{
				log_changes("Song removed $fpath\n");
				data_queueup_songremove($pair["id"]);
			}
			else if($type == "video")
			{
				log_changes("Video removed $fpath\n");
				data_queueup_videoremove($pair["id"], $fpath);
			}
		}
	}
}
function import_file($fpath, &$dbtracks = null, &$albumObj = null, &$artistObj = null)
{
	switch(audio_or_video($fpath))
	{
		case "video":
			import_video($fpath, $dbtracks, $artistObj);
		break;
		case "audio":
			import_audio($fpath, $dbtracks, $albumObj, $artistObj);
		break;

		default:
		break;
	}
}
function import_audio($fpath, &$dbtracks = null, &$albumObj = null, &$artistObj = null)
{
	$trackObj = false;

	if(array_key_exists($fpath, $dbtracks))
	{
		$t = filemtime($fpath);
		if($t > $dbtracks[$fpath]["last_update"])
		{
			$trackObj = make_song_obj($fpath, $albumObj);
			if($trackObj === false)
				return;
			log_changes("Song update $fpath\n");

			$trackObj["id"] = $dbtracks[$fpath]["id"];
			$trackObj["hash"] = hash_file("sha1", $fpath);

			//Queue up DB changes
			data_queueup_songedit($trackObj);
			$i = strpos($trackObj["titles"], "|");
			if($i !== false)
				data_assign_songalias($trackObj["id"], substr($trackObj["titles"], $i+1));
			else
				data_assign_songalias($trackObj["id"], "");

			data_assign_songtags($trackObj["id"], $trackObj["tags"]);

			if(!is_null($artistObj))
				data_assign_songartist_id($trackObj["id"], $artistObj["id"]);
			else
				data_assign_songartists_name($trackObj["id"], $trackObj["artists"]);

			if($trackObj["intro"])
				data_queueup_songintro($trackObj["id"]);

			unset($dbtracks[$fpath]);
		}
		else
		{
			unset($dbtracks[$fpath]);
			return;
		}
	}
	else
	{
		import_log("import song: $fpath\n");
		$trackObj = make_song_obj($fpath, $albumObj);
		if($trackObj === false)
			return;
		log_changes("New song ".$fpath."\n");

		$trackObj["id"] = newid(10);
		$trackObj["hash"] = hash_file("sha1", $fpath);

		data_queueup_songadd($trackObj);
		$i = strpos($trackObj["titles"], "|");
		if($i !== false)
			data_assign_songalias($trackObj["id"], substr($trackObj["titles"], $i+1));

		if(!empty($trackObj["tags"]))
			data_assign_songtags($trackObj["id"], $trackObj["tags"]);

		if(!is_null($artistObj))
			data_assign_songartist_id($trackObj["id"], $artistObj["id"]);
		else if(!empty($trackObj["artists"]))
			data_assign_songartists_name($trackObj["id"], $trackObj["artists"]);

		if($trackObj["intro"])
			data_queueup_songintro($trackObj["id"]);
	}
	if($trackObj["favorite"])
		data_queueup_favoritesong($trackObj["id"], $trackObj["filepath"]);

	if(!is_null($albumObj))
		add_song_data_to_album_obj($trackObj, $albumObj);

	if(!empty($trackObj["cover"]))
		data_queueup_songcover($trackObj["id"], $trackObj["cover"]);
	if(!empty($trackObj["parody"]))
		data_queueup_songparody($trackObj["id"], $trackObj["parody"]);
	if(!empty($trackObj["remix"]))
		data_queueup_songremix($trackObj["id"], $trackObj["remix"]);
	if(!empty($trackObj["rerelease"]))
		data_queueup_songredux($trackObj["id"], $trackObj["rerelease"]);
}
function import_video($fpath, &$dbtracks, &$artistObj = null)
{
	$videoObj = false;
	if(array_key_exists($fpath, $dbtracks))
	{
		$t = filemtime($fpath);
		if($t > $dbtracks[$fpath]["last_update"])
		{
			$videoObj = make_video_obj($fpath);
			if($videoObj !== false)
			{
				log_changes("Video update $fpath\n");

				$videoObj["id"] = $dbtracks[$fpath]["id"];

				data_queueup_videoedit($videoObj);
				if(is_null($artistObj))
					data_assign_videoartists_name($videoObj["id"], $artistObj["id"]);
				data_assign_videotags($videoObj["id"], $videoObj["tags"]);
				if($videoObj["favorite"])
					data_queueup_favoritevideo($videoObj["id"], $videoObj["filepath"]);
			}
		}
		unset($dbtracks[$fpath]);
	}
	else
	{
		$videoObj = make_video_obj($fpath);
		if($videoObj !== false)
		{
			log_changes("New video ".$fpath."\n");
			$videoObj["id"] = newid(10);

			data_queueup_videoadd($videoObj);
			if(!is_null($artistObj))
				data_assign_videoartist_id($videoObj["id"], $artistObj["id"]);
			else
				data_assign_videoartists_name($videoObj["id"], $videoObj["artists"]);
			if(!empty($videoObj["tags"]))
				data_assign_videotags($videoObj["id"], $videoObj["tags"]);
			if($videoObj["favorite"])
				data_queueup_favoritevideo($videoObj["id"], $videoObj["filepath"]);
		}
	}
}
function moved_directory($olddir, $newdir)
{
	GLOBAL $db;

	$escaped_paren = str_ireplace(")", "\\\\)", str_ireplace("(", "\\\\(", $olddir));
	$where = "filepath REGEXP '^".$db->real_escape_string($escaped_paren."/")."[^/]+$'";
	$q = "SELECT id,filepath,hash FROM songs WHERE $where UNION SELECT id,filepath,hash FROM videos WHERE $where;";
	$result = $db->query($q);
	if($result === false || $result->num_rows == 0)
	{
		import_error("Error getting songs from old directory: $olddir\n");
		return;
	}

	//Process files
	while($row = $result->fetch_assoc())
	{
		$fname = substr($row["filepath"], strpos($row["filepath"], "/"));
		$type = audio_or_video($fname);
		if($type == "audio")
			data_queueup_songmove($row["id"], "$newdir/$fname");
		else if($type == "video")
			data_queueup_videomove($row["id"], "$newdir/$fname");
	}
}

function add_song_data_to_album_obj(&$trackObj, &$albumObj)
{
	$force_album_update = false;

	//Update album artists
	if(!empty($trackObj["artists"]))
	{
		$force_album_update = true;
		if(empty($albumObj["artists"]))
		{
			$albumObj["artists"] = $trackObj["artists"];
		}
		else
		{
			if(empty($albumObj["artists"]))
			{
				$albumObj["artists"] = $trackObj["artists"];
			}
			else if(stripos($trackObj["artists"], "|") !== false)
			{
				foreach(explode("|", $trackObj["artists"]) as $a)
				{
					if(strstr($albumObj["artists"], $a) === false)
						$albumObj["artists"] .= "|".$a;
				}
			}
			else if(strstr($albumObj["artists"], $trackObj["artists"]) === false)
			{
				$albumObj["artists"] .= "|".$trackObj["artists"];
			}
		}
	}

	//Add guest artists
	if(!empty($trackObj["guest_artists"]))
	{
		$force_album_update = true;
		if(empty($albumObj["artists"]))
		{
			$albumObj["artists"] = $trackObj["guest_artists"];
		}
		else if(stripos($trackObj["guest_artists"], "|") !== false)
		{
			foreach(explode("|", $trackObj["guest_artists"]) as $a)
			{
				if(strstr($albumObj["artists"], $a) === false)
					$albumObj["artists"] .= "|".$trackObj["guest_artists"];
			}
		}
		else if(strstr($albumObj["artists"], $trackObj["guest_artists"]) === false)
		{
			$albumObj["artists"] .= "|".$trackObj["guest_artists"];
		}
	}

	//Update album genres
	if(!empty($trackObj["genre"]))
	{
		$force_album_update = true;
		if(empty($albumObj["genres"]))
		{
			$albumObj["genres"] = $trackObj["genre"];
		}
		else if(stripos($albumObj["genres"], $trackObj["genre"]) === false)
		{
			$albumObj["genres"] .= "|".$trackObj["genre"];
		}
	}

	//Force album update as well
	if($force_album_update)
		$albumObj["hash"] = "";
}

function directory_was_moved($dir)
{
	GLOBAL $db;
	//Grab some file
	$firstfile = get_first_songvideo($dir);
	if(is_null($firstfile))
		return "";
	$h = hash_file("sha1", $firstfile);
	$type = audio_or_video($firstfile);

	//See if it has already been imported
	if($type == "audio")
		$q = "SELECT filepath FROM songs WHERE hash = '".$db->real_escape_string($h)."';";
	else
		$q = "SELECT filepath FROM videos WHERE hash = '".$db->real_escape_string($h)."';";
	$res = $db->query($q);
	if($res === false)
		return "";
	if($res->num_rows != 0)
	{
		//File already in DB = directory has been moved
		$row = $res->fetch_assoc();
		$olddir = substr($row["filepath"], 0, strrpos($row["filepath"], "/"));
		return $olddir;
	}

	//File not in database = new directory
	return "";
}




function data_queueup_songadd($data)
{
	GLOBAL $ps_songs;
	$i = strpos($data["titles"], "|");
	if($i !== false)
		$title = substr($data["titles"], 0, $i);
	else
		$title = $data["titles"];
	$ps_songs["new"][] = [
		$data["id"],
		$data["filepath"],
		$data["last_update"],
		$data["hash"],
		$data["album"] ? $data["album"] : null,
		$title,
		$data["genre"],
		$data["guest_artists"],
		$data["track_number"],
		$data["disc_number"],
		$data["release_date"],
		$data["comment"],
		$data["duration"],
		$data["import_date"],
		$data["true_import_date"],
		$data["art"],
		(int)$data["embedded_art"]
	];
}
function data_queueup_songedit($data)
{
	GLOBAL $ps_songs;
	$i = strpos($data["titles"], "|");
	if($i !== false)
		$title = substr($data["titles"], 0, $i);
	else
		$title = $data["titles"];
	$ps_songs["update"][] = [
		$data["last_update"],
		$data["hash"],
		$data["album"] ? $data["album"] : null,
		$title,
		$data["genre"],
		$data["guest_artists"],
		$data["track_number"],
		$data["disc_number"],
		$data["release_date"],
		$data["comment"],
		$data["duration"],
		$data["import_date"],
		$data["art"],
		(int)$data["embedded_art"],
		$data["id"]
	];
}
function data_queueup_songmove($id, $filepath)
{
	GLOBAL $ps_songs;
	$ps_songs["moved"][] = [ $filepath, $id ];
}

function data_queueup_songremove($id)
{
	GLOBAL $ps_songs;
	$ps_songs["remove"][] = $id;
}

function data_assign_songartists_name($songid, $artists)
{
	GLOBAL $songartists;
	$songartists[$songid] = $artists;
}
function data_assign_songartist_id($songid, $artistid)
{
	GLOBAL $songartists_ids;
	$songartists_ids[$songid] = $artistid;
}

function data_assign_songalias($id, $aliases)
{
	GLOBAL $songaliases;
	$songaliases[$id] = $aliases;
}

function data_assign_songtags($id, $tagstring)
{
	GLOBAL $songtags;
	$songtags[$id] = $tagstring;
}

function data_queueup_songintro($id)
{
	GLOBAL $songintros;
	$songintros[] = $id;
}

function data_queueup_favoritesong($id, $filepath)
{
	GLOBAL $favorites;
	$favorites["songs"][$id] = $filepath;
}

function data_queueup_songcover($songid, $subject)
{
	GLOBAL $songcovers;
	$songcovers[$songid] = $subject;
}

function data_queueup_songparody($songid, $subject)
{
	GLOBAL $songparodies;
	$songparodies[$songid] = $subject;
}

function data_queueup_songremix($songid, $subject)
{
	GLOBAL $songremixes;
	$songremixes[$songid] = $subject;
}

function data_queueup_songredux($songid, $subject)
{
	GLOBAL $songrereleases;
	$songrereleases[$songid] = $subject;
}
function data_queueup_videoadd($data)
{
	GLOBAL $ps_videos;
	$ps_videos["new"][] = [
		$data["id"],
		$data["filepath"],
		$data["last_update"],
		$data["hash"],
		$data["titles"],
		$data["genre"],
		$data["guest_artists"],
		$data["duration"],
		$data["release_date"] ? $data["release_date"] : null,
		$data["import_date"],
		$data["true_import_date"],
		$data["type"],
		$data["comment"]
	];
}
function data_queueup_videoedit($data)
{
	GLOBAL $ps_videos;
	$i = strpos($data["titles"], "|");
	if($i !== false)
		$title = substr($data["titles"], 0, $i);
	else
		$title = $data["titles"];
	$ps_videos["update"][] = [
		$data["last_update"],
		$data["hash"],
		$data["titles"],
		$data["genre"],
		$data["artists"],
		$data["guest_artists"],
		$data["duration"],
		$data["release_date"] ? $data["release_date"] : null,
		$data["import_date"],
		$data["type"],
		$data["comment"],
		$data["id"]
	];
}
function data_queueup_videomove($id, $filepath)
{
	GLOBAL $ps_videos;
	$ps_videos["moved"][] = [ $filepath, $id ];
}
function data_queueup_videoremove($id, $filepath)
{
	GLOBAL $ps_videos;
	$ps_videos["remove"][] = [$id];
}
function data_assign_videoartist_id($id, $artist)
{
	GLOBAL $videoartists_ids;
	$videoartists_ids[$id] = $artist;
}
function data_assign_videoartists_name($id, $artists)
{
	GLOBAL $videoartists;
	$videoartists[$id] = $artists;
}
function data_assign_videotags($videoid, $tagstring)
{
	GLOBAL $videotags;
	$videotags[$videoid] = $tagstring;
}

function data_queueup_favoritevideo($videoid, $filepath)
{
	GLOBAL $favorites;
	$favorites["videos"][$videoid] = $filepath;
}

function data_queueup_artistadd($data)
{
	GLOBAL $ps_artists;
	$primaryname = $data["names"];
	$i = strpos($primaryname, "|");
	if($i !== false)
		$primaryname = substr($primaryname, 0, $i);
	$ps_artists["new"][] = [
		$data["id"],
		$data["directory"],
		$data["hash"],
		$primaryname,
		$data["description"],
		$data["locations"],
		$data["external_links"]
	];
}

function data_queueup_artistedit($data)
{
	GLOBAL $ps_artists;
	$primaryname = $data["names"];
	$i = strpos($primaryname, "|");
	if($i !== false)
		$primaryname = substr($primaryname, 0, $i);
	$ps_artists["update"][] = [
		$data["hash"],
		$primaryname,
		$data["description"],
		$data["locations"],
		$data["external_links"],
		$data["id"]
	];
}

function data_queueup_artistmove($id, $newdir)
{
	GLOBAL $ps_artists;
	$ps_artists["moved"][] = [ $newdir, $id ];
}

function data_assign_artistaliases($id, $aliasstring)
{
	GLOBAL $artistaliases;
	$artistaliases[$id] = $aliasstring;
}

function data_assign_artistcountries($id, $countriesstring)
{
	GLOBAL $artistcountries;
	$artistcountries[$id] = $countriesstring;
}

function data_queueup_favoriteartist($id, $artistdir)
{
	GLOBAL $favorites;
	$favorites["artists"][$id] = $artistdir;
}
function data_queueup_albumadd($data)
{
	GLOBAL $ps_albums;
	$primaryname="";
	$i = strpos($data["names"], ";");
	if($i !== false)
		$primaryname = substr($data["names"], 0, $i);
	else
		$primaryname = $data["names"];
	$ps_albums["new"][] = [
		$data["id"],
		$data["directory"],
		$data["hash"],
		$primaryname,
		$data["type"],
		$data["release_date"],
		$data["remaster_date"] ? $data["remaster_date"] : null,
		$data["comment"]
	];
}
function data_queueup_albumedit($data)
{
	GLOBAL $ps_albums;
	$primaryname="";
	$i = strpos($data["names"], ";");
	if($i !== false)
		$primaryname = substr($data["names"], 0, $i);
	else
		$primaryname = $data["names"];
	$ps_albums["update"][] = [
		$data["directory"],
		$data["hash"],
		$primaryname,
		$data["type"],
		$data["release_date"],
		$data["remaster_date"] ? $data["remaster_date"] : null,
		$data["comment"],
		$data["id"]
	];
}
function data_queueup_albummove($id, $directory)
{
	GLOBAL $ps_albums;
	$ps_albums["moved"][] = [ $directory, $id ];
}
function data_queueup_favoritealbum($id, $directory)
{
	GLOBAL $favorites;
	$favorites["albums"][$id] = $directory;
}

function data_assign_albumtags($id, $tagstring)
{
	GLOBAL $albumtags;
	$albumtags[$id] = $tagstring;
}

function data_assign_albumgenres($id, $genrestring)
{
	GLOBAL $albumgenres;
	$albumgenres[$id] = $genrestring;
}

function data_assign_albumaliases($id, $aliasstring)
{
	GLOBAL $albumaliases;
	$albumaliases[$id] = $aliasstring;
}
?>
