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

namespace aquarelay\event\default\player;

use aquarelay\event\Cancellable;
use aquarelay\event\CancellableTrait;
use aquarelay\player\Player;

class PlayerChatEvent extends PlayerEvent implements Cancellable
{

	use CancellableTrait;

	public function __construct(Player $player, protected string $message)
	{
		$this->player = $player;
	}

	public function setMessage(string $message) : void{
		$this->message = $message;
	}

	public function getMessage() : string{
		return $this->message;
	}

}
