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
		artists.locations,
		artists.description,
		artists.external_links";
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

function GetAlbumInfo_rand($count)
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

function GetAlbumSongs($id, $return_info = true)
{
	GLOBAL $db;
	GLOBAL $song_fields;

	$ret = [];
	if($return_info)
		$q = "SELECT $song_fields FROM songs WHERE album_id = '".$db->real_escape_string($id)."';";
	else
		$q = "SELECT id FROM songs WHERE album_id = '".$db->real_escape_string($id)."';";
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

function GetArtistInfo($id)
{
	GLOBAL $db;
	GLOBAL $artist_fields;
	
	$q = "SELECT
			$artist_fields,
			GROUP_CONCAT(b.alias, '|') AS aliases,
			GROUP_CONCAT(c.country, '|') AS countries
		FROM
			artists
		NATURAL LEFT JOIN
			artist_aliases b
		NATURAL LEFT JOIN
			artist_countries c
		WHERE
			artists.id = '".$db->real_escape_string($id)."'
		GROUP BY
			artists.id;";

	$ret = null;
	$result = $db->query($q);
	if($result !== false)
		$ret = $result->fetch_all(MYSQLI_ASSOC)[0];
	return $ret;
}

function GetArtistSongs($id)
{
	GLOBAL $db;
	GLOBAL $song_fields;

	$ret = [];
	$q = "WITH artist_songs AS (
			SELECT song AS id
			FROM song_artists
			WHERE artist = '".$db->real_escape_string($id)."'
		)
		SELECT
			$song_fields
		FROM
			artist_songs
		NATURAL JOIN
			songs";
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

function GetSongFilepath($id)
{
	GLOBAL $db;
	$q = "SELECT filepath FROM songs WHERE id = '".$db->real_escape_string($id)."';";
	$result = $db->query($q);
	if($result === false || $result->num_rows == 0)
		kill("Invalid id");
	$row = $result->fetch_row();
	return $row[0];
}
function GetSongFilepaths($ids)
{
	GLOBAL $db;

	if(gettype($ids) == "string")
		$idselect = "'".implode("' UNION SELECT '", explode(',', $db->real_escape_string($ids)))."'";
	else if(gettype($ids) == "array")
		$idselect = "'".implode("' UNION SELECT '", $ids)."'";
	else
		kill("Bad argument - internal");

	$q = "WITH song_ids(id) AS (
			SELECT $idselect
		)
		SELECT
			b.filepath
		FROM
			song_ids a
		JOIN
			songs b
		ON
			a.id = b.id;";
	$result = $db->query($q);
	if($result === false || $result->num_rows == 0)
		kill("Error getting song filepaths: $idselect");
	$ret = [];
	while($row = $result->fetch_row())
		$ret[] = $row[0];
	return $ret;
}
function GetAlbumFilepaths($id)
{
	GLOBAL $db;

	$q = "SELECT filepath FROM songs WHERE album_id = '".$db->real_escape_string($id)."';";
	$result = $db->query($q);
	if($result === false || $result->num_rows == 0)
		kill("Invalid id");

	$ret = [];
	while($row = $result->fetch_row())
		$ret[] = $row[0];
	return $ret;
}

?>
