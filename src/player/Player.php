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
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use Ramsey\Uuid\UuidInterface;

class Player
{
	protected UuidInterface $uuid;

	public int $proxyRuntimeId;

	public ?int $backendRuntimeId = null;
	protected string $xuid = "";

	private NetworkSession $upstreamSession;
	private ?BackendRakClient $downstreamConnection = null;

	private LoginData $loginData;

	public function __construct(NetworkSession $upstreamSession, LoginData $loginData) {
		$this->upstreamSession = $upstreamSession;
		$this->loginData = $loginData;
        $this->xuid = $loginData->xuid;
		$this->uuid = $loginData->uuid;
		$this->proxyRuntimeId = mt_rand(10000, 50000);
	}

	public function sendDataPacket(ClientboundPacket $packet): void {
		$this->upstreamSession->sendDataPacket($packet);
	}

	public function sendToBackend(DataPacket $packet): void {
		$this->downstreamConnection?->sendGamePacket($packet);
	}

	public function getLoginData(): LoginData { return $this->loginData; }
	public function getName(): string { return $this->loginData->username; }

	public function setDownstream(BackendRakClient $client): void {
		$this->downstreamConnection = $client;
	}

	public function getDownstream(): ?BackendRakClient {
		return $this->downstreamConnection;
	}

	public function getUuid(): UuidInterface {
		return $this->uuid;
	}

	public function getXuid(): string {
		return $this->xuid;
	}

	public function sendLoginToBackend(): void {
		if (is_null($this->downstreamConnection)) return;

		$pk = LoginPacket::create(
			$this->loginData->protocolVersion,
			json_encode($this->loginData->chainData),
			$this->loginData->clientData
		);

		$this->sendToBackend($pk);
	}

	public function handleBackendPacket(DataPacket $packet): void {
		if ($packet instanceof StartGamePacket) {
			$this->backendRuntimeId = $packet->runtimeEntityId;
			$packet->runtimeEntityId = $this->proxyRuntimeId;
		}

		$this->sendDataPacket($packet);
	}

	public function sendMessage(string $message) : void{
		$this->upstreamSession->onMessage($message);
	}

	public function sendPopup(string $message) : void{
		$this->upstreamSession->onPopup($message);
	}

	public function sendTip(string $message) : void{
		$this->upstreamSession->onTip($message);
	}

	public function sendJukeboxPopup(string $message) : void{
		$this->upstreamSession->onJukeboxPopup($message);
	}

	public function sendTitle(string $title, string $subtitle = "", int $fadeIn = 0, int $stay = 0, int $fadeOut = 0) : void{
      if($fadeIn >= 0 && $stay >= 0 && $fadeOut >= 0){
		$this->upstreamSession->onTitleDuration($fadeIn, $stay, $fadeOut);
	  }

	  if($subtitle !== ""){
		  $this->upstreamSession->onSubTitle($subtitle);
	  }

	  $this->upstreamSession->onTitle($title);
	}

	public function sendToastNotification(string $title, string $body) : void{
		$this->upstreamSession->onToastNotification($title, $body);
	}

	public function sendActionBar(string $actionBar) : void{
		$this->upstreamSession->onActionBar($actionBar);
	}

	public function disconnect(string $reason = "Disconnected from proxy"): void {
		$this->upstreamSession->disconnect($reason);
	}
}