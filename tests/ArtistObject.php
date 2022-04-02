<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require("../www/config.php");
require("../www/util.php");
function import_warn($str) { }
function import_error($str) { }

final class ArtistObject extends TestCase
{
	public function testCreation(): array
	{
		$artiststr = file_get_contents("testartist/artist.nfo");
		$artist = make_artist_obj($artiststr);
		$this->assertIsArray($artist);
		return $artist;
	}

	/**
	 * @depends testCreation
	 */
	public function testHashProp(array $artist): void
	{
		$this->assertArrayHasKey("hash", $artist);
		$this->assertGreaterThan(0, strlen($artist["hash"]));
	}
}
