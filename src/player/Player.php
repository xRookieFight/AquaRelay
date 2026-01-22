<?php

/*
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *             |_|                                |___/
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

namespace aquarelay\player;

use aquarelay\network\NetworkSession;
use aquarelay\network\raklib\client\BackendRakClient;
use aquarelay\utils\LoginData;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use Ramsey\Uuid\UuidInterface;

class Player
{
    public ?int $proxyRuntimeId = null;

    public ?int $backendRuntimeId = null;
    protected UuidInterface $uuid;
    protected string $xuid = '';
    private NetworkSession $upstreamSession;
    private ?BackendRakClient $downstreamConnection = null;

    private LoginData $loginData;

    public function __construct(NetworkSession $upstreamSession, LoginData $loginData)
    {
        $this->upstreamSession = $upstreamSession;
        $this->loginData = $loginData;
        $this->xuid = $loginData->xuid;
        $this->uuid = $loginData->uuid;
    }

    public function sendDataPacket(ClientboundPacket $packet): void
    {
        $this->upstreamSession->sendDataPacket($packet);
    }

    public function sendToBackend(DataPacket $packet): void
    {
        if (is_null($this->downstreamConnection)) {
            $this->upstreamSession->debug('Cannot send packet to backend: downstream connection is null');
            return;
        }
        $this->downstreamConnection->sendGamePacket($packet);
    }

    public function getLoginData(): LoginData
    {
        return $this->loginData;
    }

    public function getName(): string
    {
        return $this->loginData->username;
    }

    public function setDownstream(BackendRakClient $client): void
    {
        $this->downstreamConnection = $client;
    }

    public function getDownstream(): ?BackendRakClient
    {
        return $this->downstreamConnection;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getXuid(): string
    {
        return $this->xuid;
    }

    public function sendLoginToBackend(): void
    {
        if (is_null($this->downstreamConnection)) {
            return;
        }

        $pk = LoginPacket::create(
            $this->loginData->protocolVersion,
            json_encode($this->loginData->chainData),
            $this->loginData->clientData
        );

        $this->sendToBackend($pk);
    }

    public function sendDefaultChunkRadius(): void
    {
        $chunkRadiusPacket = new RequestChunkRadiusPacket();
        $chunkRadiusPacket->radius = 8;
        $chunkRadiusPacket->maxRadius = 8;
        $this->downstreamConnection->sendGamePacket($chunkRadiusPacket);
    }

    public function handleBackendPacket(DataPacket $packet): void
    {
        if ($packet instanceof ResourcePacksInfoPacket) {
            $pk = ResourcePackClientResponsePacket::create(
                ResourcePackClientResponsePacket::STATUS_COMPLETED,
                []
            );
            $this->sendToBackend($pk);

            return;
        }

        if ($packet instanceof StartGamePacket) {
            $this->sendDefaultChunkRadius();
            $this->backendRuntimeId = $packet->actorRuntimeId;
            $this->proxyRuntimeId = $packet->actorRuntimeId;
            $this->sendDataPacket($packet);
            return;
        }

        if ($packet instanceof AddPlayerPacket) {
            $this->sendDataPacket($packet);
            return;
        }

        if ($packet instanceof AddActorPacket) {
            $this->sendDataPacket($packet);
            return;
        }

        if ($packet instanceof MoveActorDeltaPacket) {
            $this->sendDataPacket($packet);
            return;
        }

        if ($packet instanceof PlayStatusPacket) {
			if ($packet->status === PlayStatusPacket::LOGIN_SUCCESS) {
				$this->upstreamSession->debug('Forwarding LOGIN_SUCCESS from backend to client');
				$this->sendDataPacket($packet);
				return;
			}
            if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
                if (is_null($this->backendRuntimeId)) {
                    $this->upstreamSession->debug('Cannot send spawn notification: backendRuntimeId is null.');
                } else {
                    $this->upstreamSession->debug('Sending spawn notification, waiting for spawn response');
                    $init = SetLocalPlayerAsInitializedPacket::create($this->backendRuntimeId);
                    $this->downstreamConnection->sendGamePacket($init);
                }
            }
        }

        $this->sendDataPacket($packet);
    }

    public function sendMessage(string $message): void
    {
        $this->upstreamSession->onMessage($message);
    }

    public function sendPopup(string $message): void
    {
        $this->upstreamSession->onPopup($message);
    }

    public function sendTip(string $message): void
    {
        $this->upstreamSession->onTip($message);
    }

    public function sendJukeboxPopup(string $message): void
    {
        $this->upstreamSession->onJukeboxPopup($message);
    }

    public function sendTitle(string $title, string $subtitle = '', int $fadeIn = 0, int $stay = 0, int $fadeOut = 0): void
    {
        if ($fadeIn >= 0 && $stay >= 0 && $fadeOut >= 0) {
            $this->upstreamSession->onTitleDuration($fadeIn, $stay, $fadeOut);
        }

        if ('' !== $subtitle) {
            $this->upstreamSession->onSubTitle($subtitle);
        }

        $this->upstreamSession->onTitle($title);
    }

    public function sendToastNotification(string $title, string $body): void
    {
        $this->upstreamSession->onToastNotification($title, $body);
    }

    public function sendActionBar(string $actionBar): void
    {
        $this->upstreamSession->onActionBar($actionBar);
    }

    public function disconnect(string $reason = 'Disconnected from proxy'): void
    {
        $this->upstreamSession->disconnect($reason);
    }
}
