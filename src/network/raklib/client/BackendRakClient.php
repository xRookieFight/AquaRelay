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
use pocketmine\utils\Binary;
use raklib\client\ClientSocket;
use raklib\generic\Session;
use raklib\generic\SocketException;
use raklib\protocol\ACK;
use raklib\protocol\ConnectionRequest;
use raklib\protocol\Datagram;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\MessageIdentifiers;
use raklib\protocol\NACK;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\Packet;
use raklib\protocol\PacketReliability;
use raklib\protocol\PacketSerializer;
use raklib\protocol\UnconnectedPing;
use raklib\utils\InternetAddress;
use function microtime;
use function min;
use function ord;
use function random_int;
use function strlen;
use const PHP_INT_MAX;

final class BackendRakClient extends Session
{
	private ClientSocket $socket;
	private Player $player;

	private int $clientId;
	private int $mtu = 1492;

	private array $sendQueue = [];

	public function __construct(
		\Logger $logger,
		InternetAddress $address,
		Player $player
	){
		$this->player = $player;
		$this->clientId = random_int(1, PHP_INT_MAX);

		$this->socket = new ClientSocket($address);
		$this->socket->setBlocking(false);

		parent::__construct(
			$logger,
			$address,
			$this->clientId,
			$this->mtu
		);
	}

	public function connect() : void{
		$this->sendUnconnectedPing();
	}

	public function tick() : void{
		try{
			while(($buf = $this->socket->readPacket()) !== null){
				$pid = ord($buf[0]);
				$serializer = new PacketSerializer($buf);

				if($pid === ACK::$ID){
					$pk = new ACK();
					$pk->decode($serializer);
					$this->handlePacket($pk);
					continue;
				}

				if($pid === NACK::$ID){
					$pk = new NACK();
					$pk->decode($serializer);
					$this->handlePacket($pk);
					continue;
				}

				if($pid >= MessageIdentifiers::ID_RESERVED_4) {
					$pk = new Datagram();
					$pk->decode($serializer);
					$this->handlePacket($pk);
					continue;
				}

				$this->handleRakNetConnectionPacket($buf);
			}
		} catch (SocketException) {
			$this->player->disconnect("Backend read error");
		}

		$this->update(microtime(true));
	}


	public function close() : void{
		$this->socket->close();
	}

	protected function sendPacket(Packet $packet) : void{
		$serializer = new PacketSerializer();
		$packet->encode($serializer);
		$this->socket->writePacket($serializer->getBuffer());
	}


	protected function onPacketAck(int $identifierACK) : void{
		// Nothing to do with here, maybe debugging?
	}

	protected function onDisconnect(int $reason) : void{
		$this->socket->close();
	}

	protected function onPingMeasure(int $pingMS) : void{
		var_dump("Ping: $pingMS");
	}

	protected function handleRakNetConnectionPacket(string $packet) : void{
		$pid = ord($packet[0]);

		switch($pid){
			case MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_1:
				$pk = new OpenConnectionReply1();
				$pk->decode(new PacketSerializer($packet));

				$this->mtu = min($this->mtu, $pk->mtuSize);

				$req = new OpenConnectionRequest2();
				$req->clientID = $this->clientId;
				$req->serverAddress = $this->getAddress();
				$req->mtuSize = $this->mtu;

				$this->sendPacket($req);
				break;

			case MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_2:
				$req = new ConnectionRequest();
				$req->clientID = $this->clientId;
				$req->sendPingTime = (int)(microtime(true) * 1000);
				$req->useSecurity = true;

				$this->queueConnectedPacket(
					$req,
					PacketReliability::RELIABLE_ORDERED,
					0,
					true
				);
				break;

			case MessageIdentifiers::ID_CONNECTION_REQUEST_ACCEPTED:
				$this->state = self::STATE_CONNECTED;
				break;
		}
	}

	protected function onPacketReceive(string $packet) : void{
		$pid = ord($packet[0]);

		if($pid == RakLibInterface::MCPE_RAKNET_PACKET_ID) {
			if($this->state !== self::STATE_CONNECTED){
				$this->state = self::STATE_CONNECTED;

				foreach($this->sendQueue as $p){
					$this->sendGamePacket($p);
				}
				$this->sendQueue = [];
			}

			$this->player->getServer()->getProxyLoop()->handleBackendPayload($this->player, $packet);
		}
	}

	public function sendGamePacket(DataPacket $packet) : void{
		if($this->state !== ConnectionState::LOGGED_IN){
			$this->sendQueue[] = $packet;
			return;
		}

		$writer = new ByteBufferWriter();
		$packet->encode($writer, $this->player->getProtocol());

		$payload = $writer->getData();

		$header = Binary::writeUnsignedVarInt(strlen($payload));
		$batch = $header . $payload;

		$final = RakLibInterface::MCPE_RAKNET_PACKET_ID .
			"\x00" . ZlibCompressor::getInstance()->compress($batch);

		$enc = new EncapsulatedPacket();
		$enc->buffer = $final;
		$enc->reliability = PacketReliability::RELIABLE_ORDERED;
		$enc->orderChannel = 0;

		$this->addEncapsulatedToQueue($enc, true);
	}

	private function sendUnconnectedPing() : void{
		$pk = new UnconnectedPing();
		$pk->sendPingTime = (int)(microtime(true) * 1000);
		$pk->clientId = $this->clientId;

		$this->sendPacket($pk);

		$req = new OpenConnectionRequest1();
		$req->protocol = RakLibInterface::RAKNET_PROTOCOL_VERSION;
		$req->mtuSize = $this->mtu;

		$this->sendPacket($req);
	}
}
