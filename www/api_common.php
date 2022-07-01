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
		videos.genre,
		videos.duration,
		videos.type";
$album_fields = "albums.id,
		albums.title,
		albums.type,
		albums.release_date,
		albums.remaster_date";
$artist_fields = "artists.id,
		artists.name,
		artists.locations";
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
			b.title AS album,
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
		kill("Error executing query: ".$db->error);

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
			b.title AS album,
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
			b.title AS album,
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
	GLOBAL $album_fields;
	$q = "SELECT $album_fields FROM albums ORDER BY RAND() LIMIT $count;";
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

function GetArtistInfo_rand($count)
{
	GLOBAL $db;
	return [];
}

function GetIntroInfo($ids)
{
	GLOBAL $db;
	$return = [];
	foreach($ids as $id)
	{
		
	}
}

function SearchSongs_Title($title, $limit)
{
	GLOBAL $db;
	GLOBAL $song_fields;
	$q = "WITH matches AS (
		SELECT
			$song_fields,
			GROUP_CONCAT(b.alias, '|') AS aliases
		FROM
			songs
		NATURAL LEFT JOIN
			song_aliases b
		GROUP BY
			id
	)
	SELECT *
	FROM matches
	WHERE title LIKE '%".$db->real_escape_string($title)."%'
	OR aliases LIKE '%".$db->real_escape_string($title)."%'";
	if(isset($limit))
		$q .= "LIMIT $limit";
	$q .= ";";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing query: ".$db->error);
	if($result->num_rows == 0)
		return [];
	else
		return $result->fetch_all(MYSQLI_ASSOC);
}

function SearchVideos_Title($title, $limit)
{
	GLOBAL $db;
	GLOBAL $video_fields;
	$q = "SELECT
			$video_fields
		FROM
			videos
		WHERE
			titles LIKE '%".$db->real_escape_string($title)."%'";
		if(isset($limit))
			$q .= "LIMIT $limit";
		$q .= ";";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing query: ".$db->error);
	if($result->num_rows == 0)
		return [];
	else
		return $result->fetch_all(MYSQLI_ASSOC);
}

function SearchAlbums_Title($title, $limit)
{
	GLOBAL $db;
	GLOBAL $album_fields;
	$q = "WITH matches AS (
		SELECT
			$album_fields,
			GROUP_CONCAT(b.alias, '|') AS aliases
		FROM
			albums
		NATURAL JOIN
			album_aliases b
		GROUP BY
			albums.id
		)
	SELECT *
	FROM matches
	WHERE title LIKE '%".$db->real_escape_string($title)."%'
	OR aliases LIKE '%".$db->real_escape_string($title)."%'";
	if(isset($limit))
		$q .= " LIMIT $limit";
	$q .= ";";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing query: ".$db->error);
	if($result->num_rows == 0)
		return [];
	else
		return $result->fetch_all(MYSQLI_ASSOC);
}

function SearchArtists_Name($title, $limit)
{
	GLOBAL $db;
	GLOBAL $artist_fields;
	$q = "WITH matches AS (
		SELECT
			$artist_fields,
			GROUP_CONCAT(b.alias, '|') AS aliases,
			GROUP_CONCAT(c.country, '|') AS countries
		FROM
			artists
		NATURAL LEFT JOIN
			artist_aliases b
		NATURAL LEFT JOIN
			artist_countries c
		GROUP BY
			artists.id
		)
	SELECT *
	FROM matches
	WHERE name LIKE '%".$db->real_escape_string($title)."%'
	OR aliases LIKE '%".$db->real_escape_string($title)."%'";
	if(isset($limit))
		$q .= " LIMIT $limit";
	$q .= ";";
	$result = $db->query($q);
	if($result === false)
		kill("Error executing query: ".$db->error);
	if($result->num_rows == 0)
		return [];
	else
		return $result->fetch_all(MYSQLI_ASSOC);
}
?>
