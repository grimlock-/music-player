<?php
	require("../config.php");
	require("../util.php");
	require("../api_common.php");
	
	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);
	
	$q = "SELECT
  		(SELECT COUNT(*) FROM songs) as songs,
  		(SELECT COUNT(*) FROM videos) as videos,
  		(SELECT COUNT(*) FROM albums) as albums,
  		(SELECT COUNT(*) FROM artists) as artists";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing SQL query");
	$items = $result->fetch_all(MYSQLI_ASSOC);
	
	header("Content-Type: application/json");
	echo json_encode($items[0]);
?>
