<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require("../www/config.php");
require("../www/util.php");

final class AlbumObject extends TestCase
{
	public function testCreation(): array
	{
		$artist = make_album_obj("testalbum");
		$this->assertIsArray($artist);
		return $artist;
	}

	/**
	 * @depends testCreation
	 */
	public function testDirectoryProp(array $artist): array
	{
		$this->assertArrayHasKey("directory", $artist);
		$this->assertGreaterThan(0, strlen($artist["directory"]));
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
