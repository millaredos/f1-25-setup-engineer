<?php
declare(strict_types=1);

final class GitHubRetry extends RuntimeException
{
    public int $retryAt;
    public function __construct(int $retryAt) { parent::__construct('GitHub request limit reached. Update checks are paused until the retry time.'); $this->retryAt = $retryAt; }
}

final class UpdateSettings
{
    private string $path;
    public function __construct(string $root) { $this->path = $root . '/config/update-settings.json'; }
    public function channel(): string
    {
        if (!is_file($this->path)) { return 'release'; }
        $data = json_decode(file_get_contents($this->path), true, 16, JSON_THROW_ON_ERROR);
        if (!in_array($data['channel'] ?? null, ['release', 'alpha'], true)) { throw new RuntimeException('Invalid update channel settings.'); }
        return $data['channel'];
    }
    public function save(string $channel): void
    {
        if (!in_array($channel, ['release', 'alpha'], true)) { throw new RuntimeException('Choose Alpha or Release.'); }
        Updater::atomicJson($this->path, ['channel' => $channel]);
    }
}

/** Fixed GitHub origin; never forwards the token to release storage redirects. */
final class GitHubClient
{
    public const REPOSITORY = 'millaredos/f1-25-setup-engineer';
    public const VERSION_FEED = 'https://git.millaredos.com/f125/versions.txt';
    private string $token;
    private $transport;
    public function __construct(string $token = '', ?callable $transport = null) { $this->token = $token; $this->transport = $transport; }
    public function cacheKey(): string { return hash('sha256', 'version-feed-v1:' . self::REPOSITORY . ':' . self::VERSION_FEED . ':' . $this->token); }

    public function get(string $path, bool $binary = false): array
    {
        if (!str_starts_with($path, '/repos/' . self::REPOSITORY . '/')) { throw new RuntimeException('Invalid GitHub resource.'); }
        $url = 'https://api.github.com' . $path;
        for ($redirect = 0; $redirect < 5; $redirect++) {
            $host = parse_url($url, PHP_URL_HOST);
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['api.github.com', 'github.com', 'release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PORT) !== null) {
                throw new RuntimeException('GitHub returned an unsupported download location.');
            }
            $headers = ['Accept: ' . ($binary ? 'application/octet-stream' : 'application/vnd.github+json'), 'User-Agent: F125-Setup-Engineer', 'X-GitHub-Api-Version: 2022-11-28'];
            if ($host === 'api.github.com' && $this->token !== '') { $headers[] = 'Authorization: Bearer ' . $this->token; }
            $response = $this->transport !== null ? ($this->transport)($url, $headers, $binary ? 8388608 : 2097152) : $this->request($url, $headers, $binary ? 8388608 : 2097152);
            if (!is_array($response) || !is_int($response['status'] ?? null) || !is_string($response['body'] ?? null) || strlen($response['body']) > ($binary ? 8388608 : 2097152)) { throw new RuntimeException('Invalid GitHub response.'); }
            if (in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                $url = $response['headers']['location'] ?? '';
                if (!is_string($url)) { throw new RuntimeException('Invalid GitHub redirect.'); }
                continue;
            }
            if (in_array($response['status'], [403, 429], true)) {
                $headers = $response['headers'] ?? [];
                $remaining = (string) ($headers['x-ratelimit-remaining'] ?? '');
                $rateLimited = $response['status'] === 429 || $remaining === '0'
                    || isset($headers['retry-after']) || stripos($response['body'], 'rate limit') !== false;
                if (!$rateLimited) {
                    throw new RuntimeException('GitHub denied access (HTTP 403). Check the server token permissions if a token is configured.');
                }
                $retry = (string) ($headers['retry-after'] ?? '');
                $reset = (string) ($headers['x-ratelimit-reset'] ?? '');
                if (preg_match('/^\d+$/D', $retry)) {
                    $retryAt = time() + max(1, (int) $retry);
                } elseif ($remaining === '0' && preg_match('/^\d+$/D', $reset)) {
                    $retryAt = max(time() + 1, (int) $reset);
                } else {
                    $retryAt = time() + 60;
                }
                throw new GitHubRetry($retryAt);
            }
            if (!in_array($response['status'], [200, 404], true)) { throw new RuntimeException('GitHub is unavailable. The installed application is unchanged.'); }
            return $response;
        }
        throw new RuntimeException('Too many GitHub redirects.');
    }

    /** Version discovery sends no GitHub credential and never follows redirects. */
    public function versions(): array
    {
        $headers = ['Accept: text/plain', 'User-Agent: F125-Setup-Engineer'];
        $response = $this->transport !== null
            ? ($this->transport)(self::VERSION_FEED, $headers, 2048)
            : $this->request(self::VERSION_FEED, $headers, 2048);
        if (!is_array($response) || ($response['status'] ?? null) !== 200 || !is_string($response['body'] ?? null)) {
            throw new RuntimeException('The version service is unavailable. Try again later.');
        }
        return self::parseVersions($response['body']);
    }

    public static function parseVersions(string $body): array
    {
        if (strlen($body) > 2048) { throw new RuntimeException('Invalid version service response.'); }
        $lines = explode("\n", rtrim(str_replace("\r\n", "\n", $body), "\n"));
        $versions = [];
        if (count($lines) !== 2) { throw new RuntimeException('Invalid version service response.'); }
        foreach ($lines as $line) {
            if (!preg_match('/^(alpha|release)=(.*)$/D', $line, $match) || array_key_exists($match[1], $versions)) {
                throw new RuntimeException('Invalid version service channels.');
            }
            $version = $match[2];
            if ($version !== '' && (!Updater::validVersion($version) || str_contains($version, '-alpha.') !== ($match[1] === 'alpha'))) {
                throw new RuntimeException('Invalid published version.');
            }
            $versions[$match[1]] = $version === '' ? null : $version;
        }
        return $versions;
    }

    private function request(string $url, array $headers, int $limit): array
    {
        if (!function_exists('curl_init')) { throw new RuntimeException('Enable the PHP cURL extension to check updates.'); }
        $curl = curl_init($url); $body = ''; $received = [];
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$received): int {
                if (str_starts_with($line, 'HTTP/')) { $received = []; }
                elseif (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $received[strtolower(trim($name))] = trim($value); }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $limit): int {
                if (strlen($body) + strlen($chunk) > $limit) { return 0; }
                $body .= $chunk; return strlen($chunk);
            },
        ]);
        // PHP builds using OpenSSL on Windows may not ship a CA bundle.
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        try {
            if (curl_exec($curl) === false) { throw new RuntimeException('Cannot securely contact the update service. Check network access and PHP CA certificates.'); }
            return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => $received, 'body' => $body];
        } finally { unset($curl); }
    }

    public function json(string $path): ?array
    {
        $response = $this->get($path);
        if ($response['status'] === 404) { return null; }
        $data = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) { throw new RuntimeException('Invalid GitHub metadata.'); }
        return $data;
    }
}

final class GitHubUpdates
{
    private string $root;
    private GitHubClient $client;
    public function __construct(string $root, GitHubClient $client) { $this->root = $root; $this->client = $client; }
    private function path(string $suffix): string { return '/repos/' . GitHubClient::REPOSITORY . '/' . $suffix; }

    public function check(string $channel, bool $force = false): array
    {
        if (!in_array($channel, ['release', 'alpha'], true)) { throw new RuntimeException('Invalid update channel.'); }
        $path = $this->root . '/config/update-cache.json';
        $cache = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        if (!is_array($cache)) { $cache = []; }
        $key = $this->client->cacheKey() . ':' . $channel;
        $installed = is_file($this->root . '/src/VERSION') ? trim(file_get_contents($this->root . '/src/VERSION')) : appVersion();
        $previous = $cache[$key] ?? [];
        if (($previous['installed'] ?? null) !== $installed) { $previous = []; }
        if (($previous['retryAt'] ?? 0) > time()) { return $previous; }
        $ttl = isset($previous['error']) ? 600 : ($channel === 'alpha' ? 300 : 21600);
        if (!isset($previous['retryAt']) && isset($previous['checked']) && time() - $previous['checked'] < ($force ? 60 : $ttl)) { return $previous; }
        $lock = fopen($this->root . '/config/update-cache.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) { fclose($lock); } return $previous ?: ['checked' => 0, 'error' => 'An update check is already running.']; }
        try {
            // Another request may have refreshed the cache while this request started.
            $fresh = is_file($path) ? json_decode(file_get_contents($path), true) : [];
            if (is_array($fresh) && ($fresh[$key]['installed'] ?? null) === $installed && isset($fresh[$key]['checked']) && $fresh[$key]['checked'] > ($previous['checked'] ?? 0)) { return $fresh[$key]; }
            $result = ['checked' => time(), 'installed' => $installed, 'available' => null, 'candidate' => null];
            try {
                $version = $this->announced($channel);
                $result['available'] = $version;
                if ($version !== null && version_compare($version, $installed, '>')) {
                    $known = $previous['candidate'] ?? null;
                    $result['candidate'] = is_array($known) && ($known['version'] ?? null) === $version
                        ? $known : $this->releaseForVersion($version);
                }
            }
            catch (Throwable $e) {
                $result['error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Cannot read GitHub update metadata.';
                if ($e instanceof GitHubRetry) { $result['retryAt'] = $e->retryAt; }
            }
            // Do not retain caches from old credentials.
            $cache = array_filter($cache, fn($name) => str_starts_with($name, $this->client->cacheKey() . ':'), ARRAY_FILTER_USE_KEY);
            $cache[$key] = $result;
            Updater::atomicJson($path, $cache);
            return $result;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function announced(string $channel): ?string
    {
        $versions = $this->client->versions();
        $version = $versions[$channel];
        $stable = $versions['release'];
        return $channel === 'alpha' && $stable !== null && ($version === null || version_compare($stable, $version, '>'))
            ? $stable : $version;
    }

    private function releaseForVersion(string $version): array
    {
        $release = $this->client->json($this->path('releases/tags/v' . $version));
        if ($release === null) { throw new RuntimeException('The announced release is not available on GitHub. Try again later.'); }
        $candidate = $this->candidate($release, str_contains($version, '-alpha.') ? 'alpha' : 'release');
        if ($candidate['version'] !== $version) { throw new RuntimeException('The announced version does not match the GitHub release.'); }
        return $candidate;
    }

    public function candidate(array $release, string $channel): array
    {
        $version = substr($release['tag_name'] ?? '', 1);
        if (!Updater::validVersion($version) || ($release['tag_name'][0] ?? '') !== 'v' || !empty($release['draft']) || !is_bool($release['prerelease'] ?? null) || $release['prerelease'] !== ($channel === 'alpha') || str_contains($version, '-alpha.') !== ($channel === 'alpha') || empty($release['published_at']) || !is_int($release['id'] ?? null)) {
            throw new RuntimeException('The release is not a published build for this channel.');
        }
        if (!preg_match('/<!-- f125-update (\{[^\r\n]+\}) -->/', $release['body'] ?? '', $match)) { throw new RuntimeException('The release has no application build metadata.'); }
        $build = json_decode($match[1], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($build) || ($build['channel'] ?? '') !== $channel || !preg_match('/^[a-f0-9]{40}$/D', $build['commit'] ?? '') || !is_int($build['pr'] ?? null) || $build['pr'] < 1 || !preg_match('~^release/\d+\.\d+\.\d+$~D', $build['branch'] ?? '')) { throw new RuntimeException('Invalid release build identity.'); }
        if (substr($build['branch'], 8) !== explode('-alpha.', $version)[0]) { throw new RuntimeException('The release branch and version disagree.'); }
        $assets = array_values(array_filter($release['assets'] ?? [], static fn($asset) => ($asset['name'] ?? '') === 'f125-update-' . $version . '.zip'));
        if (count($assets) !== 1) { throw new RuntimeException('The release update package is missing or ambiguous.'); }
        $asset = $assets[0];
        if (($asset['state'] ?? '') !== 'uploaded' || !is_int($asset['id'] ?? null) || !is_int($asset['size'] ?? null) || $asset['size'] < 1 || $asset['size'] > 8388608 || !preg_match('/^sha256:([a-f0-9]{64})$/D', $asset['digest'] ?? '', $digest)) { throw new RuntimeException('The release package has no valid size or GitHub SHA-256 digest.'); }
        $result = ['version' => $version, 'channel' => $channel, 'commit' => $build['commit'], 'pr' => $build['pr'], 'branch' => $build['branch'], 'releaseId' => $release['id'], 'assetId' => $asset['id'], 'size' => $asset['size'], 'sha256' => $digest[1], 'notes' => trim(preg_replace('/<!-- f125-update .*? -->/', '', $release['body'] ?? ''))];
        $result['identity'] = hash('sha256', json_encode(array_diff_key($result, ['notes' => true]), JSON_THROW_ON_ERROR));
        return $result;
    }

    public function install(string $channel, string $identity, string $public): string
    {
        $cached = $this->check($channel);
        $selected = $cached['candidate'] ?? null;
        if (!is_array($selected) || !hash_equals($selected['identity'], $identity)) { throw new RuntimeException('The selected update changed. Check for updates again.'); }
        // Recheck the announcement and exact asset; never silently switch packages.
        $latest = $this->announced($channel);
        $release = $this->client->json($this->path('releases/' . $selected['releaseId']));
        if ($latest !== $selected['version'] || $release === null) { throw new RuntimeException('This build is no longer the current update. Check again.'); }
        $candidate = $this->candidate($release, $selected['channel']);
        if (!hash_equals($candidate['identity'], $identity)) { throw new RuntimeException('The GitHub package changed. Check again.'); }
        $download = $this->client->get($this->path('releases/assets/' . $candidate['assetId']), true);
        if ($download['status'] !== 200 || strlen($download['body']) !== $candidate['size'] || !hash_equals($candidate['sha256'], hash('sha256', $download['body']))) { throw new RuntimeException('The downloaded package failed its integrity check.'); }
        $temp = tempnam($this->root . '/config', '.update-download-');
        if ($temp === false) { throw new RuntimeException('Cannot prepare the download.'); }
        try {
            if (file_put_contents($temp, $download['body']) !== $candidate['size']) { throw new RuntimeException('Cannot save the download.'); }
            return (new Updater($this->root, $public))->apply($temp, $candidate);
        } finally { if (is_file($temp)) { unlink($temp); } }
    }
}
