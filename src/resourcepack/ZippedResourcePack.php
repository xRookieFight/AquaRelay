<?php

/*
 *
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *               |_|                              |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AquaRelay Team
 * @link https://www.aquarelay.dev/
 *
 */

declare(strict_types=1);

namespace aquarelay\resourcepack;

use Ramsey\Uuid\UuidInterface;
use function fclose;
use function feof;
use function file_exists;
use function filesize;
use function fopen;
use function fread;
use function fseek;
use function hash_file;
use function is_string;
use function json_decode;
use function preg_match;
use function strlen;
use const JSON_THROW_ON_ERROR;

final class ZippedResourcePack implements ResourcePack
{
	private ResourcePackManifest $manifest;
	private ?string $sha256 = null;
	private string $path;
	/** @var resource */
	private $fileResource;

	public function __construct(string $path)
	{
		$this->path = $path;

		if (!file_exists($path)) {
			throw new ResourcePackException("Resource pack file not found");
		}

		$size = filesize($path);
		if ($size === false || $size === 0) {
			throw new ResourcePackException("Resource pack file is empty or unreadable");
		}

		$zip = new \ZipArchive();
		if ($zip->open($path) !== true) {
			throw new ResourcePackException("Resource pack is not a valid zip");
		}

		$manifestData = $zip->getFromName("manifest.json");
		if ($manifestData === false) {
			$manifestPath = null;
			for ($i = 0; $i < $zip->numFiles; ++$i) {
				$name = $zip->getNameIndex($i);
				if (!is_string($name)) {
					continue;
				}
				if (preg_match('#.*/manifest.json$#', $name) === 1) {
					if ($manifestPath === null || strlen($name) < strlen($manifestPath)) {
						$manifestPath = $name;
					}
				}
			}
			if ($manifestPath !== null) {
				$manifestData = $zip->getFromName($manifestPath);
			}
		}

		$zip->close();

		if (!is_string($manifestData)) {
			throw new ResourcePackException("manifest.json not found in resource pack");
		}

		try {
			$manifestArray = json_decode($manifestData, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new ResourcePackException("Failed to parse manifest.json: " . $e->getMessage(), 0, $e);
		}

		if (!is_array($manifestArray)) {
			throw new ResourcePackException("manifest.json should be a JSON object");
		}

		$this->manifest = ResourcePackManifest::fromArray($manifestArray);

		$this->fileResource = fopen($this->path, "rb");
	}

	public function __destruct()
	{
		fclose($this->fileResource);
	}

	public function getName() : string
	{
		return $this->manifest->getName();
	}

	public function getUuid() : UuidInterface
	{
		return $this->manifest->getUuid();
	}

	public function getVersion() : string
	{
		return $this->manifest->getVersion();
	}

	public function getType() : string
	{
		return $this->manifest->getType();
	}

	public function getSize() : int
	{
		return (int) filesize($this->path);
	}

	public function getSha256() : string
	{
		if ($this->sha256 === null) {
			$this->sha256 = hash_file("sha256", $this->path, true);
		}
		return $this->sha256;
	}

	public function getChunk(int $offset, int $length) : string
	{
		if ($length < 1) {
			throw new ResourcePackException("Invalid resource pack chunk length");
		}

		fseek($this->fileResource, $offset);
		if (feof($this->fileResource)) {
			throw new ResourcePackException("Requested resource pack chunk out of range");
		}

		$data = fread($this->fileResource, $length);
		if ($data === false) {
			throw new ResourcePackException("Failed to read resource pack chunk");
		}
		return $data;
	}
}
