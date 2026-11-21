<?php
declare(strict_types=1);

namespace App\Http;

use App\Model\ValidationError;

/** Normalizes the request envelope without interpreting business data. */
final class Request
{
    /** Read a scalar route name; arrays in query parameters never select handlers. */
    public static function route(string $key, string $default): string
    {
        $value = $_GET[$key] ?? $default;
        if (!is_string($value)) {
            throw new ValidationError('Ruta inválida.');
        }
        return $value;
    }

    /** Read bounded JSON only after method, CSRF and content-type checks at the controller. */
    public static function json(): array
    {
        if (!preg_match('~^application/json(?:\s*;|\s*$)~i', $_SERVER['CONTENT_TYPE'] ?? '')) {
            throw new ValidationError('Se necesita contenido application/json.');
        }
        $raw = file_get_contents('php://input', false, null, 0, 262145);
        if (!is_string($raw) || strlen($raw) > 262144) {
            throw new ValidationError('El JSON supera 256 KiB.');
        }
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) {
            throw new ValidationError('Se necesita un objeto JSON.');
        }
        return $data;
    }
}
