<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->notPath('Resources/config/')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP85Migration' => true,
        '@PHP8x5Migration:risky' => true,
        'header_comment' => [
            'header' => <<<'EOF'
This file is part of the ChamberOrchestra package.

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF,
            'location' => 'after_declare_strict',
            'separate' => 'both',
        ],
        'strict_param' => true,
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ;
