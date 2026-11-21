<?php

declare(strict_types=1);

/** Atomic private installation state outside HTTP/Git; database outages cannot reopen the installer. */
final class InstallationState
{
    private string $path;

    /**
     * Bind state operations to the private installation file supplied by the bootstrap.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Treat missing state as pending; malformed state is an error, never permission to reinstall.
     */
    public function isInstalled(): bool
    {
        if (!file_exists($this->path)) {
            return false;
        }
        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el estado de instalación.');
        }
        $data = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_bool($data['installed'] ?? null)) {
            throw new RuntimeException('El estado de instalación no es válido.');
        }
        return $data['installed'];
    }

    /**
     * Report whether PHP can create the private state file in its parent directory.
     */
    public function canWrite(): bool
    {
        return is_writable(dirname($this->path));
    }

    /**
     * Atomically mark installation complete only after the caller has verified the schema.
     */
    public function markInstalled(): void
    {
        if (!$this->canWrite()) {
            throw new RuntimeException('PHP necesita permiso de escritura en config/ para guardar el estado.');
        }
        $temporary = tempnam(dirname($this->path), '.installation-');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo preparar el estado de instalación.');
        }
        try {
            $json = json_encode(['installed' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
            if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $this->path)) {
                throw new RuntimeException('No se pudo guardar el estado de instalación.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Serialize installation mutations and release the lock even when the operation throws.
     * @param callable(): void $operation
     */
    public function withLock(callable $operation): void
    {
        $lock = fopen($this->path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('No se pudo bloquear la instalación.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Hay otra instalación en curso.');
            }
            $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
