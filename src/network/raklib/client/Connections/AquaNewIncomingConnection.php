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

namespace aquarelay\network\raklib\client\Connections;

use raklib\protocol\NewIncomingConnection;
use raklib\protocol\PacketSerializer;
use raklib\utils\InternetAddress;

class AquaNewIncomingConnection extends NewIncomingConnection {
    
    protected function encodePayload(PacketSerializer $out) : void {
        $out->putAddress($this->address);

        for ($i = 0; $i < 10; ++$i) {
            if (isset($this->systemAddresses[$i])) {
                $out->putAddress($this->systemAddresses[$i]);
            } else {
                $out->putAddress(new InternetAddress("127.0.0.1", 0, 4));
            }
        }

        $out->putLong($this->sendPingTime);
        $out->putLong($this->sendPongTime);
    }
}