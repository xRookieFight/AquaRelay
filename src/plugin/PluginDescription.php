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

/**
 * Stores plugin metadata from plugin.yml
 */
class PluginDescription {

	private string $name;
	private string $version;
	private string $main;
	private string $description = "";
	private array $authors = [];
	private string $website = "";
	private array $dependencies = [];
	private array $softDependencies = [];

	public function __construct(
		string $name,
		string $version,
		string $main
	) {
		$this->name = $name;
		$this->version = $version;
		$this->main = $main;
	}

	public static function fromYaml(array $data) : self
	{
		$name = $data["name"] ?? "";
		$version = $data["version"] ?? "1.0.0";
		$main = $data["main"] ?? "";

		if (empty($name) || empty($main)) {
			throw new PluginException("Plugin description must have 'name' and 'main' fields");
		}

		$desc = new self($name, $version, $main);
		$desc->description = $data["description"] ?? "";
		$desc->authors = $data["authors"] ?? [];
		$desc->website = $data["website"] ?? "";
		$desc->dependencies = $data["depend"] ?? [];
		$desc->softDependencies = $data["softdepend"] ?? [];

		return $desc;
	}

	public function getName() : string { return $this->name; }
	public function getVersion() : string { return $this->version; }
	public function getMain() : string { return $this->main; }
	public function getDescription() : string { return $this->description; }
	public function getAuthors() : array { return $this->authors; }
	public function getWebsite() : string { return $this->website; }
	public function getDependencies() : array { return $this->dependencies; }
	public function getSoftDependencies() : array { return $this->softDependencies; }
}
