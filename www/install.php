<?php
	/*
	 * Args:
	 * {
	 *   dbname: string
	 *   dbuser: string
	 *   dbpass: string
	 *   dbport: int
	 *   artistnfo: string
	 *   albumnfo: string
	 *   aafname: string
	 *   aadir: string
	 *   sadir: string
	 *   albumthumb: string
	 *   songthumb: string
	 *   thumbfmt: string (one of 'jpg', 'png', 'gif', 'webp')
	 *   thumbq: int
	 *   album_types: array of strings
	 *   video_types: array of strings
	 *   reinstall: bool
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

	function kill($message, $dberror = false)
	{
		GLOBAL $db;
		echo $message;
		if($dberror)
			echo " (".$db->errno."): ".$db->error;
		exit();
	}
	function array_to_enum(&$query, &$arr)
	{
		GLOBAL $db;
		$types = "";
		foreach($arr as $type)
		{
			if(!empty($type))
				$types .= "'".$db->real_escape_string($type)."',";
		}
		$types = substr_replace($types, "", -1);
		$query = substr_replace($query, $types, stripos($query, "ZZZZ"), 4);
	}

	//Defaults
	$dburl = "127.0.0.1";
	$dbname = "music";
	$dbuser = "music";
	$dbpass = "";
	$dbport = 3306;
	$artist_info_file = "artist.nfo";
	$album_info_file = "album.nfo";
	$album_art_filename = "FullCover";
	$album_art_directory = "art/albums/";
	$song_art_directory = "art/songs/";
	$album_thumbnail_directory = "thumbnails/albums/";
	$song_thumbnail_directory = "thumbnails/songs/";
	$thumbnail_format = "jpg";
	$thumbnail_quality = 80;
	$album_types = [];
	$video_types = [];
	$reinstall = false;

	//Apply post values
	if(!empty($_POST["dburl"])) $dburl = $_POST["dburl"];
	if(!empty($_POST["dbname"])) $dbname = $_POST["dbname"];
	if(!empty($_POST["dbuser"])) $dbuser = $_POST["dbuser"];
	if(!empty($_POST["dbpass"])) $dbpass = $_POST["dbpass"];
	if(!empty($_POST["dbport"])) $dbport = $_POST["dbport"];
	if(!empty($_POST["artistnfo"])) $artist_info_file = $_POST["artistnfo"];
	if(!empty($_POST["albumnfo"])) $album_info_file = $_POST["albumnfo"];
	if(!empty($_POST["aafname"])) $album_art_filename = $_POST["aafname"];
	if(!empty($_POST["aadir"])) $album_art_directory = $_POST["aadir"];
	if(!empty($_POST["sadir"])) $song_art_directory = $_POST["sadir"];
	if(!empty($_POST["albumthumb"])) $album_thumbnail_directory = $_POST["albumthumb"];
	if(!empty($_POST["songthumb"])) $song_thumbnail_directory = $_POST["songthumb"];
	if(!empty($_POST["thumbfmt"]) && (
		$_POST["thumbfmt"] == "jpg" ||
		$_POST["thumbfmt"] == "png" ||
		$_POST["thumbfmt"] == "gif" ||
		$_POST["thumbfmt"] == "webp")
	)
		$thumbnail_format = $_POST["thumbfmt"];
	if(!empty($_POST["thumbq"])) $thumb_quality = $_POST["thumbq"];
	if(!empty($_POST["reinstall"])) $reinstall = true;

	//Sanity checks
	if(substr($album_art_directory, -1) != "/")
		$album_art_directory .= "/";
	if(substr($song_art_directory, -1) != "/")
		$song_art_directory .= "/";
	if(substr($song_thumbnail_directory, -1) != "/")
		$song_thumbnail_directory .= "/";
	if(substr($album_thumbnail_directory, -1) != "/")
		$album_thumbnail_directory .= "/";
	//album types
	if(!is_array($_POST["album_types"]))
		kill("No album types provided");
	foreach($_POST["album_types"] as $atyp)
	{
		if(!empty($atyp))
			$album_types[] = $atyp;
	}
	$album_types = array_unique($album_types);
	if(count($album_types) < 2)
		kill("Not enough valid album types");
	//video types
	if(!is_array($_POST["video_types"]))
		kill("No video types provided");
	foreach($_POST["video_types"] as $vtyp)
	{
		if(!empty($vtyp))
			$video_types[] = $vtyp;
	}
	$video_types = array_unique($video_types);
	if(count($video_types) < 2)
		kill("Not enough valid video types");


	$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
	if($db->connect_errno)
	{
		echo "Database connection failed (".$db->connect_errno."): ".$db->connect_error."<br/>";
		exit();
	}

	$dbname = $db->real_escape_string($dbname);

	if(!empty($_POST["reinstall"]))
	{
		if(!$db->query("DROP DATABASE $dbname;"))
			kill("Error droping database<br/>", true);
		if(!$db->query("CREATE DATABASE $dbname;"))
			kill("Error creating database<br/>", true);
		if(!$db->select_db($dbname))
			kill("Error selecting database<br/>", true);
	}



	$q = "CREATE PROCEDURE split
		(IN txt TEXT, IN token CHAR(1))
		BEGIN
			DECLARE start INT DEFAULT 1;
			DECLARE end INT DEFAULT LOCATE(token, txt);
			DECLARE len INT DEFAULT 0;
			lbl: WHILE end != 0 DO
				SET len = end - start;
				INSERT split_results SELECT SUBSTR(txt FROM start FOR len);

				SET start = end + 1;
				SET end = LOCATE(token, txt, start);
				IF end = 0 THEN
					LEAVE lbl;
				END IF;
			END WHILE lbl;

			INSERT split_results SELECT SUBSTR(txt FROM start);
		END;";
	if(!$db->query($q))
		kill("Error creating split() procedure", true);
	$q = "CREATE FUNCTION GetArtistId_Exact
		(title TEXT)
		RETURNS CHAR(10)
		BEGIN
			DECLARE qt INT DEFAULT 0;
			DECLARE ret CHAR(10) DEFAULT '';
			SELECT COUNT(*) FROM artists WHERE LOWER(name) = LOWER(title) INTO qt;
			IF qt > 1 THEN
				RETURN ret;
			ELSEIF qt = 1 THEN
				SELECT id FROM artists WHERE LOWER(name) = LOWER(title) INTO ret;
				RETURN ret;
			ELSEIF qt = 0 THEN
				SELECT COUNT(*) FROM artist_aliases WHERE LOWER(alias) = LOWER(title) INTO qt;
				IF qt > 1 THEN
					RETURN ret;
				ELSEIF qt = 0 THEN
					RETURN ret;
				ELSE
					SELECT id FROM artist_aliases WHERE LOWER(alias) = Lower(title) INTO ret;
					RETURN ret;
				END IF;
			END IF;
		END;";
	if(!$db->query($q))
		kill("Error creating GetArtistId_Exact() function", true);
	$q = "CREATE FUNCTION GetArtistId_Count
		(title TEXT)
		RETURNS INT
		BEGIN
			DECLARE qt INT DEFAULT 0;
			DECLARE ret INT DEFAULT 0;
			SELECT COUNT(*) FROM artists WHERE LOWER(name) = LOWER(title) INTO qt;
			SELECT qt + COUNT(*) FROM artist_aliases WHERE LOWER(alias) = LOWER(title) INTO ret;
			RETURN ret;
		END;";
	if(!$db->query($q))
		kill("Error creating GetArtistId_Count() function", true);
	$q = "CREATE FUNCTION GetSongId
		(title TEXT)
		RETURNS CHAR(10)
		BEGIN
			DECLARE qt INT DEFAULT 0;
			DECLARE ret CHAR(10) DEFAULT '';
			SELECT COUNT(*) FROM songs WHERE songs.title = title INTO qt;
			IF qt = 1 THEN
				SELECT id FROM songs WHERE songs.title = title INTO ret;
				RETURN ret;
			ELSEIF qt = 0 THEN
				SELECT COUNT(*) FROM song_aliases WHERE alias = title INTO qt;
				IF qt = 1 THEN
					SELECT id FROM song_aliases WHERE alias = title INTO ret;
				END IF;
			END IF;

			RETURN ret;
		END;";
	if(!$db->query($q))
		kill("Error creating GetSongId() function", true);
	/*$q = "CREATE FUNCTION GetSongId_Artist
		(title TEXT, artist TEXT)
		RETURNS CHAR(10)
		BEGIN
			DECLARE qt INT DEFAULT 0;
			DECLARE ret CHAR(10) DEFAULT '';
			DECLARE artistid CHAR(10) DEFAULT '';
			SELECT GetArtistId_Exact(artist) INTO artistid;
			IF artistid NULL THEN
				RETURN ret;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10));
			INSERT temp_table SELECT id FROM songs WHERE songs.artist = artistid UNION SELECT song AS id FROM song_artists WHERE song_artists.artist = artistid;

			SELECT COUNT(*) FROM temp_table NATURAL JOIN songs WHERE artist = artistid INTO qt;
			if qt = 1 THEN
				SELECT id FROM temp_table NATURAL JOIN songs WHERE artist = artistid INTO ret;
				RETURN ret;
			ELSEIF qt = 0 THEN
				SELECT COUNT(*) FROM song_aliases WHERE alias = title INTO qt;
				IF qt = 1 THEN
					SELECT id FROM song_aliases WHERE alias = title INTO ret;
				END IF;
			END IF;

			RETURN ret;
		END;";
	if(!$db->query($q))
		kill("Error creating GetSongId() function", true);*/




	$q = "CREATE TABLE artists(".
		"id CHAR(10) PRIMARY KEY,".
		"directory TEXT UNIQUE,".
		"hash TEXT UNIQUE,".
		"name TEXT,".
		"description TEXT,".
		"locations TEXT,".
		"external_links TEXT".
	");";
	if(!$db->query($q))
		kill("Error creating artists table", true);
	$q = "CREATE TABLE artist_countries(".
		"id CHAR(10),".
		"country VARCHAR(100) NOT NULL,".
		"FOREIGN KEY (id) REFERENCES artists(id) ON DELETE CASCADE,".
		"PRIMARY KEY(country,id),".
		"INDEX b (id)".
	");";
	if(!$db->query($q))
		kill("Error creating artist_countries table", true);
	$q = "CREATE PROCEDURE update_artist_countries
		(IN artistid CHAR(10), IN countries TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(countries))) = 0 THEN
				DELETE FROM artist_countries WHERE id = artistid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), country VARCHAR(100));
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM temp_table;
			DELETE FROM split_results;
			CALL split(countries, '|');
			INSERT temp_table SELECT * FROM (SELECT artistid) a CROSS JOIN split_results b;

			DELETE artist_countries
			FROM artist_countries
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE artist_countries.id = artistid;

			INSERT artist_countries
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN artist_countries b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_artist_countries procedure: ".$db->error);
	$q = "CREATE TABLE artist_aliases(".
		"id CHAR(10),".
		"alias TEXT NOT NULL,".
		"FOREIGN KEY (id) REFERENCES artists(id) ON DELETE CASCADE,".
		"INDEX b (id)".
	");";
	if(!$db->query($q))
		kill("Error creating artist_aliases table", true);
	$q = "CREATE PROCEDURE update_artist_aliases
		(IN id CHAR(10), IN aliases TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(aliases))) = 0 THEN
				DELETE artist_aliases FROM artist_aliases WHERE id = id;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), aliases TEXT);
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(aliases, '|');
			INSERT temp_table SELECT * FROM (SELECT id) a CROSS JOIN split_results b;

			DELETE artist_aliases
			FROM artist_aliases
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE artist_aliases.id = id;

			INSERT artist_aliases
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN artist_aliases b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_artist_aliases procedure", true);




	$q = "CREATE TABLE albums(".
		"id CHAR(10) PRIMARY KEY,".
		"directory TEXT UNIQUE,".
		"hash TEXT UNIQUE,".
		"name TEXT,".
		"type ENUM(ZZZZ),".
		"release_date DATE,".
		"remaster_date DATE,".
		"comment TEXT,".
		"INDEX (type),".
		"INDEX (release_date)".
	");";
	array_to_enum($q, $album_types);
	if(!$db->query($q))
		kill("Error creating albums table", true);
	$q = "CREATE TABLE album_genres(".
		"id CHAR(10),".
		"genre VARCHAR(50),".
		"FOREIGN KEY (id) REFERENCES albums(id) ON DELETE CASCADE,".
		"PRIMARY KEY(id,genre),".
		"INDEX b (genre)".
	");";
	if(!$db->query($q))
		kill("Error creating album_genres table", true);
	$q = "CREATE PROCEDURE update_album_genres
		(IN albumid CHAR(10), IN genres TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(genres))) = 0 THEN
				DELETE album_genres FROM album_genres WHERE id = albumid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), genre VARCHAR(50));
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(genres, '|');
			INSERT temp_table SELECT * FROM (SELECT albumid) a CROSS JOIN split_results b;

			DELETE album_genres
			FROM album_genres
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE album_genres.id = albumid;

			INSERT album_genres
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN album_genres b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_album_genres procedure: ".$db->error);
	$q = "CREATE TABLE album_tags(".
		"id CHAR(10),".
		"tag VARCHAR(100),".
		"FOREIGN KEY (id) REFERENCES albums(id) ON DELETE CASCADE,".
		"PRIMARY KEY(id,tag),".
		"INDEX b (tag)".
	");";
	if(!$db->query($q))
		kill("Error creating album_tags table", true);
	$q = "CREATE PROCEDURE update_album_tags
		(IN albumid CHAR(10), IN tags TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(tags))) = 0 THEN
				DELETE album_tags FROM album_tags WHERE id = albumid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), tags VARCHAR(100));
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(tags, '|');
			INSERT temp_table SELECT * FROM (SELECT albumid) a CROSS JOIN split_results b;

			DELETE album_tags
			FROM album_tags
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE album_tags.id = albumid;

			INSERT album_tags
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN album_tags b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_album_tags procedure: ".$db->error);
	$q = "CREATE TABLE album_aliases(".
		"id CHAR(10),".
		"alias TEXT NOT NULL,".
		"FOREIGN KEY (id) REFERENCES albums(id) ON DELETE CASCADE,".
		"INDEX b (id)".
	");";
	if(!$db->query($q))
		kill("Error creating album_aliases table", true);
	$q = "CREATE PROCEDURE update_album_aliases
		(IN albumid CHAR(10), IN aliases TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(aliases))) = 0 THEN
				DELETE album_aliases FROM album_aliases WHERE id = albumid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), alias TEXT);
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(aliases, ',');
			INSERT temp_table SELECT * FROM (SELECT albumid) a CROSS JOIN split_results b;

			DELETE album_aliases
			FROM album_aliases
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE album_aliases.id = albumid;

			INSERT album_aliases
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN album_aliases b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_album_aliases procedure: ".$db->error);





	$q = "CREATE TABLE songs(".
		"id CHAR(10) PRIMARY KEY,".
		"filepath TEXT NOT NULL UNIQUE,".
		"hash TEXT UNIQUE,".
		"title TEXT,".
		"track_number VARCHAR(9),".
		"disc_number SMALLINT,".
		"genre VARCHAR(30),".
		"artist CHAR(10),".
		"guest_artists TEXT,".
		"album_id CHAR(10),".
		"duration SMALLINT UNSIGNED NOT NULL,".
		"import_date DATE NOT NULL,".
		"true_import_date DATE NOT NULL,".
		"release_date DATE,".
		"comment TEXT,".
		"art TEXT,".
		"embedded_art BIT(1) NOT NULL,".
		"INDEX (album_id),".
		"INDEX (genre),".
		"INDEX (duration),".
		"INDEX (import_date),".
		"INDEX (release_date),".
		"FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL,".
		"FOREIGN KEY (artist) REFERENCES artists(id) ON DELETE SET NULL".
	");";
	if(!$db->query($q))
		kill("Error creating songs table", true);
	$q = "CREATE TABLE song_tags(".
		"id CHAR(10),".
		"tag VARCHAR(100),".
		"FOREIGN KEY (id) REFERENCES songs(id) ON DELETE CASCADE,".
		"PRIMARY KEY(id,tag),".
		"INDEX b (tag)".
	");";
	if(!$db->query($q))
		kill("Error creating song_tags table", true);
	$q = "CREATE PROCEDURE update_song_tags
		(IN songid CHAR(10), IN tags TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(tags))) = 0 THEN
				DELETE song_tags FROM song_tags WHERE id = songid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), tag VARCHAR(100));
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(tags, '|');
			INSERT temp_table SELECT * FROM (SELECT songid) a CROSS JOIN split_results b;

			DELETE song_tags
			FROM song_tags
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE song_tags.id = songid;

			INSERT song_tags
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN song_tags b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_song_tags procedure", true);
	$q = "CREATE TABLE song_artists(".
		"song CHAR(10),".
		"artist CHAR(10),".
		"FOREIGN KEY (song) REFERENCES songs(id) ON DELETE CASCADE,".
		"FOREIGN KEY (artist) REFERENCES artists(id) ON DELETE CASCADE,".
		"PRIMARY KEY(song,artist),".
		"INDEX b (artist)".
	");";
	if(!$db->query($q))
		kill("Error creating song_artists table", true);
	$q = "CREATE PROCEDURE update_song_artists
		(IN songid CHAR(10), IN artists TEXT)
		proc:BEGIN
			DECLARE rowcount INT DEFAULT 0;
			DECLARE first CHAR(10) DEFAULT '';
			IF LENGTH(LTRIM(RTRIM(artists))) = 0 THEN
				DELETE song_artists FROM song_artists WHERE song = songid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(song CHAR(10), artist char(10));
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(artists, '|');

			INSERT temp_table
			SELECT DISTINCT songid,GetArtistId_Exact(b.parts) AS artist
			FROM (SELECT songid) a
			CROSS JOIN split_results b;

			DELETE FROM temp_table
			WHERE LENGTH(artist) = 0;

			DELETE song_artists
			FROM song_artists
			LEFT JOIN temp_table
			ON temp_table.song IS NULL
			WHERE song_artists.song = songid;

			SELECT COUNT(*) FROM temp_table INTO rowcount;
			IF rowcount = 0 THEN
				LEAVE proc;
			END IF;

			SELECT artist FROM temp_table LIMIT 1 INTO first;
			UPDATE songs SET songs.artist = artist;
			DELETE FROM temp_table LIMIT 1;

			INSERT song_artists
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN song_artists b WHERE b.song IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_song_artists procedure", true);
	/*
	 * Assign is used when the artist ID is already known
	 */
	$q = "CREATE PROCEDURE assign_song_artist
		(IN songid CHAR(10), IN artistid CHAR(10))
		proc:BEGIN
			DELETE song_artists
			FROM song_artists
			WHERE song_artists.song = songid;

			INSERT song_artists VALUES(songid, artistid);
		END;";
	if(!$db->query($q))
		kill("Error creating assign_song_artist procedure", true);
	$q = "CREATE TABLE song_aliases(".
		"id CHAR(10),".
		"alias TEXT NOT NULL,".
		"FOREIGN KEY (id) REFERENCES songs(id) ON DELETE CASCADE,".
		"INDEX b (id)".
	");";
	if(!$db->query($q))
		kill("Error creating song_aliases table", true);
	$q = "CREATE PROCEDURE update_song_aliases
		(IN id CHAR(10), IN aliases TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(aliases))) = 0 THEN
				DELETE song_aliases FROM song_aliases WHERE id = id;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), aliases TEXT);
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(aliases, '|');
			INSERT temp_table SELECT * FROM (SELECT id) a CROSS JOIN split_results b;

			DELETE song_aliases
			FROM song_aliases
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE song_aliases.id = id;

			INSERT song_aliases
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN song_aliases b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_song_aliases procedure", true);
	$q = "CREATE TABLE song_covers(
		original CHAR(10),
		cover CHAR(10),
		FOREIGN KEY (original) REFERENCES songs(id) ON DELETE CASCADE,
		FOREIGN KEY (cover) REFERENCES songs(id) ON DELETE CASCADE
	);";
	if(!$db->query($q))
		kill("Error creating song_covers table", true);
	$q = "CREATE TABLE song_remixes(
		original CHAR(10),
		remix CHAR(10),
		FOREIGN KEY (original) REFERENCES songs(id) ON DELETE CASCADE,
		FOREIGN KEY (remix) REFERENCES songs(id) ON DELETE CASCADE
	);";
	if(!$db->query($q))
		kill("Error creating song_remixes table", true);
	$q = "CREATE TABLE song_parodys(
		original CHAR(10),
		parody CHAR(10),
		FOREIGN KEY (original) REFERENCES songs(id) ON DELETE CASCADE,
		FOREIGN KEY (parody) REFERENCES songs(id) ON DELETE CASCADE
	);";
	if(!$db->query($q))
		kill("Error creating song_parodys table", true);
	$q = "CREATE TABLE song_intros(
		intro_id CHAR(10),
		subject_id CHAR(10),
		FOREIGN KEY (intro_id) REFERENCES songs(id) ON DELETE CASCADE,
		FOREIGN KEY (subject_id) REFERENCES songs(id) ON DELETE CASCADE,
		PRIMARY KEY(intro_id,subject_id),
		INDEX b (subject_id)
	);";
	if(!$db->query($q))
		kill("Error creating song_intros table", true);
	//TODO - Intro song IDs are passed in, need to use those songs' track numbers to get subsequent song
	/*$q = "CREATE PROCEDURE add_intro_songs
		(IN songs TEXT)
		proc:BEGIN
			CREATE TEMPORARY TABLE IF NOT EXISTS temp_table(intro_id CHAR(10), subject_id TEXT);
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(songs, '|');
		END;";
	if(!$db->query($q))
		kill("Error creating add_intro_songs procedure", true);*/





	$q = "CREATE TABLE videos(".
		"id CHAR(10) PRIMARY KEY,".
		"filepath TEXT NOT NULL,".
		"hash TEXT UNIQUE,".
		"titles TEXT,".
		"genre VARCHAR(30),".
		"guest_artists TEXT,".
		"duration SMALLINT UNSIGNED NOT NULL,".
		"release_date DATE,".
		"import_date DATE NOT NULL,".
		"true_import_date DATE NOT NULL,".
		"type ENUM(ZZZZ),".
		"comment TEXT,".
		"INDEX (genre),".
		"INDEX (duration),".
		"INDEX (release_date),".
		"INDEX (import_date),".
		"INDEX (type)".
	");";
	array_to_enum($q, $video_types);
	if(!$db->query($q))
		kill("Error creating videos table", true);
	$q = "CREATE TABLE video_artists(".
		"video CHAR(10),".
		"artist CHAR(10),".
		"FOREIGN KEY (video) REFERENCES videos(id) ON DELETE CASCADE,".
		"FOREIGN KEY (artist) REFERENCES artists(id) ON DELETE CASCADE,".
		"PRIMARY KEY(video,artist),".
		"INDEX b (artist)".
	");";
	if(!$db->query($q))
		kill("Error creating video_artists table", true);
	$q = "CREATE PROCEDURE update_video_artists
		(IN videoid CHAR(10), IN artists TEXT)
		proc:BEGIN
			DECLARE rowcount INT DEFAULT 0;
			DECLARE first CHAR(10) DEFAULT '';
			IF LENGTH(LTRIM(RTRIM(artists))) = 0 THEN
				DELETE video_artists FROM video_artists WHERE video = videoid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(video CHAR(10), artist char(10));
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(artists, '|');

			INSERT temp_table
			SELECT DISTINCT videoid,GetArtistId_Exact(b.parts) AS artist
			FROM (SELECT videoid) a
			CROSS JOIN split_results b;

			DELETE FROM temp_table
			WHERE LENGTH(artist) = 0;

			DELETE video_artists
			FROM video_artists
			LEFT JOIN temp_table
			ON temp_table.video IS NULL
			WHERE video_artists.video = videoid;

			SELECT COUNT(*) FROM temp_table INTO rowcount;
			IF rowcount = 0 THEN
				LEAVE proc;
			END IF;

			INSERT video_artists
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN video_artists b WHERE b.video IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_video_artists procedure", true);
	/*
	 * Assign is used when the artist ID is already known
	 */
	$q = "CREATE PROCEDURE assign_video_artist
		(IN videoid CHAR(10), IN artistid CHAR(10))
		proc:BEGIN
			DELETE video_artists
			FROM video_artists
			WHERE video_artists.video = videoid;

			INSERT video_artists VALUES(videoid, artistid);
		END;";
	if(!$db->query($q))
		kill("Error creating assign_video_artist procedure", true);
	$q = "CREATE TABLE video_tags(".
		"id CHAR(10),".
		"tag VARCHAR(100),".
		"FOREIGN KEY (id) REFERENCES videos(id) ON DELETE CASCADE,".
		"PRIMARY KEY(id,tag),".
		"INDEX b (tag)".
	");";
	if(!$db->query($q))
		kill("Error creating video_tags table", true);
	$q = "CREATE PROCEDURE update_video_tags
		(IN videoid CHAR(10), IN tags TEXT)
		proc:BEGIN
			IF LENGTH(LTRIM(RTRIM(tags))) = 0 THEN
				DELETE video_tags FROM video_tags WHERE id = videoid;
				LEAVE proc;
			END IF;
			DROP TEMPORARY TABLE IF EXISTS temp_table;
			CREATE TEMPORARY TABLE temp_table(id CHAR(10), tags TEXT);
			DELETE FROM temp_table;
			CREATE TEMPORARY TABLE IF NOT EXISTS split_results(parts TEXT);
			DELETE FROM split_results;
			CALL split(tags, ',');
			INSERT temp_table SELECT * FROM (SELECT videoid) a CROSS JOIN split_results b;

			DELETE video_tags
			FROM video_tags
			LEFT JOIN temp_table
			ON temp_table.id IS NULL
			WHERE video_tags.id = videoid;

			INSERT video_tags
			(SELECT a.* FROM temp_table a NATURAL LEFT JOIN video_tags b WHERE b.id IS NULL);
		END;";
	if(!$db->query($q))
		kill("Error creating update_video_tags procedure: ".$db->error);




	$q = "CREATE TABLE favorites(".
		"id CHAR(10) NOT NULL,".
		"type ENUM('song', 'video', 'album', 'artist') NOT NULL,".
		"PRIMARY KEY(id,type)".
	");";
	if(!$db->query($q))
		kill("Error creating favorites table", true);


	if(!$db->query("CREATE VIEW favorite_artists AS SELECT a.* FROM artists a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite artists view", true);
	if(!$db->query("CREATE VIEW favorite_albums AS SELECT a.* FROM albums a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite albums view", true);
	if(!$db->query("CREATE VIEW favorite_videos AS SELECT a.* FROM videos a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite videos view", true);
	if(!$db->query("CREATE VIEW favorite_songs AS SELECT a.* FROM songs a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite songs view", true);

	//Write config file
	$config = fopen("config.php", "w");
	fwrite($config,
	'<?php'."\n".
	'$dburl = "'.$dburl.'";'."\n".
	'$dbname = "'.$dbname.'";'."\n".
	'$dbuser = "'.$dbuser.'";'."\n".
	'$dbpass = "'.$dbpass.'";'."\n".
	'$dbport = "'.$dbport.'";'."\n".
	'$artist_info_file = "'.$artist_info_file.'";'."\n".
	'$album_info_file = "'.$album_info_file.'";'."\n".
	'$album_art_filename = "'.$album_art_filename.'";'."\n".
	'$album_art_directory = "'.$album_art_directory.'";'."\n".
	'$song_art_directory = "'.$song_art_directory.'";'."\n".
	'$album_thumbnail_directory = "'.$album_thumbnail_directory.'";'."\n".
	'$song_thumbnail_directory = "'.$song_thumbnail_directory.'";'."\n".
	'$thumbnail_format = "'.$thumbnail_format.'";'."\n".
	'$thumbnail_quality = "'.$thumbnail_quality.'";'."\n".
	'?>');
	fclose($config);
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
	<div>Database created. Navigating to library page...<span>4</span></div>
	<script>setTimeout(function(){document.querySelector("span").innerText = 3}, 1000)</script>
	<script>setTimeout(function(){document.querySelector("span").innerText = 2}, 2000)</script>
	<script>setTimeout(function(){document.querySelector("span").innerText = 1}, 3000)</script>
	<script>setTimeout(function(){location.href="library.php"}, 4000)</script>
</body>
</html>
