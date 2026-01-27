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

use ReflectionClass;
use ReflectionMethod;
use function count;
use function get_class;
use function is_subclass_of;

class HandlerList {

	/** * @var array<string, callable[]>
	 * Key = Event Class Name, Value = Array of closures to execute
	 */
	private static array $handlers = [];

	/**
	 * Registers all public methods in a Listener class that accept an Event.
	 * * @param Listener $listener The object listening for events
	 */
	public static function register(Listener $listener) : void {
		$reflection = new ReflectionClass($listener);

		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->isStatic() || $method->isConstructor()) {
				continue;
			}

			$parameters = $method->getParameters();
			if (count($parameters) !== 1) {
				continue;
			}

			$paramType = $parameters[0]->getType();
			if (!$paramType instanceof \ReflectionNamedType || $paramType->isBuiltin()) {
				continue;
			}

			$eventClass = $paramType->getName();
			if (!is_subclass_of($eventClass, Event::class)) {
				continue;
			}

			$closure = $method->getClosure($listener);

			self::$handlers[$eventClass][] = $closure;
		}
	}

	/**
	 * Executing this is extremely fast because it iterates over a pre-built list of closures.
	 */
	public static function callEvent(Event $event) : void {
		$type = get_class($event);

		if (!isset(self::$handlers[$type])) {
			return;
		}

		foreach (self::$handlers[$type] as $handler) {
			if ($event instanceof CancellableTrait && $event->isCancelled()) {
				return;
			}

			$handler($event);
		}
	}

	public static function unregisterAll() : void {
		self::$handlers = [];
	}
}
