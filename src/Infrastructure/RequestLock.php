<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\UpdateInstaller;

/** Requests share a read lock; only recovery and package replacement take exclusive ownership. */
final class RequestLock
{
    /** @var resource|null */
    private static $handle = null;

    /** Recover before loading controllers that may have been replaced in an interrupted update. */
    public static function acquire(string $root, string $public = 'public_html'): void
    {
        self::$handle = fopen($root . '/config/update.lock', 'c');
        if (!self::$handle || !flock(self::$handle, LOCK_SH)) {
            throw new \RuntimeException('Cannot lock application.');
        }
        if (is_file($root . '/config/update-journal.json')) {
            flock(self::$handle, LOCK_UN);
            if (!flock(self::$handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire recovery lock.');
            }
            (new UpdateInstaller($root, $public))->recover();
            flock(self::$handle, LOCK_SH);
        }
    }

    /** Remote update calls and exclusive replacement must not retain the request read lock. */
    public static function release(): void
    {
        if (is_resource(self::$handle)) {
            flock(self::$handle, LOCK_UN);
            fclose(self::$handle);
        }
        self::$handle = null;
    }
}
