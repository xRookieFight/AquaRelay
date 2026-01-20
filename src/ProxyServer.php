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

namespace aquarelay;

use aquarelay\config\ProxyConfig;
use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\ProxyLoop;
use aquarelay\network\raklib\RakLibInterface;
use aquarelay\player\Player;
use aquarelay\player\PlayerManager;
use aquarelay\plugin\PluginLoader;
use aquarelay\plugin\PluginManager;
use aquarelay\task\TaskScheduler;
use aquarelay\utils\Colors;
use aquarelay\utils\MainLogger;
use aquarelay\utils\Utils;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class ProxyServer {

	public const NAME = "AquaRelay";
	public const VERSION = "1.0.0-alpha2"; // Semver
	public const IS_DEVELOPMENT = true;
	public RakLibInterface $interface;
	private MainLogger $logger;
	private ProxyConfig $config;
	private static ?self $instance = null;
	private PlayerManager $playerManager;
	private PluginManager $pluginManager;
	private TaskScheduler $taskScheduler;

	private float $startProcessTime;

	/**
	 * Returns a server instance, can be nullable
	 * @return ProxyServer|null
	 */
	public static function getInstance() : ?ProxyServer
	{
		return self::$instance;
	}

	/**
	 * Returns the configuration of proxy
	 * @return ProxyConfig
	 */
	public function getConfig() : ProxyConfig
	{
		return $this->config;
	}

	public function getLogger() : MainLogger
	{
		return $this->logger;
	}

	public function getAddress() : string
	{
		return $this->getConfig()->getNetworkSettings()->getBindAddress();
	}

	public function getPort() : int
	{
		return $this->getConfig()->getNetworkSettings()->getBindPort();
	}

	public function getMotd() : string
	{
		return $this->getConfig()->getGameSettings()->getMotd();
	}

	public function getSubMotd() : string
	{
		return $this->getConfig()->getGameSettings()->getSubMotd();
	}

	public function isDebug() : bool
	{
		return $this->getConfig()->getMiscSettings()->isDebugMode();
	}

	public function getMaxPlayers() : int
	{
		return $this->getConfig()->getGameSettings()->getMaxPlayers();
	}

	/**
	 * Returns all online proxied players
	 * @return array
	 */
	public function getOnlinePlayers() : array
	{
		return $this->playerManager->all();
	}

	/**
	 * Returns count of online proxied players
	 * @return int
	 */
	public function getOnlinePlayerCount() : int
	{
		return count($this->getOnlinePlayers());
	}

	/**
	 * Returns the latest supported version of Minecraft
	 * @return string
	 */
	public function getMinecraftVersion() : string
	{
		return ProtocolInfo::MINECRAFT_VERSION_NETWORK;
	}

	public function getName() : string
	{
		return self::NAME;
	}

	public function getVersion() : string
	{
		return self::VERSION;
	}

	public function getDataPath() : string
	{
		return $this->dataPath;
	}

	public function getResourcePath() : string
	{
		return $this->resourcePath;
	}

	public function getPlayerManager() : PlayerManager
	{
		return $this->playerManager;
	}

	public function getPluginManager() : PluginManager
	{
		return $this->pluginManager;
	}

	public function getScheduler() : TaskScheduler
	{
		return $this->taskScheduler;
	}

	public function __construct(
		private string $dataPath,
		private string $resourcePath
	){
		if (self::$instance !== null) {
			throw new \LogicException("Server instance is already initialized");
		}

		self::$instance = $this;
		$this->startProcessTime = microtime(true);
		$configFile = $this->dataPath . "config.yml";

		if (!file_exists($configFile)) {
			$source = $this->resourcePath . "config.yml";

			if (!file_exists($source)) {
				throw new \RuntimeException("Default configuration file missing in resources folder");
			}

			$data = file_get_contents($source);
			if ($data === false) {
				throw new \RuntimeException("Failed to read default config.yml from resources");
			}

			if (file_put_contents($configFile, $data) === false) {
				throw new \RuntimeException("Failed to create config.yml. Please check permissions.");
			}
		}
		$this->config = ProxyConfig::load($configFile);
		$this->logger = new MainLogger("Main Thread", $this->getConfig()->getMiscSettings()->getLogName(), $this->isDebug());

		if (self::IS_DEVELOPMENT){
			$this->logger->warning("You are using development build. Be careful, your progress may be lost in future.");
		}

		$this->logger->info("Loading server configuration");

		$this->logger->info("Starting " . $this->getName() . " version " . $this->getVersion());
		$this->logger->info("This server is running Minecraft: Bedrock Edition " . Colors::AQUA . "v" . $this->getMinecraftVersion());

		$this->playerManager = new PlayerManager();
		$this->taskScheduler = new TaskScheduler();

		register_shutdown_function([$this, "shutdown"]);

		$threshold = $this->getConfig()->getNetworkSettings()->getBatchThreshold();
		$compressionThreshold = $threshold >= 0 ? $threshold : null;

		$compressionLevel = $this->getConfig()->getNetworkSettings()->getCompressionLevel();
		if ($compressionLevel < 1 || $compressionLevel > 9){
			throw new \RuntimeException("Compression level must be between 1 and 9");
		}

		ZlibCompressor::setInstance(new ZlibCompressor($compressionLevel, $compressionThreshold, ZlibCompressor::DEFAULT_MAX_DECOMPRESSION_SIZE));
		$this->logger->debug("ZLib compressor initialized");

		$this->logger->info("Initializing RakLib Interface...");
		$this->interface = new RakLibInterface($this->dataPath, $this->logger, $this->getAddress(), $this->getPort(), $this->getConfig()->getNetworkSettings()->getMaxMtu());
		$this->interface->setName($this->getMotd(), $this->getSubMotd());

		$pluginsPath = $this->dataPath . "plugins" . DIRECTORY_SEPARATOR;
		if (!is_dir($pluginsPath)) {
			@mkdir($pluginsPath, 0755, true);
		}
		$pluginLoader = new PluginLoader($this, $pluginsPath);
		$this->pluginManager = new PluginManager($this, $pluginLoader);
		$this->pluginManager->loadPlugins();

		$this->logger->info("Listening on {$this->getAddress()}:{$this->getPort()}");

		$this->interface->start();

		$this->logger->info("Proxy started! (" . round(microtime(true) - $this->startProcessTime, 3) ."s)");
		
		$loop = new ProxyLoop($this);
		$loop->run();
	}

	public function shutdown() : void
    {
        $shutdownStart = microtime(true);

		foreach ($this->getOnlinePlayers() as $player) {
			/** @var Player $player */
			$player->disconnect("Proxy is shutting down");
		}

        foreach ($this->pluginManager->getPlugins() as $plugin) {
            if ($plugin->isEnabled()) {
                try {
                    $this->pluginManager->disablePlugin($plugin);
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to disable plugin " . $plugin->getName() . ": " . $e->getMessage());
                    $this->logger->logException($e);
                }
            }
        }

        $this->taskScheduler->cancelAllTasks();
        $this->taskScheduler->shutdown();
        
        $this->interface->shutdown();

        $duration = round(microtime(true) - $shutdownStart, 3);
        $this->logger->info("Shutdown completed in {$duration}s.");

        $this->handleRestartThrottle();
        $this->logger->shutdown();
        @Utils::kill(Utils::pid()); 
    }

    /**
     * Prevents the server from restarting too quickly if it was only up for a few seconds.
     * This saves CPU/Disk if the server is in a crash-loop.
     */
    private function handleRestartThrottle() : void 
    {
        $uptime = time() - (int)$this->startProcessTime;
        $minUptime = 10;
        
        if ($uptime < $minUptime) {
            $spacing = $minUptime - $uptime;
            echo PHP_EOL . "--- [AquaRelay] Server ran for only {$uptime}s. Waiting {$spacing}s before exit ---" . PHP_EOL;
            echo "--- (Press Ctrl+C to skip wait) ---" . PHP_EOL;
            sleep($spacing);
        }
    }
}