<?php
declare(strict_types=1);

final class GitHubRetry extends RuntimeException
{
    public int $retryAt;
    public function __construct(int $retryAt) { parent::__construct('GitHub access was denied or its request limit was reached. Retry later.'); $this->retryAt = $retryAt; }
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
    private string $token;
    private $transport;
    public function __construct(string $token = '', ?callable $transport = null) { $this->token = $token; $this->transport = $transport; }
    public function cacheKey(): string { return hash('sha256', self::REPOSITORY . ':' . $this->token); }

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
                $retry = $response['headers']['retry-after'] ?? '3600';
                $reset = (int) ($response['headers']['x-ratelimit-reset'] ?? 0);
                throw new GitHubRetry(max(time() + (preg_match('/^\d+$/D', (string) $retry) ? max(60, (int) $retry) : 3600), $reset));
            }
            if (!in_array($response['status'], [200, 404], true)) { throw new RuntimeException('GitHub is unavailable. The installed application is unchanged.'); }
            return $response;
        }
        throw new RuntimeException('Too many GitHub redirects.');
    }

    private function request(string $url, array $headers, int $limit): array
    {
        if (!function_exists('curl_init')) { throw new RuntimeException('Enable the PHP cURL extension to check GitHub updates.'); }
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
            if (curl_exec($curl) === false) { throw new RuntimeException('Cannot securely download from GitHub. Check network access and PHP CA certificates.'); }
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
        $previous = $cache[$key] ?? [];
        if (($previous['retryAt'] ?? 0) > time()) { return $previous; }
        $ttl = isset($previous['error']) ? 600 : ($channel === 'alpha' ? 300 : 21600);
        if (isset($previous['checked']) && time() - $previous['checked'] < ($force ? 60 : $ttl)) { return $previous; }
        $lock = fopen($this->root . '/config/update-cache.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) { fclose($lock); } return $previous ?: ['checked' => 0, 'error' => 'An update check is already running.']; }
        try {
            // Another request may have refreshed the cache while this request started.
            $fresh = is_file($path) ? json_decode(file_get_contents($path), true) : [];
            if (is_array($fresh) && isset($fresh[$key]['checked']) && $fresh[$key]['checked'] > ($previous['checked'] ?? 0)) { return $fresh[$key]; }
            $result = ['checked' => time(), 'candidate' => null];
            try { $result['candidate'] = $this->discover($channel); }
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

    private function discover(string $channel): ?array
    {
        if ($channel === 'release') {
            $release = $this->client->json($this->path('releases/latest'));
            return $release === null ? null : $this->candidate($release, $channel);
        }
        $pulls = $this->client->json($this->path('pulls?state=open&base=main&per_page=100'));
        if ($pulls === null) { throw new RuntimeException('The repository is private or unavailable. Configure a read-only GitHub token on this server if needed.'); }
        $active = array_values(array_filter($pulls, static fn($pr) => is_array($pr) && ($pr['head']['repo']['full_name'] ?? '') === GitHubClient::REPOSITORY && preg_match('~^release/\d+\.\d+\.\d+$~D', $pr['head']['ref'] ?? '')));
        if (count($active) > 1) { throw new RuntimeException('More than one release PR is open. Keep one active release PR for the Alpha channel.'); }
        if (!$active) { return $this->stableFallback(); }
        $pr = $active[0]; $best = null;
        // A bounded scan prevents a malformed repository from producing unlimited requests.
        for ($page = 1; $page <= 3; $page++) {
            $releases = $this->client->json($this->path('releases?per_page=100&page=' . $page));
            if ($releases === null) { throw new RuntimeException('Cannot list Alpha builds.'); }
            foreach ($releases as $release) {
                if (!is_array($release) || empty($release['prerelease']) || !empty($release['draft'])) { continue; }
                try { $candidate = $this->candidate($release, 'alpha'); } catch (RuntimeException $e) { continue; }
                if ($candidate['pr'] !== $pr['number'] || $candidate['branch'] !== $pr['head']['ref'] || explode('-alpha.', $candidate['version'])[0] !== substr($pr['head']['ref'], 8)) { continue; }
                if ($best === null || version_compare($candidate['version'], $best['version'], '>')) { $best = $candidate; }
            }
            if (count($releases) < 100 || $best !== null) { break; }
        }
        $stable = $this->stableFallback();
        return $stable !== null && ($best === null || version_compare($stable['version'], $best['version'], '>')) ? $stable : $best;
    }

    private function stableFallback(): ?array
    {
        $release = $this->client->json($this->path('releases/latest'));
        if ($release === null) { return null; }
        try { return $this->candidate($release, 'release'); }
        catch (RuntimeException $error) { return null; }
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
        // Re-discover the channel and re-fetch the exact release; never silently switch packages.
        $latest = $this->discover($channel);
        $release = $this->client->json($this->path('releases/' . $selected['releaseId']));
        if ($latest === null || !hash_equals($latest['identity'], $identity) || $release === null) { throw new RuntimeException('This build is no longer the current update. Check again.'); }
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
