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

namespace aquarelay\network\rewrite;

use aquarelay\player\Player;
use aquarelay\server\BackendServer;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;

final class RewriteData
{
	private ?int $actorRuntimeId = null;
	private bool $isTransferring = false;
	private ?Vector3 $lastPosition = null;
	private ?BackendServer $targetServer = null;

	public static function create(
		?int $actorRuntimeId = null,
		bool $isTransferring = false,
		?Vector3 $lastPosition = null,
		?BackendServer $targetServer = null
	) : self
	{
		$rewriteData = new self();
		$rewriteData->actorRuntimeId = $actorRuntimeId;
		$rewriteData->isTransferring = $isTransferring;
		$rewriteData->lastPosition = $lastPosition;
		$rewriteData->targetServer = $targetServer;

		return $rewriteData;
	}

	public static function sendChunkRadius(Player $player) : void
	{
		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;

		$player->getDownstream()?->sendGamePacket($chunkRadiusPacket);
	}

	public static function injectDimChange(Player $player, int $dimensionId, Vector3 $position) : void
	{
		$changeDim = new ChangeDimensionPacket();
		$changeDim->position = $position;
		$changeDim->respawn = true;
		$changeDim->dimension = $dimensionId;
		$player->getNetworkSession()->sendDataPacket($changeDim);

		self::injectChunkPublisher($player, $position);
		self::injectEmptyChunks($player, $position, $dimensionId);
	}

	public static function injectChunkPublisher(Player $player, Vector3 $defaultSpawn) : void
	{
		$packet = NetworkChunkPublisherUpdatePacket::create(
			new BlockPosition($defaultSpawn->getFloorX(), $defaultSpawn->getFloorY(), $defaultSpawn->getFloorZ()),
			3,
			[]
		);
		$player->getNetworkSession()->sendDataPacket($packet);
	}

	public static function injectEmptyChunks(
		Player $player,
		Vector3 $spawnPosition,
		int $dimension
	) : void
	{
		$radius = 3;
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

	public static function createChunkData() : string {
		$buffer = '';

		for ($i = 0; $i < 1; $i++) {
			$buffer .= chr(8); // subchunk version
			$buffer .= chr(0); // no block storages
		}

		// 1.18+ biome palette
		$buffer .= chr(0); // biome storage count

		// biome data (zeros)
		$buffer .= str_repeat("\x00\x00\x00\x00", 8);

		$buffer .= "\x00";

		// skylight
		$buffer .= chr(0);
		// block light
		$buffer .= chr(0);

		return $buffer;
	}

	public static function injectEmptyChunk(
		int $chunkX,
		int $chunkZ,
		int $dimension
	) : LevelChunkPacket {
		return LevelChunkPacket::create(new ChunkPosition($chunkX, $chunkZ), $dimension, 1, true, null, self::createChunkData());
	}

	public static function injectPosition(Player $player, Vector3 $position, int $runtimeId) : void
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

	public function setActorRuntimeId(int $actorRuntimeId) : void
	{
		$this->actorRuntimeId = $actorRuntimeId;
	}

	public function setTransferring(bool $value) : void
	{
		$this->isTransferring = $value;
	}

	public function setLastPosition(Vector3 $lastPosition) : void
	{
		$this->lastPosition = $lastPosition;
	}

	public function setTargetServer(BackendServer $server) : void
	{
		$this->targetServer = $server;
	}

	public function getActorRuntimeId() : ?int
	{
		return $this->actorRuntimeId;
	}

	public function isTransferring() : bool
	{
		return $this->isTransferring;
	}

	public function getLastPosition() : ?Vector3
	{
		return $this->lastPosition;
	}

	public function getTargetServer(): ?BackendServer
	{
		return $this->targetServer;
	}

	public function reset() : void
	{
		$this->actorRuntimeId = null;
		$this->isTransferring = false;
		$this->lastPosition = null;
		$this->targetServer = null;
	}
}