<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Fields;

/** Coordinates explicit update forms independently from ordinary page rendering. */
final class UpdatesController
{
    private string $root;
    private string $public;

    /** Receive the private application root and the configured public directory. */
    public function __construct(string $root, string $public)
    {
        $this->root = $root;
        $this->public = $public;
    }

    /** Process an already CSRF-verified POST and return its message and reviewed candidate. */
    public function submit(string $channel): array
    {
        $message = '';
        $candidate = null;
        if (($_POST['action'] ?? '') === 'protect') {
            if (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') === false) { throw new \App\Model\ValidationError('Apache no detectado.'); }
            (new \App\Infrastructure\PublicProtection($this->root . '/' . $this->public))->apply(Fields::text($_POST['hash'] ?? '', 'Hash', 64));
            $message = 'Protección guardada. Comprueba que Apache aplica las reglas.';
            return [$message, $candidate];
        }
        \App\Infrastructure\RequestLock::release();
        session_write_close();
        $discovery = new \App\Application\UpdateDiscovery($this->root, new \App\Infrastructure\HttpClient());
        if (($_POST['action'] ?? '') === 'check') {
            $status = (new \App\Application\UpdateStatus($this->root, $discovery))->check($channel, true);
            if (isset($status['error'])) { throw new \RuntimeException($status['error']); }
            $candidate = $status['candidate'];
            $message = $candidate ? 'Hay una actualización publicada.' : 'No hay una versión posterior en este canal.';
        } elseif (($_POST['action'] ?? '') === 'install') {
            $identity = Fields::text($_POST['identity'] ?? '', 'Identidad', 64);
            $download = $discovery->download($channel, $identity);
            $temp = tempnam($this->root . '/config', '.download-');
            if (!$temp) { throw new \RuntimeException('Cannot stage download.'); }
            try {
                if (file_put_contents($temp, $download['bytes']) !== strlen($download['bytes'])) { throw new \RuntimeException('Cannot save download.'); }
                $backup = (new \App\Application\UpdateInstaller($this->root, $this->public))->install($temp, $download['candidate']['version'], $download['candidate']);
                $message = 'Actualización instalada. Copia anterior: ' . $backup;
            } finally {
                if (is_file($temp)) { unlink($temp); }
            }
        } else {
            throw new \App\Model\ValidationError('Acción de actualización desconocida.');
        }
        return [$message, $candidate];
    }
}
