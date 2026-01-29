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

namespace aquarelay\plugin\loader;

use aquarelay\ProxyServer;
use aquarelay\plugin\Plugin;
use aquarelay\plugin\PluginDescription;
use aquarelay\plugin\PluginException;
use Symfony\Component\Yaml\Yaml;

use function class_exists;
use function file_exists;
use function is_subclass_of;
use function str_replace;

readonly class PharPluginLoader implements PluginLoaderInterface
{
	public function __construct(
		private ProxyServer $server,
		private string $dataPath
	) {}

	public function canLoad(string $path) : bool
	{
		return is_file($path) && str_ends_with($path, '.phar');
	}

	public function load(string $path) : ?Plugin
	{
		try {
			$phar = new \Phar($path);
		} catch (\Throwable $e) {
			throw new PluginException("Invalid phar file: {$e->getMessage()}");
		}

		if (!isset($phar['plugin.yml'])) {
			throw new PluginException("plugin.yml not found in phar");
		}

		$yaml = Yaml::parse($phar['plugin.yml']->getContent());
		if (!is_array($yaml)) {
			throw new PluginException("plugin.yml is invalid");
		}

		$description = PluginDescription::fromYaml($yaml);

		$vendor = "phar://$path/vendor/autoload.php";
		if (file_exists($vendor)) {
			require_once $vendor;
		}

		$main = $description->getMain();
		$mainFile = "phar://{$path}/src/" . str_replace('\\', '/', $main) . '.php';

		if (!file_exists($mainFile)) {
			throw new PluginException("Main class file not found: $mainFile");
		}

		require_once $mainFile;

		if (!class_exists($main, false)) {
			throw new PluginException("Main class $main not found");
		}

		$plugin = new $main();
		$plugin->setDescription($description);
		$plugin->setServer($this->server);
		$plugin->setDataFolder($this->dataPath . DIRECTORY_SEPARATOR . $description->getName());
		$plugin->onLoad();

		return $plugin;

	}
}