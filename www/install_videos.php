<?php
function Videos()
{
	GLOBAL $db;
	GLOBAL $video_types;
	$q = "CREATE TABLE videos(".
		"id CHAR(10) PRIMARY KEY,".
		"filepath TEXT NOT NULL,".
		"last_update INT NOT NULL,".
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
}
?>
