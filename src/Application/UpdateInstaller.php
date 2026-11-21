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
                'src/Application/LegacyPackage.php',
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
        $embedded = LegacyPackage::embedded($archive);
        if ($embedded !== null) {
            $temp = tempnam($this->root . '/config', '.mvc-download-');
            if (!$temp) {
                throw new \RuntimeException('Cannot stage MVC payload.');
            }
            try {
                if (file_put_contents($temp, $embedded) !== strlen($embedded) || !chmod($temp, 0600)) {
                    throw new \RuntimeException('Cannot save MVC payload.');
                }
                if (LegacyPackage::embedded($temp) !== null) {
                    throw new \RuntimeException('Nested update containers are not supported.');
                }
                return $this->install($temp, $version, $expected);
            } finally {
                if (is_file($temp)) { unlink($temp); }
            }
        }
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
            return $this->replaceSet($contents);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Complete the POST-authorized 0.4 transport under its bootstrap's exclusive update lock.
     * The outer installer already stamped VERSION; only this explicit transition permits equality.
     * @param array{channel:string,commit:string,pr:int,branch:string} $expected
     */
    public function completeLegacyTransition(string $archive, string $version, array $expected): string
    {
        $this->recover();
        if (!is_file($this->root . '/src/MvcTransition.php') || !self::validVersion($version) || trim(file_get_contents($this->root . '/src/VERSION')) !== $version) {
            throw new \RuntimeException('Not a pending legacy transition.');
        }
        return $this->replaceSet($this->inspect($archive, $version, $expected));
    }

    /** Back up replacements and explicit retirements together before any application file changes. */
    private function replaceSet(array $contents): string
    {
        $removed = array_diff(LegacyPackage::retired(), array_keys($contents));
        $changes = $contents + array_fill_keys($removed, null);
        $backup = $this->root . '/backups/update-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        if (is_link($this->root . '/backups') || !mkdir($backup, 0700, true)) {
            throw new \RuntimeException('Cannot create private backup.');
        }
        $journal = ['backup' => basename($backup), 'files' => [], 'hashes' => [], 'phase' => 'replacing'];
        foreach ($changes as $path => $bytes) {
            $target = $this->target($path);
            if (file_exists($target) && !is_file($target)) {
                throw new \RuntimeException('Application target is not a regular file.');
            }
            $exists = is_file($target);
            if ($exists) {
                $saved = $backup . '/' . $path;
                if ((!is_dir(dirname($saved)) && !mkdir(dirname($saved), 0700, true)) || !copy($target, $saved)) {
                    throw new \RuntimeException('Cannot back up application file.');
                }
                $journal['hashes'][$path] = hash_file('sha256', $saved);
            }
            $journal['files'][$path] = $exists;
        }
        JsonFile::write($this->root . '/config/update-journal.json', $journal);
        try {
            // Keep the transition bootstrap available until all nested files and retirements are ready.
            $order = array_diff(array_keys($changes), ['src/MvcTransition.php', 'src/bootstrap.php', 'src/VERSION']);
            $order = array_merge($order, ['src/bootstrap.php', 'src/VERSION', 'src/MvcTransition.php']);
            foreach ($order as $path) {
                $target = $this->target($path);
                if ($changes[$path] === null) {
                    if (is_file($target) && !unlink($target)) {
                        throw new \RuntimeException('Cannot retire application file.');
                    }
                } else {
                    $this->replace($target, $changes[$path]);
                }
                if (function_exists('opcache_invalidate')) { opcache_invalidate($target, true); }
            }
            $journal['phase'] = 'committed';
            JsonFile::write($this->root . '/config/update-journal.json', $journal);
            $this->recover();
        } catch (\Throwable $error) {
            $this->recover();
            throw $error;
        }
        // Remove known empty application directories only; personal files prevent their removal.
        $retiredDirectory = $this->root . '/data/migrations';
        if (is_dir($retiredDirectory) && !is_link($retiredDirectory) && count(scandir($retiredDirectory)) === 2) {
            rmdir($retiredDirectory);
        }
        return basename($backup);
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
        if (isset($journal['public']) && $journal['public'] !== $this->public) {
            throw new \RuntimeException('Recovery public directory mismatch.');
        }
        // A crash during the outer 0.4 installation leaves its original journal shape.
        $temporaries = [];
        foreach ($journal['files'] as $name => $entry) {
            if (is_array($entry)) {
                if (!is_bool($entry['existed'] ?? null) || ($entry['existed'] && !preg_match('/^[a-f0-9]{64}$/D', $entry['oldHash'] ?? ''))) {
                    throw new \RuntimeException('Invalid legacy recovery entry.');
                }
                $journal['files'][$name] = $entry['existed'];
                if ($entry['existed']) { $journal['hashes'][$name] = $entry['oldHash']; }
                if (is_string($entry['temp'] ?? null) && str_starts_with($entry['temp'], '.update-') && basename($entry['temp']) === $entry['temp']) {
                    $temporaries[] = dirname($this->target($name)) . '/' . $entry['temp'];
                }
            }
        }
        $backupRoot = $this->root . '/backups/' . $journal['backup'];
        if (is_link($this->root . '/backups') || is_link($backupRoot)) {
            throw new \RuntimeException('Invalid backup path.');
        }
        // Validate the complete recovery set before restoring even its first file.
        foreach ($journal['files'] as $name => $existed) {
            if (!is_bool($existed)) {
                throw new \RuntimeException('Invalid recovery file state.');
            }
            $this->target($name);
            if ($existed && ($journal['phase'] ?? '') !== 'committed') {
                $backup = $backupRoot . '/' . $name;
                if (!is_file($backup) || (isset($journal['hashes'][$name]) && !hash_equals($journal['hashes'][$name], hash_file('sha256', $backup)))) {
                    throw new \RuntimeException('Recovery backup is missing.');
                }
            }
        }
        if (($journal['phase'] ?? '') !== 'committed') {
            foreach (array_reverse($journal['files'], true) as $name => $existed) {
                $target = $this->target($name);
                if ($existed) {
                    $this->replace($target, file_get_contents($backupRoot . '/' . $name));
                } elseif (is_file($target) && !unlink($target)) {
                    throw new \RuntimeException('Cannot remove interrupted file.');
                }
                if (function_exists('opcache_invalidate')) { opcache_invalidate($target, true); }
            }
        }
        if (!unlink($path)) {
            throw new \RuntimeException('Cannot clear recovery journal.');
        }
        foreach ($temporaries as $temp) {
            if (is_file($temp) && !unlink($temp)) { throw new \RuntimeException('Cannot remove legacy recovery staging.'); }
        }
    }

    /** Resolve only validated application paths and reject symlinked parents. */
    private function target(string $path): string
    {
        if (!self::allowed($path) && !in_array($path, LegacyPackage::retired(), true)) {
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
