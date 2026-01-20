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

use Closure;

class TaskScheduler {

	/** @var Task[] */
	private array $tasks = [];
	private int $taskIdCounter = 0;
	private ?AsyncPool $asyncPool = null;

	/**
	 * Schedule a task to run
	 * @param Task $task
	 * @return int Task ID
	 */
	public function scheduleTask(Task $task) : int {
		$taskId = ++$this->taskIdCounter;
		$task->setTaskId($taskId);
		$this->tasks[$taskId] = $task;
		return $taskId;
	}

	/**
	 * Schedule a task to run immediately
	 * @param Closure|Task $task
	 * @return int Task ID
	 */
	public function schedule(Closure|Task $task) : int {
		if ($task instanceof Closure) {
			$task = new ClosureTask($task);
		}
		return $this->scheduleTask($task);
	}

	/**
	 * Schedule a task to run after delay (in ticks)
	 * @param Closure|Task $task
	 * @param int $delayTicks
	 * @return int Task ID
	 */
	public function scheduleDelayed(Closure|Task $task, int $delayTicks) : int {
		if ($task instanceof Closure) {
			$task = new ClosureTask($task);
		}
		return $this->scheduleTask(new DelayedTask($task, $delayTicks));
	}

	/**
	 * Schedule a task to repeat
	 * @param Closure|Task $task
	 * @param int $periodTicks Period between executions
	 * @param int $delayTicks Initial delay before first execution
	 * @return int Task ID
	 */
	public function scheduleRepeating(Closure|Task $task, int $periodTicks, int $delayTicks = 0) : int {
		if ($task instanceof Closure) {
			$task = new ClosureTask($task);
		}
		return $this->scheduleTask(new RepeatingTask($task, $periodTicks, $delayTicks));
	}

	/**
	 * Cancel a task by ID
	 * @param int $taskId
	 */
	public function cancelTask(int $taskId) : void {
		if (isset($this->tasks[$taskId])) {
			$this->tasks[$taskId]->cancel();
			unset($this->tasks[$taskId]);
		}
	}

	/**
	 * Cancel all tasks
	 */
	public function cancelAllTasks() : void {
		foreach ($this->tasks as $task) {
			$task->cancel();
		}
		$this->tasks = [];
	}

	/**
	 * Process all tasks (called once per tick)
	 */
	public function processAll() : void {
		foreach ($this->tasks as $taskId => $task) {
			if ($task->isCancelled()) {
				unset($this->tasks[$taskId]);
				continue;
			}

			try {
				$task->onRun();
				if ($task instanceof DelayedTask && $task->isReady()) {
					unset($this->tasks[$taskId]);
				}
			} catch (\Throwable $e) {
				$logger = \aquarelay\ProxyServer::getInstance()?->getLogger();
				if ($logger !== null) {
					$logger->logException($e);
				}
				unset($this->tasks[$taskId]);
			}
		}

		if ($this->asyncPool !== null) {
			$this->asyncPool->collectTasks();
		}
	}

	/**
	 * Submit an async task to the thread pool
	 * @param AsyncTask $task
	 * @return int Worker ID
	 */
	public function submitAsyncTask(AsyncTask $task) : int {
		if ($this->asyncPool === null) {
			$this->asyncPool = new AsyncPool(4, -1);
		}
		return $this->asyncPool->submitTask($task);
	}

	/**
	 * Get the async pool instance
	 */
	public function getAsyncPool() : ?AsyncPool {
		return $this->asyncPool;
	}

	/**
	 * Get all pending tasks
	 * @return Task[]
	 */
	public function getAllTasks() : array {
		return array_values($this->tasks);
	}

	/**
	 * Get pending task count
	 * @return int
	 */
	public function getTaskCount() : int {
		return count($this->tasks);
	}

	/**
	 * Check if a task is pending
	 * @param int $taskId
	 * @return bool
	 */
	public function isTaskScheduled(int $taskId) : bool {
		return isset($this->tasks[$taskId]);
	}

	/**
	 * Shutdown async pool
	 */
	public function shutdown() : void {
		if ($this->asyncPool !== null) {
			$this->asyncPool->shutdown();
		}
	}

}
