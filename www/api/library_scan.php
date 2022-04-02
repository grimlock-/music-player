<?php
	/*
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 *
	 * GET response:
	 * {
	 *     running_import: boolean
	 *     status: string
	 * }
	 *
	 * POST response:
	 * {
	 *     start_successful: boolean
	 * }
	 */

	require("../util.php");

	if($_SERVER['REQUEST_METHOD'] === 'POST')
	{
		$handle = fopen("library_scan.lock", "x");
		if($handle === false)
		{
			kill("Scan could not start or already running");
		}
		else
		{
			fclose($handle);
			//the import script will change directory, so the directories.txt arg doesn't need the ..
			$args = join(' ', array_map('escapeshellarg', ["-f", "../proc_import.php", "--", "--directories-file", "directories.txt"]));

			try
			{
				exec("php $args 2>/dev/null >&- /dev/null &");
				//TODO - any way to make this windows friendly? do i _really_ care since it's the only step broken in windows?
				//exec("php $args &");
				//pclose(popen("start php $args", "r"));
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
		$running = false;
		$status = "";
		if(file_exists("library_scan.lock"))
		{
			$status = file_get_contents("library_scan.lock");
			if($status === false)
				$status = "";
			else
				$running = true;
		}

		header("Content-Type: application/json");
		$response = ["running_import" => $running , "status" => $status];
		echo json_encode($response);
	}
?>
