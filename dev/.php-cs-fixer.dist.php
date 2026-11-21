<?php
declare(strict_types=1);

/** Development-only layout rules; exclude generated catalogs so their generator remains authoritative. */
$finder = (new PhpCsFixer\Finder())
    ->in([dirname(__DIR__) . '/src', dirname(__DIR__) . '/public_html', __DIR__ . '/bin', __DIR__ . '/tests'])
    ->name('*.php')
    ->notName('locale_*.php')
    ->notName('migration_catalog.php')
    ->append([dirname(__DIR__) . '/config/local.example.php']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        'no_multiple_statements_per_line' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
    ])
    ->setFinder($finder);
