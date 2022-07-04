<?php
	/* List all artist buckets (starting letters) or
	 * get all artists for the given bucket
	 *
	 * GET
	 * {
	 *     char (optional): string
	 * }
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 * 
	 * Default response:
	 * {
	 *     buckets: string array
	 * }
	 * 
	 * Char response:
	 * [
	 *     {
	 *         id: string
	 *         name: string
	 *         albums: album array
	 *         songs: song array
	 *     },
	 *     ...
	 * ]
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");
	 
	require("../config.php");
	require("../util.php");
	require("../api_common.php");

	$response = null;

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	$match = "REGEXP '^[[:punct:]]?".$db->real_escape_string($_GET["char"]).".*'";
	if($_GET["char"] == "#")
		$match = "REGEXP '^[[:digit:]].*'";
	else if($_GET["char"] == "@")
		$match = "REGEXP '^[[:punct:]].*'";
	$result = $db->query("SELECT id,name FROM artists WHERE name $match;");
	if($result === false)
		kill("Error getting artists: ".$db->error);
	if($result->field_count == 0)
		kill("No artist results");

	$response = [];
	while($item = $result->fetch_assoc())
	{
		$response[] = array_combine(array_keys($item), array_values($item));
	}

	header("Content-Type: application/json");
	echo json_encode($response);
?>
