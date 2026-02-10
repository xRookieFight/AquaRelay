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

namespace aquarelay\config;

use aquarelay\config\category\GameSettings;
use aquarelay\config\category\MiscSettings;
use aquarelay\config\category\NetworkSettings;
use aquarelay\config\category\PermissionSettings;
use aquarelay\config\category\ResourcePackSettings;
use aquarelay\config\category\ServerSettings;
use Symfony\Component\Yaml\Yaml;

readonly class ProxyConfig
{
	public function __construct(
		private GameSettings       $gameSettings,
		private ServerSettings     $serverSettings,
		private PermissionSettings $permissionSettings,
		private MiscSettings       $miscSettings,
		private NetworkSettings    $networkSettings,
		private ResourcePackSettings $resourcePackSettings
	) {}

	public static function load(string $file, string $path) : self
	{
		$data = Yaml::parseFile($file);

		$configVersion = $data['config-version'] ?? 0;

		if (!ConfigUpdater::getInstance()->isUpToDate($configVersion)) {
			ConfigUpdater::getInstance()->update($file, $path);
		}

		$gameSettings = $data['game-settings'];
		$serverSettings = $data['server-settings'];
		$permissionSettings = $data['permissions'];
		$miscSettings = $data['misc-settings'];
		$networkSettings = $data['network-settings'];
		$resourcePackSettings = $data['resource_pack-settings'];

		return new self(
			new GameSettings(
				(int) $gameSettings['max-players'],
				$gameSettings['motd'],
				$gameSettings['sub-motd'],
				(bool) $gameSettings['xbox-auth']
			),
			new ServerSettings(
				(array) $serverSettings["servers"],
				$serverSettings['selection-strategy']
			),
			new PermissionSettings(
				(array) $permissionSettings
			),
			new MiscSettings(
				(bool) $miscSettings['debug-mode'],
				$miscSettings['log-name'],
				$miscSettings['language'],
				(bool) $miscSettings['command-injection']
			),
			new NetworkSettings(
				$networkSettings['bind']['address'],
				(int) $networkSettings['bind']['port'],
				(int) $networkSettings['batch-threshold'],
				(int) $networkSettings['compression-level'],
				(int) $networkSettings['max-mtu']
			),
			new ResourcePackSettings(
				(bool) $resourcePackSettings['enabled'],
				(bool) $resourcePackSettings['force-accept'],
				(bool) $resourcePackSettings['overwrite-client-packs'],
				$resourcePackSettings['packs-path']
			)
		);
	}

	public function getGameSettings() : GameSettings
	{
		return $this->gameSettings;
	}

	public function getServerSettings() : ServerSettings
	{
		return $this->serverSettings;
	}

	public function getPermissionSettings() : PermissionSettings
	{
		return $this->permissionSettings;
	}

	public function getMiscSettings() : MiscSettings
	{
		return $this->miscSettings;
	}

	public function getNetworkSettings() : NetworkSettings
	{
		return $this->networkSettings;
	}

	public function getResourcePackSettings() : ResourcePackSettings
	{
		return $this->resourcePackSettings;
	}
}
