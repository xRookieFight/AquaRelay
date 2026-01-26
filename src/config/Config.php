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
use function explode;
use function file_exists;
use function file_put_contents;
use function is_array;

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
		$this->reload();
	}

	/**
	 * Reloads the config from the file.
	 */
	public function reload() : void
	{
		if (file_exists($this->filePath)) {
			$this->data = Yaml::parseFile($this->filePath) ?? [];
		} else {
			$this->data = [];
		}
	}

	/**
	 * Checks if a key exists (supports dot notation 'section.key').
	 */
	public function has(string $key) : bool
	{
		if (isset($this->data[$key])) {
			return true;
		}

		$parts = explode('.', $key);
		$current = $this->data;
		foreach ($parts as $part) {
			if (!isset($current[$part])) {
				return false;
			}
			$current = $current[$part];
		}
		return true;
	}

	/**
	 * Gets a value from the config.
	 */
	public function get(string $key, mixed $default = null)
	{
		return $this->data[$key] ?? $default;
	}

	/**
	 * Gets a nested value using dot notation (e.g., 'database.host').
	 */
	public function getNested(string $key, mixed $default = null)
	{
		$parts = explode('.', $key);
		$current = $this->data;

		foreach ($parts as $part) {
			if (!isset($current[$part])) {
				return $default;
			}
			$current = $current[$part];
		}

		return $current;
	}

	/**
	 * Sets a value in the config.
	 */
	public function set(string $key, $value) : void
	{
		$this->data[$key] = $value;
	}

	/**
	 * Sets a nested value using dot notation.
	 * *
	 */
	public function setNested(string $key, mixed $value) : void
	{
		$parts = explode('.', $key);
		$current = &$this->data;

		foreach ($parts as $part) {
			if (!isset($current[$part]) || !is_array($current[$part])) {
				$current[$part] = [];
			}
			$current = &$current[$part];
		}

		$current = $value;
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
	 */
	public function setDefaults(array $defaults) : void
	{
		$this->data = $defaults + $this->data;
	}

	/**
	 * Saves the config to file.
	 */
	public function save() : void
	{
		file_put_contents($this->filePath, Yaml::dump($this->data, 4, 2));
	}

	/**
	 * Gets all config data.
	 */
	public function getAll() : array
	{
		return $this->data;
	}
}
