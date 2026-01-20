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

class RepeatingTask extends Task {

	private int $period;
	private int $delay;
	private int $elapsedTicks = 0;

	public function __construct(
		private Task $task,
		int $period,
		int $delay = 0
	) {
		parent::__construct();
		$this->period = $period;
		$this->delay = $delay;
	}

	public function getPeriod() : int {
		return $this->period;
	}

	public function getDelay() : int {
		return $this->delay;
	}

	public function getElapsedTicks() : int {
		return $this->elapsedTicks;
	}

	public function onRun() : void {
		$this->elapsedTicks++;
		
		if ($this->elapsedTicks >= $this->delay && ($this->elapsedTicks - $this->delay) % $this->period === 0 && !$this->isCancelled()) {
			$this->task->onRun();
		}
	}

}
