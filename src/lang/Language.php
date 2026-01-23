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

namespace aquarelay\lang;

use aquarelay\ProxyServer;
use Symfony\Component\Filesystem\Path;
use function file_exists;
use function str_replace;
use function strtolower;

/**
 * What about Crowdin or similar integrations?
 */
class Language
{
	public const DEFAULT_LANGUAGE = 'eng';

	private string $langName;

	private array $translations = [];

	private array $defaultTranslations = [];

	public function __construct(string $lang)
	{
		$this->langName = strtolower($lang);
		$this->defaultTranslations = $this->loadLang(\aquarelay\LOCALE_DATA_PATH, self::DEFAULT_LANGUAGE);
		$this->translations = $this->loadLang(\aquarelay\LOCALE_DATA_PATH, $this->langName);
	}

	public function getFullName() : string
	{
		return $this->translate('name');
	}

	public function getLang() : string
	{
		return $this->langName;
	}

	public function loadLang(string $path, string $languageName) : array
	{
		$file = Path::join($path, $languageName . '.ini');
		if (!file_exists($file)) {
			if ($languageName == self::DEFAULT_LANGUAGE) {
				throw new LanguageNotFoundException("Language \"{$languageName}\" not found");
			}
			ProxyServer::getInstance()->getLogger()->warning("Language file \"{$file}\" not found");
			$this->langName = self::DEFAULT_LANGUAGE;

			return [];
		}

		return LanguageParser::parseFile($file);
	}

	public function translate(string $key, array $args = []) : string
	{
		$text = $this->translations[$key] ?? $this->defaultTranslations[$key] ?? $key;
		foreach ($args as $i => $val) {
			$text = str_replace('{%' . $i . '}', (string) $val, $text);
		}

		return $text;
	}
}
