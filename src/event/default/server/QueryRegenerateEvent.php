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

namespace aquarelay\event\default\server;

use aquarelay\event\Event;

class QueryRegenerateEvent extends Event
{
	private string $name;
    private string $subMotd;
    private int $maxPlayers;
    private int $currentPlayers;

	public function __construct(string $name, string $subMotd, int $maxPlayers, int $currentPlayers)
    {
        $this->name = $name;
        $this->subMotd = $subMotd;
        $this->maxPlayers = $maxPlayers;
        $this->currentPlayers = $currentPlayers;
	}

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubMotd(): string
    {
        return $this->subMotd;
    }

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function getCurrentPlayers(): int
    {
        return $this->currentPlayers;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setSubMotd(string $subMotd): void
    {
        $this->subMotd = $subMotd;
    }

    public function setMaxPlayers(int $maxPlayers): void
    {
        $this->maxPlayers = $maxPlayers;
    }

    public function setCurrentPlayers(int $currentPlayers): void
    {
        $this->currentPlayers = $currentPlayers;
    }
}
