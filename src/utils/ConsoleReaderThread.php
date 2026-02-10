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

namespace aquarelay\utils;

use pmmp\thread\Thread;
use pmmp\thread\ThreadSafeArray;
use function fgets;
use function fopen;
use function trim;

class ConsoleReaderThread extends Thread
{
	public function __construct(
		private ThreadSafeArray $buffer
	) {}

	public function run() : void
	{
		$stdin = fopen("php://stdin", "r");

		while (true) {
			$line = fgets($stdin);
			if ($line !== false) {
				$trimmed = trim($line);
				if ($trimmed !== "") {
					$this->buffer[] = $trimmed;
				}
			}
		}
	}
}
