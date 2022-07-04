<?php
	//TODO - add constraings like tags, album/video types, min/max length, etc.
	/**
	 * GET
	 * {
	 *     type (optional): string ("song", "album", "video")
	 *     count (optional): int
	 *     resolve (optional): bool, isset() determines value (when true and type is album, return all the album's songs in addition to the album info)
	 * }
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * Response:
	 * {
	 *     type: string
	 *     items: object array
	 * }
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");
	 
	require("../config.php");
	require("../util.php");
	require("../api_common.php");
	$resolve = false;
	$type = "song";

	if(isset($_GET["type"]))
		$type = $_GET["type"];
	if(isset($_GET["count"]))
		$count = (int)$_GET["count"];

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	switch($type)
	{
		case "song":
			if(!isset($count))
				$count = 10;
			$result = GetSongInfo_rand($count);
		break;

		case "video":
			if(!isset($count))
				$count = 10;
			$result = GetVideoInfo_rand($count);
		break;

		case "album":
			if(!isset($count))
				$count = 1;
			if(isset($_GET["resolve"]))
				$resolve = true;
			$result = GetAlbumInfo_rand($count, $resolve);
		break;

		default:
			kill("Unrecognized item type: ".$type);
		break;
	}

	$response = ["type" => $type, "items" => []];
	foreach($result as $row)
	{
		$item = array_combine(array_keys($row), array_values($row));
		if($resolve)
			$item["songs"] = GetAlbumSongs($row["id"]);
		$response["items"][] = $item;
	}

	header("Content-Type: application/json");
	echo json_encode($response);
?>
