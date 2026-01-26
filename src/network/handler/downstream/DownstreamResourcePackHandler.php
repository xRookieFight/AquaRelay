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

namespace aquarelay\network\handler\downstream;

use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;

class DownstreamResourcePackHandler extends AbstractDownstreamPacketHandler
{
	public function handleResourcePacksInfo(ResourcePacksInfoPacket $packet) : bool
	{
		$pk = ResourcePackClientResponsePacket::create(
			ResourcePackClientResponsePacket::STATUS_COMPLETED,
			[]
		);
		$this->getPlayer()->sendToBackend($pk);

		$this->getPlayer()->setHandler(new DownstreamInGameHandler($this->getPlayer(), $this->logger));
		return true;
	}
}
