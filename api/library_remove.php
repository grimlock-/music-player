<?php
	/*
	 * Error response:
	 * {
	 *     error_message: string
	 * }
	 * Successful response:
	 * {
	 *     removed_dirs: array
	 * }
	 */

	require("../util.php");

	$removed_dirs = [];

	if(!isset($_POST["dirs"]))
		kill("No argument given");

	//Sanity checks
	$rem_dirs = explode("\n", trim($_POST["dirs"]));
	foreach($rem_dirs as $rd)
	{
		if($rd[0] != '/' && !preg_match('/^[A-Za-z]:.*$/', $rd))
			kill("Directories must be absolute paths");
	}

	$current_dirs = file_get_contents("../directories.txt");
	if($current_dirs === false)
		kill("Error opening directories file");

	if(empty($current_dirs))
		kill("No directories currently set. Nothing to remove");

	//Read file into string array, remove dir from there and rewrite
	//directories file
	$current_dirs = explode("\n", $current_dirs);

	foreach($current_dirs as $dirs_k => $line)
	{
		if(empty($line))
		{
			unset($current_dirs[$dirs_k]);
			continue;
		}
		foreach($rem_dirs as $rem_k => $rd)
		{
			if($line == $rd)
			{
				$removed_dirs[] = $rd;
				unset($current_dirs[$dirs_k]);
				break;
			}
		}
	}

	if(count($removed_dirs) == 0)
		kill("No matching directories found");

	//Open file and write new directories
	$handle = fopen("../directories.txt", "w");
	if(!$handle)
		kill("Error writing to directories file");
	if(fwrite($handle, implode("\n", $current_dirs)) === false)
	{
		fclose($handle);
		kill("Error writing to directories file");
	}
	fclose($handle);

	header("Content-Type: application/json");
	$response = ["removed_dirs"=>$removed_dirs];
	echo json_encode($response);
?>
