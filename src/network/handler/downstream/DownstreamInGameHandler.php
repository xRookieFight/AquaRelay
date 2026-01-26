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
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TransferPacket;

class DownstreamInGameHandler extends AbstractDownstreamPacketHandler
{

	public function handleAvailableCommands(AvailableCommandsPacket $packet) : bool
	{
		// TODO: Command injection system
		// EXAMPLE: $packet->commandData[] = new CommandRawData("test", "Test command for AquaRelay", 0, "any", -1, [], []);
		$this->getPlayer()->setHandler(new DownstreamInGameHandler($this->getPlayer(), $this->logger));
		return true;
	}

	public function handleStartGame(StartGamePacket $packet) : bool
	{
		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;

		$this->getPlayer()->getDownstream()->sendGamePacket($chunkRadiusPacket);
		$this->getPlayer()->backendRuntimeId = $packet->actorRuntimeId;

		return true;
	}

	public function handlePlayStatus(PlayStatusPacket $packet) : bool
	{
		if ($packet->status === PlayStatusPacket::LOGIN_SUCCESS) {
			$this->getPlayer()->getNetworkSession()->debug('Forwarding LOGIN_SUCCESS from backend to client');
			return true;
		}
		if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
			if ($this->getPlayer()->backendRuntimeId === null) {
				$this->getPlayer()->getNetworkSession()->debug('Cannot send spawn notification: backendRuntimeId is null.');
			} else {
				$this->getPlayer()->getNetworkSession()->debug('Sending spawn notification, waiting for spawn response');
			}
		}

		return true;
	}

    public function handleTransfer(TransferPacket $packet): bool
    {
		$serverManager = $this->getPlayer()->getServer()->getServerManager();
        $ipAddress = $packet->address;

		$server = $serverManager->get($ipAddress);
		if ($server !== null){
			$this->getPlayer()->transferToBackend($server);
			return true;
		}

        $port = $packet->port;

        foreach ($serverManager->getAll() as $data) {
            if ($data->getAddress() === $ipAddress && $data->getPort() === $port) {
                $this->getPlayer()->transferToBackend($serverManager->get($data->getName()));
				break;
            }
        }

        return true;
    }

    public function handleDisconnect(DisconnectPacket $packet): bool
    {
        $this->getPlayer()->tryFallbackOrDisconnect();
        return true;
    }
}
