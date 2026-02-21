<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->notPath('Resources/config/')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@Symfony' => true,
        'header_comment' => [
            'header' => <<<'EOF'
                This file is part of the ChamberOrchestra package.

                For the full copyright and license information, please view the LICENSE
                file that was distributed with this source code.
                EOF,
        ],
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'single_quote' => true,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'all',
            'strict' => true,
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ;
