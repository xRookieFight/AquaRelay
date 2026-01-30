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

use aquarelay\plugin\loader\PluginLoaderInterface;
use aquarelay\ProxyServer;
use aquarelay\utils\MainLogger;
use function array_map;
use function count;
use function explode;
use function is_dir;
use function mkdir;
use function scandir;
use const DIRECTORY_SEPARATOR;

class PluginLoader
{
    private MainLogger $logger;
    private string $dataPath;
    /** @var PluginLoaderInterface[] */
    private array $loaders = [];
    
    /** * @var array<string, mixed> 
     * Stores currently loaded plugins to prevent duplicates 
     */
    private array $loadedPlugins = [];

    public function __construct(private readonly ProxyServer $server, private readonly string $pluginsPath)
    {
        $this->logger = $server->getLogger();
        $this->dataPath = $this->pluginsPath . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0o755, true);
        }
    }

    public function registerLoader(PluginLoaderInterface $loader) : void
    {
        foreach ($this->loaders as $loaders) {
            if ($loaders === $loader){
                throw new PluginException("Loader already registered");
            }
        }

        $this->loaders[] = $loader;
    }

    /**
     * @return PluginLoaderInterface[]
     */
    public function getLoaders() : array
    {
        return $this->loaders;
    }

    /**
     * Loads all plugins from the plugins directory.
     */
    public function loadPlugins() : array
    {
        $plugins = [];

        foreach (scandir($this->pluginsPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'data') continue;

            $path = $this->pluginsPath . DIRECTORY_SEPARATOR . $entry;

            foreach ($this->getLoaders() as $loader) {
                if (!$loader->canLoad($path)) {
                    continue;
                }

                try {
                    $plugin = $loader->load($path);
                    if ($plugin !== null) {
                        if (isset($this->loadedPlugins[$plugin->getName()])) {
                            break;
                        }
                        
                        if (isset($plugins[$plugin->getName()])) {
                             $this->logger->warning("Duplicate plugin '{$plugin->getName()}' detected in scan. Ignoring $entry.");
                             break;
                        }

                        $this->loadedPlugins[$plugin->getName()] = $plugin;
                        $plugins[$plugin->getName()] = $plugin;
                    }
                } catch (\Throwable $e) {
                    $this->server->getLogger()->error("Failed to load plugin $entry: {$e->getMessage()}");
                }

                break;
            }
        }


        return $plugins;
    }

    /**
     * Checks if the plugin API version is compatible with the server API version.
     *
     * @param string $pluginVersion Version required by plugin (e.g. "5.0.0")
     * @param string $serverVersion Version of server (e.g. "5.3.2")
     */
    public function isCompatible(string $pluginVersion, string $serverVersion) : bool
    {
        $pluginParts = array_map('intval', explode('.', $pluginVersion));
        $serverParts = array_map('intval', explode('.', $serverVersion));

        for ($i = count($pluginParts); $i < 3; ++$i) {
            $pluginParts[$i] = 0;
        }
        for ($i = count($serverParts); $i < 3; ++$i) {
            $serverParts[$i] = 0;
        }

        if ($pluginParts[0] !== $serverParts[0]) {
            return false;
        }

        for ($i = 1; $i < 3; ++$i) {
            if ($serverParts[$i] < $pluginParts[$i]) {
                return false;
            }
        }

        return true;
    }
}