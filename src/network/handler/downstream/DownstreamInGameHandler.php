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

use aquarelay\network\rewrite\RewriteData;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandEnumRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandRawData;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use function strtolower;

class DownstreamInGameHandler extends AbstractDownstreamPacketHandler
{

	public function handleAvailableCommands(AvailableCommandsPacket $packet): bool
	{
		$player = $this->getPlayer();
		$server = $player->getServer();

		if (!$server->getConfig()->getMiscSettings()->getCommandInjection()){
			return false;
		}

		$commandMap = $server->getCommandMap();

		$added = [];

		foreach ($commandMap->getCommands() as $command) {
			$name = strtolower($command->getName());

			if (isset($added[$name])) {
				continue;
			}

			if (!$command->testPermission($player)) {
				continue;
			}

			$aliasIndexes = [];
			$aliases = $command->getAliases();
			$aliases[] = $name;

			foreach ($aliases as $alias) {
				$alias = strtolower($alias);

				$index = array_search($alias, $packet->enumValues, true);
				if ($index === false) {
					$packet->enumValues[] = $alias;
					$index = count($packet->enumValues) - 1;
				}

				$aliasIndexes[] = $index;
			}

			$enumIndex = -1;

			if ($aliasIndexes !== []) {
				$packet->enums[] = new CommandEnumRawData(
					ucfirst($name) . "Aliases",
					$aliasIndexes
				);

				$enumIndex = count($packet->enums) - 1;
			}

			$packet->commandData[] = new CommandRawData(
				$name,
				$command->getBuilder()->getDescription(),
				0,
				"any",
				$enumIndex,
				[],
				[]
			);

			$added[$name] = true;
		}

		return false;
	}

	public function handleStartGame(StartGamePacket $packet) : bool
	{
		RewriteData::sendChunkRadius($this->getPlayer());

		$position = $packet->playerPosition;
		$runtimeId = $packet->actorRuntimeId;

		if ($this->getPlayer()->getRewriteData()->isTransferring()){
			$this->getPlayer()->getRewriteData()->setLastPosition($position);

			RewriteData::injectPosition($this->getPlayer(), $position, $runtimeId);
			RewriteData::injectDimChange($this->getPlayer(), DimensionIds::NETHER, $position); // TODO: what if the player is already in nether?
		}

		$this->getPlayer()->getRewriteData()->setActorRuntimeId($runtimeId);
		return false;
	}

	public function handlePlayStatus(PlayStatusPacket $packet) : bool
	{
		if ($packet->status === PlayStatusPacket::LOGIN_SUCCESS) {
			$this->getPlayer()->getNetworkSession()->debug('Forwarding LOGIN_SUCCESS from backend to client');
			return false;
		}
		if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
			$player = $this->getPlayer();
			$rewriteData = $player->getRewriteData();
			$actorRuntimeId = $rewriteData->getActorRuntimeId();

			if ($actorRuntimeId === null) {
				$player->getNetworkSession()->debug('Cannot send spawn notification: backendRuntimeId is null.');
			} else {
				if ($player->getRewriteData()->isTransferring()) {
					RewriteData::injectPosition($player, $rewriteData->getLastPosition(), $actorRuntimeId);
					RewriteData::injectDimChange($player, DimensionIds::OVERWORLD, $rewriteData->getLastPosition());

					$player->getDownstream()->sendGamePacket(SetLocalPlayerAsInitializedPacket::create($actorRuntimeId));
					$player->getRewriteData()->setTransferring(false);
				}
				$player->getNetworkSession()->debug('Sending spawn notification, waiting for spawn response');
			}
		}

		return false;
	}

	public function handleTransfer(TransferPacket $packet) : bool
	{
		$serverManager = $this->getPlayer()->getServer()->getServerManager();
		$ipAddress = $packet->address;

		$server = $serverManager->get($ipAddress);
		if ($server !== null) {
			$packet->address = $this->getPlayer()->getServer()->getAddress();
			$packet->port = $this->getPlayer()->getServer()->getPort();
			$this->getPlayer()->transfer($server);
			return true;
		}

		$port = $packet->port;

		foreach ($serverManager->getAll() as $data) {
			if ($data->getAddress() === $ipAddress && $data->getPort() === $port) {
				$packet->address = $this->getPlayer()->getServer()->getAddress();
				$packet->port = $this->getPlayer()->getServer()->getPort();
				$this->getPlayer()->transfer($serverManager->get($data->getName()));
				break;
			}
		}

		return true;
	}

	public function handleDisconnect(DisconnectPacket $packet) : bool
	{
		$this->getPlayer()->tryFallbackOrDisconnect();
		return false;
	}
}
