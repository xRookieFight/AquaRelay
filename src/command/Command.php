<?php

namespace aquarelay\command;

use aquarelay\command\builder\CommandBuilder;
use aquarelay\command\sender\CommandSender;

abstract class Command {

	private CommandBuilder $builder;

	final public function __construct() {
		$this->builder = $this->getBuilder();
	}

	abstract public function getBuilder(): CommandBuilder;

	abstract public function execute(
		CommandSender $sender,
		string $label,
		array $args
	): bool;

	public function getName(): string {
		return $this->builder->getName();
	}

	public function getAliases(): array {
		return $this->builder->getAliases();
	}

	public function getPermission(): ?string {
		return $this->builder->getPermission();
	}

	public function testPermission(CommandSender $sender): bool {
		return true;
	}
}