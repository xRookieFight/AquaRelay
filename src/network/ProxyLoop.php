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

use aquarelay\lang\TranslationFactory;
use aquarelay\network\compression\DecompressionException;
use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\raklib\RakLibPacketSender;
use aquarelay\player\Player;
use aquarelay\ProxyServer;
use pmmp\encoding\ByteBufferReader;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\snooze\SleeperHandler;
use raklib\generic\DisconnectReason;
use function microtime;
use function ord;
use function substr;

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

	public function run() : void
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

	private function tick() : void
	{
		$this->server->getScheduler()->processAll();
		$this->server->handleConsoleInput();

		foreach ($this->sessions as $session) {
			$player = $session->getPlayer();
			$player?->getDownstream()?->tick();
		}
	}

	public function handleBackendPayload(Player $player, string $payload) : void
	{
		$pid = ord($payload[0]);
		if ($pid !== 0xFE) {
			return;
		}

		$compression = ord($payload[1]);
		$buffer = substr($payload, 2);

		if ($compression === CompressionAlgorithm::ZLIB) {
			try {
				$buffer = ZlibCompressor::getInstance()->decompress($buffer);
			}  catch (DecompressionException $e) {
				$this->server->getLogger()->critical("Backend decompression failed: " . $e->getMessage());
				$player->disconnect(TranslationFactory::translate("session.login.corrupt_packet"));
				return;
			}
		}

		try {
			$stream = new ByteBufferReader($buffer);

			$generator = PacketBatch::decodePackets(
				$stream,
				$player->getProtocol(),
				PacketPool::getInstance()
			);

			foreach ($generator as $packet) {
				if ($packet instanceof DataPacket) {
					$player->handleBackendPacket($packet);
				}
			}

		} catch (PacketHandlingException $e) {
			$this->server->getLogger()->error("Backend packet decode error: " . $e->getMessage());
		} catch (\Throwable $e) {
			// Catch generic errors (like buffer underflow) that aren't strict PacketHandlingExceptions
			$this->server->getLogger()->debug("General decode error: " . $e->getMessage());
		}
	}

	private function handleConnect(int $sessionId, string $ip, int $port) : void
	{

		$session = new NetworkSession(
			$this->server,
			NetworkSessionManager::getInstance(),
			PacketPool::getInstance(),
			new RakLibPacketSender($sessionId, $this->server->interface),
			$ip,
			$port,
			$sessionId
		);

		$session->info("Session opened");

		$this->sessions[$sessionId] = $session;
	}

	private function handlePacket(int $sessionId, string $payload) : void
	{
		$firstByte = ord($payload[0]);

		if ($firstByte !== 0xFE) {
			// Ignore non-game packets
			return;
		}

		if (!isset($this->sessions[$sessionId])) {
			return;
		}

		$this->sessions[$sessionId]->handleEncodedPacket(substr($payload, 1));
	}

	private function handleDisconnect(int $sessionId, int $reason) : void
	{
		if (isset($this->sessions[$sessionId])) {
			$session = $this->sessions[$sessionId];
			$reason = match ($reason){
				DisconnectReason::CLIENT_DISCONNECT => "Client disconnected",
				DisconnectReason::PEER_TIMEOUT => "Session timed out",
				DisconnectReason::CLIENT_RECONNECT => "New session established on same IP and port"
			};
			$session->onDisconnect($reason);
			unset($this->sessions[$sessionId]);
		}
	}

	private function handlePing(int $sessionId, int $ping) : void
	{
		if (isset($this->sessions[$sessionId])) {
			$this->sessions[$sessionId]->setPing($ping);
		}
	}
}
