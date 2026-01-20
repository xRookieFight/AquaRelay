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

use pmmp\thread\Runnable;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use function array_key_exists;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_null;
use function is_scalar;
use function spl_object_id;

/**
 * AsyncTask class for running tasks in worker threads.
 * Credits to PocketMine-MP's AsyncTask implementation.
 */
abstract class AsyncTask extends Runnable {
	/**
	 * @var mixed[][]
	 * Used to store thread-local data to be used by onCompletion()
	 */
	private static array $threadLocalStorage = [];

	private ?ThreadSafeArray $progressUpdates = null;
	private ThreadSafe|string|int|bool|null|float $result = null;
	private bool $submitted = false;
	private bool $finished = false;

	/**
	 * Called by the worker thread to execute the async task
	 */
	public function run() : void {
		$this->result = null;

		try {
			$this->onRun();
		} finally {
			$this->finished = true;
		}
	}

	/**
	 * Returns whether this task has finished executing
	 */
	public function isFinished() : bool {
		return $this->finished || $this->isTerminated();
	}

	/**
	 * Check if task has a result
	 */
	public function hasResult() : bool {
		return $this->result !== null;
	}

	/**
	 * Get the task result
	 */
	public function getResult() : mixed {
		return $this->result;
	}

	/**
	 * Set the task result
	 */
	public function setResult(mixed $result) : void {
		$this->result = is_scalar($result) || is_null($result) || $result instanceof ThreadSafe ? $result : $result;
	}

	public function setSubmitted() : void {
		$this->submitted = true;
	}

	public function isSubmitted() : bool {
		return $this->submitted;
	}

	/**
	 * Actions to execute when run (in worker thread)
	 */
	abstract public function onRun() : void;

	/**
	 * Actions to execute when completed (on main thread)
	 */
	public function onCompletion() : void {
		// NOOP
	}

	/**
	 * Publish progress update from worker thread
	 */
	public function publishProgress(mixed $progress) : void {
		$progressUpdates = $this->progressUpdates;
		if ($progressUpdates === null) {
			$progressUpdates = $this->progressUpdates = new ThreadSafeArray();
		}
		$progressUpdates[] = igbinary_serialize($progress) ?? throw new \InvalidArgumentException("Progress must be serializable");
	}

	/**
	 * Check progress updates (called from main thread)
	 */
	public function checkProgressUpdates() : void {
		$progressUpdates = $this->progressUpdates;
		if ($progressUpdates !== null) {
			while (($progress = $progressUpdates->shift()) !== null) {
				$this->onProgressUpdate(igbinary_unserialize($progress));
			}
		}
	}

	/**
	 * Called from main thread after publishProgress is called
	 */
	public function onProgressUpdate(mixed $progress) : void {
		// NOOP
	}

	/**
	 * Store thread-local data (only accessible on the thread it was stored on)
	 */
	protected function storeLocal(string $key, mixed $complexData) : void {
		self::$threadLocalStorage[spl_object_id($this)][$key] = $complexData;
	}

	/**
	 * Fetch thread-local data
	 */
	protected function fetchLocal(string $key) : mixed {
		$id = spl_object_id($this);
		if (!isset(self::$threadLocalStorage[$id]) || !array_key_exists($key, self::$threadLocalStorage[$id])) {
			throw new \InvalidArgumentException("No matching thread-local data found on this thread");
		}
		return self::$threadLocalStorage[$id][$key];
	}

	final public function __destruct() {
		$this->reallyDestruct();
		unset(self::$threadLocalStorage[spl_object_id($this)]);
	}

	/**
	 * Override for custom __destruct cleanup
	 */
	protected function reallyDestruct() : void {
		// NOOP
	}
}
