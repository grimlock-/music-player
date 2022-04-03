<?php
//Sanity checks
if(isset($_SERVER["REQUEST_SCHEME"]))
	exit();

//If running from API directory, move up one
if(strpos(getcwd(), "/api") !== false)
	chdir("..");

require("config.php");
require("util.php");
require("getid3/getid3.php");
$print_allowed = false;
$debug = false;

switch($thumbnail_format)
{
	case "jpg":
	case "png":
	case "gif":
	case "webp":
	break;

	default:
		exit();
	break;
}
if(!file_exists($album_thumbnail_directory) || !file_exists($song_thumbnail_directory))
	exit();

function initialscan($dirstr)
{
	GLOBAL $artist_info_file;
	GLOBAL $album_info_file;

	if($dirstr[strlen($dirstr)-1] == "/")
		$dirstr = substr($dirstr, 0, strlen($dirstr)-1);

	$ret = ["artists" => [], "albums" => []];

	if(file_exists($dirstr."/.nomedia"))
		return $ret;

	if(file_exists($dirstr."/$album_info_file"))
	{
		$ret["albums"][] = $dirstr;
		return $ret;
	}
	else if(file_exists($dirstr."/$artist_info_file"))
	{
		$ret["artists"][] = $dirstr;
	}

	$cdirs = [];
	foreach(scandir($dirstr) as $item)
	{
		if($item == "." || $item == "..")
			continue;
		if(is_dir("$dirstr/$item"))
			$cdirs[] = $dirstr."/".$item;
	}
	foreach($cdirs as $dir)
	{
		$results = initialscan($dir);
		$ret["artists"] = array_merge($ret["artists"], $results["artists"]);
		$ret["albums"] = array_merge($ret["albums"], $results["albums"]);
	}
	return $ret;
}
function get_song_image(string $fpath, string $suffix = "")
{
	$parts = get_filepath_parts($fpath);

	$globstr = escGlob($parts["path"].$parts["name"].$suffix).".*";
	foreach(glob($globstr) as $path)
	{
		$p = get_filepath_parts($path);
		if(supported_thumbnail_type($p["extension"]))
			return $path;
	}

	return null;
}
function get_album_image(string $path, string $suffix = "")
{
	GLOBAL $album_art_filename;
	if(substr($path, -1) != "/")
		$path .= "/";

	$globstr = escGlob("$path$album_art_filename$suffix").".*";
	foreach(glob($globstr) as $fpath)
	{
		$p = get_filepath_parts($fpath);
		if(supported_thumbnail_type($p["extension"]))
			return $fpath;
	}

	return null;
}
function handle_image_file($fpath, $name_root, $copy_destination, $thumb_destination)
{
	GLOBAL $debug;
	if($debug)
		echo "\nHandling image: $fpath\n";
	$gdimage = false;
	$parts = get_filepath_parts($fpath);
	switch($parts["extension"])
	{
		case "png":
		case "PNG":
			$gdimage = imagecreatefrompng($fpath);
		break;

		case "jpg":
		case "JPG":
			$gdimage = imagecreatefromjpeg($fpath);
		break;

		case "gif":
		case "GIF":
			$gdimage = imagecreatefromgif($fpath);
		break;

		case "bmp":
		case "BMP":
			$gdimage = imagecreatefrombmp($fpath);
		break;

		case "webp":
		case "WEBP":
			$gdimage = imagecreatefromwebp($fpath);
		break;
	}
	if($gdimage !== false)
	{
		if(copy($fpath, $copy_destination.$name_root.".".$parts["extension"]))
		{
			if($debug)
				echo "Copied art for $name_root";
		}
		else
		{
			if($debug)
				echo "Failed to copy art for $name_root";
		}
		generate_thumbnails($gdimage, $thumb_destination, $name_root);
	}
}
function handle_image_string(&$data, $format, $name_root, $src_destination, $thumb_destination)
{
	//Make a copy first
	$srcfile = fopen($src_destination.$name_root.".$format", "w");
	if($srcfile !== false)
	{
		fwrite($srcfile, $data);
		fclose($srcfile);
	}

	//Then make thumbnails
	$srcimage = imagecreatefromstring($data);
	if($srcimage !== false)
		generate_thumbnails($srcimage, $thumb_destination, $name_root);
}
function generate_thumbnails($srcimage, $destination, $name_root)
{
	GLOBAL $debug;
	if($debug)
		echo "\nGenerating thumbnails from $name_root\n";
	GLOBAL $thumbnail_format;
	GLOBAL $thumbnail_quality;
	GLOBAL $thumbnail_sizes;
	$srcw = imagesx($srcimage);
	$srch = imagesy($srcimage);
	if($srcw > $srch)
		$ref = $srcw;
	else
		$ref = $srch;

	foreach($thumbnail_sizes as $size)
	{
		if($ref <= $size * 1.2)
			continue;
		$ratio = $size / $ref;
		$w = (int) round($srcw * $ratio);
		$h = (int) round($srch * $ratio);

		$newimage = imagecreatetruecolor($w, $h);
		if(!imagecopyresampled($newimage, $srcimage, 0, 0, 0, 0, $w, $h, $srcw, $srch))
			continue;
		switch($thumbnail_format)
		{
			case "jpg":
				imagejpeg($newimage, $destination.$name_root."_$size.jpg");
			break;
			case "png":
				imagepng($newimage, $destination.$name_root."_$size.png");
			break;
			case "gif":
				imagegif($newimage, $destination.$name_root."_$size.gif");
			break;
			case "webp":
				imagewebp($newimage, $destination.$name_root."_$size.webp");
			break;
		}
	}
}
function commandline_print($message)
{
	GLOBAL $print_allowed;
	if($print_allowed)
		echo $message;
}


//Start
//Since cli_set_process_title wasn't working
if(file_exists("/proc/".getmypid()."/comm"))
	file_put_contents("/proc/".getmypid()."/comm", "music_thumbnail_generator");
$thumbnail_sizes = [1000, 700, 400];
commandline_print("Thumbnail sizes: ".implode(',', $thumbnail_sizes)."\n");
$dirs_file = "";
$mediadirs = [];
$artistdirs = [];
$albumdirs = [];
$options = getopt("f:p", ["directories-file:", "debug", "print"]);

//Setup libs
$id3 = new getID3;
$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
if($db->connect_errno)
	import_kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

//Command line args
if(isset($options["f"]))
	$dirs_file = $options["f"];
if(isset($options["directories-file"]))
	$dirs_file = $options["directories-file"];
if(isset($options["debug"]))
{
	$print_allowed = true;
	$debug = true;
}
else if(isset($options["print"]) || isset($options["p"]))
	$print_allowed = true;

//Assemble list of album directories
if(!empty($dirs_file))
{
	$dirFile = file_get_contents($dirs_file);
	if($dirFile)
	{
		if(stripos($dirFile, "\n") !== false)
			$mediadirs = explode("\n", $dirFile);
		else
			$mediadirs[] = $dirFile;
	}
}
foreach($mediadirs as $dir)
{
	if(empty($dir) || !file_exists($dir))
		continue;
	$scan = initialscan($dir);
	$albumdirs = array_merge($albumdirs, $scan["albums"]);
	$artistdirs = array_merge($albumdirs, $scan["artist"]);
}

//Handle artists
/*commandline_print("Handling artists\n");
$i = 0;
$len = count($songs);
foreach($artistdirs as $adir)
{
	commandline_print("\r".++$i."/$len");
	$sql = "SELECT id FROM artists WHERE directory = '".$db->real_escape_string($adir)."';";
	$result = $db->query($sql);
	if($result === false)
		continue;

	$id = $result->fetch_row()[0];
	if(file_exists("$adir/$artist_tile_file"))
}*/

//Handle songs with embedded art
commandline_print("Handling songs with embedded art\n");
$sql = "SELECT id,filepath FROM songs WHERE embedded_art = 1;";
$result = $db->query($sql);
if($result)
{
	$songs = $result->fetch_all(MYSQLI_ASSOC);
	$i = 0;
	$len = count($songs);
	foreach($songs as $row)
	{
		commandline_print("\r".++$i."/$len");

		//Give dummy media file to see if image has already been extracted
		$artcopy = get_song_image($song_art_directory.$row["id"].".xyz");
		if($artcopy !== null && file_exists($artcopy) && filemtime($artcopy) >= filemtime($row["filepath"]))
			continue;

		$libinfo = $id3->analyze($row["filepath"]);
		if(isset($libinfo["comments"]["picture"]) && isset($libinfo["comments"]["picture"][0]))
		{
			$data = $libinfo["comments"]["picture"][0]["data"];
			$mime = $libinfo["comments"]["picture"][0]["image_mime"];
			$fmt = substr($mime, strpos($mime, '/')+1);
			handle_image_string($data, $fmt, $row["id"], $song_art_directory, $song_thumbnail_directory);
		}
	}
	commandline_print("\n");
}

//Handle songs with their own art
commandline_print("Handling songs with non-embedded art\n");
$i = 0;
$sql = "SELECT id,filepath FROM songs WHERE art = 'song';";
$result = $db->query($sql);
if($result)
{
	$songs = $result->fetch_all(MYSQLI_ASSOC);
	$len = count($songs);
	foreach($songs as $row)
	{
		commandline_print("\r".++$i."/$len");
		$img = get_song_image($row["filepath"]);
		if($img === null)
			continue;
		$parts = get_filepath_parts($img);
		$thumb_file = $song_art_directory.$row["id"].".".$parts["extension"];
		if(!file_exists($thumb_file) || filemtime($thumb_file) < filemtime($img))
			handle_image_file($img, $row["id"], $song_art_directory, $song_thumbnail_directory);
	}
	commandline_print("\n");
}


//Handle albums
commandline_print("Handling albums\n");
$j = 0;
$len = count($albumdirs);
commandline_print("Found $len album directories\n");
foreach($albumdirs as $albumdir)
{
	commandline_print("\r".++$j."/$len");
	//Make list of album covers
	$art_files = [];
	$img = get_album_image($albumdir);
	if($img === null)
	{
		commandline_print("\nNo album image: $albumdir\n");
		continue;
	}
	$i = 2;
	do
	{
		$art_files[] = $img;
		$img = get_album_image($albumdir, (string)++$i);
	} while($img !== null);

	//Get album ID
	$album = make_album_obj($albumdir);
	$primaryname="";
	if(strpos($album["names"], ";") !== false)
		$primaryname = substr($album["names"], 0, strpos($album["names"], ";"));
	else
		$primaryname = $album["names"];
	$q = "SELECT id FROM albums WHERE name REGEXP '^".$db->real_escape_string(escRegex($primaryname))."(;|$)';";
	$result = $db->query($q);
	if(!$result || $result->num_rows == 0)
	{
		commandline_print("\nNo results for $q\n");
		continue;
	}
	if($result->num_rows > 1)
		commandline_print("\nFound ".$result->num_rows." albums with primary name \"$primaryname\"\n");
	$albums = $result->fetch_all();
	$id = $albums[0][0];

	//Generate thumbnails for updated src images
	$i = 0;
	foreach($art_files as $file)
	{
		$parts = get_filepath_parts($file);

		$thumb_file = $album_art_directory.$id;
		if(++$i == 1)
		{
			$prefix = $id;
		}
		else
		{
			$prefix = $id."_$i";
			$thumb_file .= "_$i";
		}
		$thumb_file .= ".".$parts["extension"];

		if(!file_exists($thumb_file) || filemtime($thumb_file) < filemtime($file))
			handle_image_file($file, $prefix, $album_art_directory, $album_thumbnail_directory);
	}
}
commandline_print("\n");

if(file_exists("api/thumbnails.lock"))
	unlink("api/thumbnails.lock");
?>
