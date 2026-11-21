<?php
declare(strict_types=1);

namespace App\Infrastructure;

/** Bounded HTTPS transport from fixed services; their redirects remain restricted to verified HTTPS. */
final class HttpClient
{
    private ?\Closure $transport;

    /** Tests may supply deterministic HTTPS responses without sending network requests. */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport === null ? null : \Closure::fromCallable($transport);
    }

    /** Fetch a trusted service URL and enforce byte/time limits while downloading. */
    public function get(string $url, int $limit = 20971520, bool $binary = false): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!str_starts_with($url, 'https://') || !in_array($host, ['api.github.com', 'git.millaredos.com'], true)) {
            throw new \RuntimeException('Untrusted update source.');
        }
        if ($this->transport !== null) {
            $body = ($this->transport)($url, $binary);
            if (!is_string($body) || strlen($body) > $limit) { throw new \RuntimeException('Invalid test transport response.'); }
            return $body;
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is required.');
        }
        $curl = curl_init($url);
        $body = '';
        curl_setopt_array($curl, [
            CURLOPT_USERAGENT => 'SetupEngineer-MVC',
            CURLOPT_HTTPHEADER => ['Accept: ' . ($binary ? 'application/octet-stream' : 'application/vnd.github+json')],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $bytes) use (&$body, $limit): int {
                if (strlen($body) + strlen($bytes) > $limit) {
                    return 0;
                }
                $body .= $bytes;
                return strlen($bytes);
            },
        ]);
        try {
            if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
                throw new \RuntimeException('HTTPS request failed.');
            }
            return $body;
        } finally {
            curl_close($curl);
        }
    }
}
