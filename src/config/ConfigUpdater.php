<?php

/*
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *             |_|                                |___/
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

namespace aquarelay\config;

use aquarelay\utils\InstanceTrait;

class ConfigUpdater {

	use InstanceTrait;

	public const CONFIG_VERSION = 1;

	public function isUpToDate(int $configVersion) : bool
	{
		return $configVersion >= self::CONFIG_VERSION;
	}

	public function update(array $current, array $config) : array {
		foreach ($config as $key => $value) {
			if (!array_key_exists($key, $current)) {
				$current[$key] = $value;
				continue;
			}

			if (is_array($value) && is_array($current[$key])) {
				$current[$key] = self::update($current[$key], $value);
			}
		}

		foreach ($current as $key => $_) {
			if (!array_key_exists($key, $config)) {
				unset($current[$key]);
			}
		}

		return $current;
	}
}