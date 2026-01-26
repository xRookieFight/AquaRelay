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

namespace aquarelay\utils;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function escapeshellarg;
use function exec;
use function file_exists;
use function getmypid;
use function php_uname;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function stripos;
use function substr;

class Utils
{
	public const OS_WINDOWS = 'win';
	public const OS_IOS = 'ios';
	public const OS_MACOS = 'mac';
	public const OS_ANDROID = 'android';
	public const OS_LINUX = 'linux';
	public const OS_BSD = 'bsd';
	public const OS_UNKNOWN = 'other';

	private static ?string $os = null;

	/**
	 * Kill a process by its PID.
	 *
	 * * @param int $pid The Process ID to kill
	 * @param bool $subprocesses If true, attempts to kill the process tree (subprocesses)
	 */
	public static function kill(int $pid, bool $subprocesses = false) : void
	{
		$os = self::getOS();

		if ($os === self::OS_WINDOWS) {
			$command = sprintf(
				'taskkill.exe /F %s /PID %d > NUL 2> NUL',
				$subprocesses ? '/T' : '',
				$pid
			);
			exec($command);

			return;
		}

		$cmdPid = $subprocesses ? '-' . $pid : (string) $pid;
		exec('kill -9 ' . escapeshellarg($cmdPid) . ' > /dev/null 2>&1');
	}

	/**
	 * Get the current Operating System.
	 *
	 * * @param bool $recalculate Force recalculation of the OS
	 *
	 * @return string one of the Utils::OS_* constants
	 */
	public static function getOS(bool $recalculate = false) : string
	{
		if ((self::$os === null) || $recalculate) {
			$uname = php_uname('s');
			$machine = php_uname('m');

			if (stripos($uname, 'Win') !== false || $uname === 'Msys') {
				self::$os = self::OS_WINDOWS;

				return self::$os;
			}

			self::$os = match (true) {
				stripos($uname, 'Darwin') !== false => str_starts_with($machine, 'iP')
					? self::OS_IOS
					: self::OS_MACOS,

				stripos($uname, 'Linux') !== false => @file_exists('/system/build.prop')
					? self::OS_ANDROID
					: self::OS_LINUX,

				stripos($uname, 'BSD') !== false,
				$uname === 'DragonFly' => self::OS_BSD,

				default => self::OS_UNKNOWN,
			};
		}

		return self::$os;
	}

	public static function pid() : int
	{
		$result = getmypid();
		if ($result === false) {
			throw new \LogicException("getmypid() doesn't work on this platform");
		}

		return $result;
	}

	/**
	 * Helper to check if the current OS is Windows.
	 */
	public static function isWindows() : bool
	{
		return self::getOS() === self::OS_WINDOWS;
	}

	/**
	 * A hacky way to turn protocol id to version network.
	 */
	public static function protocolIdToVersion(int $protocolId) : ?string {
		$ref = new \ReflectionClass(ProtocolInfo::class);

		foreach ($ref->getConstants() as $name => $value) {
			if ($value === $protocolId && str_starts_with($name, 'PROTOCOL_')) {
				return str_replace('_', '.', substr($name, 9));
			}
		}

		return null;
	}
}
