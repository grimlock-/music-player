<?php
	/**
	 * GET
	 * {
	 *     char (optional): string
	 *     initial_punct: boolean, isset() determines value
	 *     separate_the: boolean, isset() determines value
	 * }
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * Default response:
	 * {
	 *     types: string array
	 *     buckets: string array
	 * }
	 *
	 * Char response:
	 * [
	 *     {
	 *         id,
	 *         name,
	 *         type,
	 *         artists,
	 *         release_date,
	 *     },
	 *     ...
	 * ]
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");
	 
	require("../config.php");
	require("../util.php");

	$response = null;
	if(isset($_GET["char"]))
		$give_params = false;
	else
		$give_params = true;

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	if(strtolower($_GET["char"]) == "the")
		$_GET["char"] = "the";
	//regex
	$match = "^";
	if(isset($_GET["initial_punct"]) && $_GET["char"] != "@")
		$match .= "[[:punct:]]*";
	if($_GET["char"] == "#")
		$match .= "[[:digit:]].*";
	else if($_GET["char"] == "@")
		$match .= "[[:punct:]].*";
	else if(($_GET["char"] == "T" || $_GET["char"] == "t") && isset($_GET["separate_the"]))
		$match .= antiTheRegex();
	else
		$match .= $db->real_escape_string($_GET["char"]).".*";
	//query
	$q = "SELECT id,title,type,release_date FROM albums WHERE LOWER(title) REGEXP '$match';";
	$result = $db->query($q);
	if($result === false)
		kill("Error getting albums: ".$db->error);
	if($result->field_count == 0)
		kill("No album results");

	$response = [];
	while($item = $result->fetch_assoc())
	{
		$response[] = array_combine(array_keys($item), array_values($item));
	}

	header("Content-Type: application/json");
	echo json_encode($response);
?>
