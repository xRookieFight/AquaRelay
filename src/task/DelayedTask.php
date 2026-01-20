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

namespace aquarelay\task;

class DelayedTask extends Task {

	private int $delay;
	private int $elapsedTicks = 0;

	public function __construct(
		private Task $task,
		int $delay
	) {
		parent::__construct();
		$this->delay = $delay;
	}

	public function getDelay() : int {
		return $this->delay;
	}

	public function getElapsedTicks() : int {
		return $this->elapsedTicks;
	}

	public function onRun() : void {
		$this->elapsedTicks++;

		if ($this->elapsedTicks >= $this->delay && !$this->task->isCancelled() && !$this->isCancelled()) {
			$this->task->onRun();
		}
	}

	public function isReady() : bool {
		return $this->elapsedTicks >= $this->delay;
	}

}
