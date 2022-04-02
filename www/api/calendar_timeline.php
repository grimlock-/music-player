<?php
	/*
	 * Args:
	 *     type: string (song, album, video)
	 *     count: int
	 *     resolve: any, presence is all that's checked (when true and type is album, return all the album's songs in addition to the album info)
	 *
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * {
	 *     serialized associative array
	 * }
	 */

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must use GET request");

	require("../config.php");
	require("../util.php");

	kill("Calender timeline not yet implemented");
?>
