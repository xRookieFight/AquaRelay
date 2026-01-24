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
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\utils\Binary;
use raklib\client\ClientSocket;
use raklib\generic\SocketException;
use raklib\protocol\ConnectionRequest;
use raklib\protocol\DisconnectionNotification;
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
	private ClientSocket $socket;
	private ConnectionState $state = ConnectionState::UNCONNECTED;
	private int $clientId;
	private int $mtu = 1492;
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

	public function tick(callable $onPacket) : void
	{
		try {
			while (!is_null($buf = $this->socket->readPacket())) {
				$pid = ord($buf[0]);

				if ($pid < 0x80) {
					$this->handleInternalPacket($buf);
				} else {
					$this->sendAck($buf);
					$this->handleDatagram($buf, $onPacket);
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
		$pk = new OpenConnectionRequest2();
		$pk->serverAddress = new InternetAddress($this->address->getIp(), 0, 4);
		$pk->clientID = $this->clientId;
		$pk->mtuSize = $this->mtu;
		if (!is_null($this->rakCookie)){
			$pk->cookie = $this->rakCookie;
		}
		$this->sendRawPacket($pk);
		$this->state = ConnectionState::CONNECTING_2;
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

	private function sendNewIncomingConnection() : void
	{
		$pk = new NewIncomingConnection();
		$pk->address = new InternetAddress($this->address->getIp(), 0, 4);
		for ($i = 0; $i < 10; ++$i) {
			$pk->systemAddresses[] = new InternetAddress($this->address->getIp(), 0, 4);
		}
		$pk->sendPingTime = (int) (microtime(true) * 1000);
		$pk->sendPongTime = (int) (microtime(true) * 1000);
		$this->sendEncapsulated($pk);
	}

	private function sendNetworkSettingsRequest() : void
	{
		$packet = RequestNetworkSettingsPacket::create(ProtocolInfo::CURRENT_PROTOCOL);
		$writer = new ByteBufferWriter();
		$packet->encode($writer, ProtocolInfo::CURRENT_PROTOCOL);

		$payload = $writer->getData();

		$header = Binary::writeUnsignedVarInt(strlen($payload));
		$batch = $header . $payload;

		$final = RakLibInterface::MCPE_RAKNET_PACKET_ID . $batch;

		$this->sendEncapsulatedRaw($final);
	}

	private function encodeAndSend(DataPacket $packet) : void
	{
		$writer = new ByteBufferWriter();
		$packet->encode($writer, ProtocolInfo::CURRENT_PROTOCOL);
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
		$limit = $this->mtu - 60;

		if (strlen($payload) <= $limit) {
			$s = new PacketSerializer();
			$s->putByte(0x84);
			$s->putLTriad($this->seqNumber++);
			$s->putByte(0x40);
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
			$s->putByte(0x84);
			$s->putLTriad($this->seqNumber++);
			$s->putByte(0x50);
			$s->putShort(strlen($chunk) << 3);
			$s->putLTriad($this->messageIndex++);
			$s->putInt($count);
			$s->putShort($splitId);
			$s->putInt($index);
			$s->put($chunk);
			$this->sendRaw($s->getBuffer());
		}
	}

	private function handleDatagram(string $buf, callable $onPacket) : void
	{
		$s = new PacketSerializer($buf);
		$s->getByte();
		$s->getLTriad();

		while (!$s->feof()) {
			try {
				$flags = $s->getByte();
				$length = (int) ceil($s->getShort() / 8);
				$reliability = ($flags & 0xE0) >> 5;
				$split = 0 !== ($flags & 0x10);

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
					$this->handleSplit($id, $index, $count, $s->get($length), $onPacket);
				} else {
					$this->processPayload($s->get($length), $onPacket);
				}
			} catch (\Throwable $e) {
				break;
			}
		}
	}

	private function handleSplit(int $id, int $index, int $count, string $chunk, callable $onPacket) : void
	{
		$this->splitBuffer[$id] ??= ['total' => $count, 'chunks' => []];
		$this->splitBuffer[$id]['chunks'][$index] = $chunk;

		if (count($this->splitBuffer[$id]['chunks']) === $count) {
			ksort($this->splitBuffer[$id]['chunks']);
			$this->processPayload(implode('', $this->splitBuffer[$id]['chunks']), $onPacket);
			unset($this->splitBuffer[$id]);
		}
	}

	private function processPayload(string $payload, callable $onPacket) : void
	{
		if ($payload === '') {
			return;
		}

		$pid = ord($payload[0]);

		if ($pid === 0x10 && $this->state === ConnectionState::CONNECTING_3) {
			$this->state = ConnectionState::CONNECTED;

			$this->sendNewIncomingConnection();
			$this->sendNetworkSettingsRequest();
			$this->state = ConnectionState::GAME_HANDSHAKE;

			return;
		}

		if ($pid === 0xFE) {
			if ($this->state === ConnectionState::GAME_HANDSHAKE) {
				$this->state = ConnectionState::LOGGED_IN;

				foreach ($this->sendQueue as $p) {
					$this->encodeAndSend($p);
				}
				$this->sendQueue = [];
			}
			$onPacket($payload);
		}
	}

	private function handleInternalPacket(string $buf) : void
	{
		$s = new PacketSerializer($buf);

		switch (ord($buf[0])) {
			case 0x06:
				$pk = new OpenConnectionReply1();
				$pk->decode($s);
				$this->mtu = min($this->mtu, $pk->mtuSize);
				$this->rakCookie = $pk->cookie;
				$this->sendRequest2();

				break;

			case 0x08:
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
		$s->putByte(0xC0);
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
