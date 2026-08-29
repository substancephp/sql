<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'cast_spaces' => true,
        'no_break_comment' => false,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_extra_blank_lines' => true,
        'no_superfluous_elseif' => true,
        'no_superfluous_phpdoc_tags' => ['allow_hidden_params' => true],
        'no_trailing_comma_in_singleline_array' => true,
        'not_operator_with_successor_space' => true,
        'phpdoc_line_span' => [
            'case' => 'single',
            'class' => 'single',
            'const' => 'single',
            'method' => 'single',
            'other' => 'single',
            'property' => 'single',
            'trait_import' => 'single',
        ],
        'single_quote' => true,
        'single_space_around_construct' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        'trim_array_spaces' => true,
        'type_declaration_spaces' => true,
        'types_spaces' => true,
        'unary_operator_spaces' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    // 💡 by default, Fixer looks for `*.php` files excluding `./vendor/` - here, you can groom this config
    ->setFinder(
        (new Finder())
            // 💡 root folder to check
            ->in(__DIR__),
        // 💡 additional files, eg bin entry file
        // ->append([__DIR__.'/bin-entry-file'])
        // 💡 folders to exclude, if any
        // ->exclude([/* ... */])
        // 💡 path patterns to exclude, if any
        // ->notPath([/* ... */])
        // 💡 extra configs
        // ->ignoreDotFiles(false) // true by default in v3, false in v4 or future mode
        // ->ignoreVCS(true) // true by default
    )
;
