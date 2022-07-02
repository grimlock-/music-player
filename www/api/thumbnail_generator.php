<?php
	/*
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * {
	 *     generating_thumbs: boolean
	 * }
	 *
	 * POST response:
	 * {
	 *     start_successful: boolean
	 * }
	 */

	require("../util.php");
	require("../config.php");

	if($_SERVER['REQUEST_METHOD'] === 'POST')
	{
		$handle = fopen("thumbnails.lock", "x");
		if($handle === false)
		{
			kill("Could not start process or already running");
		}
		else
		{
			fclose($handle);
			//the import script will change directory, so the directories.txt arg doesn't need the ..
			$args = join(' ', array_map('escapeshellarg', ["-f", "../proc_thumbs.php", "--", "--directories-file", "directories.txt"]));

			try
			{
				exec("php $args 2>/dev/null >&- /dev/null &");
			}
			catch(Error $e)
			{
				kill("Could not start background scan: ".print_r($e, true));
			}

			header("Content-Type: application/json");
			$response = ["start_successful" => true];
			echo json_encode($response);
		}
	}
	else if($_SERVER['REQUEST_METHOD'] === 'GET')
	{
		header("Content-Type: application/json");
		$response = [
			"generating_thumbs" => file_exists("thumbnails.lock"),
			"missing_album_art" => !file_exists("../$album_art_directory"),
			"missing_song_art" => !file_exists("../$song_art_directory"),
			"missing_album_thumbnail" => !file_exists("../$album_thumbnail_directory"),
			"missing_song_thumbnail" => !file_exists("../$song_thumbnail_directory")
		];
		echo json_encode($response);
	}
?>
