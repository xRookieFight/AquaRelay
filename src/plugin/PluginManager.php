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
use function count;

class PluginManager
{
	/** @var Plugin[] */
	private array $plugins = [];

	public function __construct(private readonly ProxyServer $server, private readonly PluginLoader $loader) {}

	/**
	 * Loads all plugins from the plugins directory.
	 */
	public function loadPlugins() : void
	{
		$this->plugins = $this->loader->loadPlugins();

		foreach ($this->plugins as $plugin) {
			try {
				$this->enablePlugin($plugin);
			} catch (PluginException $e) {
				$this->server->getLogger()->error("Failed to enable plugin {$plugin->getName()}: " . $e->getMessage());
			}
		}
	}

	public function registerLoader(PluginLoaderInterface $loader) : void
	{
		try {
			$this->loader->registerLoader($loader);
		} catch (PluginException $e) {
			$this->server->getLogger()->error("Failed to register plugin loader: {$e->getMessage()}");
		}
	}

	/**
	 * Enables a plugin.
	 *
	 * @throws PluginException
	 */
	public function enablePlugin(Plugin $plugin) : void
	{
		if ($plugin->isEnabled()) {
			return;
		}

		if (!$this->loader->isCompatible($plugin->getDescription()->getApiVersion(), $this->server::VERSION)) {
			throw new PluginException("Plugin requires API version {$plugin->getDescription()->getApiVersion()}, server is " . ProxyServer::VERSION);
		}

		if (str_starts_with($plugin->getDescription()->getMain(), "aquarelay")) {
			throw new PluginException("You cannot use namespace aquarelay");
		}

		if (!class_exists($plugin->getDescription()->getMain(), true)){
			throw new PluginException("Main class not found");
		}

		if (!is_a($plugin->getDescription()->getMain(), Plugin::class, true)) {
			throw new PluginException("Plugin should extend " . Plugin::class);
		}

		$reflect = new \ReflectionClass($plugin->getDescription()->getMain());
		if (!$reflect->isInstantiable()){
			throw new PluginException("Plugin is not instantiable");
		}

		$dependencies = $plugin->getDescription()->getDependencies();
		foreach ($dependencies as $dep) {
			if (!$this->plugins[$dep]->isEnabled()) {
				throw new PluginException("Dependency $dep is not enabled");
			}
		}

		$plugin->setEnabled(true);
		$plugin->onEnable();
		$this->server->getLogger()->info('Enabled plugin: ' . $plugin->getName());
	}

	/**
	 * Disables a plugin.
	 */
	public function disablePlugin(Plugin $plugin) : void
	{
		if (!$plugin->isEnabled()) {
			return;
		}

		$plugin->setEnabled(false);
		$plugin->onDisable();
		$this->server->getLogger()->info('Disabled plugin: ' . $plugin->getName());
	}

	/**
	 * Gets a plugin by name.
	 */
	public function getPlugin(string $name) : ?Plugin
	{
		return $this->plugins[$name] ?? null;
	}

	/**
	 * Gets all loaded plugins.
	 */
	public function getPlugins() : array
	{
		return $this->plugins;
	}

	/**
	 * Gets the number of loaded plugins.
	 */
	public function getPluginCount() : int
	{
		return count($this->plugins);
	}
}
