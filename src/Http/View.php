<?php
declare(strict_types=1);

namespace App\Http;

/** Render only explicit private templates and escape at the output boundary. */
final class View
{
    /** Render a view with shared layout data; the name never comes directly from a request. */
    public static function render(string $template, array $data = []): void
    {
        if (!preg_match('/^[a-z-]+$/D', $template)) {
            throw new \LogicException('Invalid template name.');
        }
        $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $translator = new \App\Infrastructure\Translator(dirname(__DIR__) . '/Translation');
        $translate = static fn (string $text): string => $translator->text($text);
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/View/layout.php';
    }
}
