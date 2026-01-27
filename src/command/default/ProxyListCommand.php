<?php

namespace aquarelay\command\default;

use aquarelay\command\Command;
use aquarelay\command\builder\CommandBuilder;
use aquarelay\command\sender\CommandSender;
use aquarelay\server\BackendServer;

class ProxyListCommand extends Command
{
	public function getBuilder(): CommandBuilder
	{
		return new CommandBuilder(
			"proxylist",
			"Shows players in proxy",
			"/proxylist",
			["plist"],
			"aquarelay.command.proxylist"
		);
	}

	public function execute(CommandSender $sender, string $label, array $args): bool
	{
		$server = $sender->getServer();
		$onlinePlayers = $server->getOnlinePlayers();
		$servers = array_map(
			static fn(BackendServer $server) => $server->getName(),
			$server->getServerManager()->getAll()
		);

		/** @var array<string, string[]> $byServer */
		$byServer = [];

		foreach ($servers as $backend) {
			$byServer[$backend] = [];
		}

		foreach ($server->getOnlinePlayers() as $player) {
			$backend = $player->getBackendServer()?->getName();
			$byServer[$backend][] = $player->getName();
		}

		foreach ($byServer as $serverName => $players) {
			$count = count($players);

			$names = $count > 0 ? implode(', ', $players) : '';
			$sender->sendMessage("§7(§b{$serverName}§7): §f" . $names);

		}
		$sender->sendMessage("§3Online players: §f" . count($onlinePlayers));
		return true;
	}
}