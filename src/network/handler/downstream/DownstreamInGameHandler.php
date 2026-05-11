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

use aquarelay\event\default\player\PlayerJoinEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketDisassembler;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use function array_map;
use function in_array;
use function strtolower;
use function ucfirst;

class DownstreamInGameHandler extends AbstractDownstreamPacketHandler
{

	public function handleAvailableCommands(AvailableCommandsPacket $packet) : bool{
		$player = $this->getPlayer();
		$server = $player->getServer();

		if(!$server->getConfig()->getMiscSettings()->getCommandInjection()){
			return false;
		}

		$commandMap = $server->getCommandMap();

		$disassembled = AvailableCommandsPacketDisassembler::disassemble($packet);
		$data = $disassembled->commandData;
		$softEnums = $disassembled->unusedSoftEnums;
		$hardEnums = $disassembled->unusedHardEnums;

		foreach ($commandMap->getCommands() as $command) {
				$exists = false;

				foreach($data as $commandData){
						if(strtolower($commandData->name) === strtolower($command->getName())){
								$exists = true;
								break;
						}
				}

				if($exists || $command->getName() === "help" || !$command->testPermission($player)){
						continue;
				}

				$name = strtolower($command->getName());
				$enum = null;
				$aliases = array_map('strtolower', $command->getAliases());

				if (!empty($aliases)) {
						if (!in_array($name, $aliases, true)) {
								$aliases[] = $name;
						}

						$enum = new CommandHardEnum(
								ucfirst($name) . "Aliases",
								$aliases
						);
				}

				$commandData = new CommandData(
						$name,
						$command->getBuilder()->getDescription(),
						0,
						CommandPermissions::NORMAL,
						$enum,
						[
							new CommandOverload(false, [
								CommandParameter::standard(
										"args",
										AvailableCommandsPacket::ARG_TYPE_RAWTEXT,
										0,
										true
								)
							]),
						],
						[]
				);

				$data[] = $commandData;
		}

		$this->getPlayer()->sendDataPacket(
				AvailableCommandsPacketAssembler::assemble($data, $hardEnums, $softEnums)
		);

		return true;
	}

	public function handleStartGame(StartGamePacket $packet) : bool
	{
		$player = $this->getPlayer();
		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;

		if ($player->getRewriteData()->entityId !== 0) {
			$player->getDownstream()->sendGamePacket($chunkRadiusPacket);
		}
		$player->setBackendRuntimeId($packet->actorRuntimeId);

		$rewriteData = $player->getRewriteData();
		$rewriteData->entityId = $packet->actorRuntimeId;
		$rewriteData->originalEntityId = $packet->actorRuntimeId;
		$rewriteData->dimension = $packet->levelSettings->spawnSettings->getDimension();

		return false;
	}

	public function handleAddPlayer(AddPlayerPacket $packet) : bool
	{
		$this->getPlayer()->addEntity($packet->actorRuntimeId);
		return false;
	}

	public function handleAddActor(AddActorPacket $packet) : bool
	{
		$this->getPlayer()->addEntity($packet->actorRuntimeId);
		return false;
	}

	public function handleRemoveActor(RemoveActorPacket $packet) : bool
	{
		$this->getPlayer()->removeEntity($packet->actorUniqueId);
		return false;
	}

	public function handleBossEvent(BossEventPacket $packet) : bool
	{
		if ($packet->eventType === BossEventPacket::TYPE_SHOW) {
			$this->getPlayer()->addBossbar($packet->bossActorUniqueId);
		} elseif ($packet->eventType === BossEventPacket::TYPE_HIDE) {
			$this->getPlayer()->removeBossbar($packet->bossActorUniqueId);
		}
		return false;
	}

	public function handlePlayerList(PlayerListPacket $packet) : bool
	{
		$player = $this->getPlayer();
		foreach ($packet->entries as $entry) {
			if ($packet->type === PlayerListPacket::TYPE_ADD) {
				$player->addPlayerToList($entry->uuid->toString());
			} else {
				$player->removePlayerFromList($entry->uuid->toString());
			}
		}
		return false;
	}

	public function handleSetDisplayObjective(SetDisplayObjectivePacket $packet) : bool
	{
		$this->getPlayer()->addObjective($packet->objectiveName);
		return false;
	}

	public function handleRemoveObjective(RemoveObjectivePacket $packet) : bool
	{
		$this->getPlayer()->removeObjective($packet->objectiveName);
		return false;
	}

	public function handlePlayStatus(PlayStatusPacket $packet) : bool
	{
		if ($packet->status === PlayStatusPacket::LOGIN_SUCCESS) {
			$this->getPlayer()->getNetworkSession()->getLogger()->debug('Suppressing duplicate LOGIN_SUCCESS from backend');
			return true;
		}
		if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
			if ($this->getPlayer()->backendRuntimeId === null) {
				$this->getPlayer()->getNetworkSession()->getLogger()->debug('Cannot send spawn notification: backendRuntimeId is null.');
			} else {
				$this->getPlayer()->getNetworkSession()->getLogger()->debug('Sending spawn notification, waiting for spawn response');
				$event = new PlayerJoinEvent($this->getPlayer());
				$event->call();
			}
		}

		return false;
	}

	public function handleTransfer(TransferPacket $packet) : bool
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

	public function handleDisconnect(DisconnectPacket $packet) : bool
	{
		$this->getPlayer()->tryFallbackOrDisconnect();
		return true;
	}
}
