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

abstract class Task
{
	private int $taskId;
	private bool $cancelled = false;

	public function __construct(int $taskId = 0)
	{
		$this->taskId = $taskId;
	}

	/**
	 * Called when the task is executed.
	 */
	abstract public function onRun() : void;

	/**
	 * Get the task ID.
	 */
	final public function getTaskId() : int
	{
		return $this->taskId;
	}

	/**
	 * Set the task ID.
	 */
	final public function setTaskId(int $id) : void
	{
		$this->taskId = $id;
	}

	/**
	 * Check if the task is cancelled.
	 */
	final public function isCancelled() : bool
	{
		return $this->cancelled;
	}

	/**
	 * Cancel the task.
	 */
	final public function cancel() : void
	{
		$this->cancelled = true;
	}
}
