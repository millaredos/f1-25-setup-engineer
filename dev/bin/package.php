<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once __DIR__ . '/translations.php';

use App\Application\UpdateInstaller;

/** Build a reproducible MVC update ZIP containing only allowlisted application payloads. */
function buildPackage(string $root, string $output, string $version, array $identity): void
{
    if (!UpdateInstaller::validVersion($version) || !in_array($identity['channel'] ?? '', ['alpha', 'release'], true) || (($identity['channel'] === 'alpha') !== str_contains($version, '-alpha.')) || !preg_match('/^[a-f0-9]{40}$/D', $identity['commit'] ?? '') || !is_int($identity['pr'] ?? null) || $identity['pr'] < 0) {
        throw new RuntimeException('Invalid package identity.');
    }
    $files = [];
    // Manual builds enforce the same generated-source contract as CI.
    foreach (glob($root . '/languages/*.po') as $source) {
        $target = $root . '/src/Translation/catalog_' . basename($source, '.po') . '.php';
        if (!is_file($target) || file_get_contents($target) !== compilePo($source, basename($source, '.po'))) {
            throw new RuntimeException('Generate current translation catalogs before packaging.');
        }
    }
    foreach (['src', 'data', 'public_html'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->isLink()) { throw new RuntimeException('Symlinks are not package sources.'); }
            if ($file->isFile() && UpdateInstaller::allowed($path)) { $files[$path] = file_get_contents($file->getPathname()); }
        }
    }
    $files['src/VERSION'] = $version . "\n";
    ksort($files, SORT_STRING);
    $manifest = ['format' => 'f125-update-v3', 'version' => $version, 'build' => $identity, 'requires' => ['php' => 80000, 'schema' => 1], 'files' => array_map(static fn (string $bytes): string => hash('sha256', $bytes), $files)];
    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('Cannot create package.'); }
    try {
        foreach (['manifest.json' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)] + $files as $name => $bytes) {
            if (!$zip->addFromString($name, $bytes) || !$zip->setMtimeName($name, 315532800) || !$zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)) { throw new RuntimeException('Cannot write deterministic archive entry.'); }
        }
    } finally { $zip->close(); }
}

// Including this file from tests defines the builder without creating output artifacts.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $options = getopt('', ['version:', 'channel:', 'commit:', 'pr:', 'branch:', 'output:']);
        $root = dirname(__DIR__, 2);
        $version = $options['version'] ?? trim(file_get_contents($root . '/src/VERSION'));
        $output = $options['output'] ?? null;
        if (!is_string($output) || !isset($options['commit'], $options['branch'])) { throw new RuntimeException('Provide --output, --commit and --branch; no implicit publication identity.'); }
        $identity = ['channel' => $options['channel'] ?? (str_contains($version, '-alpha.') ? 'alpha' : 'release'), 'commit' => $options['commit'], 'pr' => (int) ($options['pr'] ?? 0), 'branch' => $options['branch']];
        buildPackage($root, $output, $version, $identity);
        echo $output . PHP_EOL;
    } catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); }
}
