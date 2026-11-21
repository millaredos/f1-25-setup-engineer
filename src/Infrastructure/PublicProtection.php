<?php
declare(strict_types=1);

namespace App\Infrastructure;

/** Optional Apache protection manages only its marked block after an explicit form action. */
final class PublicProtection
{
    private string $directory;
    private const START = '# BEGIN SETUP ENGINEER';
    private const END = '# END SETUP ENGINEER';

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    /** Show the current file identity so stale forms cannot overwrite another edit. */
    public function inspect(): array
    {
        $path = $this->directory . '/.htaccess';
        if (is_link($path)) { throw new \RuntimeException('Linked protection files are not supported.'); }
        $text = is_file($path) ? file_get_contents($path) : '';
        if (!is_string($text)) { throw new \RuntimeException('Cannot read Apache protection.'); }
        return ['hash' => hash('sha256', $text), 'managed' => str_contains($text, self::START), 'text' => $text];
    }

    /** Preserve custom directives and reject concurrent edits before replacing the complete file. */
    public function apply(string $expected): void
    {
        $lock = fopen(dirname($this->directory) . '/config/protection.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot acquire protection lock.');
        }
        try {
            $this->replaceBlock($expected);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Recheck the submitted identity inside the lock shared by application protection writers. */
    private function replaceBlock(string $expected): void
    {
        $state = $this->inspect();
        if (!hash_equals($state['hash'], $expected)) { throw new \RuntimeException('La protección cambió. Recarga antes de continuar.'); }
        $text = $state['text'];
        if ($state['managed']) {
            if (substr_count($text, self::START) !== 1 || substr_count($text, self::END) !== 1) { throw new \RuntimeException('Invalid managed protection block.'); }
            $start = strpos($text, self::START); $end = strpos($text, self::END);
            if ($end < $start) { throw new \RuntimeException('Invalid protection markers.'); }
            $text = substr($text, 0, $start) . substr($text, $end + strlen(self::END));
        }
        $block = self::START . "\nOptions -Indexes\n<FilesMatch \"(?i)(^\\.|\\.(json|sql|zip|log|bak|ini|env|po|phar)$)\">\n    Require all denied\n</FilesMatch>\n" . self::END . "\n";
        $temp = tempnam($this->directory, '.protection-');
        if (!$temp) { throw new \RuntimeException('Cannot prepare protection file.'); }
        try {
            if (file_put_contents($temp, rtrim($text) . "\n" . $block) === false || !chmod($temp, 0644) || !rename($temp, $this->directory . '/.htaccess')) { throw new \RuntimeException('Cannot write Apache protection.'); }
        } finally { if (is_file($temp)) { unlink($temp); } }
    }
}
