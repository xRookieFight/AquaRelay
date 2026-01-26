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

namespace aquarelay\task;

use pmmp\thread\ThreadSafeArray;
use pmmp\thread\Worker;
use function ini_set;

/**
 * Worker thread for processing async tasks
 * Credits to PocketMine-MP's AsyncWorker.
 */
class AsyncWorker extends Worker
{
	/** @var mixed[] */
	private static array $store = [];

	private ThreadSafeArray $queue;

	public function __construct(
		private int $id,
		private int $memoryLimit = -1
	) {
		$this->queue = new ThreadSafeArray();
	}

	/**
	 * Submit a task to this worker.
	 */
	public function submitTask(AsyncTask $task) : void
	{
		$this->queue[] = $task;
	}

	/**
	 * Get queue size.
	 */
	public function getQueueSize() : int
	{
		return $this->queue->count();
	}

	/**
	 * Get worker ID.
	 */
	public function getWorkerId() : int
	{
		return $this->id;
	}

	/**
	 * Save data to thread-local storage.
	 */
	public function saveToThreadStore(string $identifier, mixed $value) : void
	{
		if (\Thread::getCurrentThread() !== $this) {
			throw new \LogicException('Thread-local data can only be stored in the thread context');
		}
		self::$store[$identifier] = $value;
	}

	/**
	 * Retrieve data from thread-local storage.
	 */
	public function getFromThreadStore(string $identifier) : mixed
	{
		if (\Thread::getCurrentThread() !== $this) {
			throw new \LogicException('Thread-local data can only be fetched in the thread context');
		}

		return self::$store[$identifier] ?? null;
	}

	/**
	 * Remove data from thread-local storage.
	 */
	public function removeFromThreadStore(string $identifier) : void
	{
		if (\Thread::getCurrentThread() !== $this) {
			throw new \LogicException('Thread-local data can only be removed in the thread context');
		}
		unset(self::$store[$identifier]);
	}

	/**
	 * Process tasks from queue.
	 */
	public function process() : int
	{
		$count = 0;
		while ($this->queue->count() > 0) {
			$task = $this->queue->shift();
			if ($task instanceof AsyncTask) {
				try {
					$task->run();
				} catch (\Throwable $e) {
					// Log exception in result
				}
				++$count;
			}
		}

		return $count;
	}

	protected function onRun() : void
	{
		if ($this->memoryLimit > 0) {
			ini_set('memory_limit', $this->memoryLimit . 'M');
		} else {
			ini_set('memory_limit', '-1');
		}
	}
}
