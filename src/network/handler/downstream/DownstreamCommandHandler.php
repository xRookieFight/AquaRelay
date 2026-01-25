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

namespace aquarelay\network\handler\downstream;

use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandRawData;

class DownstreamCommandHandler extends AbstractDownstreamPacketHandler
{

	public function handleAvailableCommands(AvailableCommandsPacket $packet): bool
	{
		// TODO: Command injection system
		// EXAMPLE: $packet->commandData[] = new CommandRawData("test", "Test command for AquaRelay", 0, "any", -1, [], []);
		$this->getPlayer()->setHandler(new DownstreamInGameHandler($this->getPlayer(), $this->logger));
		return true;
	}
}