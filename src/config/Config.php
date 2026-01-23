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

namespace aquarelay\config;

use Symfony\Component\Yaml\Yaml;
use function file_exists;
use function file_put_contents;

/**
 * Configuration manager for plugins.
 */
class Config
{
	private array $data = [];
	private string $filePath;

	public function __construct(string $filePath)
	{
		$this->filePath = $filePath;
		if (file_exists($this->filePath)) {
			$this->data = Yaml::parseFile($this->filePath) ?? [];
		}
	}

	/**
	 * Gets a value from the config.
	 *
	 * @param null|mixed $default
	 */
	public function get(string $key, $default = null)
	{
		return $this->data[$key] ?? $default;
	}

	/**
	 * Sets a value in the config.
	 *
	 * @param mixed $value
	 */
	public function set(string $key, $value) : void
	{
		$this->data[$key] = $value;
	}

	/**
	 * Removes a value from the config.
	 */
	public function remove(string $key) : void
	{
		if (isset($this->data[$key])) {
			unset($this->data[$key]);
		}
	}

	/**
	 * Sets default values.
	 * These will only be applied if the key does not already exist.
	 */
	public function setDefaults(array $defaults) : void
	{
		$this->data += $defaults;
	}

	/**
	 * Loads a default configuration file and merges it.
	 * Existing values in the current config will take precedence.
	 */
	public function loadDefault(string $defaultFilePath) : void
	{
		if (file_exists($defaultFilePath)) {
			$defaults = Yaml::parseFile($defaultFilePath) ?? [];
			$this->setDefaults($defaults);
		}
	}

	/**
	 * Saves the default config if it doesn't exist.
	 */
	public function saveDefaultConfig() : void
	{
		if (!file_exists($this->filePath)) {
			file_put_contents($this->filePath, Yaml::dump($this->data));
		}
	}

	/**
	 * Saves the config to file.
	 */
	public function save() : void
	{
		file_put_contents($this->filePath, Yaml::dump($this->data));
	}

	/**
	 * Gets all config data.
	 */
	public function getAll() : array
	{
		return $this->data;
	}
}
