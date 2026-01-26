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

namespace aquarelay\plugin;

use aquarelay\config\Config;
use aquarelay\ProxyServer;
use aquarelay\task\TaskScheduler;
use function is_dir;
use function mkdir;
use const DIRECTORY_SEPARATOR;

/**
 * Base class for all AquaRelay plugins.
 */
abstract class Plugin
{
	private PluginDescription $description;
	private ProxyServer $server;
	private bool $enabled = false;
	private string $dataFolder;
	private ?Config $config = null;

	/**
	 * Called when the plugin is loaded.
	 */
	public function onLoad() : void {}

	/**
	 * Called when the plugin is enabled.
	 */
	public function onEnable() : void {}

	/**
	 * Called when the plugin is disabled.
	 */
	public function onDisable() : void {}

	/**
	 * Sets the plugin description.
	 */
	public function setDescription(PluginDescription $description) : void
	{
		$this->description = $description;
	}

	/**
	 * Gets the plugin description.
	 */
	public function getDescription() : PluginDescription
	{
		return $this->description;
	}

	/**
	 * Sets the server instance.
	 */
	public function setServer(ProxyServer $server) : void
	{
		$this->server = $server;
	}

	/**
	 * Gets the server instance.
	 */
	public function getServer() : ProxyServer
	{
		return $this->server;
	}

	/**
	 * Gets the plugin name.
	 */
	public function getName() : string
	{
		return $this->description->getName();
	}

	/**
	 * Gets the plugin version.
	 */
	public function getVersion() : string
	{
		return $this->description->getVersion();
	}

	/**
	 * Gets the plugin authors.
	 */
	public function getAuthors() : array
	{
		return $this->description->getAuthors();
	}

	/**
	 * Checks if the plugin is enabled.
	 */
	public function isEnabled() : bool
	{
		return $this->enabled;
	}

	/**
	 * Sets the enabled state.
	 */
	public function setEnabled(bool $enabled) : void
	{
		$this->enabled = $enabled;
	}

	/**
	 * Returns task scheduler, alias of ProxyServer#getScheduler.
	 */
	public function getScheduler() : TaskScheduler
	{
		return $this->server->getScheduler();
	}

	/**
	 * Sets the data folder for the plugin.
	 */
	public function setDataFolder(string $dataFolder) : void
	{
		$this->dataFolder = $dataFolder;
		if (!is_dir($this->dataFolder)) {
			mkdir($this->dataFolder, 0o755, true);
		}
	}

	/**
	 * Gets the data folder for the plugin.
	 */
	public function getDataFolder() : string
	{
		return $this->dataFolder;
	}

	/**
	 * Gets the config object.
	 */
	public function getConfig() : Config
	{
		if ($this->config === null) {
			$configPath = $this->dataFolder . DIRECTORY_SEPARATOR . 'config.yml';
			$this->config = new Config($configPath);
		}

		return $this->config;
	}
}
