<?php

/*
 * Example Plugin for AquaRelay
 */

declare(strict_types=1);

namespace ExamplePlugin;

use aquarelay\plugin\PluginBase;

class ExamplePlugin extends PluginBase {

	public function onLoad() : void
	{
		$this->getServer()->getLogger()->info("ExamplePlugin is loading...");
	}

	public function onEnable() : void
	{
		$this->getServer()->getLogger()->info(
			"ExamplePlugin v" . $this->getVersion() . " enabled!"
		);
	}

	public function onDisable() : void
	{
		$this->getServer()->getLogger()->info("ExamplePlugin disabled!");
	}
}
