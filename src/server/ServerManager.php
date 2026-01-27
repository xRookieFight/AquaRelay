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

namespace aquarelay\server;

use aquarelay\config\category\ServerSettings;
use function array_rand;
use function array_values;
use function count;
use function usort;

class ServerManager
{
	/** @var BackendServer[] */
	private array $servers = [];

	private int $roundRobinIndex = 0;

	public function __construct(private readonly ServerSettings $serverSettings)
	{
		foreach ($this->serverSettings->getServers() as $name => $data) {
			$this->servers[$name] = BackendServer::create($name, $data["address"], $data["port"], $data["priority"]);
		}
	}

	/** @return BackendServer[] */
	public function getAll() : array
	{
		return array_values($this->servers);
	}

	public function get(string $name) : ?BackendServer
	{
		return $this->servers[$name] ?? null;
	}

	public function select() : BackendServer
	{
		$strategy = $this->serverSettings->getSelectionStrategy() ?? "priority";
		$available = $this->getAll();

		if ($available === []) {
			throw new ServerException("No backend servers available");
		}

		return match ($strategy) {
			'random' => $available[array_rand($available)],
			'round-robin' => $this->roundRobin($available),
			default => $this->priority($available)
		};
	}

	private function priority(array $servers) : BackendServer
	{
		usort(
			$servers,
			fn($a, $b) => $a->getPriority() <=> $b->getPriority()
		);
		return $servers[0];
	}

	private function roundRobin(array $servers) : BackendServer
	{
		$server = $servers[$this->roundRobinIndex % count($servers)];
		$this->roundRobinIndex++;
		return $server;
	}
}
