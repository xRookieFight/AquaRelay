<?php

/*
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *             |_|                                |___/
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

final class LanguageParser
{
    /**
     * @return array<string, string>
     */
    public static function parseFile(string $file): array
    {
        $contents = file_get_contents($file);
        if (false === $contents) {
            return [];
        }

        $result = [];
        $lines = preg_split('/\R/', $contents) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if (false === $pos) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = self::stripQuotes($value);
            if ('' !== $key) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function stripQuotes(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (('"' === $first && '"' === $last) || ("'" === $first && "'" === $last)) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
