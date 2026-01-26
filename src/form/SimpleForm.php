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

use aquarelay\player\Player;
use function count;
use function is_int;

class SimpleForm implements Form {

	private string $title;
	private string $content;
	/** @var array<array{ text: string, image?: array{ type: string, data: string } }> */
	private array $buttons = [];
	/** @var callable[] */
	private array $buttonActions = [];

	public function __construct(string $title, string $content = "") {
		$this->title = $title;
		$this->content = $content;
	}

	public function setTitle(string $title) : self {
		$this->title = $title;
		return $this;
	}

	public function setContent(string $content) : self {
		$this->content = $content;
		return $this;
	}

	public function addButton(string $text, ?callable $action = null, ?array $image = null) : self {
		$button = ['text' => $text];
		if ($image !== null) {
			$button['image'] = $image;
		}
		$this->buttons[] = $button;
		$this->buttonActions[] = $action;
		return $this;
	}

	public function handleResponse(Player $player, mixed $data) : void {
		if (!is_int($data) || $data < 0 || $data >= count($this->buttons)) {
			throw new FormValidationException("Invalid button selection");
		}

		$action = $this->buttonActions[$data];
		if ($action !== null) {
			$action($player);
		}
	}

	public function jsonSerialize() : array {
		return [
			'type' => 'form',
			'title' => $this->title,
			'content' => $this->content,
			'buttons' => $this->buttons
		];
	}
}
