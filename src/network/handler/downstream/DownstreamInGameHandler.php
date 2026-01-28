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

use aquarelay\player\Player;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandEnumRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandRawData;
use function strtolower;

class DownstreamInGameHandler extends AbstractDownstreamPacketHandler
{

	public function handleAvailableCommands(AvailableCommandsPacket $packet): bool
	{
		$player = $this->getPlayer();
		$server = $player->getServer();

		if (!$server->getConfig()->getMiscSettings()->getCommandInjection()){
			return true;
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

		return true;
	}

	public function handleStartGame(StartGamePacket $packet) : bool
	{
		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;

		$this->getPlayer()->getDownstream()->sendGamePacket($chunkRadiusPacket);
		$this->getPlayer()->position = $packet->playerPosition;

		if ($this->getPlayer()->backendRuntimeId !== null){
			$this->injectPosition($this->getPlayer(), $this->getPlayer()->position, $packet->actorRuntimeId);
			$this->injectDimChange($this->getPlayer(), 1, $this->getPlayer()->position, $packet->actorRuntimeId);
		}
		$this->getPlayer()->backendRuntimeId = $packet->actorRuntimeId;

		return true;
	}

	private function injectDimChange(Player $player, int $dimensionId, Vector3 $position, int $entityId) : void
	{
		$changeDim = new ChangeDimensionPacket();
		$changeDim->position = $position;
		$changeDim->respawn = true;
		$changeDim->dimension = $dimensionId;
		$player->getNetworkSession()->sendDataPacket($changeDim);

		$this->injectChunkPublisher($player, $position, 3);
		$this->injectEmptyChunks($player, $position, 3, $dimensionId);
	}

	private function injectChunkPublisher(Player $player, Vector3 $defaultSpawn, int $radius) : void
	{
		$packet = NetworkChunkPublisherUpdatePacket::create(
			new BlockPosition($defaultSpawn->getFloorX(), $defaultSpawn->getFloorY(), $defaultSpawn->getFloorZ()),
			$radius,
			[]
		);
		$player->getNetworkSession()->sendDataPacket($packet);
	}

	private function injectEmptyChunks(
		Player $player,
		Vector3 $spawnPosition,
		int $radius,
		int $dimension
	) : void
	{
		$chunkX = $spawnPosition->getFloorX() >> 4;
		$chunkZ = $spawnPosition->getFloorZ() >> 4;

		for ($x = -$radius; $x <= $radius; $x++) {
			for ($z = -$radius; $z <= $radius; $z++) {
				$packet = self::injectEmptyChunk(
					$chunkX + $x,
					$chunkZ + $z,
					$dimension
				);
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	private function createChunkData(int $subChunkCount, int $biomeCount) : string {
		$buffer = '';

		for ($i = 0; $i < $subChunkCount; $i++) {
			$buffer .= chr(8); // subchunk version
			$buffer .= chr(0); // no block storages
		}

		// 1.18+ biome palette
		$buffer .= chr(0); // biome storage count

		// biome data (zeros)
		$buffer .= str_repeat("\x00\x00\x00\x00", $biomeCount);

		$buffer .= "\x00";

		// skylight
		$buffer .= chr(0);
		// block light
		$buffer .= chr(0);

		return $buffer;
	}

	private function injectEmptyChunk(
		int $chunkX,
		int $chunkZ,
		int $dimension
	) : LevelChunkPacket {
		return LevelChunkPacket::create(new ChunkPosition($chunkX, $chunkZ), $dimension, 1, true, null, $this->createChunkData(1,8));
	}

	private function injectPosition(Player $player, Vector3 $position, int $runtimeId) : void
	{
		$packet = new MovePlayerPacket();
		$packet->actorRuntimeId =  $runtimeId;
		$packet->position = $position;
		$packet->mode = MovePlayerPacket::MODE_RESET;
		$packet->pitch = 0.0;
		$packet->yaw = 0.0;
		$packet->headYaw = 0.0;

		$player->getNetworkSession()->sendDataPacket($packet);
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
				if ($this->getPlayer()->isTransferring){
					$this->injectPosition($this->getPlayer(), $this->getPlayer()->position, $this->getPlayer()->backendRuntimeId);
					$this->injectDimChange($this->getPlayer(), 0, $this->getPlayer()->position, $this->getPlayer()->backendRuntimeId);
					$this->getPlayer()->getDownstream()->sendGamePacket(SetLocalPlayerAsInitializedPacket::create($this->getPlayer()->backendRuntimeId));
					$this->getPlayer()->isTransferring = false;
				}
				$this->getPlayer()->getNetworkSession()->debug('Sending spawn notification, waiting for spawn response');
			}
		}

		return true;
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
		return true;
	}
}
