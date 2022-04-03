<?php
function Albums()
{
	GLOBAL $db;
	GLOBAL $album_types;
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
}
?>
