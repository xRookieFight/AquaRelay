<?php

declare(strict_types=1);

namespace aquarelay\network\handler;

use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\TextPacket;

class InGamePacketHandler extends PacketHandler
{
    public function handleSetLocalPlayerAsInitialized(SetLocalPlayerAsInitializedPacket $packet): bool
    {
		$this->forward($packet);
        return true;
    }

    public function handlePlayerAuthInput(PlayerAuthInputPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

	public function handleMovePlayer(MovePlayerPacket $packet): bool
    {
        $player = $this->session->getPlayer();
        if (null !== $player && null !== $player->backendRuntimeId) {
            $packet->actorRuntimeId = $player->backendRuntimeId;
        }
        $this->forward($packet);

        return true;
    }

    public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleText(TextPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleCommandRequest(CommandRequestPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleInteract(InteractPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleInventoryTransaction(InventoryTransactionPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handlePlayerAction(PlayerActionPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleMobEquipment(MobEquipmentPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleAnimate(AnimatePacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleContainerClose(ContainerClosePacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    public function handleLevelSoundEvent(LevelSoundEventPacket $packet): bool
    {
        $this->forward($packet);
        return true;
    }

    private function forward(DataPacket $packet): void
    {
        $player = $this->session->getPlayer();
        if (null !== $player && null !== $player->getDownstream()) {
			$this->session->debug("Forwarding packet: " . $packet->getName());
            $player->sendToBackend($packet);
        }
    }
}
