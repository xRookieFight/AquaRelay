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

namespace aquarelay\player;

use aquarelay\network\handler\downstream\AbstractDownstreamPacketHandler;
use aquarelay\network\handler\downstream\DownstreamResourcePackHandler;
use aquarelay\network\NetworkSession;
use aquarelay\network\raklib\client\BackendRakClient;
use aquarelay\ProxyServer;
use aquarelay\utils\LoginData;
use aquarelay\utils\Utils;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use Ramsey\Uuid\UuidInterface;
use function json_encode;

class Player
{
	public ?int $backendRuntimeId = null;
	protected UuidInterface $uuid;
	protected string $xuid = '';
	private ?BackendRakClient $downstreamConnection = null;
	private ?AbstractDownstreamPacketHandler $handler = null;

	public function __construct(
		private readonly ProxyServer    $proxyServer,
		private readonly NetworkSession $upstreamSession,
		private readonly LoginData $loginData
	)
	{
		$this->xuid = $loginData->xuid;
		$this->uuid = $loginData->uuid;

		$this->setHandler(new DownstreamResourcePackHandler($this, $this->proxyServer->getLogger()));
	}

	public function sendDataPacket(ClientboundPacket $packet) : void
	{
		$this->upstreamSession->sendDataPacket($packet);
	}

	public function sendToBackend(DataPacket $packet) : void
	{
		if ($this->downstreamConnection === null) {
			$this->upstreamSession->debug('Cannot send packet to backend: downstream connection is null');
			return;
		}
		$this->downstreamConnection->sendGamePacket($packet);
	}

	public function getNetworkSession() : NetworkSession
	{
		return $this->upstreamSession;
	}

	public function getLoginData() : LoginData
	{
		return $this->loginData;
	}

	public function getName() : string
	{
		return $this->loginData->username;
	}

	public function setDownstream(BackendRakClient $client) : void
	{
		$this->downstreamConnection = $client;
	}

	public function getDownstream() : ?BackendRakClient
	{
		return $this->downstreamConnection;
	}

	public function getUuid() : UuidInterface
	{
		return $this->uuid;
	}

	public function getXuid() : string
	{
		return $this->xuid;
	}

	public function sendLoginToBackend() : void
	{
		if ($this->downstreamConnection === null) {
			return;
		}

		$pk = LoginPacket::create(
			$this->loginData->protocolVersion,
			json_encode($this->loginData->chainData),
			$this->loginData->clientData
		);

		$this->sendToBackend($pk);
	}

	public function setHandler(AbstractDownstreamPacketHandler $handler) : void
	{
		$this->handler = $handler;
	}

	public function handleBackendPacket(DataPacket $packet) : void
	{
		if ($this->handler !== null) {
			$packet->handle($this->handler);
		}

		$this->sendDataPacket($packet);
	}

	public function sendMessage(string $message) : void
	{
		$this->upstreamSession->onMessage($message);
	}

	public function sendPopup(string $message) : void
	{
		$this->upstreamSession->onPopup($message);
	}

	public function sendTip(string $message) : void
	{
		$this->upstreamSession->onTip($message);
	}

	public function sendJukeboxPopup(string $message) : void
	{
		$this->upstreamSession->onJukeboxPopup($message);
	}

	public function sendTitle(string $title, string $subtitle = '', int $fadeIn = 0, int $stay = 0, int $fadeOut = 0) : void
	{
		if ($fadeIn >= 0 && $stay >= 0 && $fadeOut >= 0) {
			$this->upstreamSession->onTitleDuration($fadeIn, $stay, $fadeOut);
		}

		if ($subtitle !== '') {
			$this->upstreamSession->onSubTitle($subtitle);
		}

		$this->upstreamSession->onTitle($title);
	}

	public function sendToastNotification(string $title, string $body) : void
	{
		$this->upstreamSession->onToastNotification($title, $body);
	}

	public function sendActionBar(string $actionBar) : void
	{
		$this->upstreamSession->onActionBar($actionBar);
	}

	public function disconnect(string $reason = 'Disconnected from proxy') : void
	{
		$this->upstreamSession->disconnect($reason);
	}

	public function getServer() : ProxyServer
	{
		return $this->proxyServer;
	}

	/**
	 * Returns the protocol id of player.
	 */
	public function getProtocol() : int
	{
		return $this->upstreamSession->getProtocolId();
	}

	/**
	 * Returns the Minecraft version of player.
	 */
	public function getMinecraftVersion() : string
	{
		return Utils::protocolIdToVersion($this->getProtocol()) ?? "unknown";
	}
}
