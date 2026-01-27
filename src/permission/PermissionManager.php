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

namespace aquarelay\permission;

use aquarelay\config\ProxyConfig;
use function array_map;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;

class PermissionManager
{
	/** @var array<string, string[]> */
	private array $playerPermissions = [];

	/** @var array<string, PermissionAttachment[]> */
	private array $attachments = [];

	public function __construct(ProxyConfig $config)
	{
		foreach ($config->getPermissionSettings()->getPlayers() as $player => $permissions) {
			$this->playerPermissions[strtolower($player)] = array_map('strtolower', $permissions);
		}
	}

	public function hasPermission(string $playerName, string $permission) : bool
	{
		$permission = strtolower($permission);
		$playerName = strtolower($playerName);

		foreach ($this->playerPermissions[$playerName] ?? [] as $perm) {
			if ($this->matches($perm, $permission)) {
				return true;
			}
		}

		foreach ($this->attachments[$playerName] ?? [] as $attachment) {
			foreach ($attachment->getPermissions() as $perm => $value) {
				if ($value && $this->matches($perm, $permission)) {
					return true;
				}
			}
		}

		return false;
	}

	private function matches(string $defined, string $requested) : bool
	{
		if ($defined === '*') {
			return true;
		}

		if ($defined === $requested) {
			return true;
		}

		if (str_ends_with($defined, '.*')) {
			$base = substr($defined, 0, -2);
			return str_starts_with($requested, $base . '.');
		}

		return false;
	}

	public function addAttachment(string $playerName, PermissionAttachment $attachment) : void
	{
		$this->attachments[strtolower($playerName)][] = $attachment;
	}
}
