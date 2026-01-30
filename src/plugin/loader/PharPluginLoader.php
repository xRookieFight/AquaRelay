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

readonly class PharPluginLoader implements PluginLoaderInterface
{
    public function __construct(
        private ProxyServer $server,
        private string $dataPath
    ) {}

    public function canLoad(string $path): bool
    {
        return is_file($path) && str_ends_with($path, '.phar');
    }

    public function load(string $path): ?Plugin
    {
        $pharPath = "phar://{$path}";

        if (!file_exists($pharPath . '/plugin.yml')) {
            throw new PluginException("Invalid Plugin: 'plugin.yml' missing in '{$path}'");
        }

        try {
            $data = Yaml::parse(file_get_contents($pharPath . '/plugin.yml'));
            $description = PluginDescription::fromYaml($data);
        } catch (\Throwable $e) {
            throw new PluginException("Error loading plugin.yml in '{$path}': {$e->getMessage()}");
        }

        $vendorAutoload = $pharPath . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        }

        $srcPath = $pharPath . '/src';
        if (file_exists($srcPath)) {
            $this->registerAutoloader($srcPath);
        }

        $mainClass = $description->getMain();
        
        if (!class_exists($mainClass, true)) {
            $mainFile = $srcPath . '/' . str_replace('\\', '/', $mainClass) . '.php';
            if (file_exists($mainFile)) {
                require_once $mainFile;
            } else {
                throw new PluginException("Main class file not found: $mainFile");
            }
        }

        if (!class_exists($mainClass)) {
            throw new PluginException("Class '$mainClass' not found. Namespace mismatch?");
        }

        try {
            $plugin = new $mainClass();
            $plugin->setDescription($description);
            $plugin->setServer($this->server);
            $plugin->setDataFolder($this->dataPath . 'data' . DIRECTORY_SEPARATOR . $description->getName());
            $plugin->setResourceFolder($pharPath . '/resources');
            $plugin->onLoad();
        } catch (\Throwable $e) {
            throw new PluginException("Error enabling plugin '{$description->getName()}': {$e->getMessage()}");
        }

        return $plugin;
    }

    private function registerAutoloader(string $srcPath): void
    {
        spl_autoload_register(function (string $class) use ($srcPath): void {
            $path = str_replace('\\', '/', $class);
            $file = $srcPath . '/' . $path . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}