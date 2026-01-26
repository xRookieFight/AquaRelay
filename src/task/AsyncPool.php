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

use function array_values;
use function count;
use function spl_object_id;
use function time;
use const PHP_INT_MAX;

/**
 * Manages worker threads and async task processing
 * Credits to PocketMine-MP's AsyncPool.
 */
class AsyncPool
{
	/** @var AsyncWorker[] */
	private array $workers = [];

	/** @var AsyncTask[] */
	private array $pendingTasks = [];

	/** @var int[] worker ID => last used time */
	private array $workerLastUsed = [];

	private int $workerCount;
	private int $workerMemoryLimit;

	public function __construct(int $workerCount = 4, int $workerMemoryLimit = -1)
	{
		$this->workerCount = $workerCount;
		$this->workerMemoryLimit = $workerMemoryLimit;
		$this->initializeWorkers();
	}

	/**
	 * Select a worker with the least load.
	 */
	public function selectWorker() : int
	{
		$worker = null;
		$minUsage = PHP_INT_MAX;

		foreach ($this->workers as $i => $entry) {
			$usage = $entry->getQueueSize();
			if ($usage < $minUsage) {
				$worker = $i;
				$minUsage = $usage;
				if ($usage === 0) {
					break;
				}
			}
		}

		if (($worker === null) || ($minUsage > 0 && count($this->workers) < $this->workerCount)) {
			for ($i = 0; $i < $this->workerCount; ++$i) {
				if (!isset($this->workers[$i])) {
					$worker = $i;

					break;
				}
			}
		}

		return $worker ?? 0;
	}

	/**
	 * Submit an async task to a specific worker.
	 */
	public function submitTaskToWorker(AsyncTask $task, int $worker) : void
	{
		if ($worker < 0 || $worker >= $this->workerCount) {
			throw new \InvalidArgumentException("Invalid worker {$worker}");
		}

		if ($task->isSubmitted()) {
			throw new \InvalidArgumentException('Cannot submit the same AsyncTask instance more than once');
		}

		$task->setSubmitted();

		if (!isset($this->workers[$worker]) || !$this->workers[$worker]->isRunning()) {
			$this->workers[$worker] = new AsyncWorker($worker, $this->workerMemoryLimit);
			$this->workers[$worker]->start();
		}

		$this->workers[$worker]->submitTask($task);
		$this->pendingTasks[spl_object_id($task)] = $task;
		$this->workerLastUsed[$worker] = time();
	}

	/**
	 * Submit an async task to the pool.
	 */
	public function submitTask(AsyncTask $task) : int
	{
		if ($task->isSubmitted()) {
			throw new \InvalidArgumentException('Cannot submit the same AsyncTask instance more than once');
		}

		$worker = $this->selectWorker();
		$this->submitTaskToWorker($task, $worker);

		return $worker;
	}

	/**
	 * Collect finished tasks from all workers.
	 */
	public function collectTasks() : array
	{
		$finished = [];

		foreach ($this->pendingTasks as $id => $task) {
			if ($task->isFinished()) {
				$task->checkProgressUpdates();
				$task->onCompletion();
				$finished[] = $task;
				unset($this->pendingTasks[$id]);
			}
		}

		return $finished;
	}

	/**
	 * Get all pending tasks.
	 */
	public function getPendingTasks() : array
	{
		return array_values($this->pendingTasks);
	}

	/**
	 * Get pending task count.
	 */
	public function getPendingTaskCount() : int
	{
		return count($this->pendingTasks);
	}

	/**
	 * Check if a task is pending.
	 */
	public function isTaskPending(AsyncTask $task) : bool
	{
		return isset($this->pendingTasks[spl_object_id($task)]);
	}

	/**
	 * Get running worker IDs.
	 */
	public function getRunningWorkers() : array
	{
		$running = [];
		foreach ($this->workers as $i => $worker) {
			if ($worker->isRunning()) {
				$running[] = $i;
			}
		}

		return $running;
	}

	/**
	 * Get task queue sizes by worker.
	 */
	public function getTaskQueueSizes() : array
	{
		$sizes = [];
		foreach ($this->workers as $i => $worker) {
			$sizes[$i] = $worker->getQueueSize();
		}

		return $sizes;
	}

	/**
	 * Shutdown unused workers.
	 */
	public function shutdownUnusedWorkers(int $idleTimeout = 300) : int
	{
		$ret = 0;
		$time = time();

		foreach ($this->workers as $i => $worker) {
			if ($worker->isRunning() && $time > $this->workerLastUsed[$i] + $idleTimeout && $worker->getQueueSize() === 0) {
				$worker->quit();
				unset($this->workers[$i]);
				++$ret;
			}
		}

		return $ret;
	}

	/**
	 * Shutdown the entire pool.
	 */
	public function shutdown() : void
	{
		foreach ($this->workers as $worker) {
			if ($worker->isRunning()) {
				$worker->quit();
			}
		}
		$this->workers = [];
		$this->pendingTasks = [];
	}

	/**
	 * Get worker count.
	 */
	public function getWorkerCount() : int
	{
		return count($this->workers);
	}

	/**
	 * Get total size of all worker queues.
	 */
	public function getTotalQueueSize() : int
	{
		$total = 0;
		foreach ($this->workers as $worker) {
			$total += $worker->getQueueSize();
		}

		return $total;
	}

	/**
	 * Initialize worker threads.
	 */
	private function initializeWorkers() : void
	{
		for ($i = 0; $i < $this->workerCount; ++$i) {
			$worker = new AsyncWorker($i, $this->workerMemoryLimit);
			$worker->start();
			$this->workers[$i] = $worker;
			$this->workerLastUsed[$i] = time();
		}
	}
}
