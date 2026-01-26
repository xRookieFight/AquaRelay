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

namespace aquarelay\network\raklib\client;

use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\raklib\RakLibInterface;
use aquarelay\player\Player;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\utils\Binary;
use raklib\client\ClientSocket;
use raklib\generic\SocketException;
use raklib\protocol\ConnectedPing;
use raklib\protocol\ConnectedPong;
use raklib\protocol\ConnectionRequest;
use raklib\protocol\DisconnectionNotification;
use raklib\protocol\MessageIdentifiers;
use raklib\protocol\NewIncomingConnection;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionReply2;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\Packet;
use raklib\protocol\PacketSerializer;
use raklib\protocol\UnconnectedPing;
use raklib\utils\InternetAddress;
use Ramsey\Uuid\Uuid;
use function ceil;
use function count;
use function implode;
use function in_array;
use function ksort;
use function microtime;
use function min;
use function ord;
use function random_int;
use function str_split;
use function strlen;
use function substr;
use function unpack;
use const PHP_INT_MAX;

final class BackendRakClient
{
	private const ID_ACK = 0xC0;

	private const ID_USER_PACKET_ENUM = 0x80;

	private const ID_FRAME_SET = 0x84;

	private const ID_MCPE_GAME_PACKET = 0xFE;

	private const FLAG_SPLIT = 0x10;

	private const MASK_RELIABILITY = 0xE0;

	private const RELIABILITY_RELIABLE = 0x40;

	private const HEADER_SIZE_LIMIT_OFFSET = 60;
	private const DEFAULT_MTU = 1492;

	private ClientSocket $socket;
	private ConnectionState $state = ConnectionState::UNCONNECTED;
	private int $clientId;
	private int $mtu = self::DEFAULT_MTU;
	private int $seqNumber = 0;
	private int $messageIndex = 0;
	private int $splitId = 0;
	private array $splitBuffer = [];
	private array $sendQueue = [];
	private ?int $rakCookie;

	public function __construct(
		private InternetAddress $address,
		private Player $player
	)
	{
		$this->clientId = random_int(1, PHP_INT_MAX);
		$this->socket = new ClientSocket($address);
		$this->socket->setBlocking(false);
	}

	public function connect() : void
	{
		$this->sendPing();
	}

	public function sendGamePacket(DataPacket $packet) : void
	{
		if ($this->state->value < ConnectionState::LOGGED_IN->value) {
			$this->sendQueue[] = $packet;
			return;
		}
		$this->encodeAndSend($packet);
	}

	public function tick() : void
	{
		try {
			while (($buf = $this->socket->readPacket()) !== null) {
				$pid = ord($buf[0]);

				if ($pid < self::ID_USER_PACKET_ENUM) {
					$this->handleInternalPacket($buf);
				} else {
					$this->sendAck($buf);
					$this->handleDatagram($buf);
				}
			}
		} catch (SocketException $e) {
			$this->player->disconnect("Couldn't read packets from backend (" . Uuid::uuid4()->toString() . ")");
			$this->player->getNetworkSession()->debug("Backend packet reading error: " . $e->getMessage());
		}
	}

	public function close() : void
	{
		$this->socket->close();
	}

	private function sendPing() : void
	{
		$pk = new UnconnectedPing();
		$pk->sendPingTime = (int) (microtime(true) * 1000);
		$pk->clientId = $this->clientId;
		$this->sendRawPacket($pk);
		$this->sendRequest1();
	}

	private function sendRequest1() : void
	{
		$pk = new OpenConnectionRequest1();
		$pk->protocol = RakLibInterface::RAKNET_PROTOCOL_VERSION;
		$pk->mtuSize = $this->mtu;
		$this->sendRawPacket($pk);
		$this->state = ConnectionState::CONNECTING_1;
	}

	private function sendRequest2() : void
	{
		$packet = new OpenConnectionRequest2();
		$packet->clientID = $this->clientId;
		$packet->serverAddress = new InternetAddress($this->address->getIp(), $this->address->getPort(), 4);
		$packet->mtuSize = $this->mtu;
		if ($this->rakCookie !== null) {
			$packet->cookie = $this->rakCookie;
		}

		$this->sendRawPacket($packet);
		$this->state = ConnectionState::CONNECTING_2;
	}

	private function sendNewIncomingConnection() : void
	{
		$address = $this->player->getServer()->getAddress();
		if (empty($address) || $address === "0.0.0.0") {
			$address = "127.0.0.1";
		}

		$port = $this->player->getServer()->getPort();

		$packet = new NewIncomingConnection();
		$packet->address = new InternetAddress($address, $port, 4);

		$internalAddr = new InternetAddress("127.0.0.1", 0, 4);
		$packet->systemAddresses = [];
		for ($i = 0; $i < 10; ++$i) {
			$packet->systemAddresses[] = $internalAddr;
		}

		$ping = (int) (microtime(true) * 1000);
		$packet->sendPingTime = $ping;
		$packet->sendPongTime = $ping;

		$this->sendEncapsulated($packet);
	}

	private function sendConnectionRequest() : void
	{
		$pk = new ConnectionRequest();
		$pk->clientID = $this->clientId;
		$pk->sendPingTime = (int) (microtime(true) * 1000);
		$pk->useSecurity = true;
		$this->sendEncapsulated($pk);
		$this->state = ConnectionState::CONNECTING_3;
	}

	private function sendNetworkSettingsRequest() : void
	{
		$packet = RequestNetworkSettingsPacket::create($this->player->getProtocol());
		$writer = new ByteBufferWriter();
		$packet->encode($writer, $this->player->getProtocol());

		$payload = $writer->getData();

		$header = Binary::writeUnsignedVarInt(strlen($payload));
		$batch = $header . $payload;

		$final = RakLibInterface::MCPE_RAKNET_PACKET_ID . $batch;

		$this->sendEncapsulatedRaw($final);
	}

	private function encodeAndSend(DataPacket $packet) : void
	{
		$writer = new ByteBufferWriter();
		$packet->encode($writer, $this->player->getProtocol());
		$this->sendBatch($writer->getData());
	}

	private function sendBatch(string $payload) : void
	{
		if (empty($payload)) return;

		$header = Binary::writeUnsignedVarInt(strlen($payload));
		$batch = $header . $payload;

		$finalPayload = "\x00" . ZlibCompressor::getInstance()->compress($batch);
		$final = RakLibInterface::MCPE_RAKNET_PACKET_ID . $finalPayload;

		$this->sendEncapsulatedRaw($final);
	}

	private function sendEncapsulated(Packet $packet) : void
	{
		$s = new PacketSerializer();
		$packet->encode($s);
		$this->sendEncapsulatedRaw($s->getBuffer());
	}

	private function sendEncapsulatedRaw(string $payload) : void
	{
		$limit = $this->mtu - self::HEADER_SIZE_LIMIT_OFFSET;

		if (strlen($payload) <= $limit) {
			$s = new PacketSerializer();
			$s->putByte(self::ID_FRAME_SET);
			$s->putLTriad($this->seqNumber++);

			$s->putByte(self::RELIABILITY_RELIABLE);

			$s->putShort(strlen($payload) << 3);
			$s->putLTriad($this->messageIndex++);
			$s->put($payload);
			$this->sendRaw($s->getBuffer());

			return;
		}

		$chunks = str_split($payload, $limit);
		$count = count($chunks);
		$splitId = $this->splitId++ & 0xFFFF;

		foreach ($chunks as $index => $chunk) {
			$s = new PacketSerializer();
			$s->putByte(self::ID_FRAME_SET);
			$s->putLTriad($this->seqNumber++);

			$s->putByte(self::RELIABILITY_RELIABLE | self::FLAG_SPLIT);

			$s->putShort(strlen($chunk) << 3);
			$s->putLTriad($this->messageIndex++);
			$s->putInt($count);
			$s->putShort($splitId);
			$s->putInt($index);
			$s->put($chunk);
			$this->sendRaw($s->getBuffer());
		}
	}

	private function handleDatagram(string $buf) : void
	{
		$s = new PacketSerializer($buf);
		$s->getByte();
		$s->getLTriad();

		while (!$s->feof()) {
			try {
				$flags = $s->getByte();
				$length = (int) ceil($s->getShort() / 8);
				$reliability = ($flags & self::MASK_RELIABILITY) >> 5;
				$split = 0 !== ($flags & self::FLAG_SPLIT);

				if ($reliability >= 2) {
					$s->getLTriad();
				}
				if (in_array($reliability, [3, 6, 7], true)) {
					$s->getLTriad();
					$s->getByte();
				}

				if ($split) {
					$count = $s->getInt();
					$id = $s->getShort();
					$index = $s->getInt();
					$this->handleSplit($id, $index, $count, $s->get($length));
				} else {
					$this->processPayload($s->get($length));
				}
			} catch (\Throwable $e) {
				break;
			}
		}
	}

	private function handleSplit(int $id, int $index, int $count, string $chunk) : void
	{
		$this->splitBuffer[$id] ??= ['total' => $count, 'chunks' => []];
		$this->splitBuffer[$id]['chunks'][$index] = $chunk;

		if (count($this->splitBuffer[$id]['chunks']) === $count) {
			ksort($this->splitBuffer[$id]['chunks']);
			$this->processPayload(implode('', $this->splitBuffer[$id]['chunks']));
			unset($this->splitBuffer[$id]);
		}
	}

	private function processPayload(string $payload) : void
	{
		if ($payload === '') {
			return;
		}

		$pid = ord($payload[0]);

		if ($pid === MessageIdentifiers::ID_CONNECTED_PING) {
			$this->handleConnectedPing($payload);
			return;
		}

		if ($pid === MessageIdentifiers::ID_CONNECTION_REQUEST_ACCEPTED && $this->state === ConnectionState::CONNECTING_3) {
			$this->state = ConnectionState::CONNECTED;

			$this->sendNewIncomingConnection();
			$this->sendNetworkSettingsRequest();
			$this->state = ConnectionState::GAME_HANDSHAKE;

			return;
		}

		if ($pid === self::ID_MCPE_GAME_PACKET) {
			if ($this->state === ConnectionState::GAME_HANDSHAKE) {
				$this->state = ConnectionState::LOGGED_IN;

				foreach ($this->sendQueue as $p) {
					$this->encodeAndSend($p);
				}
				$this->sendQueue = [];
			}
			$this->player->getServer()->getProxyLoop()->handleBackendPayload($this->player, $payload);
		}
	}

	private function handleConnectedPing(string $payload) : void
	{
		$s = new PacketSerializer($payload);
		$pk = new ConnectedPing();
		$pk->decode($s);

		$pong = new ConnectedPong();
		$pong->sendPingTime = $pk->sendPingTime;
		$pong->sendPongTime = (int) (microtime(true) * 1000);

		$this->sendEncapsulated($pong);
	}

	private function handleInternalPacket(string $buf) : void
	{
		$s = new PacketSerializer($buf);

		switch (ord($buf[0])) {
			case MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_1:
				$pk = new OpenConnectionReply1();
				$pk->decode($s);
				$this->mtu = min($this->mtu, $pk->mtuSize);
				$this->rakCookie = $pk->cookie;
				$this->sendRequest2();

				break;

			case MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_2:
				$pk = new OpenConnectionReply2();
				$pk->decode($s);
				$this->mtu = $pk->mtuSize;
				$this->sendConnectionRequest();

				break;
		}
	}

	private function sendAck(string $buf) : void
	{
		$seq = unpack('V', substr($buf, 1, 3) . "\x00")[1];
		$s = new PacketSerializer();
		$s->putByte(self::ID_ACK);
		$s->putShort(1);
		$s->putByte(1);
		$s->putLTriad($seq);
		$s->putLTriad($seq);
		$this->sendRaw($s->getBuffer());
	}

	private function sendRawPacket(Packet $packet) : void
	{
		$s = new PacketSerializer();
		$packet->encode($s);
		$this->sendRaw($s->getBuffer());
	}

	private function sendRaw(string $buf) : void
	{
		$this->socket->writePacket($buf);
	}

	public function disconnect() : void
	{
		if ($this->state->value < ConnectionState::CONNECTED->value) {
			$this->close();
			return;
		}

		$pk = new DisconnectionNotification();
		$this->sendEncapsulated($pk);

		$this->state = ConnectionState::UNCONNECTED;
		$this->close();
	}
}
