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

namespace aquarelay\event\default\command;

use aquarelay\command\sender\CommandSender;
use aquarelay\event\Cancellable;
use aquarelay\event\CancellableTrait;
use aquarelay\event\Event;

/**
 * Called before a player dispatches a command.
 */
final class CommandEvent extends Event implements Cancellable {

	use CancellableTrait;

	private CommandSender $sender;
	private string $command;
	private array $args = [];

	public function __construct(CommandSender $sender, string $command, array $args = []) {
		$this->sender = $sender;
		$this->command = $command;
		$this->args = $args;
	}

	public function getSender() : CommandSender
	{
		return $this->sender;
	}

	public function getCommand() : string
	{
		return $this->command;
	}

	public function getArgs() : array
	{
		return $this->args;
	}
}
