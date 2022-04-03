<?php
function Artists()
{
	GLOBAL $db;
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
}
?>
