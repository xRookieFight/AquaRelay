<?php

namespace aquarelay\command;

use aquarelay\command\default\ProxyListCommand;
use aquarelay\command\sender\CommandSender;

class SimpleCommandMap {

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

	public function register(Command $command): void {
		$this->commands[strtolower($command->getName())] = $command;

		foreach ($command->getAliases() as $alias) {
			$this->commands[strtolower($alias)] = $command;
		}
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