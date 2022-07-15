<?php
	/*
	 * Endpoint to get binary song data
	 * 
	 * Args:
	 *     id: string
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * {
	 *     type: string
	 *     items: item array
	 * }
	 */

	function GetPlaylistFilepaths($id)
	{
		GLOBAL $db;
		kill("Not yet implemented");
	}
	function HandleSingleSong($id)
	{
		GLOBAL $db;
		$fpath = GetSongFilepath($id);
		if(!is_readable($fpath))
			kill("File not found or not readable");
		header("Content-Type: ".mime_content_type($fpath));
		header("Content-Length: ".filesize($fpath));
		$fhandle = fopen($fpath, "rb");
		fpassthru($fhandle);
		exit;
	}

	require("../util.php");

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must be GET or HEAD request");
	if(!isset($_GET["id"]))
		kill("No id");
	if(!isset($_GET["type"]))
		kill("No download type");
	switch($_GET["type"])
	{
		case "album":
		case "playlist":
		case "song":
			$type = $_GET["type"];
		break;
		default:
			kill("Invalid type");
		break;
	}

	require("../config.php");
	require("../api_common.php");

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	$id = $db->real_escape_string($id);
	switch($type)
	{
		case "album":
			$songs = GetAlbumFilepaths($db->real_escape_string($_GET["id"]));
		case "playlist":
			$songs = GetPlaylistFilepaths($db->real_escape_string($_GET["id"]));
		case "song":
			if(strpos($_GET["id"], ',') !== false)
				$songs = GetSongFilepaths(explode(',', $id));
			else
				HandleSingleSong($db->real_escape_string($_GET["id"]));
		break;
	}

	/*$zfile = tmpfile();
	if($zfile === false)
		kill("Unable to create archive");
	$zip = new ZipArchive();*/
	foreach($songs as $fpath)
	{
		if(!is_readable($fpath))
			kill("File not found or not readable");
		//$zip->addFile($fpath);
	}
	//$zip->
?>
