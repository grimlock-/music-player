<?php
	/*
	 * Endpoint to get binary song data
	 * 
	 * Args:
	 *     id: string
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

	if($_SERVER['REQUEST_METHOD'] !== 'GET')
		kill("Must be GET or HEAD request");
	if(!isset($_GET["id"]))
		kill("No song id");

	require("../config.php");

	$ranges = [];
	$headers = apache_request_headers();
	foreach($headers as $header => $value)
	{
		if($header != "Range")
			continue;

		$result = parse_range_header($value);
		if($result === false)
			continue;

		$ranges = array_merge($ranges, $result);
	}

	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
		kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

	$q = "SELECT filepath FROM songs WHERE id = '".$db->real_escape_string($_GET["id"])."';";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing SQL query");
	if($result->field_count == 0)
		kill("No results for song ID");
	$row = $result->fetch_row();
	if($row === null)
		kill("Error getting SQL query result");
	$fpath = $row[0];
	if(!is_readable($fpath))
		kill("File not found or not readable");


	$mtype = mime_content_type($fpath);
	$fsize = filesize($fpath);
	header("Accept-Ranges: bytes");

	if($_SERVER["REQUEST_METHOD"] == "HEAD")
		exit;

	//If not returning a range, return entire file the easy way
	switch(count($ranges))
	{
		case 0:
			header("Content-Type: $mtype");
			header("Content-Length: $fsize");
			$fhandle = fopen($fpath, "rb");
			fpassthru($fhandle);
		break;

		case 1:
			header("Content-Type: multipart/byteranges; boundary=asdfasdf");
			if($ranges[0]["start"] != 0 || isset($ranges[0]["end"]) && $ranges[0]["end"] < filesize($fpath))
			{
				$byte_count = $ranges[0]["end"] - $ranges[0]["start"];

				$fhandle = fopen($fpath, "rb");
				fseek($fhandle, $ranges[0]["start"]);
				$content = fread($fhandle, $byte_count);

				header("Content-Length: ".$byte_count);
				header("Content-Range: bytes ".$ranges[0]["start"]."-".$ranges[0]["end"]."/".filesize($fpath));
				echo $content;
			}
			else
			{
				header("Content-Length: $fsize");
				header("Content-Range: bytes 0-$fsize/$fsize");
				$fhandle = fopen($fpath, "rb");
				fpassthru($fhandle);
			}
		break;

		default:
			//Not really sure this will ever happen, do browsers even request multiple ranges at once?
			kill("Multiple ranges in single request not supported");
			/*header("Content-Type: multipart/byteranges; boundary=asdfasdf");
			//status code 206
			//TODO - total up length of all ranges and write header("Content-Length: ");
			foreach($ranges as $range)
			{
				//Initial sanity checks
				if(isset($range["end"]) && $range["end"] < $range["start"])
					kill("Bad range request");
			}
			$fhandle = fopen($fpath, "rb");
			foreach($ranges as $range)
			{
				echo "\r\n--asdfasdf\r\n";
				echo "Content-Type: $mtype\r\n";
				echo "Content-Range: bytes ".$range["start"]."-".$range["end"]."/$fsize\r\n\r\n".
				header("Content-Type: $fsize");
				header("Content-Range: bytes ".$range["start"]."-".$range["end"]."/$fsize");
				//TODO - write content
			}
			echo "--";
*/
		break;
	}
?>
