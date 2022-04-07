<?php
$song_fields = "songs.id,
		songs.title,
		songs.track_number,
		songs.disc_number,
		songs.genre,
		songs.duration,
		songs.art,
		songs.album_id,
		songs.import_date";
$video_fields = "videos.id,
		videos.titles,
		videos.songs,
		videos.genre,
		videos.duration,
		videos.type";
function GetSongInfo_id(array $ids)
{
	GLOBAL $db;
	GLOBAL $song_fields;
	$idselect = "'".implode("' UNION SELECT '", $ids)."'";

	$q = "WITH song_ids(id) AS (
			SELECT $idselect
		)
		SELECT
			$song_fields,
			b.name AS album,
			GROUP_CONCAT(d.name SEPARATOR '|') AS artists
		FROM song_ids
		NATURAL JOIN
			songs
		LEFT JOIN
			albums b
		ON
			songs.album_id = b.id
		LEFT JOIN
			song_artists c
		ON
			c.song = songs.id
		LEFT JOIN
			artists d
		ON
			d.id = c.artist
		GROUP BY
			id;";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing SQL query: ".$db->error);

	$items = $result->fetch_all(MYSQLI_ASSOC);
	return $items;
}

function GetSongInfo_date($rangeStart, $rangeEnd)
{
	GLOBAL $db;
	GLOBAL $song_fields;
	if(!$rangeEnd || $rangeEnd == "" || $rangeEnd == $rangeStart)
		$where = "import_date = '$rangeStart'";
	else
		$where = "import_date BETWEEN '$rangeStart' AND '$rangeEnd'";

	$q = "WITH song_range AS (
			SELECT $song_fields FROM songs WHERE $where
		)
		SELECT
			songs.*,
			b.name AS album,
			GROUP_CONCAT(d.name SEPARATOR '|') AS artists
		FROM song_range AS songs
		LEFT JOIN
			albums b
		ON
			songs.album_id = b.id
		LEFT JOIN
			song_artists c
		ON
			c.song = songs.id
		LEFT JOIN
			artists d
		ON
			d.id = c.artist
		GROUP BY
			id
		ORDER BY
			import_date DESC;";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing SQL query: ".$db->error);

	$items = $result->fetch_all(MYSQLI_ASSOC);
	return $items;
}

function GetSongInfo_rand($count)
{
	GLOBAL $db;
	GLOBAL $song_fields;

	$q = "WITH song_range AS (
			SELECT $song_fields FROM songs ORDER BY RAND() LIMIT $count
		)
		SELECT
			songs.*,
			b.name AS album,
			GROUP_CONCAT(d.name SEPARATOR '|') AS artists
		FROM song_range AS songs
		LEFT JOIN
			albums b
		ON
			songs.album_id = b.id
		LEFT JOIN
			song_artists c
		ON
			c.song = songs.id
		LEFT JOIN
			artists d
		ON
			d.id = c.artist
		GROUP BY
			id;";
	$result = $db->query($q);
	if($result === false)
		return $db->error;

	$items = $result->fetch_all(MYSQLI_ASSOC);
	return $items;
}

function GetVideoInfo_rand($count)
{
	GLOBAL $db;

	$q = "SELECT $video_fields FROM videos ORDER BY RAND() LIMIT $count;";

	$result = $db->query($q);
	if($result === false)
		return [];

	$items = $result->fetch_all(MYSQLI_ASSOC);
	return $items;
}

function GetAlbumInfo_rand($count, $resolve)
{
	GLOBAL $db;
	$q = "SELECT * FROM albums ORDER BY RAND() LIMIT $count;";
	$result = $db->query($q);
	if($result === false)
		return [];
	$items = $result->fetch_all(MYSQLI_ASSOC);
	return $items;
}

function GetAlbumSongs($id, $song_fields = "*")
{
	GLOBAL $db;
	GLOBAL $song_fields;

	$ret = [];
	$q = "SELECT $song_fields FROM songs WHERE album_id = '$id';";
	$result = $db->query($q);
	if($result !== false)
	{
		$songs = $result->fetch_all(MYSQLI_ASSOC);
		foreach($songs as $song)
		{
			$ret[] = array_combine(array_keys($song), array_values($song));
		}
	}
	return $ret;
}

function GetIntroInfo($ids)
{
	GLOBAL $db;
	$return = [];
	foreach($ids as $id)
	{
		
	}
}

function SearchSongs_Title($title)
{
	GLOBAL $db;
	GLOBAL $song_fields;
	$q = "WITH matches (
		SELECT $song_fields,GROUP_CONCAT(b.alias, '|') AS aliases FROM songs a JOIN song_aliases b WHERE title LIKE %".$db->real_escape_string($title)."% OR aliases LIKE %".$db->real_escape_string($title)."% GROUP BY a.id
	)
	SELECT ";
	$result = $db->query($q);
	if($result === false)
		kill();
	if($result->num_rows == 0)
		return [];
}
?>
