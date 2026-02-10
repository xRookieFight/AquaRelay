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

namespace aquarelay\resourcepack;

use aquarelay\config\category\ResourcePackSettings;
use aquarelay\lang\TranslationFactory;
use aquarelay\ProxyServer;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\resourcepacks\BehaviorPackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackType;
use Ramsey\Uuid\Uuid;
use function count;
use function in_array;
use function is_file;
use function is_dir;
use function mkdir;
use function pathinfo;
use function rtrim;
use function scandir;
use function str_starts_with;
use function strtolower;
use const DIRECTORY_SEPARATOR;

final class ResourcePackManager
{
	private const CHUNK_SIZE = 102400;
	private const PACK_EXTENSIONS = ['zip', 'mcpack'];
	private const DEFAULT_PACKS_DIR = 'resourcepacks';

	private string $packsPath;
	/** @var ResourcePack[] */
	private array $packs = [];
	/** @var array<string, ResourcePack> */
	private array $packsById = [];

	private ResourcePacksInfoPacket $packsInfoPacket;
	private ResourcePackStackPacket $stackPacket;

	public function __construct(
		private ProxyServer $server,
		private ResourcePackSettings $settings,
		string $dataPath
	) {
		$this->packsPath = $this->resolvePath($settings->getPacksPath(), $dataPath);
		$this->ensurePacksPath();
		$this->packsInfoPacket = $this->buildEmptyInfoPacket();
		$this->stackPacket = $this->buildEmptyStackPacket();

		if ($this->settings->isEnabled()) {
			$this->loadPacks();
		}
	}

	public function isEnabled() : bool
	{
		return $this->settings->isEnabled();
	}

	public function isForceAccept() : bool
	{
		return $this->settings->isForceAccept();
	}

	public function isOverwriteClientPacks() : bool
	{
		return $this->settings->isOverwriteClientPacks();
	}

	public function getPacksPath() : string
	{
		return $this->packsPath;
	}

	/**
	 * @return ResourcePack[]
	 */
	public function getPacks() : array
	{
		return $this->packs;
	}

	public function getPackById(string $uuid) : ?ResourcePack
	{
		return $this->packsById[strtolower($uuid)] ?? null;
	}

	public function getPacksInfoPacket() : ResourcePacksInfoPacket
	{
		return $this->packsInfoPacket;
	}

	public function getStackPacket() : ResourcePackStackPacket
	{
		return $this->stackPacket;
	}

	private function buildEmptyInfoPacket() : ResourcePacksInfoPacket
	{
		return ResourcePacksInfoPacket::create(
			[],
			[],
			false,
			false,
			false,
			false,
			[],
			Uuid::fromString(Uuid::NIL),
			"",
			true
		);
	}

	public function buildEmptyStackPacket() : ResourcePackStackPacket
	{
		return ResourcePackStackPacket::create(
			[],
			[],
			false,
			ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			new Experiments([], false),
			false
		);
	}

	public function createPackInfoPacket(string $packId) : ?ResourcePackDataInfoPacket
	{
		$pack = $this->getPackById($packId);
		if ($pack === null) {
			return null;
		}

		$chunkCount = (int) (($pack->getSize() - 1) / self::CHUNK_SIZE) + 1;
		$packType = $pack->getType() === ResourcePack::TYPE_DATA
			? ResourcePackType::ADDON
			: ResourcePackType::RESOURCES;

		return ResourcePackDataInfoPacket::create(
			$pack->getUuid()->toString(),
			self::CHUNK_SIZE,
			$chunkCount,
			$pack->getSize(),
			$pack->getSha256(),
			false,
			$packType
		);
	}

	public function createPackChunkPacket(string $packId, int $chunkIndex) : ?ResourcePackChunkDataPacket
	{
		$pack = $this->getPackById($packId);
		if ($pack === null) {
			return null;
		}

		$offset = $chunkIndex * self::CHUNK_SIZE;
		try {
			$data = $pack->getChunk($offset, self::CHUNK_SIZE);
		} catch (ResourcePackException) {
			return null;
		}

		return ResourcePackChunkDataPacket::create(
			$pack->getUuid()->toString(),
			$chunkIndex,
			$offset,
			$data
		);
	}

	public function loadPacks() : void
	{
		$this->packs = [];
		$this->packsById = [];

		$entries = scandir($this->packsPath);
		if ($entries === false) {
			$this->server->getLogger()->warning(TranslationFactory::translate("resource_pack.failed_to_scan", [$this->packsPath]));
			return;
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$fullPath = $this->packsPath . DIRECTORY_SEPARATOR . $entry;
			if (!is_file($fullPath)) {
				continue;
			}

			$extension = strtolower((string) (pathinfo($fullPath, PATHINFO_EXTENSION) ?? ''));
			if (!in_array($extension, self::PACK_EXTENSIONS, true)) {
				continue;
			}

			try {
				$pack = new ZippedResourcePack($fullPath);
			} catch (ResourcePackException $e) {
				$this->server->getLogger()->warning(TranslationFactory::translate("resource_pack.failed_to_load", [
					$entry, $e->getMessage()
				]));
				continue;
			}

			$uuid = strtolower($pack->getUuid()->toString());
			if (isset($this->packsById[$uuid])) {
				$this->server->getLogger()->warning(TranslationFactory::translate("resource_pack.duplicate_uuid", [
					$uuid, $entry
				]));
				continue;
			}

			$this->packs[] = $pack;
			$this->packsById[$uuid] = $pack;
		}

		$this->rebuildPackets();
		$this->server->getLogger()->info(TranslationFactory::translate("resource_pack.loaded", [count($this->packs)]));
	}

	private function rebuildPackets() : void
	{
		$resourcePackEntries = [];
		$behaviorPackEntries = [];
		$resourcePackStack = [];
		$behaviorPackStack = [];
		$hasAddons = false;

		foreach ($this->packs as $pack) {
			if ($pack->getType() === ResourcePack::TYPE_DATA) {
				$hasAddons = true;
				$behaviorPackEntries[] = new BehaviorPackInfoEntry(
					$pack->getUuid()->toString(),
					$pack->getVersion(),
					$pack->getSize()
				);
				$behaviorPackStack[] = new ResourcePackStackEntry(
					$pack->getUuid()->toString(),
					$pack->getVersion(),
					""
				);
				continue;
			}

			$resourcePackEntries[] = new ResourcePackInfoEntry(
				$pack->getUuid(),
				$pack->getVersion(),
				$pack->getSize()
			);
			$resourcePackStack[] = new ResourcePackStackEntry(
				$pack->getUuid()->toString(),
				$pack->getVersion(),
				""
			);
		}

		$this->packsInfoPacket = ResourcePacksInfoPacket::create(
			$resourcePackEntries,
			$behaviorPackEntries,
			$this->settings->isForceAccept(),
			$hasAddons,
			false,
			$this->settings->isOverwriteClientPacks(),
			[],
			Uuid::fromString(Uuid::NIL),
			"",
			true
		);

		$this->stackPacket = ResourcePackStackPacket::create(
			$resourcePackStack,
			$behaviorPackStack,
			$this->settings->isForceAccept(),
			ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			new Experiments([], false),
			false
		);
	}

	private function ensurePacksPath() : void
	{
		if (!is_dir($this->packsPath)) {
			mkdir($this->packsPath, 0o755, true);
		}
	}

	private function resolvePath(string $path, string $dataPath) : string
	{
		$path = rtrim($path, DIRECTORY_SEPARATOR);

		if ($path === '') {
			$path = self::DEFAULT_PACKS_DIR;
		}

		if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
			return $path;
		}

		return rtrim($dataPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
	}
}
