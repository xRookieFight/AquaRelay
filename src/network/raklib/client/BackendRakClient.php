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

namespace aquarelay\network\raklib\client;

use aquarelay\network\raklib\RakLibInterface;
use aquarelay\ProxyServer;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use raklib\client\ClientSocket;
use raklib\protocol\ConnectionRequest;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionReply2;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\Packet;
use raklib\protocol\PacketSerializer as Stream;
use raklib\protocol\UnconnectedPing;
use raklib\utils\InternetAddress;

final class BackendRakClient
{
    private const STATE_UNCONNECTED = 0;
    private const STATE_CONNECTING_1 = 1;
    private const STATE_CONNECTING_2 = 2;
    private const STATE_CONNECTING_3 = 3;
    private const STATE_CONNECTED = 4;
    private const STATE_GAME_HANDSHAKE = 5;
    private const STATE_LOGGED_IN = 6;

    private const MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";

    private ClientSocket $socket;
    private int $state = self::STATE_UNCONNECTED;
    private int $clientId;
    private int $mtu = 1492;

    private int $seqNumber = 0;
    private int $messageIndex = 0;
    private int $splitId = 0;

    private array $splitBuffer = [];
    private array $sendQueue = [];

    public function __construct(private InternetAddress $address)
    {
        $this->clientId = random_int(1, PHP_INT_MAX);
        $this->socket = new ClientSocket($address);
        $this->socket->setBlocking(false);
    }

    public function connect(): void
    {
        $this->sendPing();
    }

    public function sendGamePacket(DataPacket $packet): void
    {
        if ($this->state < self::STATE_LOGGED_IN) {
            $this->sendQueue[] = $packet;

            return;
        }
        $this->encodeAndSend($packet);
    }

    public function tick(callable $onPacket): void
    {
        while (($buf = $this->socket->readPacket()) !== null) {
            $pid = ord($buf[0]);

            if ($pid < 0x80) {
                $this->handleInternalPacket($buf);
            } else {
                $this->sendAck($buf);
                $this->handleDatagram($buf, $onPacket);
            }
        }
    }

    public function close(): void
    {
        $this->socket->close();
    }

    private function sendPing(): void
    {
        $pk = new UnconnectedPing();
        $pk->sendPingTime = (int) (microtime(true) * 1000);
        $pk->clientId = $this->clientId;
        $this->sendRawPacket($pk);
        $this->sendRequest1();
    }

    private function sendRequest1(): void
    {
        $pk = new OpenConnectionRequest1();
        $pk->protocol = RakLibInterface::RAKNET_PROTOCOL_VERSION;
        $pk->mtuSize = $this->mtu;
        $this->sendRawPacket($pk);
        $this->state = self::STATE_CONNECTING_1;
    }

    private function sendRequest2(): void
    {
        $s = new Stream();
        $s->putByte(0x07);
        $s->put(self::MAGIC);
        $s->putByte(4);
        $s->putByte(127);
        $s->putByte(0);
        $s->putByte(0);
        $s->putByte(1);
        $s->putShort(0);
        $s->putShort($this->mtu);
        $s->putLong($this->clientId);
        $this->sendRaw($s->getBuffer());
        $this->state = self::STATE_CONNECTING_2;
    }

    private function sendConnectionRequest(): void
    {
        $pk = new ConnectionRequest();
        $pk->clientID = $this->clientId;
        $pk->sendPingTime = (int) (microtime(true) * 1000);
        $pk->useSecurity = false;
        $this->sendEncapsulated($pk);
        $this->state = self::STATE_CONNECTING_3;
    }

    private function sendNewIncomingConnection(): void
    {
        $s = new Stream();
        $s->putByte(0x13);
        $s->putByte(4);
        $s->putByte(127);
        $s->putByte(0);
        $s->putByte(0);
        $s->putByte(1);
        $s->putShort(0);
        for ($i = 0; $i < 10; ++$i) {
            $s->putByte(4);
            $s->putByte(127);
            $s->putByte(0);
            $s->putByte(0);
            $s->putByte(1);
            $s->putShort(0);
        }
        $t = (int) (microtime(true) * 1000);
        $s->putLong($t);
        $s->putLong($t);
        $this->sendEncapsulatedRaw($s->getBuffer());
    }

    private function sendNetworkSettingsRequest(): void
    {
        $pid = "\xC1\x01";
        $pver = pack('N', ProtocolInfo::CURRENT_PROTOCOL);
        $payload = $pid.$pver;

        $header = $this->writeVarInt(strlen($payload));
        $batch = $header.$payload;
        $final = "\xFE".$batch;

        $this->sendEncapsulatedRaw($final);
    }

    private function encodeAndSend(DataPacket $packet): void
    {
        $writer = new ByteBufferWriter();
        $packet->encode($writer, ProtocolInfo::CURRENT_PROTOCOL);
        $this->sendBatch($writer->getData());
    }

    private function sendBatch(string $payload): void
    {
        if ('' === $payload) {
            return;
        }

        $header = $this->writeVarInt(strlen($payload));
        $batch = $header.$payload;

        // Standard Game Packets use ZLIB Deflate (0x00)
        $compressed = @zlib_encode($batch, ZLIB_ENCODING_DEFLATE, 7);
        if (false === $compressed) {
            return;
        }

        // 0xFE + 0x00 + Data
        $final = "\xFE\x00".$compressed;

        $this->sendEncapsulatedRaw($final);
    }

    private function writeVarInt(int $v): string
    {
        $out = '';
        while (($v & 0xFFFFFF80) !== 0) {
            $out .= chr(($v & 0x7F) | 0x80);
            $v >>= 7;
        }

        return $out.chr($v & 0x7F);
    }

    private function sendEncapsulated(Packet $packet): void
    {
        $s = new Stream();
        $packet->encode($s);
        $this->sendEncapsulatedRaw($s->getBuffer());
    }

    private function sendEncapsulatedRaw(string $payload): void
    {
        $limit = $this->mtu - 60;

        if (strlen($payload) <= $limit) {
            $s = new Stream();
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
            $s = new Stream();
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

    private function handleDatagram(string $buf, callable $onPacket): void
    {
        $s = new Stream($buf);
        $s->getByte();
        $s->getLTriad();

        while (!$s->feof()) {
            try {
                $flags = $s->getByte();
                $length = (int) ceil($s->getShort() / 8);
                $reliability = ($flags & 0xE0) >> 5;
                $split = ($flags & 0x10) !== 0;

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

    private function handleSplit(int $id, int $index, int $count, string $chunk, callable $onPacket): void
    {
        $this->splitBuffer[$id] ??= ['total' => $count, 'chunks' => []];
        $this->splitBuffer[$id]['chunks'][$index] = $chunk;

        if (count($this->splitBuffer[$id]['chunks']) === $count) {
            ksort($this->splitBuffer[$id]['chunks']);
            $this->processPayload(implode('', $this->splitBuffer[$id]['chunks']), $onPacket);
            unset($this->splitBuffer[$id]);
        }
    }

    private function processPayload(string $payload, callable $onPacket): void
    {
        if ('' === $payload) {
            return;
        }

        $pid = ord($payload[0]);

        if (0x10 === $pid && self::STATE_CONNECTING_3 === $this->state) {
            $this->state = self::STATE_CONNECTED;
            ProxyServer::getInstance()->getLogger()->info('RakNet Connected. Negotiating Protocol...');

            $this->sendNewIncomingConnection();
            $this->sendNetworkSettingsRequest();
            $this->state = self::STATE_GAME_HANDSHAKE;

            return;
        }

        if (0xFE === $pid) {
            if (self::STATE_GAME_HANDSHAKE === $this->state) {
                $this->state = self::STATE_LOGGED_IN;
                ProxyServer::getInstance()->getLogger()->info('Protocol Negotiated. Logging in...');

                foreach ($this->sendQueue as $p) {
                    $this->encodeAndSend($p);
                }
                $this->sendQueue = [];
            }
            $onPacket($payload);
        }
    }

    private function handleInternalPacket(string $buf): void
    {
        $s = new Stream($buf);

        switch (ord($buf[0])) {
            case 0x06:
                $pk = new OpenConnectionReply1();
                $pk->decode($s);
                $this->mtu = min($this->mtu, $pk->mtuSize);
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

    private function sendAck(string $buf): void
    {
        $seq = unpack('V', substr($buf, 1, 3)."\x00")[1];
        $s = new Stream();
        $s->putByte(0xC0);
        $s->putShort(1);
        $s->putByte(1);
        $s->putLTriad($seq);
        $s->putLTriad($seq);
        $this->sendRaw($s->getBuffer());
    }

    private function sendRawPacket(Packet $packet): void
    {
        $s = new Stream();
        $packet->encode($s);
        $this->sendRaw($s->getBuffer());
    }

    private function sendRaw(string $buf): void
    {
        $this->socket->writePacket($buf);
    }
}
