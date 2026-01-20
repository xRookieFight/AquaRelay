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

	private MainLogger $logger;

	public function __construct(private ProxyServer $server, private string $pluginsPath)
	{
		$this->logger = $server->getLogger();
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
	 * Checks if the plugin API version is compatible with the server API version
	 *
	 * @param string $pluginVersion Version required by plugin (e.g. "5.0.0")
	 * @param string $serverVersion Version of server (e.g. "5.3.2")
	 * @return bool
	 */
	private function isCompatible(string $pluginVersion, string $serverVersion) : bool {
		$pluginParts = array_map('intval', explode(".", $pluginVersion));
		$serverParts = array_map('intval', explode(".", $serverVersion));

		for ($i = count($pluginParts); $i < 3; $i++) $pluginParts[$i] = 0;
		for ($i = count($serverParts); $i < 3; $i++) $serverParts[$i] = 0;

		if ($pluginParts[0] !== $serverParts[0]) return false;

		for ($i = 1; $i < 3; $i++) {
			if ($serverParts[$i] < $pluginParts[$i]) return false;
		}

		return true;
	}

	/**
	 * Loads a plugin from a directory
	 * @throws PluginException
	 */
	private function loadDirectoryPlugin(string $path) : ?Plugin
	{
		$pluginYmlPath = $path . DIRECTORY_SEPARATOR . "plugin.yml";

		if (!file_exists($pluginYmlPath)) {
			return null;
		}

		$data = Yaml::parseFile($pluginYmlPath);
		$description = PluginDescription::fromYaml($data);

		if (!$this->isCompatible($description->getApiVersion(), $this->server::VERSION)) {
			$this->logger->error("Could not load plugin '{$description->getName()}': requires API version {$description->getApiVersion()}, server is " . ProxyServer::VERSION);
			return null;
		}

		$vendorPath = $path . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
		if (file_exists($vendorPath)) {
			require_once $vendorPath;
		}

		$srcPath = $path . DIRECTORY_SEPARATOR . "src";
		if (is_dir($srcPath)) {
			$this->registerPluginAutoloader($srcPath);
		}

		$this->loadPhpFilesRecursive($srcPath);

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

		if (!is_subclass_of($mainClass, Plugin::class)) {
			throw new PluginException("Main class must extend " . Plugin::class);
		}

		$plugin = new $mainClass();
		$plugin->setDescription($description);
		$plugin->setServer($this->server);
		
		try {
			$plugin->onLoad();
		} catch (\Throwable $e) {
			$this->logger->error("Error in plugin {$description->getName()} onLoad: " . $e->getMessage());
			throw new PluginException("Plugin onLoad failed: " . $e->getMessage());
		}
		return $plugin;
	}

	/**
	 * Recursively loads all PHP files from a directory
	 */
	private function loadPhpFilesRecursive(string $dir) : void
	{
		if (!is_dir($dir)) {
			return;
		}

		$files = @scandir($dir);
		if ($files === false) {
			return;
		}

		foreach ($files as $file) {
			if ($file === "." || $file === "..") {
				continue;
			}

			$fullPath = $dir . DIRECTORY_SEPARATOR . $file;

			if (is_dir($fullPath)) {
				$this->loadPhpFilesRecursive($fullPath);
			} elseif (is_file($fullPath) && str_ends_with($fullPath, ".php")) {
				require_once $fullPath;
			}
		}
	}

	/**
	 * Registers a custom autoloader for the plugin
	 */
	private function registerPluginAutoloader(string $srcPath) : void
	{
		spl_autoload_register(function(string $class) use ($srcPath) : void {
			$classPath = str_replace("\\", DIRECTORY_SEPARATOR, $class);
			$filePath = $srcPath . DIRECTORY_SEPARATOR . $classPath . ".php";

			if (file_exists($filePath)) {
				require_once $filePath;
			}
		});
	}

	/**
	 * Loads a plugin from a phar archive
	 * @throws PluginException
	 */
	private function loadPharPlugin(string $path) : ?Plugin
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

			if (!is_subclass_of($mainClass, Plugin::class)) {
				throw new PluginException("Main class must extend " . Plugin::class);
			}

			$plugin = new $mainClass();
			$plugin->setDescription($description);
			$plugin->setServer($this->server);
			$plugin->onLoad();

			return $plugin;
		} catch (\PharException $e) {
			throw new PluginException("Failed to load phar: " . $e->getMessage());
		}
	}
}