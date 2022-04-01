<?php
	/*
	 * Args:
	 *     date: date string
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * {
	 *     last_date: date string
	 *     songs: [
	 *         song data,
	 *         ...
	 *     ]
	 *     videos: [
	 *         video data,
	 *         ...
	 *     ]
	 * }
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");

	require("../config.php");
	require("../util.php");
	require("../api_common.php");


	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	if(isset($_GET["date"]) && preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $_GET["date"]))
		$q = "SELECT DISTINCT(import_date) FROM (SELECT import_date FROM songs WHERE import_date < '".$db->real_escape_string($_GET["date"])."' ORDER BY import_date DESC LIMIT 200) AS a;";
	else
		$q = "SELECT DISTINCT(import_date) FROM (SELECT import_date FROM songs ORDER BY import_date DESC LIMIT 200) AS a;";

	$result = $db->query($q);
	if($result === false)
		kill("Error getting dates");
	if($result->num_rows == 0)
	{
		$response = ["songs" => []];
		header("Content-Type: application/json");
		echo json_encode($response);
		exit;
	}
	$results = $result->fetch_all();
	$last = count($results) - 1;
	$lastdate = $results[$last][0];

	$response = ["last_date" => $lastdate, "songs" => [], "videos" => []];
	$items = GetSongInfo_date($lastdate, $results[0][0]);
	foreach($items as $row)
	{
		$response["songs"][] = array_combine(array_keys($row), array_values($row));
	}
	header("Content-Type: application/json");
	echo json_encode($response);
?>
