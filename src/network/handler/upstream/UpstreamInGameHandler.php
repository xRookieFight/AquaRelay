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

use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\BlockPickRequestPacket;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\CraftingEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\EmotePacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\PlayerHotbarPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\SettingsCommandPacket;
use pocketmine\network\mcpe\protocol\TextPacket;

class UpstreamInGameHandler extends AbstractUpstreamPacketHandler
{

	public function handleItemStackRequest(ItemStackRequestPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handlePlayerHotbar(PlayerHotbarPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleBlockActorData(BlockActorDataPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleBlockPickRequest(BlockPickRequestPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleEmote(EmotePacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleSettingsCommand(SettingsCommandPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleSetLocalPlayerAsInitialized(SetLocalPlayerAsInitializedPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handlePlayerAuthInput(PlayerAuthInputPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleMovePlayer(MovePlayerPacket $packet) : bool
	{
		$this->forward($packet);

		return true;
	}

	public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleText(TextPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleCommandRequest(CommandRequestPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleInteract(InteractPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleInventoryTransaction(InventoryTransactionPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handlePlayerAction(PlayerActionPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleAnimate(AnimatePacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleContainerClose(ContainerClosePacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleLevelSoundEvent(LevelSoundEventPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleCraftingEvent(CraftingEventPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	public function handleNetworkStackLatency(NetworkStackLatencyPacket $packet) : bool
	{
		$this->forward($packet);
		return true;
	}

	private function forward(DataPacket $packet) : void
	{
		$player = $this->session->getPlayer();
		if ($player !== null && $player->getDownstream() !== null) {
			$player->sendToBackend($packet);
		}
	}
}
