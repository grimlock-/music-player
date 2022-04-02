<?php
	/*
	 * Args:
	 * {
	 *   dirs: string (newline delimited directory list, must be absolute paths)
	 * }
	 * Error response:
	 * {
	 *   error_message: string
	 * }
	 * Successful response:
	 * {
	 *   approved_additions: array
	 *   rejected_additions: array
	 * }
	 */

	require("../util.php");

	$approved_additions = [];
	$rejected_additions = [];

	if(!isset($_POST["dirs"]))
		kill("No argument given");

	//Sanity checks
	$new_dirs = explode("\n", urldecode($_POST["dirs"]));
	foreach($new_dirs as $key => $nd)
	{
		//TODO - move this below and just reject the dirs instead of killing the script
		if($nd[0] != '/' && !preg_match('/^[A-Za-z]:.*$/', $nd))
			kill("Directories must be absolute paths");
		if($nd[strlen($nd)-1] == '/')
			kill("Directories must not end with a slash");
	}

	$current_dirs = file_get_contents("../directories.txt");
	if($current_dirs === false)
		kill("Error getting directories file contents");

	//If we have any directories currently, check to see if any
	//additions are already in there
	if(empty($current_dirs))
	{
		$current_dirs = [];
	}
	else
	{
		$current_dirs = explode("\n", $current_dirs);

		foreach($current_dirs as $dirs_k => $line)
		{
			if(empty($line))
			{
				unset($current_dirs[$dirs_k]);
				continue;
			}
			foreach($new_dirs as $new_k => $nd)
			{
				if($line == $nd)
				{
					$rejected_additions[] = $nd;
					unset($new_dirs[$new_k]);
					continue;
				}
			}
		}

		if(count($new_dirs) == 0)
			kill("No directories to add");
	}

	$current_dirs = array_merge($current_dirs, $new_dirs);
	$approved_additions = array_values($new_dirs);

	//Open file and write new directories
	$handle = fopen("../directories.txt", "w");
	if(!$handle)
		kill("Error opening directories file");
	if(!fwrite($handle, implode("\n", $current_dirs)))
	{
		fclose($handle);
		kill("Error writing to directories file");
	}
	fclose($handle);

	header("Content-Type: application/json");
	$response = array("approved_additions" => $approved_additions, "rejected_additions" => $rejected_additions);
	echo json_encode($response);
?>
