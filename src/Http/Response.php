<?php
declare(strict_types=1);

namespace App\Http;

/** Response serialization and security headers shared by the web adapters. */
final class Response
{
    /** Apply the browser policy before templates or error output. */
    public static function headers(): void
    {
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    }

    /** Emit JSON and an explicit status; callers return rather than terminating inside the model. */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Redirect within the installation after a successful form action. */
    public static function redirect(string $url): void
    {
        header('Location: ' . $url, true, ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 303 : 302);
    }
}
