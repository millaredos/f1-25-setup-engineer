<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\JsonFile;

/** Validates complete packages and uses a durable backup journal for rollback and crash recovery. */
final class UpdateInstaller
{
    private string $root;
    private string $public;

    public function __construct(string $root, string $public = 'public_html')
    {
        $resolved = realpath($root);
        if ($resolved === false) {
            throw new \RuntimeException('Invalid application directory.');
        }
        $this->root = $resolved;
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $public) || in_array($public, ['src', 'data', 'config', 'dev', 'backups'], true)) { throw new \RuntimeException('Invalid public directory.'); }
        $this->public = $public;
    }

    /** Package identities accept only stable and per-version Alpha releases. */
    public static function validVersion(string $version): bool
    {
        return preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-alpha\.(?:0|[1-9]\d*))?$/D', $version) === 1;
    }

    /** Never allow private configuration, development tools, symlinks or traversal in a package path. */
    public static function allowed(string $path): bool
    {
        if (!preg_match('~^(?:src/[A-Za-z0-9_/-]+\.(?:php)|src/VERSION|data/[a-zA-Z0-9_-]+\.json|public_html/(?:[a-zA-Z0-9_-]+\.php|assets/[a-zA-Z0-9_/-]+\.(?:js|css)))$~D', $path)) {
            return false;
        }
        return !str_contains($path, '//') && !str_contains($path, '..');
    }

    /** Validate hashes and size limits before any application file can change. */
    public function inspect(string $archive, string $version, ?array $expected = null): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Cannot open update package.');
        }
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > 500) {
                throw new \RuntimeException('Invalid package file count.');
            }
            $seen = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if (!$entry || isset($seen[$entry['name']]) || $entry['size'] > 2097152 || ($entry['name'] !== 'manifest.json' && !self::allowed($entry['name']))) {
                    throw new \RuntimeException('Invalid package entry.');
                }
                $seen[$entry['name']] = true;
                $total += $entry['size'];
            }
            if ($total > 16777216) {
                throw new \RuntimeException('Package is too large.');
            }
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 64, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? '') !== 'f125-update-v3' || ($manifest['version'] ?? '') !== $version || !is_array($manifest['files'] ?? null) || ($manifest['requires']['schema'] ?? null) !== 1 || !is_int($manifest['requires']['php'] ?? null) || $manifest['requires']['php'] < 80000 || $manifest['requires']['php'] > PHP_VERSION_ID || count($manifest['files']) + 1 !== count($seen)) {
                throw new \RuntimeException('Package needs a compatible MVC installation.');
            }
            if ($expected !== null) {
                foreach (['channel', 'commit', 'pr', 'branch'] as $key) {
                    if (($manifest['build'][$key] ?? null) !== ($expected[$key] ?? null)) { throw new \RuntimeException('Package source identity mismatch.'); }
                }
            }
            $contents = [];
            foreach ($manifest['files'] as $path => $hash) {
                if (!is_string($path) || !self::allowed($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !isset($seen[$path])) {
                    throw new \RuntimeException('Invalid manifest path or digest.');
                }
                $bytes = $zip->getFromName($path);
                if (!is_string($bytes) || !hash_equals($hash, hash('sha256', $bytes))) {
                    throw new \RuntimeException('Package is damaged.');
                }
                $contents[$path] = $bytes;
            }
            $requiredFiles = [
                'src/VERSION',
                'src/bootstrap.php',
                'public_html/index.php',
                'public_html/install.php',
                'public_html/api.php',
                'src/Http/Request.php',
                'src/Http/Response.php',
                'src/Http/Session.php',
                'src/Http/View.php',
                'src/Persistence/SetupRepository.php',
                'src/Controller/ApiController.php',
                'src/Controller/PageController.php',
                'src/Controller/UpdatesController.php',
                'src/Application/Comparison.php',
                'src/Application/Installation.php',
                'src/Application/SetupCodec.php',
                'src/Application/UpdateDiscovery.php',
                'src/Application/UpdateInstaller.php',
                'src/Application/UpdateStatus.php',
                'src/Model/Car.php',
                'src/Model/F1Car.php',
                'src/Model/Circuit.php',
                'src/Model/Setup.php',
                'src/Model/Fields.php',
                'src/Model/ValidationError.php',
                'src/Infrastructure/Catalog.php',
                'src/Infrastructure/Database.php',
                'src/Infrastructure/HttpClient.php',
                'src/Infrastructure/JsonFile.php',
                'src/Infrastructure/PublicProtection.php',
                'src/Infrastructure/RequestLock.php',
                'src/Infrastructure/Translator.php',
                'src/View/layout.php',
                'src/View/workspace.php',
                'src/View/home.php',
                'src/View/circuits.php',
                'src/View/workshop.php',
                'src/View/tests.php',
                'src/View/install.php',
                'src/View/options.php',
                'src/View/updates.php',
                'src/View/database.php',
                'src/View/faq.php',
                'src/View/error.php',
                'data/editions.json',
                'data/catalog.json',
                'data/parameters.json',
                'data/presets-2026.json',
                'data/circuit-profiles.json',
                'data/symptoms.json',
                'public_html/assets/api.js',
                'public_html/assets/draft.js',
                'public_html/assets/workshop.js',
                'public_html/assets/navigation.js',
                'public_html/assets/base.css',
                'public_html/assets/components.css',
                'public_html/assets/workshop.css',
                'src/Translation/catalog_es.php',
            ];
            foreach ($requiredFiles as $required) {
                if (!isset($contents[$required])) {
                    throw new \RuntimeException('Incomplete application package.');
                }
            }
            if (trim($contents['src/VERSION']) !== $version) {
                throw new \RuntimeException('Package version mismatch.');
            }
            return $contents;
        } finally {
            $zip->close();
        }
    }

    /** Install only a newer selected package; preserve backups and recover all replacements on failure. */
    public function install(string $archive, string $version, ?array $expected = null): string
    {
        if (!self::validVersion($version) || !version_compare($version, trim(file_get_contents($this->root . '/src/VERSION')), '>')) {
            throw new \RuntimeException('Automatic downgrades are disabled.');
        }
        $contents = $this->inspect($archive, $version, $expected);
        $lock = fopen($this->root . '/config/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot acquire update lock.');
        }
        try {
            $this->recover();
            // Another request may have installed a newer version while this request waited for the lock.
            if (!version_compare($version, trim(file_get_contents($this->root . '/src/VERSION')), '>')) {
                throw new \RuntimeException('Automatic downgrades are disabled.');
            }
            $backup = $this->root . '/backups/update-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
            if (!mkdir($backup, 0700, true)) {
                throw new \RuntimeException('Cannot create private backup.');
            }
            $journal = ['backup' => basename($backup), 'files' => []];
            foreach ($contents as $path => $bytes) {
                $target = $this->target($path);
                $exists = is_file($target);
                if ($exists) {
                    $saved = $backup . '/' . $path;
                    if (!is_dir(dirname($saved)) && !mkdir(dirname($saved), 0700, true)) {
                        throw new \RuntimeException('Cannot prepare backup.');
                    }
                    if (!copy($target, $saved)) {
                        throw new \RuntimeException('Cannot backup application file.');
                    }
                }
                $journal['files'][$path] = $exists;
            }
            JsonFile::write($this->root . '/config/update-journal.json', $journal);
            try {
                foreach ($contents as $path => $bytes) {
                    $this->replace($this->target($path), $bytes);
                }
                if (!unlink($this->root . '/config/update-journal.json')) {
                    throw new \RuntimeException('Cannot complete update journal.');
                }
            } catch (\Throwable $error) {
                $this->recover();
                throw $error;
            }
            return basename($backup);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Recover an interrupted installation under the caller's exclusive update lock. */
    public function recover(): void
    {
        $path = $this->root . '/config/update-journal.json';
        if (!is_file($path)) {
            return;
        }
        $journal = JsonFile::read($path);
        if (!preg_match('/^update-[a-zA-Z0-9-]+$/D', $journal['backup'] ?? '') || !is_array($journal['files'] ?? null)) {
            throw new \RuntimeException('Invalid recovery journal.');
        }
        foreach ($journal['files'] as $name => $existed) {
            if (!is_bool($existed)) {
                throw new \RuntimeException('Invalid recovery file state.');
            }
            $target = $this->target($name);
            if ($existed === true) {
                $backup = $this->root . '/backups/' . $journal['backup'] . '/' . $name;
                if (!is_file($backup)) {
                    throw new \RuntimeException('Recovery backup is missing.');
                }
                $this->replace($target, file_get_contents($backup));
            } elseif ($existed === false && is_file($target) && !unlink($target)) {
                throw new \RuntimeException('Cannot remove interrupted file.');
            }
        }
        if (!unlink($path)) {
            throw new \RuntimeException('Cannot clear recovery journal.');
        }
    }

    /** Resolve only validated application paths and reject symlinked parents. */
    private function target(string $path): string
    {
        if (!self::allowed($path)) {
            throw new \RuntimeException('Disallowed update target.');
        }
        if (str_starts_with($path, 'public_html/')) { $path = $this->public . substr($path, 11); }
        $current = $this->root;
        foreach (explode('/', $path) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) {
                throw new \RuntimeException('Symlink in update target.');
            }
        }
        return $current;
    }

    /** Replace one file atomically within its target directory. */
    private function replace(string $target, string $bytes): void
    {
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
            throw new \RuntimeException('Cannot prepare application directory.');
        }
        $temp = tempnam(dirname($target), '.update-');
        if (!$temp) {
            throw new \RuntimeException('Cannot prepare replacement.');
        }
        try {
            if (file_put_contents($temp, $bytes) !== strlen($bytes) || !chmod($temp, 0644) || !rename($temp, $target)) {
                throw new \RuntimeException('Cannot replace application file.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
