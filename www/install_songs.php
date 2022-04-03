<?php
function Songs()
{
	GLOBAL $db;
	$q = "CREATE TABLE songs(".
		"id CHAR(10) PRIMARY KEY,".
		"filepath TEXT NOT NULL UNIQUE,".
		"last_update INT NOT NULL,".
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
}
?>
