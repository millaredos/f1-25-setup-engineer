<?php
declare(strict_types=1);

namespace App\Infrastructure;

/** Data-only translation lookup; missing messages preserve the source text. */
final class Translator
{
    private array $catalog;

    /** Only generated catalog paths selected by code are loaded. */
    public function __construct(string $directory, string $language = 'es')
    {
        if (!in_array($language, ['es'], true)) { throw new \InvalidArgumentException('Unsupported language.'); }
        $file = $directory . '/catalog_' . $language . '.php';
        $this->catalog = is_file($file) ? require $file : ['messages' => [], 'plurals' => []];
    }

    /** Interpolate named values before the caller escapes the destination context. */
    public function text(string $source, array $values = []): string
    {
        $message = $this->catalog['messages'][$source] ?? $source;
        $replace = [];
        foreach ($values as $name => $value) { $replace['{' . $name . '}'] = (string) $value; }
        return strtr($message, $replace);
    }
}
