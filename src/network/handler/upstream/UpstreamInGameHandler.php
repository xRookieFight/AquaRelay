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

namespace aquarelay\network\handler\upstream;

use aquarelay\event\default\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use function array_shift;
use function count;
use function explode;
use function implode;
use function ltrim;
use function strtolower;
use function trim;

class UpstreamInGameHandler extends AbstractUpstreamPacketHandler
{
	public function shouldForwardUnhandled() : bool
	{
		return true;
	}

	public function handleText(TextPacket $packet) : bool
	{
		$event = new PlayerChatEvent($this->session->getPlayer(), $packet->message);
		$event->call();

		$packet->message = $event->getMessage();

		if ($event->isCancelled()) {
			return true;
		}
		
		return false;
	}

	public function handleCommandRequest(CommandRequestPacket $packet) : bool
	{
		$message = trim($packet->command);

		if ($message === "" || $message[0] !== "/") {
			return false;
		}

		$commandLine = ltrim($message, "/");

		if ($commandLine === "") {
			return false;
		}

		if ($this->session->getPlayer() !== null) {
			$player = $this->session->getPlayer();
			$commandMap = $player->getServer()->getCommandMap();

			$parts = explode(" ", $commandLine);
			$commandName = strtolower(array_shift($parts));
			$args = $parts;

			$command = $commandMap->getCommand($commandName);

			if ($command === null) {
				return false;
			}
			
			$commandMap->dispatch($player, $commandName . (count($args) > 0 ? " " : "") . implode(" ", $args));
		}
		return true;
	}

	public function handleModalFormResponse(ModalFormResponsePacket $packet) : bool
	{
		if ($this->session->getPlayer()->onFormSubmit($packet->formId, $packet->formData)) {
			return true;
		}
		
		return false;
	}
}
