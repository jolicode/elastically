<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$fileHeaderComment = <<<'EOF'
    This file is part of the jolicode/elastically library.

    (c) JoliCode <coucou@jolicode.com>

    For the full copyright and license information, please view the LICENSE
    file that was distributed with this source code.
    EOF;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->notPath('tests/Jane/generated/')
    ->append([
        __FILE__,
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP74Migration' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'php_unit_internal_class' => false, // From @PhpCsFixer but we don't want it
        'php_unit_test_class_requires_covers' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_add_missing_param_annotation' => false, // From @PhpCsFixer but we don't want it
        'header_comment' => ['header' => $fileHeaderComment, 'separate' => 'both'],
        'concat_space' => ['spacing' => 'one'],
        'method_chaining_indentation' => false, // Does not work with tree builder
    ])
    ->setFinder($finder)
;
