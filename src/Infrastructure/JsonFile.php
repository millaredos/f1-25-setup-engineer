<?php
declare(strict_types=1);

namespace App\Infrastructure;

/** Private JSON state uses locked reads and atomic replacement to avoid partial files. */
final class JsonFile
{
    /** Read state; missing files use an explicit caller-supplied initial value. */
    public static function read(string $path, array $initial = []): array
    {
        if (!is_file($path)) {
            return $initial;
        }
        $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid private state.');
        }
        return $data;
    }

    /** Replace a complete state file in its own directory; cleanup temporary files on errors. */
    public static function write(string $path, array $data): void
    {
        $temp = tempnam(dirname($path), '.state-');
        if ($temp === false) {
            throw new \RuntimeException('Cannot prepare state file.');
        }
        try {
            chmod($temp, 0600);
            $bytes = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temp, $path)) {
                throw new \RuntimeException('Cannot replace state file.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
