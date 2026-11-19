<?php
declare(strict_types=1);

/** Replaces application files under an exclusive lock, with a durable recovery journal. */
final class Updater
{
    private string $root;
    private string $public;
    private static $requestLock = null;

    public function __construct(string $root, string $public = 'public_html')
    {
        $resolved = realpath($root);
        if ($resolved === false || !preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*$/D', $public) || in_array($public, ['src', 'config', 'data', 'backups'], true)) {
            throw new RuntimeException('Invalid application directory.');
        }
        $this->root = str_replace('\\', '/', $resolved);
        $this->public = $public;
    }

    public static function allowed(string $path): bool
    {
        return preg_match('~^(?:public_html/(?:[A-Za-z0-9_-]+\.php|assets/[A-Za-z0-9_-]+\.(?:js|css))|src/(?:[A-Za-z0-9_-]+\.php|VERSION)|data/(?:parameters|symptoms)\.json)$~D', $path) === 1;
    }

    public static function validVersion(string $version): bool
    {
        return preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-alpha\.(?:0|[1-9]\d*))?$/D', $version) === 1;
    }

    public static function atomicJson(string $path, array $data): void
    {
        $temp = tempnam(dirname($path), '.update-');
        if ($temp === false) { throw new RuntimeException('Cannot prepare private update state.'); }
        try {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temp, $json) !== strlen($json) || !chmod($temp, 0600) || !rename($temp, $path)) {
                throw new RuntimeException('Cannot save private update state.');
            }
        } finally { if (is_file($temp)) { unlink($temp); } }
    }

    private function target(string $path): string
    {
        if (!self::allowed($path)) { throw new RuntimeException('Disallowed application path.'); }
        $mapped = str_starts_with($path, 'public_html/') ? $this->public . substr($path, 11) : $path;
        $target = $this->root . '/' . $mapped;
        $cursor = $target;
        while ($cursor !== $this->root) {
            if (is_link($cursor)) { throw new RuntimeException('Symlinks are not allowed in update paths.'); }
            $cursor = str_replace('\\', '/', dirname($cursor));
        }
        return $target;
    }

    private function journal(): string { return $this->root . '/config/update-journal.json'; }

    public static function releaseRequestLock(): void
    {
        if (is_resource(self::$requestLock)) { flock(self::$requestLock, LOCK_UN); fclose(self::$requestLock); }
        self::$requestLock = null;
    }

    /** Called before loading domain code so an interrupted update cannot run mixed versions. */
    public static function guard(string $root): void
    {
        $lockPath = $root . '/config/update.lock';
        if (!is_file($lockPath) && !is_writable($root . '/config')) { return; }
        $lock = fopen($lockPath, 'c');
        if (!$lock || !flock($lock, LOCK_SH | LOCK_NB)) { if ($lock) { fclose($lock); } throw new RuntimeException('An update is in progress. Try again shortly.'); }
        if (is_file($root . '/config/update-journal.json')) {
            flock($lock, LOCK_UN);
            if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Update recovery is in progress.'); }
            try {
                $journal = json_decode(file_get_contents($root . '/config/update-journal.json'), true, 64, JSON_THROW_ON_ERROR);
                (new self($root, $journal['public'] ?? ''))->recover();
            } catch (Throwable $error) {
                flock($lock, LOCK_UN); fclose($lock);
                throw new RuntimeException('Update recovery needs attention. Restore the private backup before retrying.', 0, $error);
            }
            flock($lock, LOCK_SH);
        }
        self::$requestLock = $lock;
        register_shutdown_function([self::class, 'releaseRequestLock']);
    }

    /** Caller must hold the update lock. Recovery is idempotent after another interruption. */
    public function recover(): void
    {
        if (!is_file($this->journal())) { return; }
        $journal = json_decode(file_get_contents($this->journal()), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($journal) || !preg_match('/^update-\d{8}-\d{6}-[a-f0-9]{8}$/D', $journal['backup'] ?? '') || !is_array($journal['files'] ?? null)) {
            throw new RuntimeException('Invalid update recovery journal.');
        }
        $backup = $this->root . '/backups/' . $journal['backup'];
        if (is_link($this->root . '/backups') || is_link($backup)) { throw new RuntimeException('Invalid backup path.'); }
        if (($journal['phase'] ?? '') !== 'committed') {
            // Check every original before touching any target.
            foreach ($journal['files'] as $path => $entry) {
                $this->target($path);
                if (!is_array($entry) || !is_bool($entry['existed'] ?? null)) { throw new RuntimeException('Invalid recovery file record.'); }
                if ($entry['existed'] && (!is_file($backup . '/' . $path) || hash_file('sha256', $backup . '/' . $path) !== ($entry['oldHash'] ?? null))) {
                    throw new RuntimeException('The update backup is incomplete.');
                }
            }
            foreach (array_reverse($journal['files'], true) as $path => $entry) {
                $target = $this->target($path);
                if ($entry['existed']) {
                    $restore = tempnam(dirname($target), '.update-restore-');
                    if ($restore === false) { throw new RuntimeException('Cannot stage update recovery.'); }
                    try {
                        if (!copy($backup . '/' . $path, $restore) || !chmod($restore, $entry['mode'] ?? 0644) || !rename($restore, $target)) { throw new RuntimeException('Cannot restore an update backup.'); }
                    } finally { if (is_file($restore)) { unlink($restore); } }
                } elseif (is_file($target) && !unlink($target)) { throw new RuntimeException('Cannot remove an interrupted update file.'); }
                if (function_exists('opcache_invalidate')) { opcache_invalidate($target, true); }
            }
        }
        foreach ($journal['files'] as $path => $entry) {
            $target = $this->target($path);
            $temp = $entry['temp'] ?? '';
            if (is_string($temp) && str_starts_with($temp, '.update-') && basename($temp) === $temp && is_file(dirname($target) . '/' . $temp)) {
                unlink(dirname($target) . '/' . $temp);
            }
        }
        if (!unlink($this->journal())) { throw new RuntimeException('Cannot complete update recovery.'); }
    }

    public function apply(string $package, ?array $expected = null): string
    {
        if (!class_exists('ZipArchive')) { throw new RuntimeException('Enable the PHP ZIP extension to update.'); }
        self::releaseRequestLock();
        $lock = fopen($this->root . '/config/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) { fclose($lock); } throw new RuntimeException('The application is busy. Retry the update shortly.'); }
        $zip = new ZipArchive(); $opened = false; $pending = [];
        try {
            $this->recover();
            if ($zip->open($package) !== true) { throw new RuntimeException('Invalid ZIP package.'); }
            $opened = true;
            if ($zip->numFiles < 2 || $zip->numFiles > 150) { throw new RuntimeException('Invalid package file count.'); }
            $entries = []; $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if (!$entry || isset($entries[$entry['name']]) || $entry['size'] > 2097152) { throw new RuntimeException('Duplicate or oversized package file.'); }
                $total += $entry['size'];
                if ($total > 16777216 || ($entry['name'] !== 'manifest.json' && !self::allowed($entry['name']))) { throw new RuntimeException('Package contains disallowed content.'); }
                $entries[$entry['name']] = true;
            }
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 64, JSON_THROW_ON_ERROR);
            if (!in_array($manifest['format'] ?? '', ['f125-update-v1', 'f125-update-v2'], true) || !is_string($manifest['version'] ?? null) || !self::validVersion($manifest['version']) || !is_array($manifest['files'] ?? null)) { throw new RuntimeException('Invalid update manifest.'); }
            if ($expected !== null) {
                if (($manifest['format'] ?? '') !== 'f125-update-v2' || $manifest['version'] !== $expected['version'] || ($manifest['build']['channel'] ?? '') !== $expected['channel'] || ($manifest['build']['commit'] ?? '') !== $expected['commit'] || ($manifest['build']['pr'] ?? null) !== $expected['pr'] || ($manifest['build']['branch'] ?? '') !== $expected['branch']) { throw new RuntimeException('Package identity does not match the selected GitHub build.'); }
                if (!is_int($manifest['requires']['php'] ?? null) || $manifest['requires']['php'] < 80000 || $manifest['requires']['php'] > PHP_VERSION_ID || ($manifest['requires']['schema'] ?? null) !== 1) { throw new RuntimeException('This package needs a different PHP version or database schema.'); }
            }
            $files = $manifest['files'];
            if (count($files) + 1 !== count($entries)) { throw new RuntimeException('Package content does not match the manifest.'); }
            foreach (['src/VERSION', 'src/Updater.php', 'src/Updates.php', 'src/bootstrap.php', 'src/Domain.php', 'src/Store.php', 'src/Migrations.php', 'src/migration_catalog.php', 'src/circuit_profiles.php', 'src/InstallationState.php', 'src/ApacheProtection.php', 'src/Translation.php', 'src/Header.php', 'src/locale_es.php', 'public_html/index.php', 'public_html/api.php', 'public_html/install.php', 'public_html/update.php', 'public_html/options.php', 'public_html/database.php', 'public_html/i18n.php', 'data/parameters.json', 'data/symptoms.json', 'public_html/assets/app.js', 'public_html/assets/app.css', 'public_html/assets/navigation.js'] as $required) {
                if (!isset($files[$required])) { throw new RuntimeException('The package must contain the complete application.'); }
            }
            $current = trim(file_get_contents($this->root . '/src/VERSION'));
            if (!version_compare($manifest['version'], $current, '>')) { throw new RuntimeException('The available build is not newer. Automatic downgrades are disabled.'); }
            $contents = [];
            foreach ($files as $path => $hash) {
                if (!is_string($path) || !isset($entries[$path]) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) { throw new RuntimeException('Invalid package file record.'); }
                $target = $this->target($path);
                if (!is_dir(dirname($target)) || !is_writable(dirname($target)) || (file_exists($target) && (!is_file($target) || !is_writable($target)))) { throw new RuntimeException('Check application file write permissions.'); }
                $content = $zip->getFromName($path);
                if (!is_string($content) || !hash_equals($hash, hash('sha256', $content))) { throw new RuntimeException('The package is damaged.'); }
                $contents[$path] = $content;
            }
            if (trim($contents['src/VERSION']) !== $manifest['version']) { throw new RuntimeException('Package versions do not agree.'); }
            $backupRoot = $this->root . '/backups';
            if (is_link($backupRoot) || (!is_dir($backupRoot) && !mkdir($backupRoot, 0700))) { throw new RuntimeException('Cannot prepare the private backup directory.'); }
            $name = 'update-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
            $backup = $backupRoot . '/' . $name;
            if (!mkdir($backup, 0700)) { throw new RuntimeException('Cannot create the update backup.'); }
            $plan = [];
            foreach ($contents as $path => $content) {
                $target = $this->target($path); $existed = is_file($target);
                if ($existed) {
                    $copy = $backup . '/' . $path;
                    if ((!is_dir(dirname($copy)) && !mkdir(dirname($copy), 0700, true)) || !copy($target, $copy)) { throw new RuntimeException('Cannot back up application files.'); }
                }
                $temp = tempnam(dirname($target), '.update-');
                if ($temp === false) { throw new RuntimeException('Cannot stage an application file.'); }
                $pending[$path] = $temp;
                if (file_put_contents($temp, $content) !== strlen($content) || !chmod($temp, $existed ? (fileperms($target) & 0777) : 0644)) { throw new RuntimeException('Cannot stage an application file.'); }
                $plan[$path] = ['existed' => $existed, 'oldHash' => $existed ? hash_file('sha256', $target) : null, 'mode' => $existed ? (fileperms($target) & 0777) : 0644, 'temp' => basename($temp)];
            }
            $journal = ['phase' => 'replacing', 'backup' => $name, 'public' => $this->public, 'files' => $plan];
            self::atomicJson($this->journal(), $journal);
            $order = array_values(array_diff(array_keys($contents), ['src/VERSION'])); $order[] = 'src/VERSION';
            foreach ($order as $path) {
                $target = $this->target($path);
                if (!rename($pending[$path], $target) || hash_file('sha256', $target) !== $files[$path]) { throw new RuntimeException('Application file replacement failed.'); }
                if (function_exists('opcache_invalidate')) { opcache_invalidate($target, true); }
            }
            $journal['phase'] = 'committed';
            self::atomicJson($this->journal(), $journal);
            $this->recover();
            return $name;
        } catch (Throwable $error) {
            if (is_file($this->journal())) {
                try { $this->recover(); }
                catch (Throwable $recovery) { throw new RuntimeException('Update recovery is incomplete. Keep the backup and retry recovery.', 0, $recovery); }
            }
            throw $error;
        } finally {
            // A surviving journal owns its temporary files until recovery succeeds.
            if (!is_file($this->journal())) { foreach ($pending as $temp) { if (is_file($temp)) { unlink($temp); } } }
            if ($opened) { $zip->close(); }
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}
