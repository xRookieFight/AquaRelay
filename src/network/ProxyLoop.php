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

namespace aquarelay\network;

use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\raklib\RakLibPacketSender;
use aquarelay\player\Player;
use aquarelay\ProxyServer;
use pmmp\encoding\ByteBufferReader;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\snooze\SleeperHandler;

class ProxyLoop
{
    public const TICK_INTERVAL = 0.05;

    /** @var NetworkSession[] */
    private array $sessions = [];

    private SleeperHandler $sleeper;

    public function __construct(
        private ProxyServer $server
    ) {
        $this->sleeper = new SleeperHandler();
        $this->server->interface->setHandlers(
            $this->handleConnect(...),
            $this->handlePacket(...),
            $this->handleDisconnect(...),
            $this->handlePing(...)
        );
    }

    public function run(): void
    {
        $nextTick = microtime(true);

        while (true) {
            $now = microtime(true);

            $this->server->interface->tick();

            if ($now >= $nextTick) {
                $this->tick();
                $nextTick += self::TICK_INTERVAL;
            }

            $this->sleeper->sleepUntil($nextTick);
        }
    }

    private function tick(): void
    {
        $this->server->getScheduler()->processAll();

        foreach ($this->sessions as $session) {
            $player = $session->getPlayer();

            if (null !== $player && null !== $player->getDownstream()) {
                $player->getDownstream()->tick(function ($payload) use ($player): void {
                    $this->handleBackendPayload($player, $payload);
                });
            }
        }
    }

    private function handleBackendPayload(Player $player, string $payload): void
    {
        $pid = \ord($payload[0]);
        if (0xFE !== $pid) {
            return;
        }

        $compression = \ord($payload[1]);
        $buffer = substr($payload, 2);

        if (CompressionAlgorithm::ZLIB === $compression) {
            try {
                $buffer = ZlibCompressor::getInstance()->decompress($buffer);
            } catch (\Exception $e) {
                return;
            }
        }

        try {
            $stream = new ByteBufferReader($buffer);
            $packets = PacketBatch::decodeRaw($stream);

            foreach ($packets as $pktBuffer) {
                $packet = PacketPool::getInstance()->getPacket($pktBuffer);
                if (null !== $packet) {
                    $packet->decode(new ByteBufferReader($pktBuffer), ProtocolInfo::CURRENT_PROTOCOL);
                    $player->handleBackendPacket($packet);
                }
            }
        } catch (\Exception $e) {
            // Log error if needed
        }
    }

    private function handleConnect(int $sessionId, string $ip, int $port): void
    {
        $this->server->getLogger()->info("Client connected: {$ip}:{$port} (ID: {$sessionId})");

        $session = new NetworkSession(
            $this->server,
            NetworkSessionManager::getInstance(),
            PacketPool::getInstance(),
            new RakLibPacketSender($sessionId, $this->server->interface),
            $ip,
            $port
        );

        $this->sessions[$sessionId] = $session;
    }

    private function handlePacket(int $sessionId, string $payload): void
    {
        $firstByte = ord($payload[0]);

        if (0xFE !== $firstByte) {
            // Ignore non-game packets
            return;
        }

        if (!isset($this->sessions[$sessionId])) {
            return;
        }

        $this->sessions[$sessionId]->handleEncodedPacket(substr($payload, 1));
    }

    private function handleDisconnect(int $sessionId, string $reason): void
    {
        $this->server->getLogger()->info("Client disconnected: {$reason}");
        if (isset($this->sessions[$sessionId])) {
            NetworkSessionManager::getInstance()->remove($this->sessions[$sessionId]);
            $this->server->getPlayerManager()->removePlayer($this->sessions[$sessionId]);
            unset($this->sessions[$sessionId]);
        }
    }

    private function handlePing(int $sessionId, int $ping): void
    {
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]->setPing($ping);
        }
    }
}
