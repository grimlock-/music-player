<?php
	require("util.php");

	function test($str)
	{
		echo $str."\n";
		$res = parse_range_header($str);
		if($res === false)
		{
			echo "parse failed\n\n";
			return;
		}

		echo count($res)." ranges\n";
		foreach($res as $range)
		{
			echo "Start: ";
			if($range["start"][0] == '-')
				echo substr($range["start"], 1)." before EOF\n";
			else
				echo $range["start"]."\n";
			echo "End: ";
			if(empty($range["end"]))
				echo "EOF\n";
			else
				echo $range["end"]."\n";
		}
		echo "\n";
		/*else if($res[0]["start"] != "21010")
		{
			echo "start fail".$res[0]["start"]."\n\n";
		}
		else if($res[0]["end"] != "47021")
		{
			echo "end fail: ".$res[0]["end"]."\n\n";
		}*/
	}

	test("bytes=21010-47021");
	test("bytes=-47021");
	test("bytes=0-499,-500");
	test("bytes=0-499, -500");
	test("bytes=200-1000, 2000-6576, 19000-");
	test("bytes=200-1000,2000-6576,19000-");
?>
