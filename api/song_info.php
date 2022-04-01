<?php
	/*
	 * Endpoint to get song metadata
	 * 
	 * Args:
	 *     ids: string
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * [
	 *     {
	 *       "id"
	 *       "title"
	 *       "track_number"
	 *       "disc_number"
	 *       "genre"
	 *       "artists"
	 *       "duration"
	 *       "art"
	 *       "album"
	 *     }
	 *     ...
	 * ]
	 */

	/*if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must be GET request");*/

	require("../config.php");
	require("../util.php");
	require("../api_common.php");

	if(!isset($_GET["ids"]))
		kill("No song ids");
	$ids = explode(",", $_GET["ids"]);


	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	$items = GetSongInfo_id($ids);
	$response = [];
	foreach($items as $row)
	{
		$item = array_combine(array_keys($row), array_values($row));
		$response[] = $item;
	}

	header("Content-Type: application/json");
	echo json_encode($response);
?>
