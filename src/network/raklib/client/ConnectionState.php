<?php

declare(strict_types=1);

namespace aquarelay\network\raklib\client;

enum ConnectionState: int
{
	case UNCONNECTED = 0;
	case CONNECTING_1 = 1;
	case CONNECTING_2 = 2;
	case CONNECTING_3 = 3;
	case CONNECTED = 4;
	case GAME_HANDSHAKE = 5;
	case LOGGED_IN = 6;
}
