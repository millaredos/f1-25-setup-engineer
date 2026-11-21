<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\JsonFile;

/** Shared bounded discovery cache avoids remote polling on every screen or open browser tab. */
final class UpdateStatus
{
    private string $root;
    private UpdateDiscovery $discovery;

    public function __construct(string $root, UpdateDiscovery $discovery)
    {
        $this->root = $root;
        $this->discovery = $discovery;
    }

    /** Throttle manual checks and preserve a previous notification when the service is temporarily unavailable. */
    public function check(string $channel, bool $manual = false): array
    {
        if (!in_array($channel, ['alpha', 'release'], true)) { throw new \RuntimeException('Invalid channel.'); }
        $path = $this->root . '/config/update-cache-' . $channel . '.json';
        $lock = fopen($path . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) { throw new \RuntimeException('Cannot lock update cache.'); }
        try {
            $cache = JsonFile::read($path, ['checkedAt' => 0, 'candidate' => null]);
            $ttl = $manual ? 60 : ($channel === 'alpha' ? 300 : 21600);
            if (time() - (int) $cache['checkedAt'] < $ttl) { return $cache; }
            try {
                $cache = ['checkedAt' => time(), 'candidate' => $this->discovery->check($channel)];
            } catch (\Throwable $error) {
                error_log('Update discovery: ' . $error->getMessage());
                $cache['checkedAt'] = time();
                $cache['error'] = 'No se pudo consultar el servicio de versiones. Se conserva la última información disponible.';
            }
            JsonFile::write($path, $cache);
            return $cache;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
