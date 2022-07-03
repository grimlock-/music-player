<?php
	/*
	 * Args:
	 *     before: date string
	 *     after: date string
	 *     album: album ID
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * [
	 *     song data,
	 *     ...
	 * ]
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");

	require("../config.php");
	require("../util.php");

	if(isset($_GET["before"]) && preg_match("^[0-9]{4}-[0-9]{2}-[0-9]{2}$", $_GET["before"]))
		$before = $_GET["before"];
	if(isset($_GET["after"]) && preg_match("^[0-9]{4}-[0-9]{2}-[0-9]{2}$", $_GET["after"]))
		$after = $_GET["after"];
	if(isset($_GET["album"] && strlen($_GET["album"] == 10)))
		$album = $_GET["album"];
	if(isset($_GET["qt"]) && is_numeric($_GET["qt"]))
		$qt = $_GET["qt"];

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);
	$q = "SELECT a.id,a.import_date,a.titles,a.album as album_id,a.track_number,a.disc_number,a.genre,a.artists,a.duration,a.art,b.name AS album FROM songs a LEFT JOIN albums b ON a.album = b.id";
	if(isset($before))
		$q .= " AND a.import_date < '$cap'";
	if(isset($after))
		$q .= " AND a.import_date > '$min'";
	$q .= " ORDER BY import_date DESC";
	if(isset($qt))
		$q .= " LIMIT $qt";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing SQL query");

	$response = [];
	$items = $result->fetch_all(MYSQLI_ASSOC);
	foreach($items as $row)
	{
		$response[] = array_combine(array_keys($row), array_values($row));
	}
	header("Content-Type: application/json");
	echo json_encode($response);

?>
