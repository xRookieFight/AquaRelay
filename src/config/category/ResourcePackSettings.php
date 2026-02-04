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

namespace aquarelay\config\category;

final class ResourcePackSettings
{
	public function __construct(
		private bool $enabled,
		private bool $forceAccept,
		private bool $overwriteClientPacks,
		private string $packsPath
	) {}

	public function isEnabled() : bool
	{
		return $this->enabled;
	}

	public function setEnabled(bool $enabled) : void
	{
		$this->enabled = $enabled;
	}

	public function isForceAccept() : bool
	{
		return $this->forceAccept;
	}

	public function setForceAccept(bool $forceAccept) : void
	{
		$this->forceAccept = $forceAccept;
	}

	public function isOverwriteClientPacks() : bool
	{
		return $this->overwriteClientPacks;
	}

	public function setOverwriteClientPacks(bool $overwriteClientPacks) : void
	{
		$this->overwriteClientPacks = $overwriteClientPacks;
	}

	public function getPacksPath() : string
	{
		return $this->packsPath;
	}

	public function setPacksPath(string $packsPath) : void
	{
		$this->packsPath = $packsPath;
	}
}
