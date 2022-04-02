<?php
function kill($message)
{
	header("Content-Type: application/json");
	$response = ["error_message"=>$message];
	echo json_encode($response);
	exit;
}

//https://www.php.net/manual/en/ref.mbstring.php
function mb_trim($string, $trim_chars = '\s')
{
	return preg_replace('/^['.$trim_chars.']*(?U)(.*)['.$trim_chars.']*$/u', '\\1',$string);
}

function newid($length)
{
	$_tokenpool = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_";
	$id = "";
	if($length <= 3)
		return $id;
	for($j = 0; $j < $length; $j++)
	{
		$id .= $_tokenpool[random_int(0, strlen($_tokenpool)-1)];
	}
	return $id;
}

function format_date($input)
{
	switch(strlen($input))
	{
		case 4:
			return $input."-00-00";
		case 7:
			return $input."-00";
		case 10:
			return $input;
		default:
			return "0000-00-00";
	}
}

function audio_or_video($fpath)
{
	$ext = strrchr($fpath, ".");
	if($ext === false)
		return false;

	switch($ext)
	{
		case ".mp3":
		case ".m4a":
		case ".aac":
		case ".ogg":
		case ".oga":
		case ".flac":
		case ".ape":
		case ".wav":
			return "audio";
		break;

		case ".mkv":
		case ".mp4":
		case ".webm":
		case ".avi":
		case ".mpeg":
		case ".mpg":
		case ".mov":
		case ".ts":
			return "video";
		break;
	}

	return false;
}

function run_prepared_statement($table, &$dataset, string $statement, Closure $cb_success = null, Closure $cb_fail = null)
{
	GLOBAL $db;
	GLOBAL $write_db;
	$n = count($dataset);
	$counter = 0;

	if($n == 0)
		return;

	if(!$write_db)
	{
		$statement_file = fopen("log/debugdb_$table.txt", "a");
		fwrite($statement_file, "$statement\n");
		foreach($dataset as $item)
		{
			$st = "";
			foreach($item as $blah)
			{
				$st .= " $blah ()";
			}
			fwrite($statement_file, "$st\n");
		}
		fclose($statement_file);
		return;
	}

	$statement = str_replace("*TABLE*", $table, $statement);

	$ps = $db->prepare($statement);
	if($ps === false)
	{
		$type = substr($statement, 0, stripos($statement, " "));
		import_error("Error ".$db->errno." creating $type prepared statement for '$table' table: ".$db->error."\n");
		$cb_fail(null);
		return;
	}

	$params = "";
	foreach($dataset[0] as $element)
	{
		switch(gettype($element))
		{
			case "string":
				$params .= 's';
			break;
			case "integer":
				$params .= 'i';
			break;
			case "NULL":
				$params .= 's';
			break;
			default:
				$str = "Prepared statement arguments must be strings, integers or null, got ".gettype($element);
				if(gettype($element) != "array")
					$str .= "($element)";
				import_error("$str\n");
				import_error(print_r($dataset[0], true));
				return;
			break;
		}
	}

	foreach($dataset as $item)
	{
		commandline_print("\r".++$counter."/$n");

		//heh heh.... "splat" operator
		$ps->bind_param($params, ...$item);

		if($ps->execute())
		{
			if(!is_null($cb_success))
				$cb_success($item);
		}
		else
		{
			if(!is_null($cb_fail))
				$cb_fail($item);
		}
	}
	commandline_print("\n");
}

//Takes an array holding values for one of the many to many tables (artist countries, album genres, etc.)
//and a list of the distinct entries already in the associated table in order to setup the object fed
//to the INSERT and DELETE prepared statements. UPDATEs are done using stored procedures, so are kept
//in the first argument
function setup_ps_object(&$keyed_holder, &$distinctids, &$ps_object, $delimiter = "|")
{
	$tmp = [];
	foreach($distinctids as $row)
	{
		$id = $row[0];
		if(array_key_exists($id, $keyed_holder))
		{
			if(empty($keyed_holder[$id]))
			{
				//Empty string means e.g. album has no more e.g. tags
				$ps_object["remove"][] = [$id];
			}
			else
			{
				//String isn't empty, move to temp for updates
				$tmp[$id] = $keyed_holder[$id];
				unset($keyed_holder[$id]);
			}
		}
	}

	//All remaining objects are new ones that are not in DB. Copy them to ps_object
	foreach($keyed_holder as $id => $val)
	{
		foreach(explode($delimiter, $val) as $data)
		{
			$ps_object["new"][] = [$id, $data];
		}
	}

	//New and updated objects have been removed, keep data for object updates in input array
	$keyed_holder = array_combine(array_keys($tmp), array_values($tmp));
}

//Since the many to many tables have the same number of fields, do this to save on some lines
function mtm_delete_insert($table, &$ps_object)
{
	GLOBAL $db;
	if(!empty($ps_object["remove"]))
	{
		run_prepared_statement($table, $ps_object["remove"],
		"DELETE FROM *TABLE* WHERE id=?;",
		null,
		function($data) use($db,$table) {
			if(!is_null($data))
				import_error("Error ".$db->errno." deleting ".$data[0]." from $table: ".$db->error."\n");
		});
	}
	if(!empty($ps_object["new"]))
	{
		run_prepared_statement($table, $ps_object["new"],
		//FIXME - I'd rather the calling function pass in the field names for the insert statement, but I think this will work
		"INSERT INTO *TABLE* VALUES(?,?);",
		null,
		function($data) use($db,$table) {
			if(!is_null($data))
				import_error("Error ".$db->errno." inserting (".$data[0]." ".$data[1].") into $table: ".$db->error."\n");
		});
	}
}

function get_filepath_parts($filepath)
{
	if(substr($filepath, -1) == "." || substr($filepath, -1) == "/" || substr($filepath, -1) == "\\")
		return null;
	$ret = ["path" => "", "name" => "", "extension" => ""];
	$last_slash = strripos($filepath, "/");
	$last_period = strripos($filepath, ".");
	$ret["path"] = substr($filepath, 0, $last_slash+1);
	if($last_period === false || $last_period < $last_slash)
	{
		$ret["name"] = substr($filepath, $last_slash+1);
	}
	else
	{
		$ret["name"] = substr($filepath, $last_slash+1, $last_period-($last_slash+1));
		if($last_period !== false)
			$ret["extension"] = substr($filepath, $last_period+1);
	}
	return $ret;
}

function supported_thumbnail_type($extension)
{
	switch($extension)
	{
		case "png":
		case "PNG":
		case "jpg":
		case "JPG":
		case "jpeg":
		case "JPEG":
		case "gif":
		case "GIF":
		case "bmp":
		case "BMP":
		case "webp":
		case "WEBP":
			return true;
		break;
	}
	return false;
}

function make_artist_obj($configstr)
{
	GLOBAL $artist_info_file;
	$artist = [
		"id" => "",
		"directory" => "",
		"hash" => hash("sha1", $configstr),
		"names" => "",
		"primaryname" => "",
		"aliases" => "",
		"description" => "",
		"countries" => "",
		"locations" => "",
		"external_links" => "",
		"favorite" => false
	];

	$in_desc = false;
	foreach(explode("\n", $configstr) as $line)
	{
		if($in_desc)
		{
			$artist["description"] .= $line;
			continue;
		}
		$l = explode("=", $line);
		if(count($l) != 2)
			continue;
		$k = mb_trim($l[0]);
		$v = mb_trim($l[1]);
		if(empty($l[0]) || empty($l[1]))
			continue;

		$v2 = "";
		switch($k)
		{
			case "name":
			case "names":
				if(!empty($v))
					$artist["names"] = $v;
			break;

			case "country":
				if(!empty($v))
					$artist["countries"] = $v;
			break;

			case "location":
				if(!empty($v))
					$artist["locations"] = $v;
			break;

			case "description":
			case "countries":
			case "locations":
				if(!empty($v))
					$artist[$k] = $v;
			break;

			case "favorite":
				$artist["favorite"] = true;
			break;

			case "link":
				if(!empty($album["external_links"]))
					$artist["external_links"] .= "|$v";
				else
					$artist["external_links"] = $v;
			break;

			case "musicbrainz_id":
				if(!empty($artist["external_links"]))
					$artist["external_links"] .= "|".make_external_url("musicbrainz.artist", $v);
				else
					$artist["external_links"] = make_external_url("musicbrainz.artist", $v);
			case "discogs_id":
				if(!empty($artist["external_links"]))
					$artist["external_links"] .= "|".make_external_url("discogs.artist", $v);
				else
					$artist["external_links"] = make_external_url("discogs.artist", $v);
			case "soundcloud_id":
				if(!empty($artist["external_links"]))
					$artist["external_links"] .= "|".make_external_url("soundcloud.artist", $v);
				else
					$artist["external_links"] = make_external_url("soundcloud.artist", $v);
			break;
			case "rym_id":
			case "rateyourmusic_id":
				if(!empty($artist["external_links"]))
					$artist["external_links"] .= "|".make_external_url("rym.artist", $v);
				else
					$artist["external_links"] = make_external_url("rym.artist", $v);
			break;

			default:
				import_warn("[WARN] Unknown artist property: $k\n");
			break;
		}
	}

	if(empty($artist["names"]))
	{
		import_error("No artist name\n");
		return false;
	}
	$i = strpos($artist["names"], "|");
	if($i !== false)
	{
		$artist["primaryname"] = substr($artist["names"], 0, $i);
		$artist["aliases"] = substr($artist["names"], $i+1);
	}

	return $artist;
}
function make_album_obj($albumdir)
{
	GLOBAL $album_info_file;
	if(!file_exists($albumdir."/$album_info_file"))
	{
		import_error("No $album_info_file in $albumdir\n");
		return false;
	}

	$album = [
		"id" => "",
		"directory" => $albumdir,
		"hash" => hash_file("sha1", "$albumdir/$album_info_file"),
		"names" => "",
		"type" => "",
		"genres" => "",
		"artists" => "",
		"release_date" => "",
		"remaster_date" => "",
		"comment" => "",
		"tags" => "",
		"import_date" => "",
		"external_links" => "",
		"favorite" => false
	];
	$albuminfo = file_get_contents("$albumdir/$album_info_file");
	if(!$albuminfo)
	{
		import_error("Empty $album_info_file in ".$albumdir."\n");
		return false;
	}

	//nfo file fields
	foreach(explode("\n", $albuminfo) as $line)
	{
		$l = explode("=", $line);
		if(count($l) != 2)
			continue;
		$k = mb_trim($l[0]);
		$v = mb_trim($l[1]);
		if(empty($l[0]) || empty($l[1]))
			continue;

		switch($k)
		{
			case "name":
			case "names":
				if(!empty($v))
					$album["names"] = $v;
			break;

			case "comment":
			case "description":
				if(!empty($v))
					$album["comment"] = $v;
			break;

			case "release_date":
			case "remaster_date":
			case "import_date":
				if(!empty($v))
					$album[$k] = format_date($v);
			break;

			case "type":
			case "tags":
				if(!empty($v))
					$album[$k] = $v;
			break;

			case "favorite":
				$album["favorite"] = true;
			break;

			case "link":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|$v";
				else
					$album["external_links"] = $v;
			break;

			case "musicbrainz_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("musicbrainz.album", $v);
				else
					$album["external_links"] = make_external_url("musicbrainz.album", $v);
			break;
			case "musicbrainz_release_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("musicbrainz.release", $v);
				else
					$album["external_links"] = make_external_url("musicbrainz.release", $v);
			break;
			case "discogs_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("discogs.album", $v);
				else
					$album["external_links"] = make_external_url("discogs.album", $v);
			break;
			case "discogs_release_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("discogs.release", $v);
				else
					$album["external_links"] = make_external_url("discogs.release", $v);
			break;
			case "soundcloud_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("soundcloud.album", $v);
				else
					$album["external_links"] = make_external_url("soundcloud.album", $v);
			break;
			case "rym_id":
			case "rateyourmusic_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("rym.album", $v);
				else
					$album["external_links"] = make_external_url("rym.album", $v);
			break;
			case "vgmdb_id":
				if(!empty($album["external_links"]))
					$album["external_links"] .= "|".make_external_url("vgmdb.album", $v);
				else
					$album["external_links"] = make_external_url("vgmdb.album", $v);
			break;

			default:
				import_warn("[WARN] Unknown album property: $k\n");
			break;
		}
	}
	return $album;
}
function make_song_obj($filepath, &$albumObj = null)
{
	GLOBAL $id3;
	GLOBAL $import_modified_date;

	//Check file type
	$libinfo = null;
	try
	{
		$libinfo = $id3->analyze($filepath);
	}
	catch(Error $e)
	{
		import_error("Error getting metadata for $filepath\n");
		import_error(print_r($e, true));
		return false;
	}
	if(!isset($libinfo["mime_type"]) || (substr($libinfo["mime_type"], 0, 5) != "audio" && substr($libinfo["mime_type"], 0, 15) != "application/ogg"))
		return false;

	$track_info = [
		"id" => "",
		"filepath" => $filepath,
		"hash" => hash_file("sha1", $filepath),
		"album" => (!is_null($albumObj)) ? $albumObj["id"] : "",
		"titles" => "",
		"genre" => "",
		"artists" => "",
		"guest_artists" => "",
		"track_number" => "",
		"disc_number" => 1,
		"release_date" => "",
		"rerelease" => false,
		"comment" => "",
		"tags" => "",
		"duration" => 0,
		"import_date" => "",
		"true_import_date" => "",
		"favorite" => false,
		"art" => "",
		"embedded_art" => false,
		"cover" => "",
		"parody" => "",
		"remix" => "",
		"intro" => false
	];
	$id3->CopyTagsToComments($libinfo);

	//Title
	if(isset($libinfo["comments"]["title"]))
		$track_info["titles"] = $libinfo["comments"]["title"][0];

	//Genre
	if(isset($libinfo["comments"]["genre"]))
		$track_info["genre"] = $libinfo["comments"]["genre"][0];
	//Artist
	if(isset($libinfo["comments"]["artist"]))
		$track_info["artists"] = $libinfo["comments"]["artist"][0];
	if(empty($track_info["artists"]) && isset($libinfo["comments"]["albumartist"]))
		$track_info["artists"] = $libinfo["comments"]["albumartist"][0];
	//Track number
	if(isset($libinfo["comments"]["track_number"]))
	{
		$tn = $libinfo["comments"]["track_number"][0];
		if(strstr($tn, "/") !== false)
			$track_info["track_number"] = substr($tn, 0, stripos($tn, "/"));
		else
			$track_info["track_number"] = $tn;
	}
	//Disc number
	if(isset($libinfo["comments"]["discnumber"]))
	{
		$track_info["disc_number"] = $libinfo["comments"]["discnumber"][0];
	}
	else if(isset($libinfo["comments"]["disc_number"]))
	{
		$dn = $libinfo["comments"]["disc_number"][0];
		if(strstr($dn, "/") !== false)
			$track_info["disc_number"] = substr($dn, 0, stripos($dn, "/"));
		else
			$track_info["disc_number"] = $dn;
	}
	else if(isset($libinfo["comments"]["part_of_a_set"]))
	{
		$dn = $libinfo["comments"]["part_of_a_set"][0];
		if(strstr($dn, "/") !== false)
			$track_info["disc_number"] = substr($dn, 0, stripos($dn, "/"));
		else
			$track_info["disc_number"] = $dn;
	}
	//Release date
	if(!is_null($albumObj) && !empty($albumObj["release_date"]))
		$track_info["release_date"] = $albumObj["release_date"];
	else if(isset($libinfo["comments"]["date"]))
		$track_info["release_date"] = $libinfo["comments"]["date"][0];
	else if(isset($libinfo["comments"]["creation_date"]))
		$track_info["release_date"] = $libinfo["comments"]["creation_date"][0];
	else if(isset($libinfo["comments"]["year"]))
		$track_info["release_date"] = $libinfo["comments"]["year"][0];

	$track_info["release_date"] = format_date($track_info["release_date"]);

	//Embedded art
	if(isset($libinfo["comments"]["picture"]) && count($libinfo["comments"]["picture"]) > 0)
		$track_info["embedded_art"] = true;

	//Duration
	if(isset($libinfo["playtime_seconds"]))
	{
		//Casting because sometimes this'll give a double
		$track_info["duration"] = (int)$libinfo["playtime_seconds"];
	}
	else
	{
		import_error("No song duration\n");
		return false;
	}

	//Comment
	//Tags
	$tagstring = "";
	$tags_in_comment = false;
	if(isset($libinfo["comments"]["tags"]))
	{
		$tagstring = $libinfo["comments"]["tags"][0];
	}
	else if(isset($libinfo["comments"]["text"]) && isset($libinfo["comments"]["text"]["tags"]))
	{
		$tagstring = $libinfo["comments"]["text"]["tags"];
	}
	else if(isset($libinfo["comments"]["comment"]) && isset($libinfo["comments"]["comment"][0]))
	{
		if(strncasecmp($libinfo["comments"]["comment"][0], "tags:", 5) == 0)
		{
			$tagstring = $libinfo["comments"]["comment"][0];
			$tags_in_comment = true;
		}
	}
	else if(isset($libinfo["comments"]["description"]))
	{
		if(strncasecmp($libinfo["comments"]["description"][0], "tags:", 5) == 0)
		{
			$tagstring = substr($libinfo["comments"]["description"][0], 5);
			$tags_in_comment = true;
		}
	}
	//Regular comment
	if(!$tags_in_comment)
	{
		if(isset($libinfo["comments"]["comment"]) && is_array($libinfo["comments"]["comment"]) && isset($libinfo["comments"]["comment"][0]))
		{
			$track_info["comment"] = $libinfo["comments"]["comment"][0];
		}
		else if(isset($libinfo["comments"]["description"]) && is_array($libinfo["comments"]["description"]))
		{
			$track_info["comment"] = $libinfo["comments"]["description"][0];
		}
	}

	//Tag processing
	if(!empty($tagstring))
	{
		foreach(explode("|", $tagstring) as $pair)
		{
			$tag = "";
			$value = "";
			if(strstr($pair, ":") === false)
			{
				$tag = $pair;
			}
			else
			{
				$i = strpos($pair, ":");
				$tag = substr($pair, 0, $i);
				$value = substr($pair, $i+1);
			}

			switch($tag)
			{
				case "import_date":
					$track_info["import_date"] = $value;
				break;

				case "guest":
					if(empty($track_info["guest_artists"]))
						$track_info["guest_artists"] = $value;
					else
						$track_info["guest_artists"] .= "|$value";
				break;

				case "alias":
					if(empty($track_info["titles"]))
						$track_info["titles"];
					else
						$track_info["titles"] .= "|$value";
				break;

				case "fav":
					$track_info["favorite"] = true;
				break;

				case "rerelease":
					$track_info["rerelease"] = true;
					if(empty($track_info["tags"]))
						$track_info["tags"] = $tag;
					else
						$track_info["tags"] .= "|$tag";
				break;

				case "cover":
				case "parody":
				case "remix":
					if(empty($track_info["tags"]))
						$track_info["tags"] = $tag;
					else
						$track_info["tags"] .= "|$tag";
				case "art":
					if(!empty($value))
						$track_info[$tag] = $value;
				break;

				case "intro":
					$track_info["intro"] = true;
				break;

				default:
					if(empty($track_info["tags"]))
						$track_info["tags"] = $tag;
					else
						$track_info["tags"] .= "|$tag";
				break;
			}
		}
	}
	//Import dates
	$track_info["true_import_date"] = date("Y-m-d");
	if(empty($track_info["import_date"]))
	{
		if(!is_null($albumObj) && !empty($albumObj["import_date"]))
			$track_info["import_date"] = $albumObj["import_date"];
		else if($import_modified_date)
			$track_info["import_date"] = date("Y-m-d", filemtime($filepath));
		else
			$track_info["import_date"] = $track_info["true_import_date"];
	}
	$track_info["import_date"] = format_date($track_info["import_date"]);

	//Art
	if(empty($track_info["art"]))
	{
		if($track_info["embedded_art"])
		{
			$track_info["art"] = "song";
		}
		else
		{
			$found_image = false;
			$parts = get_filepath_parts($filepath);
			foreach(glob($parts["path"].$parts["name"].".*") as $fname)
			{
				$p = get_filepath_parts($fname);
				if(supported_thumbnail_type($p["extension"]))
				{
					$found_image = true;
					break;
				}
			}

			if($found_image)
				$track_info["art"] = "song";
			else if(!is_null($albumObj))
				$track_info["art"] = "album";
			else
				$track_info["art"] = "none";
		}
	}
	return $track_info;
}
function make_video_obj($filepath)
{
	GLOBAL $id3;
	GLOBAL $import_modified_date;

	//Check file type
	$libinfo = null;
	try
	{
		$libinfo = $id3->analyze($filepath);
	}
	catch(Error $e)
	{
		import_error("Error getting metadata for $filepath\n");
		import_error(print_r($e, true));
		return false;
	}
	if(!isset($libinfo["mime_type"]) || substr($libinfo["mime_type"], 0, 5) != "video")
		return false;

	import_log("import video: $filepath\n");
	$video_info = [
		"id" => "",
		"filepath" => $filepath,
		"hash" => hash_file("sha1", $filepath),
		"titles" => "",
		"comment" => "",
		"genre" => "",
		"artists" => "",
		"guest_artists" => "",
		"duration" => 0,
		"thumbnail" => "",
		"import_date" => "",
		"true_import_date" => "",
		"release_date" => "",
		"type" => "",
		"tags" => "",
		"favorite" => false
	];
	$id3->CopyTagsToComments($libinfo);

	//import_log(print_r($libinfo["comments"], true));

	//Title
	if(isset($libinfo["comments"]["title"]))
		$video_info["titles"] = $libinfo["comments"]["title"][0];

	//Genre
	if(isset($libinfo["comments"]["genre"]))
		$video_info["genre"] = $libinfo["comments"]["genre"][0];

	//Artists
	if(isset($libinfo["comments"]["artist"]))
		$video_info["artists"] = $libinfo["comments"]["artist"][0];

	//Duration
	if(isset($libinfo["playtime_seconds"]))
		//Casting because sometimes this'll give a double
		$video_info["duration"] = (int)$libinfo["playtime_seconds"];

	//Tags
	$tagstring = "";
	$tags_in_comment = false;
	if(isset($libinfo["comments"]["tags"]))
	{
		$tagstring = $libinfo["comments"]["tags"][0];
	}
	else if(isset($libinfo["comments"]["comment"]) && strncasecmp($libinfo["comments"]["comment"][0], "tags:", 5) == 0)
	{
		$tagstring = substr($libinfo["comments"]["comment"][0], 5);
		$tags_in_comment = true;
	}

	//Description
	if(!$tags_in_comment)
	{
		if(isset($libinfo["comments"]["comment"]))
			$video_info["comment"] = $libinfo["comments"]["comment"][0];
	}

	//Tag processing
	if(!empty($tagstring))
	{
		foreach(explode("|", $tagstring) as $pair)
		{
			$tag = "";
			$value = "";
			if(strstr($pair, ":") === false)
			{
				$tag = $pair;
			}
			else
			{
				$i = strpos($pair, ":");
				$tag = substr($pair, 0, $i);
				$value = substr($pair, $i+1);
			}

			switch($tag)
			{
				//Tag: Import date
				case "import_date":
					$video_info["import_date"] = $value;
				break;

				//Tag: Video type
				case "type":
					$video_info["type"] = $value;
				break;

				//Tag: Guest artists
				case "guest":
					if(empty($video_info["guest_artists"]))
						$video_info["guest_artists"] = $value;
					else
						$video_info["guest_artists"] .= "|$value";
				break;

				//Tag: Title aliases
				case "alias":
					if(empty($video_info["titles"]))
						$video_info["titles"];
					else
						$video_info["titles"] .= "|$value";
				break;

				//Release date
				case "release_date":
					if(empty($video_info["release_date"]))
						$video_info["release_date"] = $value;
				break;

				//Tag: Favorite
				case "fav":
					$video_info["favorite"] = true;
				break;

				case "tag":
					if(empty($video_info["tags"]))
						$video_info["tags"] = $value;
					else
						$video_info["tags"] .= "|$value";
				break;

				case "comment":
					$video_info["comment"] = $value;
				break;

				default:
					if(empty($video_info["tags"]))
						$video_info["tags"] = $tag;
					else
						$video_info["tags"] .= "|$tag";
				break;
			}
		}
	}

	//title backup
	if(empty($video_info["titles"]))
		$video_info["titles"] = substr($filepath, strrpos($filepath, "."));
	//Import dates
	$video_info["true_import_date"] = date("Y-m-d");
	if(empty($video_info["import_date"]))
	{
		if($import_modified_date)
			$video_info["import_date"] = date("Y-m-d", filemtime($filepath));
		else
			$video_info["import_date"] = $video_info["true_import_date"];
	}
	$video_info["import_date"] = format_date($video_info["import_date"]);

	return $video_info;
}

//Ex. "bytes=200-1000, 2000-6576, 19000-"
function parse_range_header(string $header)
{
	$ranges = [];
	if(strpos($header, "bytes=") === false)
		return false;

	$rangereqs = explode(",", substr($header, strpos($header, "=")+1));
	foreach($rangereqs as $rangereq)
	{
		$pair = ["start" => 0];
		$bounds = explode("-", trim($rangereq));
		if(!isset($bounds[0]) || $bounds[0] === "")
		{
			$pair["start"] = "-".$bounds[1];
		}
		else
		{
			$pair["start"] = $bounds[0];
			if(!empty($bounds[1]))
			{
				if($bounds[1] > $pair["start"])
					$pair["end"] = $bounds[1];
				else
					continue;
			}
		}
		$ranges[] = $pair;
	}

	if(count($ranges) == 0)
		return false;
	return $ranges;
}

//example input: "enum('asdf','asdf','asdf')"
function parseEnumString($enumstr)
{
	$ret = [];
	$i = strpos($enumstr, "'");
	while($i !== false)
	{
		$i2 = strpos($enumstr, "'", $i+1);
		$t = substr($enumstr, $i+1, $i2-($i+1));
		$ret[] = $t;
		$i = strpos($enumstr, "'", $i2+1);
	}
	return $ret;
}



function escRegex(string $str)
{
	$str = str_replace("\\", "\\\\", $str);
	$str = str_replace("^", "\\^", $str);
	$str = str_replace("$", "\\$", $str);
	$str = str_replace("(", "\\(", $str);
	$str = str_replace(")", "\\)", $str);
	$str = str_replace("[", "\\[", $str);
	$str = str_replace("]", "\\]", $str);
	$str = str_replace("{", "\\{", $str);
	$str = str_replace("}", "\\}", $str);
	$str = str_replace("<", "\\<", $str);
	$str = str_replace(">", "\\>", $str);
	$str = str_replace("|", "\\|", $str);
	$str = str_replace("|", "\\|", $str);
	$str = str_replace(".", "\\.", $str);
	$str = str_replace("*", "\\*", $str);
	$str = str_replace("+", "\\+", $str);
	$str = str_replace("?", "\\?", $str);
	return $str;
}

//Square brackets were originally escaped like \[ and \] which worked
//for linux php builds but wasn't working for windows php builds, so
//instead they're "escaped" with single char character classes
//see https://stackoverflow.com/questions/2595119/glob-and-bracket-characters
function escGlob(string $str)
{
	$ret = "";
	$cnt = strlen($str);
	for($i = 0; $i < $cnt; ++$i)
	{
		switch($str[$i])
		{
			case "\\":
				$ret .= "\\\\";
			break;
			case "*":
				$ret .= "\\*";
			break;
			case "?":
				$ret .= "\\?";
			break;
			case "[":
				$ret .= "[[]";
			break;
			case "]":
				$ret .= "[]]";
			break;
			default:
				$ret .= $str[$i];
			break;
		}
	}
	return $ret;
}

//Matches any word starting with 't' except 'the'
function antiTheRegex()
{
	//use this if MySQL ever supports negative look-aheads
	//return "t(?!he\s).*";
	return "t([a-gi-z].*|.[a-df-z].*|..[\s]).*";
}

function parse_multiartist_file($filepath)
{
	$artists = [];
	$tmpartist = [
		"hash" => hash_file("sha1", $filepath),
		"description" => "",
		"countries" => "",
		"locations" => "",
		"aliases" => "",
		"external_links" => ""
	];
	$in_desc = false;
	foreach(file($filepath) as $line)
	{
		$l = explode("=", $line);

		if($in_desc)
		{
			if(substr($line, 0, 5) == "name=" || substr($line, 0, 6) == "names=")
				$in_desc = false;
			else
			{
				$tmpartist["description"] .= $value;
				continue;
			}
		}

		if(empty($l[0]) || empty($l[1]))
			continue;
		$name = mb_trim($l[0]);
		$value = mb_trim($l[1]);

		switch($name)
		{
			case "name":
			case "names":
				if(isset($tmpartist["primaryname"]))
				{
					array_push($artists, $tmpartist);
					$tmpartist = ["description" => null, "countries" => null, "locations" => null, "aliases" => ""];
				}
				$i = strpos($value, "|");
				if($i === false)
				{
					$tmpartist["primaryname"] = $value;
				}
				else
				{
					$tmpartist["primaryname"] = substr($value, 0, $i);
					$tmpartist["aliases"] = substr($value, $i+1);
				}
			break;

			case "country":
			case "countries":
				$tmpartist["countries"] = $value;
			break;

			case "location":
			case "locations":
				$tmpartist["locations"] = $value;
			break;

			case "description":
				$tmpartist[$name] = $value;
				$in_desc = true;
			break;

			case "favorite":
				$tmpartist["favorite"] = true;
			break;

			case "link":
				if(empty($tmpartist["external_links"]))
					$tmpartist["external_links"] = $value;
				else
					$tmpartist["external_links"] .= "|$value";
			break;

			//TODO - add external ID cases
		}
	}
	if(isset($tmpartist["primaryname"]))
		$artists[] = $tmpartist;
	return $artists;
}

function make_external_url($urltype, $urlpart)
{
	switch($urltype)
	{
		case "musicbrainz.artist":
			return "https://musicbrainz.org/artist/$urlpart";
		break;
		case "musicbrainz.album":
			return "https://musicbrainz.org/release-group/$urlpart";
		break;
		case "musicbrainz.release":
			return "https://musicbrainz.org/release/$urlpart";
		break;
		case "discogs.artist":
			return "https://www.discogs.com/artist/$urlpart";
		break;
		case "discogs.album":
			return "https://www.discogs.com/master/$urlpart";
		break;
		case "discogs.release":
			return "https://www.discogs.com/release/$urlpart";
		break;
		case "soundcloud.artist":
			return "https://soundcloud.com/$urlpart";
		break;
		case "soundcloud.playlist":
			return "https://soundcloud.com/$urlpart";
		break;
		case "rym.artist":
			return "https://rateyourmusic.com/artist/$urlpart";
		break;
		case "rym.album":
			return "https://rateyourmusic.com/album/$urlpart";
		break;
		case "vgmdb.artist":
			return "https://vgmdb.net/artist/$urlpart";
		break;
		case "vgmdb.album":
			return "https://vgmdb.net/album/$urlpart";
		break;
		default:
			return "";
		break;
	}
}

function get_first_songvideo($dir)
{
	foreach(scandir($dir) as $item)
	{
		if($item == "." || $item == "..")
			continue;
		$f = "$dir/$item";
		$t = audio_or_video($f);
		if(is_dir($f) || $t != "audio" && $t != "video")
			continue;
		return $f;
	}
	return null;
}

?>
