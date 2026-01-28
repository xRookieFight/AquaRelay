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

namespace aquarelay\network;

use aquarelay\form\Form;
use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\handler\upstream\AbstractUpstreamPacketHandler;
use aquarelay\network\handler\upstream\UpstreamLoginHandler;
use aquarelay\network\handler\upstream\UpstreamPreLoginHandler;
use aquarelay\network\handler\upstream\UpstreamResourcePackHandler;
use aquarelay\network\raklib\client\BackendRakClient;
use aquarelay\player\Player;
use aquarelay\ProxyServer;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\ClientboundCloseFormPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\ToastRequestPacket;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use raklib\generic\DisconnectReason;
use raklib\utils\InternetAddress;
use Ramsey\Uuid\Uuid;
use function count;
use function json_encode;
use function ord;
use function substr;
use function time;
use const JSON_THROW_ON_ERROR;

class NetworkSession
{
	/** @var string[] */
	private array $sendBuffer = [];
	private ?bool $enableCompression = null;
	private int $lastUsed;
	private ?int $protocolId = null;
	private ?string $username = null;
	private ?int $ping = null;
	private bool $connected = true;
	private bool $logged = false;
	private ?AbstractUpstreamPacketHandler $handler;
	private ?Player $player = null;

	public function __construct(
		private readonly ProxyServer           $server,
		private readonly NetworkSessionManager $manager,
		private readonly PacketPool            $packetPool,
		private readonly PacketSender          $sender,
		private readonly string                $ip,
		private readonly int                   $port,
		private readonly int                   $sessionId
	) {
		$this->manager->add($this);
		$this->lastUsed = time();
		$this->setHandler(new UpstreamPreLoginHandler($this, $this->server->getLogger()));
	}

	public function getServer() : ProxyServer
	{
		return $this->server;
	}

	public function getAddress() : string
	{
		return $this->ip;
	}

	public function getPort() : int
	{
		return $this->port;
	}

	public function getSessionId() : int
	{
		return $this->sessionId;
	}

	public function handleEncodedPacket(string $payload) : void
	{
		$compressionType = ord($payload[0]);
		$data = substr($payload, 1);

		if ($compressionType === CompressionAlgorithm::ZLIB) {
			try {
				$data = ZlibCompressor::getInstance()->decompress($data);
			} catch (\Exception $e) {
				$this->server->getLogger()->error('Decompressing error: ' . $e->getMessage());

				return;
			}
		}

		if (ord($data[0]) === 0xC1) {
			$this->processSinglePacket($data);
		} else {
			try {
				$stream = new ByteBufferReader($data);
				foreach (PacketBatch::decodeRaw($stream) as $buffer) {
					$this->processSinglePacket($buffer);
				}
			} catch (\Exception $e) {
				$this->debug('Batch decode error: ' . $e->getMessage());
			}
		}
	}

	public function connectBackendTo(string $ip, int $port) : void
	{
		$player = $this->player;
		if ($player === null) return;

		$this->debug("Connecting to $ip:$port...");

		$backend = new BackendRakClient(new InternetAddress($ip, $port, 4), $player);

		$player->setDownstream($backend);
		$player->sendLoginToBackend();

		$backend->connect();
	}

	public function setProtocolId(int $protocolId) : void
	{
		$this->protocolId = $protocolId;
	}

	public function getProtocolId() : int
	{
		return $this->protocolId ?? ProtocolInfo::CURRENT_PROTOCOL;
	}

	public function sendDataPacket(ClientboundPacket $packet, bool $immediate = true) : void
	{
		$writer = new ByteBufferWriter();

		$packet->encode($writer, $this->getProtocolId());
		$payload = $writer->getData();

		$this->addToSendBuffer($payload);

		if ($immediate) {
			$this->flushGamePacketQueue();
		}
	}

	public function flushGamePacketQueue() : void
	{
		if (count($this->sendBuffer) > 0) {
			$stream = new ByteBufferWriter();
			PacketBatch::encodeRaw($stream, $this->sendBuffer);
			$batchData = $stream->getData();
			$this->sendBuffer = [];

			if ($this->enableCompression === null) {
				$finalPayload = $batchData;
			} else {
				$finalPayload = $this->enableCompression ? "\x00" . ZlibCompressor::getInstance()->compress($batchData) : "\xff" . $batchData;
			}

			try {
				$this->sendEncoded($finalPayload);
			} catch (\Throwable) {}
		}
	}

	public function sendEncoded(string $payload) : void
	{
		$this->sender->sendRawPacket($payload);
	}

	public function enableCompression() : void
	{
		$this->enableCompression = true;
	}

	public function addToSendBuffer(string $buffer) : void
	{
		$this->sendBuffer[] = $buffer;
	}

	public function disconnect(string $reason) : void
	{
		$this->sendDataPacket(DisconnectPacket::create(DisconnectReason::CLIENT_DISCONNECT, $reason, ''));
	}

	public function onNetworkSettingsSuccess() : void
	{
		$this->setHandler(new UpstreamLoginHandler($this, $this->server->getLogger()));
	}

	public function onClientLoginSuccess() : void
	{
		$this->debug('Login handled. Starting Resource Pack sequence...');

		$this->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::LOGIN_SUCCESS));

		$infoPacket = ResourcePacksInfoPacket::create(
			resourcePackEntries: [],
			behaviorPackEntries: [],
			mustAccept: false,
			hasAddons: false,
			hasScripts: false,
			forceServerPacks: false,
			cdnUrls: [],
			worldTemplateId: Uuid::fromString(Uuid::NIL),
			worldTemplateVersion: '',
			forceDisableVibrantVisuals: true,
		);

		$this->sendDataPacket($infoPacket, true);

		$this->setHandler(new UpstreamResourcePackHandler($this, $this->server->getLogger()));
	}

	public function setPlayer(Player $player) : void
	{
		$this->player = $player;
	}

	public function getPlayer() : ?Player
	{
		return $this->player;
	}

	public function getPing() : int
	{
		return $this->ping;
	}

	public function tick() : void
	{
		$this->lastUsed = time();
	}

	public function getUsername() : string
	{
		return $this->username;
	}

	public function setUsername(string $name) : void
	{
		$this->username = $name;
	}

	public function setPing(int $ping) : void
	{
		$this->ping = $ping;
	}

	public function onFormSent(int $id, Form $form) : void {
		$this->sendDataPacket(ModalFormRequestPacket::create($id, json_encode($form, JSON_THROW_ON_ERROR)));
	}

	public function onCloseAllForms() : void{
		$this->sendDataPacket(ClientboundCloseFormPacket::create());
	}

	public function isConnected() : bool
	{
		return $this->connected;
	}

	public function isLogged() : bool
	{
		return $this->logged;
	}

	public function setHandler(?AbstractUpstreamPacketHandler $handler) : void
	{
		if ($this->connected) {
			$this->handler = $handler;
			$this->handler?->setUp();
		}
	}

	public function onMessage(string $message) : void
	{
		$this->sendDataPacket(TextPacket::raw($message));
	}

	public function onJukeboxPopup(string $message) : void
	{
		$this->sendDataPacket(TextPacket::jukeboxPopup($message));
	}

	public function onPopup(string $message) : void
	{
		$this->sendDataPacket(TextPacket::popup($message));
	}

	public function onTip(string $message) : void
	{
		$this->sendDataPacket(TextPacket::tip($message));
	}

	public function onTitle(string $title) : void
	{
		$this->sendDataPacket(SetTitlePacket::title($title));
	}

	public function onSubTitle(string $subtitle) : void
	{
		$this->sendDataPacket(SetTitlePacket::subtitle($subtitle));
	}

	public function onActionBar(string $actionBar) : void
	{
		$this->sendDataPacket(SetTitlePacket::actionBarMessage($actionBar));
	}

	public function onTitleDuration(int $fadeIn, int $stay, int $fadeOut) : void
	{
		$this->sendDataPacket(SetTitlePacket::setAnimationTimes($fadeIn, $stay, $fadeOut));
	}

	public function onToastNotification(string $title, string $body) : void
	{
		$this->sendDataPacket(ToastRequestPacket::create($title, $body));
	}

	public function debug(string $message) : void
	{
		$format = $this->username ?? "$this->ip:$this->port";
		$this->server->getLogger()->debug("[NetworkSession - $format]: $message");
	}

	public function info(string $message) : void
	{
		$format = $this->username ?? "$this->ip:$this->port";
		$this->server->getLogger()->info("[NetworkSession - $format]: $message");
	}

	public function warning(string $message) : void
	{
		$format = $this->username ?? "$this->ip:$this->port";
		$this->server->getLogger()->warning("[NetworkSession - $format]: $message");
	}

	private function processSinglePacket(string $buffer) : void
	{
		$packet = $this->packetPool->getPacket($buffer);
		if ($packet !== null) {
			$packet->decode(new ByteBufferReader($buffer), $this->getProtocolId());

			if ($this->handler !== null) {
				if (!$packet->handle($this->handler)){
					$this->debug("Unhandled packet: " . $packet->getName());
				}
			}
		}
	}

	public function onDisconnect(string $reason) : void
	{
		$this->connected = false;
		$this->info("Session disconnected: $reason");

		NetworkSessionManager::getInstance()->remove($this);

		$player = $this->getPlayer();
		$player?->getDownstream()?->disconnect();

		$this->server->getPlayerManager()->removePlayer($this);
	}
}
