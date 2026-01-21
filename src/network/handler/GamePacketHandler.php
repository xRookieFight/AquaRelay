<?php

declare(strict_types=1);

namespace aquarelay\network\handler;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class GamePacketHandler extends PacketHandler {

    /**
     * This is the "Spawn Response" the backend is waiting for.
     * Without this, you stay stuck on 'Locating Server'.
     */
    public function handleSetLocalPlayerAsInitialized(SetLocalPlayerAsInitializedPacket $packet): bool {
        $player = $this->session->getPlayer();
        if ($player !== null && $player->backendRuntimeId !== null) {
            $packet->actorRuntimeId = $player->backendRuntimeId;
            
            $this->session->debug("Handshaking Spawn: Forwarding Initialization to Backend.");
            $player->clearAwaitingSpawnResponse();
            $player->sendToBackend($packet);
        }
        return true;
    }

    /**
     * Handles modern movement (1.16+).
     */
    public function handlePlayerAuthInput(PlayerAuthInputPacket $packet): bool {
        $this->forward($packet);
        return true;
    }

    /**
     * Handles legacy movement and teleportation.
     */
    public function handleMovePlayer(MovePlayerPacket $packet): bool {
        $player = $this->session->getPlayer();
        if ($player !== null && $player->backendRuntimeId !== null) {
            $packet->actorRuntimeId = $player->backendRuntimeId;
        }
        $this->forward($packet);
        return true;
    }

    public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet): bool {
        $this->forward($packet);
        return true;
    }

    // --- Standard Gameplay Forwarding ---

    public function handleText(TextPacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleCommandRequest(CommandRequestPacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleInteract(InteractPacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleInventoryTransaction(InventoryTransactionPacket $packet): bool { $this->forward($packet); return true; }
    
    public function handlePlayerAction(PlayerActionPacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleMobEquipment(MobEquipmentPacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleAnimate(AnimatePacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleContainerClose(ContainerClosePacket $packet): bool { $this->forward($packet); return true; }
    
    public function handleLevelSoundEvent(LevelSoundEventPacket $packet): bool { $this->forward($packet); return true; }

    /**
     * Internal helper to route packets to the backend server.
     */
    private function forward(DataPacket $packet): void {
        $player = $this->session->getPlayer();
        if ($player !== null && $player->getDownstream() !== null) {
            $player->sendToBackend($packet);
        }
    }
}
