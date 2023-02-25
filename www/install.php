<?php
	/*
	 * Args:
	 * {
	 *   dbname: string
	 *   dbuser: string
	 *   dbpass: string
	 *   dbport: int
	 *   artistnfo: string
	 *   artisttile: string
	 *   albumnfo: string
	 *   aafname: string
	 *   aadir: string
	 *   sadir: string
	 *   albumthumb: string
	 *   songthumb: string
	 *   thumbfmt: string (one of 'jpg', 'png', 'gif', 'webp')
	 *   thumbq: int
	 *   album_types: array of strings
	 *   video_types: array of strings
	 *   reinstall: bool
	 * }
	 * Error response:
	 * {
	 *   error_message: string
	 * }
	 * Successful response:
	 * {
	 *   approved_additions: array
	 *   rejected_additions: array
	 * }
	 */

	function kill($message, $dberror = false)
	{
		GLOBAL $db;
		echo $message;
		if($dberror)
			echo " (".$db->errno."): ".$db->error;
		exit();
	}
	function array_to_enum(&$query, &$arr)
	{
		GLOBAL $db;
		$types = "";
		foreach($arr as $type)
		{
			if(!empty($type))
				$types .= "'".$db->real_escape_string($type)."',";
		}
		$types = substr_replace($types, "", -1);
		$query = substr_replace($query, $types, stripos($query, "ZZZZ"), 4);
	}

	require("install_global.php");
	require("install_artist.php");
	require("install_album.php");
	require("install_songs.php");
	require("install_videos.php");

	//Defaults
	$dburl = "127.0.0.1";
	$dbname = "music";
	$dbuser = "music";
	$dbpass = "";
	$dbport = 3306;
	$artist_info_file = "artist.nfo";
	$artist_tile_file = "tile.png";
	$album_info_file = "album.nfo";
	$album_art_filename = "FullCover";
	$album_art_directory = "art/albums/";
	$song_art_directory = "art/songs/";
	$album_thumbnail_directory = "thumbnails/albums/";
	$song_thumbnail_directory = "thumbnails/songs/";
	$thumbnail_format = "jpg";
	$thumbnail_quality = 80;
	$album_types = [];
	$video_types = [];
	$reinstall = false;

	//Apply post values
	if(!empty($_POST["dburl"])) $dburl = $_POST["dburl"];
	if(!empty($_POST["dbname"])) $dbname = $_POST["dbname"];
	if(!empty($_POST["dbuser"])) $dbuser = $_POST["dbuser"];
	if(!empty($_POST["dbpass"])) $dbpass = $_POST["dbpass"];
	if(!empty($_POST["dbport"])) $dbport = $_POST["dbport"];
	if(!empty($_POST["artistnfo"])) $artist_info_file = $_POST["artistnfo"];
	if(!empty($_POST["artisttile"])) $artist_tile_file = $_POST["artisttile"];
	if(!empty($_POST["albumnfo"])) $album_info_file = $_POST["albumnfo"];
	if(!empty($_POST["aafname"])) $album_art_filename = $_POST["aafname"];
	if(!empty($_POST["aadir"])) $album_art_directory = $_POST["aadir"];
	if(!empty($_POST["sadir"])) $song_art_directory = $_POST["sadir"];
	if(!empty($_POST["albumthumb"])) $album_thumbnail_directory = $_POST["albumthumb"];
	if(!empty($_POST["songthumb"])) $song_thumbnail_directory = $_POST["songthumb"];
	if(!empty($_POST["thumbfmt"]) && (
		$_POST["thumbfmt"] == "jpg" ||
		$_POST["thumbfmt"] == "png" ||
		$_POST["thumbfmt"] == "gif" ||
		$_POST["thumbfmt"] == "webp")
	)
		$thumbnail_format = $_POST["thumbfmt"];
	if(!empty($_POST["thumbq"])) $thumb_quality = $_POST["thumbq"];
	if(!empty($_POST["reinstall"])) $reinstall = true;

	//Directory checks
	if(substr($album_art_directory, -1) != "/")
		$album_art_directory .= "/";
	if(!file_exists($album_art_directory))
		mkdir($album_art_directory, 0755, true);
	if(substr($song_art_directory, -1) != "/")
		$song_art_directory .= "/";
	if(!file_exists($song_art_directory))
		mkdir($song_art_directory, 0755, true);
	if(substr($song_thumbnail_directory, -1) != "/")
		$song_thumbnail_directory .= "/";
	if(!file_exists($song_thumbnail_directory))
		mkdir($song_thumbnail_directory, 0755, true);
	if(substr($album_thumbnail_directory, -1) != "/")
		$album_thumbnail_directory .= "/";
	if(!file_exists($album_thumbnail_directory))
		mkdir($album_thumbnail_directory, 0755, true);
	//album types
	if(!is_array($_POST["album_types"]))
		kill("No album types provided");
	foreach($_POST["album_types"] as $atyp)
	{
		if(!empty($atyp))
			$album_types[] = $atyp;
	}
	$album_types = array_unique($album_types);
	if(count($album_types) < 2)
		kill("Not enough valid album types");
	//video types
	if(!is_array($_POST["video_types"]))
		kill("No video types provided");
	foreach($_POST["video_types"] as $vtyp)
	{
		if(!empty($vtyp))
			$video_types[] = $vtyp;
	}
	$video_types = array_unique($video_types);
	if(count($video_types) < 2)
		kill("Not enough valid video types");


	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
	{
		echo "Database connection failed (".$db->connect_errno."): ".$db->connect_error."<br/>";
		exit();
	}

	$dbname = $db->real_escape_string($dbname);

	if(!empty($_POST["reinstall"]))
	{
		if(!$db->query("DROP DATABASE $dbname;"))
			kill("Error droping database<br/>", true);
		if(!$db->query("CREATE DATABASE $dbname;"))
			kill("Error creating database<br/>", true);
		if(!$db->select_db($dbname))
			kill("Error selecting database<br/>", true);
	}

	Procedures();
	Artists();
	Albums();
	Songs();
	Videos();

	$q = "CREATE TABLE favorites(".
		"id CHAR(10) NOT NULL,".
		"type ENUM('song', 'video', 'album', 'artist') NOT NULL,".
		"PRIMARY KEY(id,type)".
	");";
	if(!$db->query($q))
		kill("Error creating favorites table", true);

	Views();

	//Write config file
	$config = fopen("config.php", "w");
	fwrite($config,
	'<?php'."\n".
	'$dburl = "'.$dburl.'";'."\n".
	'$dbname = "'.$dbname.'";'."\n".
	'$dbuser = "'.$dbuser.'";'."\n".
	'$dbpass = "'.$dbpass.'";'."\n".
	'$dbport = "'.$dbport.'";'."\n".
	'$artist_info_file = "'.$artist_info_file.'";'."\n".
	'$artist_tile_file = "'.$artist_tile_file.'";'."\n".
	'$album_info_file = "'.$album_info_file.'";'."\n".
	'$album_art_filename = "'.$album_art_filename.'";'."\n".
	'$album_art_directory = "'.$album_art_directory.'";'."\n".
	'$song_art_directory = "'.$song_art_directory.'";'."\n".
	'$album_thumbnail_directory = "'.$album_thumbnail_directory.'";'."\n".
	'$song_thumbnail_directory = "'.$song_thumbnail_directory.'";'."\n".
	'$thumbnail_format = "'.$thumbnail_format.'";'."\n".
	'$thumbnail_quality = "'.$thumbnail_quality.'";'."\n".
	'?>');
	fclose($config);
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
	<div>Database created. Navigating to library page...<span>4</span></div>
	<script>setTimeout(function(){document.querySelector("span").innerText = 3}, 1000)</script>
	<script>setTimeout(function(){document.querySelector("span").innerText = 2}, 2000)</script>
	<script>setTimeout(function(){document.querySelector("span").innerText = 1}, 3000)</script>
	<script>setTimeout(function(){location.href="index.php"}, 4000)</script>
</body>
</html>
