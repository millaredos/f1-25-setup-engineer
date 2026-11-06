<?php
declare(strict_types=1);

/** Generated catalogs are local release assets; never load a path supplied by a request. */
final class Translation
{
    public const LANGUAGES = ['es' => 'Español'];
    private array $messages = [];
    private array $plurals = [];
    private string $language;

    public function __construct(string $language = 'es', ?string $directory = null)
    {
        $this->language = isset(self::LANGUAGES[$language]) ? $language : 'es';
        $directory ??= __DIR__;
        foreach (array_unique(['es', $this->language]) as $code) {
            try {
                $file = $directory . '/locale_' . $code . '.php';
                if (!is_file($file)) { throw new RuntimeException('Missing translation catalog.'); }
                $catalog = require $file;
                if (!is_array($catalog) || !is_array($catalog['messages'] ?? null) || !is_array($catalog['plurals'] ?? null)) {
                    throw new RuntimeException('Invalid translation catalog.');
                }
                foreach ($catalog['messages'] as $key => $value) {
                    if (!is_string($key) || !is_string($value)) { throw new RuntimeException('Invalid translation entry.'); }
                }
                foreach ($catalog['plurals'] as $key => $forms) {
                    if (!is_string($key) || !is_array($forms) || count($forms) !== 2 || !is_string($forms[0] ?? null) || !is_string($forms[1] ?? null)) {
                        throw new RuntimeException('Invalid plural entry.');
                    }
                }
                $this->messages = array_replace($this->messages, $catalog['messages']);
                $this->plurals = array_replace($this->plurals, $catalog['plurals']);
            } catch (Throwable $error) {
                error_log('F125 translations: ' . $error->getMessage());
            }
        }
    }

    public function language(): string { return $this->language; }
    public function catalog(): array { return ['messages' => $this->messages, 'plurals' => $this->plurals]; }
    public function text(string $message, array $values = []): string
    {
        $result = $this->messages[$message] ?? $message;
        $replace = [];
        foreach ($values as $key => $value) { $replace['{' . $key . '}'] = (string) $value; }
        return strtr($result, $replace);
    }

    public function plural(string $one, string $many, int $count, array $values = []): string
    {
        $forms = $this->plurals[$one] ?? [$one, $many];
        $replace = ['{count}' => (string) $count];
        foreach ($values as $key => $value) { $replace['{' . $key . '}'] = (string) $value; }
        return strtr($forms[$count === 1 ? 0 : 1], $replace);
    }
}

function appTranslation(): Translation
{
    static $translation;
    return $translation ??= new Translation(is_string($_COOKIE['f125_language'] ?? null) ? $_COOKIE['f125_language'] : 'es');
}

function t(string $message, array $values = []): string { return appTranslation()->text($message, $values); }
function tn(string $one, string $many, int $count, array $values = []): string { return appTranslation()->plural($one, $many, $count, $values); }
function h(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
