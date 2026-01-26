<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
	->in(__DIR__ . '/src')
	->in(__DIR__ . '/scripts')
	->in(__DIR__ . '/tools')
	->notPath('vendor')
	->notPath('plugins')
	->notName('AquaRelay.php');

return (new Config())
	->setRiskyAllowed(true)
	->setRules([
		'align_multiline_comment' => [
			'comment_type' => 'phpdocs_only',
		],
		'array_indentation' => true,
		'array_syntax' => [
			'syntax' => 'short',
		],
		'binary_operator_spaces' => [
			'default' => 'single_space',
		],
		'blank_line_after_namespace' => true,
		'blank_line_after_opening_tag' => true,
		'blank_line_before_statement' => [
			'statements' => ['declare'],
		],
		'cast_spaces' => [
			'space' => 'single',
		],
		'concat_space' => [
			'spacing' => 'one',
		],
		'declare_strict_types' => true,
		'elseif' => true,
		'fully_qualified_strict_types' => true,
		'global_namespace_import' => [
			'import_constants' => true,
			'import_functions' => true,
			'import_classes' => null,
		],
		'header_comment' => [
			'comment_type' => 'comment',
			'header' => <<<BODY

                           _____      _
    /\                    |  __ \    | |
   /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
  / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
/_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
              |_|                              |___/
 
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
 
@author AquaRelay Team
@link https://www.aquarelay.dev/

BODY,
			'location' => 'after_open',
		],
		'indentation_type' => true,
		'logical_operators' => true,
		'native_constant_invocation' => [
			'scope' => 'namespaced',
		],
		'native_function_invocation' => [
			'scope' => 'namespaced',
			'include' => ['@all'],
		],
		'new_with_braces' => [
			'named_class' => true,
			'anonymous_class' => false,
		],
		'no_closing_tag' => true,
		'no_empty_phpdoc' => true,
		'no_extra_blank_lines' => true,
		'no_superfluous_phpdoc_tags' => [
			'allow_mixed' => true,
		],
		'no_trailing_whitespace' => true,
		'no_trailing_whitespace_in_comment' => true,
		'no_whitespace_in_blank_line' => true,
		'no_unused_imports' => true,
		'ordered_imports' => [
			'imports_order' => ['class', 'function', 'const'],
			'sort_algorithm' => 'alpha',
		],
		'phpdoc_align' => [
			'align' => 'vertical',
			'tags' => ['param'],
		],
		'phpdoc_line_span' => [
			'property' => 'single',
			'method' => null,
			'const' => null,
		],
		'phpdoc_trim' => true,
		'phpdoc_trim_consecutive_blank_line_separation' => true,
		'return_type_declaration' => [
			'space_before' => 'one',
		],
		'single_blank_line_at_eof' => true,
		'single_import_per_statement' => true,
		'strict_param' => true,
		'unary_operator_spaces' => true,
	])
	->setFinder($finder)
	->setIndent("\t")
	->setLineEnding("\n");