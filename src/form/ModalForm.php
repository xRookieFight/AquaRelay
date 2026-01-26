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
use function gettype;
use function is_bool;

class ModalForm implements Form {

	private string $title;
	private string $content;
	private string $button1Text;
	private string $button2Text;

	/** @var callable|null */
	private $button1Action = null;

	/** @var callable|null */
	private $button2Action = null;

	public function __construct(string $title, string $content = "", string $button1Text = "Yes", string $button2Text = "No") {
		$this->title = $title;
		$this->content = $content;
		$this->button1Text = $button1Text;
		$this->button2Text = $button2Text;
	}

	public function setTitle(string $title) : self {
		$this->title = $title;
		return $this;
	}

	public function setContent(string $content) : self {
		$this->content = $content;
		return $this;
	}

	/**
	 * Sets the text and action for the first button (True/Yes).
	 * @param callable|null $action fn(Player $player)
	 */
	public function setButton1(string $text, ?callable $action = null) : self {
		$this->button1Text = $text;
		$this->button1Action = $action;
		return $this;
	}

	/**
	 * Sets the text and action for the second button (False/No).
	 * @param callable|null $action fn(Player $player)
	 */
	public function setButton2(string $text, ?callable $action = null) : self {
		$this->button2Text = $text;
		$this->button2Action = $action;
		return $this;
	}

	/**
	 * Handles the response from the client.
	 * In a Modal form, data is boolean: true (button1) or false (button2).
	 */
	public function handleResponse(Player $player, mixed $data) : void {
		if ($data === null) {
			return;
		}

		if (!is_bool($data)) {
			throw new FormValidationException("Invalid modal response: Expected boolean, got " . gettype($data));
		}

		if ($data) {
			if ($this->button1Action !== null) {
				($this->button1Action)($player);
			}
		} else {
			if ($this->button2Action !== null) {
				($this->button2Action)($player);
			}
		}
	}

	public function jsonSerialize() : array {
		return [
			'type' => 'modal',
			'title' => $this->title,
			'content' => $this->content,
			'button1' => $this->button1Text,
			'button2' => $this->button2Text
		];
	}
}
