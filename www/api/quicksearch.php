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

	require("../config.php");
	require("../util.php");
	require("../api_common.php");

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	//$q = "";

	if(strlen($query) >= 6)
	{
		$response = ["songs" => [], "videos" => [], "albums" => [], "artists" => []];
	}
	else
	{
		if(strlen($query) >= 4
			$qt = 3;
		else
			$qt = 1;
		$response = ["songs" => GetSongInfo_rand($qt), "videos" => GetVideoInfo_rand($qt), "albums" => [], "artists" => []];
	}

	header("Content-Type: application/json");
	echo json_encode($response);
?>
