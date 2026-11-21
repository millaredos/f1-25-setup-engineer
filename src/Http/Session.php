<?php
declare(strict_types=1);

namespace App\Http;

use App\Model\ValidationError;

/** One session policy for forms and JSON mutations; CSRF is not authentication. */
final class Session
{
    /** Open a browser-scoped session using the same cookie path in root and subfolder installs. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/.') . '/';
        session_name('f125_session');
        session_start(['use_strict_mode' => true, 'cookie_path' => $path, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    /** Verify an explicit token before any operation can persist or replace data. */
    public static function verify(mixed $token): void
    {
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            throw new ValidationError('Sesión del formulario inválida. Recarga la página.');
        }
    }
}
