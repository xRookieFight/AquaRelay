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

/**
 * Manages plugin loading and lifecycle
 */
class PluginManager {

	private ProxyServer $server;
	private PluginLoader $loader;
	/** @var PluginBase[] */
	private array $plugins = [];

	public function __construct(ProxyServer $server, PluginLoader $loader)
	{
		$this->server = $server;
		$this->loader = $loader;
	}

	/**
	 * Loads all plugins from the plugins directory
	 */
	public function loadPlugins() : void
	{
		$this->plugins = $this->loader->loadPlugins();
		$this->server->getLogger()->info("Loaded " . count($this->plugins) . " plugin(s)");

		foreach ($this->plugins as $plugin) {
			try {
				$this->enablePlugin($plugin);
			} catch (PluginException $e) {
				$this->server->getLogger()->error("Failed to enable plugin {$plugin->getName()}: " . $e->getMessage());
			}
		}
	}

	/**
	 * Enables a plugin
	 */
	public function enablePlugin(PluginBase $plugin) : void
	{
		if ($plugin->isEnabled()) {
			return;
		}

		$dependencies = $plugin->getDescription()->getDependencies();
		foreach ($dependencies as $dep) {
			if (!isset($this->plugins[$dep])) {
				throw new PluginException("Plugin {$plugin->getName()} requires plugin $dep which is not loaded");
			}
		}

		$plugin->setEnabled(true);
		$plugin->onEnable();
		$this->server->getLogger()->info("Enabled plugin: " . $plugin->getName());
	}

	/**
	 * Disables a plugin
	 */
	public function disablePlugin(PluginBase $plugin) : void
	{
		if (!$plugin->isEnabled()) {
			return;
		}

		$plugin->setEnabled(false);
		$plugin->onDisable();
		$this->server->getLogger()->info("Disabled plugin: " . $plugin->getName());
	}

	/**
	 * Gets a plugin by name
	 */
	public function getPlugin(string $name) : ?PluginBase
	{
		return $this->plugins[$name] ?? null;
	}

	/**
	 * Gets all loaded plugins
	 */
	public function getPlugins() : array
	{
		return $this->plugins;
	}

	/**
	 * Gets the number of loaded plugins
	 */
	public function getPluginCount() : int
	{
		return count($this->plugins);
	}
}