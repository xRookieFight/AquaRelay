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

namespace aquarelay\network\protocol;

use aquarelay\network\handler\downstream\DownstreamInGameHandler;
use aquarelay\player\Player;
use aquarelay\server\BackendServer;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class TransferCallback
{
	public const PHASE_RESET = 0;
	public const PHASE_1 = 1;
	public const PHASE_2 = 2;

	private int $phase = self::PHASE_1;

	public function __construct(
		private readonly Player        $player,
		private readonly ?BackendServer $sourceServer,
		private readonly int           $targetDimension
	) {}

	public function onDimChangeSuccess() : bool
	{
		return match ($this->phase) {
			self::PHASE_1 => $this->handlePhase1(),
			self::PHASE_2 => $this->handlePhase2(),
			default => false,
		};
	}

	private function handlePhase1() : bool
	{
		$this->phase = self::PHASE_2;
		$rewriteData = $this->player->getRewriteData();
		$session = $this->player->getNetworkSession();

		if ($rewriteData->dimension === $this->targetDimension) {
			return true;
		}

		$spawnPos = $rewriteData->spawnPosition ?? new Vector3(0, 64, 0);
		$fakePosition = $spawnPos->add(-2000, 0, -2000);

		PlayerRewriteUtils::injectPosition(
			$session,
			$fakePosition,
			$rewriteData->pitch,
			$rewriteData->yaw,
			$rewriteData->entityId
		);

		$rewriteData->dimension = PlayerRewriteUtils::determineDimensionId(
			$rewriteData->dimension,
			$this->targetDimension
		);

		PlayerRewriteUtils::injectDimensionChange(
			$session,
			$rewriteData->dimension,
			$spawnPos,
			$rewriteData->entityId,
			true
		);

		return true;
	}

	private function handlePhase2() : bool
	{
		$this->phase = self::PHASE_RESET;
		$rewriteData = $this->player->getRewriteData();
		$session = $this->player->getNetworkSession();

		$rewriteData->transferCallback = null;

		PlayerRewriteUtils::injectStopSound($session);

		$spawnPos = $rewriteData->spawnPosition ?? new Vector3(0, 64, 0);
		PlayerRewriteUtils::injectDimensionChange(
			$session,
			$this->targetDimension,
			$spawnPos,
			$rewriteData->entityId,
			false
		);

		$rewriteData->dimension = $this->targetDimension;

		$session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::PLAYER_SPAWN));

		$publisherPk = NetworkChunkPublisherUpdatePacket::create(
			new BlockPosition((int)$spawnPos->x, (int)$spawnPos->y, (int)$spawnPos->z),
			8 * 16,
			[]
		);
		$session->sendDataPacket($publisherPk);

		$downstream = $this->player->getDownstream();
		if ($downstream === null || !$downstream->isConnected()) {
			$this->onTransferFailed();
			return true;
		}

		$downstream->sendGamePacket(SetLocalPlayerAsInitializedPacket::create($rewriteData->originalEntityId));

		$this->player->setHandler(new DownstreamInGameHandler(
			$this->player,
			$this->player->getServer()->getLogger()
		));

		$session->getLogger()->debug('Transfer completed successfully');

		return true;
	}

	public function onTransferFailed() : void
	{
		$rewriteData = $this->player->getRewriteData();
		$rewriteData->transferCallback = null;

		$this->player->getNetworkSession()->getLogger()->warning('Transfer failed, attempting fallback');
		$this->player->tryFallbackOrDisconnect();
	}

	public function getPhase() : int
	{
		return $this->phase;
	}
}
