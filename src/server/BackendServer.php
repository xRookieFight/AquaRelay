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

use function fclose;
use function fsockopen;

final readonly class BackendServer
{
	public function __construct(
		private string $name,
		private string $address,
		private int    $port,
		private int    $priority
	) {}

	public static function create(string $name, string $address, int $port, int $priority) : BackendServer
	{
		return new self(
			$name,
			$address,
			$port,
			$priority
		);
	}

	public function getName() : string { return $this->name; }
	public function getAddress() : string { return $this->address; }
	public function getPort() : int { return $this->port; }
	public function getPriority() : int { return $this->priority; }

	public function isOnline(int $timeout = 5) : bool
	{
		$errno = 0;
		$errstr = '';

		$socket = @fsockopen(
			$this->address,
			$this->port,
			$errno,
			$errstr,
			$timeout
		);

		if ($socket === false) {
			return false;
		}

		fclose($socket);
		return true;
	}
}
