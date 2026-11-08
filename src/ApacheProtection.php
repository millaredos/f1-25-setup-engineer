<?php
declare(strict_types=1);

/** The web server's local configuration is not part of the update package. */
final class ApacheProtection
{
    private const BEGIN = '# BEGIN F125 SETUP ENGINEER';
    private const END = '# END F125 SETUP ENGINEER';
    private string $path;

    public function __construct(string $public) { $this->path = $public . '/.htaccess'; }

    public static function block(): string
    {
        return self::BEGIN . "\n"
            . "Options -Indexes\n"
            . "<FilesMatch \"(^\\.|\\.(bak|old|orig|save|swp|sql|log)$|~$)\">\n"
            . "    Require all denied\n"
            . "</FilesMatch>\n"
            . "ErrorDocument 404 \"No se ha encontrado la pagina solicitada.\"\n"
            . self::END;
    }

    public function inspect(): array
    {
        clearstatcache(true, $this->path);
        if (is_link($this->path)) { throw new RuntimeException('No se puede modificar un enlace simbólico.'); }
        if (!file_exists($this->path)) { return ['state' => 'missing', 'hash' => 'missing']; }
        if (!is_file($this->path) || !is_readable($this->path) || filesize($this->path) > 1048576) {
            throw new RuntimeException('No se puede leer el archivo .htaccess.');
        }
        $content = file_get_contents($this->path);
        if ($content === false) { throw new RuntimeException('No se puede leer el archivo .htaccess.'); }
        $start = strpos($content, self::BEGIN); $end = strpos($content, self::END);
        if (($start === false) !== ($end === false) || ($start !== false && ($end < $start || substr_count($content, self::BEGIN) !== 1 || substr_count($content, self::END) !== 1))) {
            throw new RuntimeException('El bloque de protección existente necesita una revisión manual.');
        }
        $current = $start === false ? '' : substr($content, $start, $end + strlen(self::END) - $start);
        return ['state' => $current === self::block() ? 'current' : ($start === false ? 'custom' : 'outdated'), 'hash' => hash('sha256', $content)];
    }

    /** Caller supplies the hash of the content presented for confirmation. */
    public function apply(string $expected): void
    {
        $lock = fopen(dirname($this->path) . '/../config/installation.json.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) { if ($lock) { fclose($lock); } throw new RuntimeException('No se pudo bloquear la operación.'); }
        try {
            $state = $this->inspect();
            if (!hash_equals($state['hash'], $expected)) { throw new RuntimeException('El archivo ha cambiado. Revísalo antes de continuar.'); }
            if ($state['state'] === 'current') { return; }
            if ($state['state'] === 'missing') {
                $file = @fopen($this->path, 'xb');
                if (!$file) { throw new RuntimeException('No se pudo crear .htaccess. Revisa los permisos.'); }
                $complete = false;
                try {
                    $content = self::block() . "\n";
                    if (fwrite($file, $content) !== strlen($content) || !fflush($file)) { throw new RuntimeException('No se pudo guardar .htaccess completo.'); }
                    $complete = true;
                } finally { fclose($file); if (!$complete) { @unlink($this->path); } }
                return;
            }
            $content = file_get_contents($this->path);
            if (!is_string($content) || !hash_equals($expected, hash('sha256', $content))) { throw new RuntimeException('El archivo ha cambiado. Revísalo antes de continuar.'); }
            if ($state['state'] === 'custom') { $new = $content . (str_ends_with($content, "\n") ? "\n" : "\n\n") . self::block() . "\n"; }
            else {
                $start = strpos($content, self::BEGIN); $end = strpos($content, self::END) + strlen(self::END);
                $new = substr($content, 0, $start) . self::block() . substr($content, $end);
            }
            $temp = tempnam(dirname($this->path), '.f125-htaccess-');
            if ($temp === false) { throw new RuntimeException('No se pudo preparar .htaccess.'); }
            try {
                if (file_put_contents($temp, $new) !== strlen($new) || !chmod($temp, fileperms($this->path) & 0777)) { throw new RuntimeException('No se pudo preparar .htaccess.'); }
                if (is_link($this->path) || !is_file($this->path) || !hash_equals($expected, hash_file('sha256', $this->path))) { throw new RuntimeException('El archivo ha cambiado. Revísalo antes de continuar.'); }
                if (!rename($temp, $this->path)) { throw new RuntimeException('No se pudo guardar .htaccess.'); }
            } finally { if (is_file($temp)) { unlink($temp); } }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
