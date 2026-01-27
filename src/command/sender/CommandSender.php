<?php

namespace aquarelay\command\sender;

use aquarelay\ProxyServer;

interface CommandSender {
	public function sendMessage(string $message): void;
	public function getName(): string;
	public function getServer(): ProxyServer;
}