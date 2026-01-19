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

namespace aquarelay\plugin;

use aquarelay\ProxyServer;
use aquarelay\utils\MainLogger;
use Symfony\Component\Yaml\Yaml;
use function is_dir;
use function is_file;
use function is_subclass_of;
use function scandir;

/**
 * Loads plugins from directory and phar files
 */
class PluginLoader {

	private ProxyServer $server;
	private MainLogger $logger;
	private string $pluginsPath;

	public function __construct(ProxyServer $server, string $pluginsPath)
	{
		$this->server = $server;
		$this->logger = $server->getLogger();
		$this->pluginsPath = $pluginsPath;
	}

	/**
	 * Loads all plugins from the plugins directory
	 */
	public function loadPlugins() : array
	{
		$plugins = [];

		if (!is_dir($this->pluginsPath)) {
			$this->logger->debug("Plugins directory does not exist, creating it...");
			if (!@mkdir($this->pluginsPath, 0755, true)) {
				$this->logger->warning("Failed to create plugins directory");
				return $plugins;
			}
		}

		$entries = @scandir($this->pluginsPath);
		if ($entries === false) {
			$this->logger->error("Failed to scan plugins directory");
			return $plugins;
		}

		$count = count($entries) - 2;
		$this->logger->debug("Found $count potential plugin(s)");

		foreach ($entries as $entry) {
			if ($entry === "." || $entry === "..") {
				continue;
			}

			$fullPath = $this->pluginsPath . DIRECTORY_SEPARATOR . $entry;

			try {
				if (is_dir($fullPath)) {
					$plugin = $this->loadDirectoryPlugin($fullPath);
					if ($plugin !== null) {
						$plugins[$plugin->getName()] = $plugin;
					}
				}
				elseif (is_file($fullPath) && str_ends_with($fullPath, ".phar")) {
					$plugin = $this->loadPharPlugin($fullPath);
					if ($plugin !== null) {
						$plugins[$plugin->getName()] = $plugin;
					}
				}
			} catch (PluginException $e) {
				$this->logger->error("Failed to load plugin from $entry: " . $e->getMessage());
			} catch (\Throwable $e) {
				$this->logger->error("Unexpected error loading plugin from $entry: " . $e->getMessage() . " (File: " . $e->getFile() . ":" . $e->getLine() . ")");
			}
		}

		return $plugins;
	}

	/**
	 * Loads a plugin from a directory
	 */
	private function loadDirectoryPlugin(string $path) : ?PluginBase
	{
		$pluginYmlPath = $path . DIRECTORY_SEPARATOR . "plugin.yml";

		if (!file_exists($pluginYmlPath)) {
			return null;
		}

		$data = Yaml::parseFile($pluginYmlPath);
		$description = PluginDescription::fromYaml($data);

		$vendorPath = $path . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
		if (file_exists($vendorPath)) {
			require_once $vendorPath;
		}

		$mainClass = $description->getMain();
		
		$classFile = $path . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . str_replace("\\", DIRECTORY_SEPARATOR, $mainClass) . ".php";
		
		if (file_exists($classFile)) {
			require_once $classFile;
		} else {
			$this->logger->debug("Expected class file not found at: $classFile");
		}

		if (!class_exists($mainClass)) {
			throw new PluginException("Main class $mainClass not found in $path");
		}

		if (!is_subclass_of($mainClass, PluginBase::class)) {
			throw new PluginException("Main class must extend " . PluginBase::class);
		}

		$plugin = new $mainClass();
		$plugin->setDescription($description);
		$plugin->setServer($this->server);
		$plugin->onLoad();

		$this->logger->info("Successfully loaded plugin: " . $plugin->getName() . " v" . $plugin->getVersion());

		return $plugin;
	}

	/**
	 * Loads a plugin from a phar archive
	 */
	private function loadPharPlugin(string $path) : ?PluginBase
	{
		try {
			$pharYmlPath = "phar://" . $path . "/plugin.yml";

			if (!file_exists($pharYmlPath)) {
				$this->logger->debug("No plugin.yml found in phar: $path");
				return null;
			}

			$content = file_get_contents($pharYmlPath);
			$data = Yaml::parse($content);
			$description = PluginDescription::fromYaml($data);

			$vendorPath = "phar://" . $path . "/vendor/autoload.php";
			if (file_exists($vendorPath)) {
				require_once $vendorPath;
			}

			$mainClass = $description->getMain();
			$classFile = "phar://" . $path . "/src/" . str_replace("\\", "/", $mainClass) . ".php";

			if (file_exists($classFile)) {
				require_once $classFile;
			} else {
				$this->logger->debug("Expected class file not found in phar at: $classFile");
			}

			if (!class_exists($mainClass)) {
				throw new PluginException("Main class $mainClass not found in phar: $path");
			}

			if (!is_subclass_of($mainClass, PluginBase::class)) {
				throw new PluginException("Main class must extend " . PluginBase::class);
			}

			$plugin = new $mainClass();
			$plugin->setDescription($description);
			$plugin->setServer($this->server);
			$plugin->onLoad();

			$this->logger->info("Successfully loaded plugin from phar: " . $plugin->getName() . " v" . $plugin->getVersion());

			return $plugin;
		} catch (\PharException $e) {
			throw new PluginException("Failed to load phar: " . $e->getMessage());
		}
	}
}
