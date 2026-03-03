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
use function is_array;

class CustomForm implements Form {

	private string $title;

	/** @var array<array<string, mixed>> */
	private array $elements = [];

	/** @var callable|null */
	private $submitAction = null;

	/** @var callable[] */
	private array $validations = [];

	public function __construct(string $title) {
		$this->title = $title;
	}

	public function title(string $title) : self {
		$this->title = $title;
		return $this;
	}

	public function label(string $text) : self {
		$this->elements[] = [
			'type' => 'label',
			'text' => $text
		];
		$this->validations[] = null;
		return $this;
	}

	public function input(string $text, string $placeholder = "", string $default = "", ?string $tooltip = null, ?callable $validation = null) : self {
		$element = [
			'type' => 'input',
			'text' => $text,
			'placeholder' => $placeholder,
			'default' => $default
		];

		if ($tooltip !== null) {
			$element["tooltip"] = $tooltip;
		}

		$this->elements[] = $element;
		$this->validations[] = $validation;

		return $this;
	}

	public function toggle(string $text, bool $default = false, ?string $tooltip = null, ?callable $validation = null) : self {
		$element = [
			'type' => 'toggle',
			'text' => $text,
			'default' => $default
		];

		if ($tooltip !== null) {
			$element["tooltip"] = $tooltip;
		}

		$this->elements[] = $element;
		$this->validations[] = $validation;

		return $this;
	}

	public function slider(string $text, float $min, float $max, float $step = 1.0, float $default = 0.0, ?string $tooltip = null, ?callable $validation = null) : self {
		$element = [
			'type' => 'slider',
			'text' => $text,
			'min' => $min,
			'max' => $max,
			'step' => $step,
			'default' => $default
		];

		if ($tooltip !== null) {
			$element["tooltip"] = $tooltip;
		}

		$this->elements[] = $element;
		$this->validations[] = $validation;

		return $this;
	}

	public function stepSlider(string $text, array $steps, int $defaultIndex = 0, ?string $tooltip = null, ?callable $validation = null) : self {
		$element = [
			'type' => 'step_slider',
			'text' => $text,
			'steps' => $steps,
			'default' => $defaultIndex
		];

		if ($tooltip !== null) {
			$element["tooltip"] = $tooltip;
		}

		$this->elements[] = $element;
		$this->validations[] = $validation;

		return $this;
	}

	public function dropdown(string $text, array $options, int $defaultIndex = 0, ?string $tooltip = null, ?callable $validation = null) : self {
		$element = [
			'type' => 'dropdown',
			'text' => $text,
			'options' => $options,
			'default' => $defaultIndex
		];

		if ($tooltip !== null) {
			$element["tooltip"] = $tooltip;
		}

		$this->elements[] = $element;
		$this->validations[] = $validation;

		return $this;
	}

	/**
	 * Sets the action to run when the form is submitted.
	 * @param callable|null $action fn(Player $player, array $data)
	 */
	public function setSubmitAction(?callable $action) : self {
		$this->submitAction = $action;
		return $this;
	}

	public function handleResponse(Player $player, mixed $data) : void {
		if ($data === null) {
			return;
		}

		if (!is_array($data)) {
			throw new FormValidationException("Invalid form data: Expected array, got " . gettype($data));
		}

		$validatedData = [];

		foreach ($this->elements as $index => $element) {
			$value = $data[$index] ?? null;

			if ($element['type'] === 'label') {
				$validatedData[] = null;
				continue;
			}

			$validation = $this->validations[$index] ?? null;
			if ($validation !== null) {
				if (!$validation($value)) {
					throw new FormValidationException("Validation failed for field: " . $element['text']);
				}
			}

			$validatedData[] = $value;
		}

		if ($this->submitAction !== null) {
			($this->submitAction)($player, $validatedData);
		}
	}

	public function jsonSerialize() : array {
		return [
			'type' => 'custom_form',
			'title' => $this->title,
			'content' => $this->elements
		];
	}
}
