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

namespace aquarelay\command;

use aquarelay\command\default\ProxyListCommand;
use aquarelay\command\sender\CommandSender;

class SimpleCommandMap implements CommandMap {

	/** @var Command[] */
	private array $commands = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    public function registerDefaults() : void
    {
        $this->register(new ProxyListCommand());
    }

	public function register(Command $command): bool {
		if (isset($this->commands[$command->getName()])) return false;

		$this->commands[strtolower($command->getName())] = $command;

		foreach ($command->getAliases() as $alias) {
			$this->commands[strtolower($alias)] = $command;
		}

		return true;
	}

    public function getCommand(string $name) : ?Command
    {
        return $this->commands[strtolower($name)] ?? null;
    }

	public function dispatch(CommandSender $sender, string $line): bool {
		$args = explode(" ", trim($line));
		$label = strtolower(array_shift($args));

		if (!isset($this->commands[$label])) {
			return false;
		}

		$command = $this->commands[$label];

		if (!$command->testPermission($sender)) {
			$sender->sendMessage("§cYou don't have permission to use this command.");
			return false;
		}

		$command->execute($sender, $label, $args);
		return true;
	}

	public function getCommands(): array {
		return $this->commands;
	}
}