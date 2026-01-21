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

class GamePacketHandler extends PacketHandler
{
    public function handleSetLocalPlayerAsInitialized(SetLocalPlayerAsInitializedPacket $packet): bool
    {
        $player = $this->session->getPlayer();
        if (null !== $player) {
            $this->session->debug('Received SetLocalPlayerAsInitialized from client; packet props: '.print_r(get_object_vars($packet), true));
            if (null !== $player->backendRuntimeId) {
                $packet->actorRuntimeId = $player->backendRuntimeId;
                $this->session->debug("Handshaking Spawn: Forwarding Initialization to Backend (actorRuntimeId={$packet->actorRuntimeId}).");
                $player->clearAwaitingSpawnResponse();
                $player->sendToBackend($packet);
            } else {
                $this->session->debug('Received SetLocalPlayerAsInitialized but backendRuntimeId is null; not forwarding.');
            }
        }

        return true;
    }

    /**
     * Handles modern movement (1.16+).
     */
    public function handlePlayerAuthInput(PlayerAuthInputPacket $packet): bool
    {
        $this->forward($packet);

        return true;
    }

    /**
     * Handles legacy movement and teleportation.
     */
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

    // --- Standard Gameplay Forwarding ---

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

    /**
     * Internal helper to route packets to the backend server.
     */
    private function forward(DataPacket $packet): void
    {
        $player = $this->session->getPlayer();
        if (null !== $player && null !== $player->getDownstream()) {
            $name = method_exists($packet, 'getName') ? $packet->getName() : $packet::class;
            $info = "Forwarding packet: {$name}";
            if (property_exists($packet, 'actorRuntimeId')) {
                $info .= "; actorRuntimeId={$packet->actorRuntimeId}";
            } elseif (property_exists($packet, 'actorUniqueId')) {
                $info .= "; actorUniqueId={$packet->actorUniqueId}";
            }
            $this->session->debug($info);
            $player->sendToBackend($packet);
        }
    }
}
