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
	 *     type: string
	 *     items: item array
	 * }
	 */

	require("../util.php");

	kill("Favorites endpoint not implemented");
?>
