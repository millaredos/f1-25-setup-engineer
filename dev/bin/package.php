<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once __DIR__ . '/translations.php';

use App\Application\UpdateInstaller;
use App\Application\LegacyPackage;

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

/** Wrap the authenticated MVC ZIP in the published 0.4 transport contract for automatic migration. */
function buildReleasePackage(string $root, string $output, string $version, array $identity): void
{
    $inner = tempnam(sys_get_temp_dir(), 'f125-mvc-');
    if (!$inner) { throw new RuntimeException('Cannot prepare MVC archive.'); }
    try {
        buildPackage($root, $inner, $version, $identity);
        $bytes = file_get_contents($inner);
        $parameters = json_decode(file_get_contents($root . '/data/parameters.json'), true, 64, JSON_THROW_ON_ERROR);
        $parameters['mvcPackage'] = ['version' => $version, 'identity' => $identity, 'sha256' => hash('sha256', $bytes), 'archive' => base64_encode($bytes)];
        $files = [];
        foreach (LegacyPackage::required() as $path) {
            if (str_ends_with($path, '.php')) {
                $target = str_starts_with($path, 'public_html/') ? "dirname(__DIR__) . '/src/MvcTransition.php'" : "__DIR__ . '/MvcTransition.php'";
                $files[$path] = "<?php\ndeclare(strict_types=1);\n// Generated transport entry point; removed when the MVC transition completes.\nrequire " . $target . ";\n";
            } else {
                $files[$path] = "/* Temporary transition asset; the next request installs the MVC application. */\n";
            }
        }
        $files['data/parameters.json'] = json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $files['data/symptoms.json'] = file_get_contents($root . '/data/symptoms.json');
        $files['public_html/assets/navigation.js'] = "/** Complete only the visible CSRF-protected continuation form after the authorized update. */\ndocument.querySelector('#mvc-transition-form')?.requestSubmit();\n";
        $files['src/VERSION'] = $version . "\n";
        $files['src/MvcTransition.php'] = str_replace('<?php', "<?php\n// Generated from dev/templates/mvc-transition.php by dev/bin/package.php.", file_get_contents($root . '/dev/templates/mvc-transition.php'));
        ksort($files, SORT_STRING);
        $manifest = ['format' => 'f125-update-v2', 'transition' => 'f125-mvc-1', 'version' => $version, 'build' => $identity, 'requires' => ['php' => 80000, 'schema' => 1], 'files' => array_map(static fn (string $file): string => hash('sha256', $file), $files)];
        $zip = new ZipArchive();
        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('Cannot create transition package.'); }
        try {
            foreach (['manifest.json' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)] + $files as $path => $content) {
                if (strlen($content) > 2097152 || !$zip->addFromString($path, $content) || !$zip->setMtimeName($path, 315532800) || !$zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
                    throw new RuntimeException('Cannot write bounded transition entry.');
                }
            }
        } finally { $zip->close(); }
    } finally { if (is_file($inner)) { unlink($inner); } }
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
