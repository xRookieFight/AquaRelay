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

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function is_array;
use function is_string;
use function sprintf;

final class ResourcePackManifest
{
	public function __construct(
		private string $name,
		private UuidInterface $uuid,
		private string $version,
		private string $type
	) {}

	public function getName() : string
	{
		return $this->name;
	}

	public function getUuid() : UuidInterface
	{
		return $this->uuid;
	}

	public function getVersion() : string
	{
		return $this->version;
	}

	public function getType() : string
	{
		return $this->type;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data) : self
	{
		$header = $data['header'] ?? null;
		if (!is_array($header)) {
			throw new ResourcePackException("manifest.json is missing header");
		}

		$name = $header['name'] ?? null;
		$uuid = $header['uuid'] ?? null;
		$version = $header['version'] ?? null;

		if (!is_string($name) || !is_string($uuid) || !is_array($version)) {
			throw new ResourcePackException("manifest.json header is invalid");
		}

		if (!Uuid::isValid($uuid)) {
			throw new ResourcePackException("manifest.json has invalid uuid");
		}

		$versionString = sprintf(
			"%d.%d.%d",
			(int) ($version[0] ?? 0),
			(int) ($version[1] ?? 0),
			(int) ($version[2] ?? 0)
		);

		$modules = $data['modules'] ?? null;
		if (!is_array($modules) || $modules === [] || !is_array($modules[0])) {
			throw new ResourcePackException("manifest.json has no modules");
		}

		$type = $modules[0]['type'] ?? null;
		if (!is_string($type)) {
			throw new ResourcePackException("manifest.json module type is invalid");
		}

		return new self($name, Uuid::fromString($uuid), $versionString, $type);
	}
}
