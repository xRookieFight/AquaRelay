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

namespace aquarelay\form;

class ButtonImage
{

	private string $imageType;

	private string $data;

	public function __construct(string $data, string $imageType)
	{
		$this->imageType = $imageType;
		$this->data = $data;
	}

	public static function texture(string $data) : self
	{
		return new self($data, "path");
	}

	public static function url(string $data) : self
	{
		return new self($data, "url");
	}

	public function getImageType() : string
	{
		return $this->imageType;
	}

	public function getData() : string
	{
		return $this->data;
	}

	public function toArray() : array
	{
		return [
			"type" => $this->getImageType(),
			"data" => $this->getData()
		];
	}
}
