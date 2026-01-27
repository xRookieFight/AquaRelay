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

namespace aquarelay\command\builder;

readonly class CommandBuilder {

	public function __construct(
		private string  $name,
		private string  $description = "",
		private string  $usage = "",
		private array   $aliases = [],
		private ?string $permission = null
	) {}

	public function getName() : string { return $this->name; }
	public function getDescription() : string { return $this->description; }
	public function getUsage() : string { return $this->usage; }
	public function getAliases() : array { return $this->aliases; }
	public function getPermission() : ?string { return $this->permission; }
}
