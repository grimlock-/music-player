<?php
	/* Get info for artist page including
	 *     Name and aliases
	 *     Country and locations
	 *     Albums
	 *     Songs
	 *
	 * GET
	 * {
	 *     id: string
	 * }
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 * 
	 * Default response:
	 * {
	 *     name: string
	 *     albums: album array
	 *     songs: song array
	 * }
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");

	if(!isset($_GET["id"]) || strlen($_GET["id"]) != 10)
		kill("Need artist ID");
	 
	require("../config.php");
	require("../util.php");
	require("../api_common.php");

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	$response = GetArtistInfo($_GET["id"]);

	header("Content-Type: application/json");
	echo json_encode($response);
?>
