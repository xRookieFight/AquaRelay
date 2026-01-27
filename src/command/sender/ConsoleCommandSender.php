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

namespace aquarelay\command\sender;

use aquarelay\ProxyServer;
use aquarelay\utils\Colors;
use const PHP_EOL;

readonly class ConsoleCommandSender implements CommandSender
{

	public function __construct(private ProxyServer $server)
	{
		// NOOP
	}

	public function sendMessage(string $message) : void
	{
		echo Colors::colorize($message) . PHP_EOL;
	}

	public function getName() : string
	{
		return "CONSOLE";
	}

	public function getServer() : ProxyServer
	{
		return $this->server;
	}

	public function hasPermission(string $permission) : bool
	{
		return true;
	}
}
