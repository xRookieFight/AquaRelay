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

namespace aquarelay\form;

use aquarelay\player\Player;

/**
 * Form implementations must implement this interface to be able to utilize the Player form-sending mechanism.
 * There is no restriction on custom implementations other than that they must implement this.
 */
interface Form extends \JsonSerializable{

	/**
	 * Handles a form response from a player.
	 *
	 * @param Player $player
	 * @param mixed $data
	 *
	 * @throws FormValidationException if the data could not be processed
	 */
	public function handleResponse(Player $player, mixed $data) : void;
}
