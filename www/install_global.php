<?php
function Procedures()
{
	GLOBAL $db;
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
}

function Views()
{
	GLOBAL $db;
	if(!$db->query("CREATE VIEW favorite_artists AS SELECT a.* FROM artists a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite artists view", true);
	if(!$db->query("CREATE VIEW favorite_albums AS SELECT a.* FROM albums a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite albums view", true);
	if(!$db->query("CREATE VIEW favorite_videos AS SELECT a.* FROM videos a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite videos view", true);
	if(!$db->query("CREATE VIEW favorite_songs AS SELECT a.* FROM songs a JOIN favorites b ON a.id = b.id;"))
		kill("Error creating favorite songs view", true);
}
?>
