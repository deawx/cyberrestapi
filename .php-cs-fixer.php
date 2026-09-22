<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/Core',
        __DIR__ . '/Apps',
        __DIR__ . '/Routes',
        __DIR__ . '/Database',
        __DIR__ . '/tests',
    ])
    ->append([
        __DIR__ . '/index.php',
        __DIR__ . '/.php-cs-fixer.php',
    ])
    ->name('*.php')
    ->ignoreDotFiles(false)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS3x0' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
