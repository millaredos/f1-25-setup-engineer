<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\HttpClient;

/** Resolves only the exact announced release and its complete GitHub asset identity. */
final class UpdateDiscovery
{
    private HttpClient $http;
    private string $root;

    public function __construct(string $root, HttpClient $http)
    {
        $this->root = $root;
        $this->http = $http;
    }

    /** Report no update when the announced version is absent or not newer; outages propagate. */
    public function check(string $channel): ?array
    {
        if (!in_array($channel, ['alpha', 'release'], true)) {
            throw new \RuntimeException('Invalid update channel.');
        }
        $feed = trim($this->http->get('https://git.millaredos.com/f125/versions.txt', 512));
        if (!preg_match('/^alpha=([^\r\n]*)\r?\nrelease=([^\r\n]*)$/D', $feed, $match)) {
            throw new \RuntimeException('Invalid version feed.');
        }
        foreach ([$match[1], $match[2]] as $announced) {
            if ($announced !== '' && !UpdateInstaller::validVersion($announced)) { throw new \RuntimeException('Invalid feed version.'); }
        }
        if ($match[2] !== '' && str_contains($match[2], '-alpha.')) { throw new \RuntimeException('Invalid stable feed version.'); }
        $version = $channel === 'alpha' ? $match[1] : $match[2];
        if ($channel === 'alpha' && $match[2] !== '' && ($version === '' || version_compare($match[2], $version, '>'))) { $version = $match[2]; }
        if ($version === '') {
            return null;
        }
        if (!UpdateInstaller::validVersion($version) || ($channel === 'release' && str_contains($version, '-alpha.'))) {
            throw new \RuntimeException('Invalid announced version.');
        }
        $current = trim(file_get_contents($this->root . '/src/VERSION'));
        if (!version_compare($version, $current, '>')) {
            return null;
        }
        $url = 'https://api.github.com/repos/millaredos/f1-25-setup-engineer/releases/tags/v' . rawurlencode($version);
        $release = json_decode($this->http->get($url), true, 64, JSON_THROW_ON_ERROR);
        if (($release['draft'] ?? true) || ($release['tag_name'] ?? '') !== 'v' . $version || !is_bool($release['prerelease'] ?? null) || $release['prerelease'] !== str_contains($version, '-alpha.')) {
            throw new \RuntimeException('Release is not a complete published build.');
        }
        if (!preg_match('/<!-- f125-update (\{[^\r\n]*\}) -->/', $release['body'] ?? '', $marker)) { return null; }
        $build = json_decode($marker[1], true, 16, JSON_THROW_ON_ERROR);
        if (($build['format'] ?? '') !== 'f125-update-v3') { return null; }
        if (!preg_match('/^[a-f0-9]{40}$/D', $build['commit'] ?? '') || !is_int($build['pr'] ?? null) || $build['pr'] < 1 || !preg_match('/^release\/\d+\.\d+\.\d+$/D', $build['branch'] ?? '')) { throw new \RuntimeException('Invalid published build identity.'); }
        foreach ($release['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? '') !== 'f125-update-' . $version . '.zip') {
                continue;
            }
            if (($asset['state'] ?? '') !== 'uploaded' || !is_int($asset['size'] ?? null) || $asset['size'] < 1 || $asset['size'] > 20971520 || !preg_match('/^sha256:([a-f0-9]{64})$/D', $asset['digest'] ?? '', $digest) || !is_int($asset['id'] ?? null)) {
                throw new \RuntimeException('Package digest or identity is missing.');
            }
            $candidate = ['version' => $version, 'channel' => str_contains($version, '-alpha.') ? 'alpha' : 'release', 'assetId' => $asset['id'], 'size' => $asset['size'], 'sha256' => $digest[1], 'commit' => $build['commit'], 'pr' => $build['pr'], 'branch' => $build['branch']];
            $candidate['identity'] = hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR));
            return $candidate;
        }
        throw new \RuntimeException('The published release has no complete update package.');
    }

    /** Re-fetch the selected identity before downloading; changed candidates require a new confirmation. */
    public function download(string $channel, string $identity): array
    {
        $candidate = $this->check($channel);
        if ($candidate === null || !hash_equals($candidate['identity'], $identity)) {
            throw new \RuntimeException('La actualización disponible ha cambiado. Comprueba de nuevo.');
        }
        $bytes = $this->http->get('https://api.github.com/repos/millaredos/f1-25-setup-engineer/releases/assets/' . $candidate['assetId'], $candidate['size'], true);
        if (strlen($bytes) !== $candidate['size'] || !hash_equals($candidate['sha256'], hash('sha256', $bytes))) {
            throw new \RuntimeException('Downloaded package failed integrity verification.');
        }
        return ['candidate' => $candidate, 'bytes' => $bytes];
    }
}
