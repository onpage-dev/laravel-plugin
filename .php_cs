<?php

$finder = Symfony\Component\Finder\Finder::create()
    ->files()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('resources')
    ->exclude('storage')
    ->exclude('public')
    ->notName("*.txt")
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2'                              => true,
        'declare_equal_normalize'            => true,
        'method_separation'                  => true,
        'array_indentation'                  => true,
        'object_operator_without_whitespace' => true,
        'return_type_declaration'            => ['space_before' => 'one'],
        'whitespace_after_comma_in_array'    => true,
        'no_spaces_after_function_name'      => true,
        'no_spaces_inside_parenthesis'       => true,
        'visibility_required'                => [],
        'no_extra_blank_lines'               => true,
        'no_extra_consecutive_blank_lines'   => [
            'curly_brace_block',
            'extra',
            'parenthesis_brace_block',
            'square_brace_block',
            'throw',
            'use',
        ],
        'binary_operator_spaces' => [
            'operators' => [
                '='  => 'single_space',
                '=>' => 'align_single_space_minimal',
            ]
        ],
        'strict_param'             => false,
        'array_syntax'             => ['syntax' => 'short'],
        'no_unneeded_curly_braces' => true,
        'braces'                   => [
            'allow_single_line_closure'                   => true,
            'position_after_functions_and_oop_constructs' => 'same'
        ],
    ])
    ->setFinder($finder)
    ->setLineEnding("\n")
;

return $config;
