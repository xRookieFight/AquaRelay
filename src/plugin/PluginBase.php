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
 * Base class for all AquaRelay plugins
 */
abstract class PluginBase {

	private PluginDescription $description;
	private ProxyServer $server;
	private bool $enabled = false;

	/**
	 * Called when the plugin is loaded
	 */
	public function onLoad() : void
	{
	}

	/**
	 * Called when the plugin is enabled
	 */
	public function onEnable() : void
	{
	}

	/**
	 * Called when the plugin is disabled
	 */
	public function onDisable() : void
	{
	}

	/**
	 * Sets the plugin description
	 */
	public function setDescription(PluginDescription $description) : void
	{
		$this->description = $description;
	}

	/**
	 * Gets the plugin description
	 */
	public function getDescription() : PluginDescription
	{
		return $this->description;
	}

	/**
	 * Sets the server instance
	 */
	public function setServer(ProxyServer $server) : void
	{
		$this->server = $server;
	}

	/**
	 * Gets the server instance
	 */
	public function getServer() : ProxyServer
	{
		return $this->server;
	}

	/**
	 * Gets the plugin name
	 */
	public function getName() : string
	{
		return $this->description->getName();
	}

	/**
	 * Gets the plugin version
	 */
	public function getVersion() : string
	{
		return $this->description->getVersion();
	}

	/**
	 * Gets the plugin authors
	 */
	public function getAuthors() : array
	{
		return $this->description->getAuthors();
	}

	/**
	 * Checks if the plugin is enabled
	 */
	public function isEnabled() : bool
	{
		return $this->enabled;
	}

	/**
	 * Sets the enabled state
	 */
	public function setEnabled(bool $enabled) : void
	{
		$this->enabled = $enabled;
	}
}
