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

namespace aquarelay\network\raklib;

use aquarelay\event\default\server\QueryRegenerateEvent;
use aquarelay\network\raklib\ipc\PthreadsChannelReader;
use aquarelay\network\raklib\ipc\PthreadsChannelWriter;
use aquarelay\ProxyServer;
use aquarelay\utils\MainLogger;
use pmmp\thread\Thread as NativeThread;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\server\ipc\RakLibToUserThreadMessageReceiver;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use raklib\server\ServerEventListener;
use function addcslashes;
use function implode;
use function random_int;
use function rtrim;

class RakLibInterface implements ServerEventListener
{
	public const MCPE_RAKNET_PACKET_ID = "\xfe";
	public const RAKNET_PROTOCOL_VERSION = 11;

	private int $tickCounter = 0;

	private RakLibServerThread $thread;
	private RakLibToUserThreadMessageReceiver $eventReceiver;
	private UserToRakLibThreadMessageSender $interface;

	/** @var callable(int, string, int, int): void */
	private $onConnect;

	/** @var callable(int, string): void */
	private $onPacket;

	/** @var callable(int, string): void */
	private $onDisconnect;

	/** @var callable(int, string): void */
	private $onPing;
	private int $rakServerId;

	public function __construct(string $mainPath, MainLogger $logger, string $address, int $port, int $maxMtu)
	{
		$this->rakServerId = random_int(0, 1000000);
		$this->thread = new RakLibServerThread($mainPath, $logger, $address, $port, $maxMtu, self::RAKNET_PROTOCOL_VERSION, $this->rakServerId);

		$this->eventReceiver = new RakLibToUserThreadMessageReceiver(
			new PthreadsChannelReader($this->thread->getReadBuffer())
		);
		$this->interface = new UserToRakLibThreadMessageSender(
			new PthreadsChannelWriter($this->thread->getWriteBuffer())
		);
	}

	public function getInterface() : UserToRakLibThreadMessageSender
	{
		return $this->interface;
	}

	public function setHandlers(callable $onConnect, callable $onPacket, callable $onDisconnect, callable $onPing) : void
	{
		$this->onConnect = $onConnect;
		$this->onPacket = $onPacket;
		$this->onDisconnect = $onDisconnect;
		$this->onPing = $onPing;
	}

	public function start() : void
	{
		$this->thread->start(NativeThread::INHERIT_NONE);
	}

	public function tick() : void
	{
		while ($this->eventReceiver->handle($this));

		if (++$this->tickCounter >= 20) {
			$this->tickCounter = 0;
			$server = ProxyServer::getInstance();
			$this->setName($server);
		}
	}

	public function sendPacket(int $sessionId, string $payload, bool $immediate = true, ?int $receiptId = null) : void
	{
		$pk = new EncapsulatedPacket();
		$pk->buffer = self::MCPE_RAKNET_PACKET_ID . $payload;
		$pk->reliability = PacketReliability::RELIABLE_ORDERED;
		$pk->orderChannel = 0;
		$pk->identifierACK = $receiptId;

		$this->interface->sendEncapsulated($sessionId, $pk, $immediate);
	}

	public function closeSession(int $sessionId) : void
	{
		$this->interface->closeSession($sessionId);
	}

	public function shutdown() : void
	{
		$this->thread->stop();
		$this->thread->join();
	}

	public function onClientConnect(int $sessionId, string $address, int $port, int $clientID) : void
	{
		($this->onConnect)($sessionId, $address, $port, $clientID);
	}

	public function onPacketReceive(int $sessionId, string $packet) : void
	{
		($this->onPacket)($sessionId, $packet);
	}

	public function onClientDisconnect(int $sessionId, int $reason) : void
	{
		($this->onDisconnect)($sessionId, $reason);
	}

	public function close(int $sessionId) : void
	{
		if (isset($this->sessions[$sessionId])) {
			unset($this->sessions[$sessionId]);
			$this->interface->closeSession($sessionId);
		}
	}

	public function setPacketLimit(int $limit) : void
	{
		$this->interface->setPacketsPerTickLimit($limit);
	}

	public function setPortCheck(bool $check) : void
	{
		$this->interface->setPortCheck($check);
	}

	public function setName(ProxyServer $server) : void
	{
		$ev = new QueryRegenerateEvent($server->getName(), $server->getSubMotd(), $server->getMaxPlayers(), $server->getOnlinePlayerCount());
		$ev->call();
		$this->interface->setName(
			implode(
				';',
				[
					'MCPE',
					rtrim(addcslashes($ev->getName(), ';'), '\\'),
					ProtocolInfo::CURRENT_PROTOCOL,
					ProtocolInfo::MINECRAFT_VERSION_NETWORK,
					$ev->getCurrentPlayers(),
					$ev->getMaxPlayers(),
					$this->rakServerId,
					$ev->getSubMotd(),
					'Survival', // This shouldn't matter since we're a proxy
				]
			) . ';'
		);
	}

	public function onPingMeasure(int $sessionId, int $pingMS) : void
	{
		($this->onPing)($sessionId, $pingMS);
	}

	public function onPacketAck(int $sessionId, int $identifierACK) : void {}

	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff) : void {}

	public function onRawPacketReceive(string $address, int $port, string $payload) : void {}
}
