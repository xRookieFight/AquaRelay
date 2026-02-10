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
use aquarelay\event\HandlerList;
use aquarelay\event\Listener;
use aquarelay\ProxyServer;
use aquarelay\task\TaskScheduler;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use function copy;
use function dirname;
use function error_log;
use function file_exists;
use function is_dir;
use function mkdir;
use function trim;

abstract class Plugin
{
	private PluginDescription $description;
	private ProxyServer $server;
	private bool $enabled = false;
	private string $dataFolder;
	private string $resourceFolder;
	private ?Config $config = null;

	public function onLoad() : void {}
	public function onEnable() : void {}
	public function onDisable() : void {}

	public function setDescription(PluginDescription $description) : void
	{
		$this->description = $description;
	}

	public function getDescription() : PluginDescription
	{
		return $this->description;
	}

	public function setServer(ProxyServer $server) : void
	{
		$this->server = $server;
	}

	public function getServer() : ProxyServer
	{
		return $this->server;
	}

	public function getName() : string
	{
		return $this->description->getName();
	}

	public function getVersion() : string
	{
		return $this->description->getVersion();
	}

	public function getAuthors() : array
	{
		return $this->description->getAuthors();
	}

	public function isEnabled() : bool
	{
		return $this->enabled;
	}

	public function setEnabled(bool $enabled) : void
	{
		$this->enabled = $enabled;
	}

	public function getScheduler() : TaskScheduler
	{
		return $this->server->getScheduler();
	}

   public function setDataFolder(string $dataFolder) : void
	{
		$this->dataFolder = $dataFolder;
	}

	public function setResourceFolder(string $resourceFolder) : void
	{
		$this->resourceFolder = $resourceFolder;
	}

	public function getDataFolder() : string
	{
		return $this->dataFolder;
	}

	public function saveResource(string $filename, bool $replace = false) : void
	{
		if (trim($filename) === "") {
			return;
		}

		if (!isset($this->resourceFolder)) {
			throw new RuntimeException("Resource folder not initialized for plugin " . $this->getName());
		}

		$source = Path::join($this->resourceFolder, $filename);

		if (!file_exists($source)) {
			error_log("Warning: Could not save resource '$filename' for " . $this->getName() . ". Source not found.");
			return;
		}

		$destination = Path::join($this->dataFolder, $filename);
		$destDir = dirname($destination);

		if (!is_dir($destDir)) {
			mkdir($destDir, 0755, true);
		}

		if (file_exists($destination) && !$replace) {
			return;
		}

		copy($source, $destination);
	}

	public function saveFile(string $file) : void
	{
		$this->saveResource($file, false);
	}

	public function registerEvent(Listener $listener) : void
	{
		HandlerList::register($listener);
	}

	/**
	 * Gets the config object safely.
	 */
	public function getConfig() : ?Config
	{
		if ($this->config !== null) {
			return $this->config;
		}

		$folder = $this->getDataFolder();
		$configPath = Path::join($folder, "config.yml");

		if (!file_exists($configPath)) {
			$this->saveDefaultConfig();
		}

		if (file_exists($configPath)) {
			$this->config = new Config($configPath);
		}

		return $this->config;
	}

	public function saveDefaultConfig() : void
	{
		$this->saveResource("config.yml");
	}
}
