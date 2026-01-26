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

namespace aquarelay\event;

abstract class Event {

	protected ?string $eventName = null;

	/**
	 * Returns the event name (usually the class name).
	 */
	public function getEventName() : string {
		return $this->eventName ??= (new \ReflectionClass($this))->getShortName();
	}

	/**
	 * Triggers the event processing.
	 * This is a helper method to make code cleaner: $event->call();
	 */
	public function call() : void {
		HandlerList::callEvent($this);
	}
}
