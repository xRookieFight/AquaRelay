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

namespace aquarelay\utils;

use pmmp\thread\Thread;
use pmmp\thread\ThreadSafeArray;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

class MainLoggerThread extends Thread
{
	private ThreadSafeArray $buffer;
	private bool $shutdown = false;

	private int $currentSize = 0;

	public function __construct(
		private string $logFile,
		private ?string $archiveDir = null,
		private int $maxFileSize = 32 * 1024 * 1024
	) {
		$this->buffer = new ThreadSafeArray();

		\touch($this->logFile);

		if (($this->archiveDir !== null) && !@\mkdir($this->archiveDir) && !\is_dir($this->archiveDir)) {
			throw new \RuntimeException('Unable to create archive directory');
		}
	}

	public function write(string $line) : void
	{
		$this->synchronized(function () use ($line) : void {
			$this->buffer[] = $line;
			$this->notify();
		});
	}

	public function shutdown() : void
	{
		$this->synchronized(function () : void {
			$this->shutdown = true;
			$this->notify();
		});
		$this->join();
	}

	public function run() : void
	{
		$handle = $this->openLogFile();

		while (!$this->shutdown) {
			$this->synchronized(function () : void {
				if (\count($this->buffer) === 0 && !$this->shutdown) {
					$this->wait();
				}
			});

			while (($line = $this->buffer->shift()) !== null) {
				echo $line;

				$clean = \preg_replace('/\x1b\[[0-9;]*m/', '', $line);
				\fwrite($handle, $clean);
				$this->currentSize += \strlen($clean);

				$this->archiveIfNeeded($handle);
			}
		}

		\fclose($handle);
	}

	private function openLogFile()
	{
		$handle = \fopen($this->logFile, 'ab');
		$stat = \fstat($handle);
		$this->currentSize = $stat !== false ? $stat['size'] : 0;

		return $handle;
	}

	private function archiveIfNeeded(&$handle) : void
	{
		if (($this->archiveDir === null) || $this->currentSize < $this->maxFileSize) {
			return;
		}

		\fclose($handle);
		\clearstatcache();

		$date = \date('Y-m-d\\TH.i.s');
		$base = \pathinfo($this->logFile, PATHINFO_FILENAME);
		$ext = \pathinfo($this->logFile, PATHINFO_EXTENSION);

		$i = 0;
		do {
			$name = "{$base}.{$date}.{$i}.{$ext}";
			$target = $this->archiveDir . '/' . $name;
			++$i;
		} while (\file_exists($target));

		@\mkdir($this->archiveDir);
		\rename($this->logFile, $target);

		$handle = $this->openLogFile();
		\fwrite($handle, "--- Log archived, previous file archived as {$name} ---\n");
	}
}
