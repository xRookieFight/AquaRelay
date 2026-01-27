<?php

namespace aquarelay\command\builder;

readonly class CommandBuilder {

	public function __construct(
		private string  $name,
		private string  $description = "",
		private string  $usage = "",
		private array   $aliases = [],
		private ?string $permission = null
	) {}

	public function getName(): string { return $this->name; }
	public function getDescription(): string { return $this->description; }
	public function getUsage(): string { return $this->usage; }
	public function getAliases(): array { return $this->aliases; }
	public function getPermission(): ?string { return $this->permission; }
}