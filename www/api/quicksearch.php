<?php
	/**
	 * GET
	 * {
	 *     query: string
	 * }
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * Response:
	 * {
	 *     ...
	 * }
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");

	if(!isset($_GET["query"]))
		kill("No query");
	$limit = 3;
	if(isset($_GET["limit"]) && is_numeric($_GET["limit"]) && $_GET["limit"] > 0)
		$limit = $_GET["limit"];
	if($limit > 20)
		$limit = 20;

	require("../config.php");
	require("../util.php");
	require("../api_common.php");

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	$search_text = $db->real_escape_string($_GET["query"]);

	$response = ["songs" => SearchSongs_Title($search_text, $limit), "videos" => SearchVideos_Title($search_text, $limit), "albums" => SearchAlbums_Title($search_text, $limit), "artists" => SearchArtists_Name($search_text, $limit)];
	//$response = ["songs" => GetSongInfo_rand(2), "videos" => GetVideoInfo_rand(2), "albums" => GetAlbumInfo_rand(2, true), "artists" => GetArtistInfo_rand(2)];

	header("Content-Type: application/json");
	echo json_encode($response);
?>
