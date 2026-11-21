<?php
declare(strict_types=1);

namespace App\Model;

/** Shared scalar validation; no coercion of imported numbers or unknown enumerations. */
final class Fields
{
    /** Validate a UTF-8 text field with a byte limit matching existing saved formats. */
    public static function text(mixed $value, string $label, int $limit, bool $empty = false): string
    {
        if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0") || strlen($value) > $limit || (!$empty && trim($value) === '')) {
            throw new ValidationError($label . ': texto inválido.');
        }
        return trim($value);
    }

    /** Accept only explicitly supported identifiers, preserving unknown only where allowed. */
    public static function choice(mixed $value, array $allowed, string $label): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new ValidationError($label . ': opción inválida.');
        }
        return $value;
    }
}
