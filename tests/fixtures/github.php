<?php
declare(strict_types=1);

/** Offline GitHub fixture, used only through private test configuration. */
function fixtureGitHub(string $package = '', array $options = [], ?object $log = null): callable
{
    $manifest = ['version' => '0.3.0-alpha.1', 'build' => ['channel' => 'alpha', 'commit' => str_repeat('a', 40), 'pr' => 1, 'branch' => 'release/0.3.0']];
    $bytes = '';
    if (is_file($package) && class_exists('ZipArchive')) {
        $zip = new ZipArchive(); $zip->open($package);
        $manifest = json_decode($zip->getFromName('manifest.json'), true, 64, JSON_THROW_ON_ERROR);
        $zip->close(); $bytes = file_get_contents($package);
    }
    $build = $manifest['build'];
    $make = static function (string $version, string $channel, int $id) use ($build, $bytes): array {
        $meta = array_replace($build, ['channel' => $channel]);
        return ['id' => $id, 'tag_name' => 'v' . $version, 'draft' => false, 'prerelease' => $channel === 'alpha',
            'published_at' => '2026-11-05T12:00:00Z', 'target_commitish' => $meta['commit'],
            'body' => "Test release notes.\n<!-- f125-update " . json_encode($meta) . ' -->',
            'assets' => [['id' => $id + 100, 'name' => 'f125-update-' . $version . '.zip', 'state' => 'uploaded', 'size' => max(1, strlen($bytes)), 'digest' => 'sha256:' . hash('sha256', $bytes)]]];
    };
    $alpha = $make($manifest['version'], 'alpha', 2);
    $stable = $make($options['stableVersion'] ?? '0.2.0', 'release', 1);
    $pull = ['number' => $build['pr'], 'head' => ['ref' => $build['branch'], 'sha' => $build['commit'], 'repo' => ['full_name' => GitHubClient::REPOSITORY]]];
    return static function (string $url, array $headers, int $limit) use ($options, $alpha, $stable, $pull, $bytes, $log): array {
        if ($log !== null) { $log->requests[] = ['url' => $url, 'headers' => $headers]; }
        if (defined('GitHubClient::VERSION_FEED') && $url === GitHubClient::VERSION_FEED) {
            $feed = 'alpha=' . ($alpha['prerelease'] && str_contains($alpha['tag_name'], '-alpha.') ? substr($alpha['tag_name'], 1) : '')
                . "\nrelease=" . substr($stable['tag_name'], 1) . "\n";
            return ['status' => $options['feedStatus'] ?? 200, 'body' => $options['feed'] ?? $feed, 'headers' => []];
        }
        if (isset($options['status'])) { return ['status' => $options['status'], 'body' => '', 'headers' => []]; }
        $path = substr($url, strlen('https://api.github.com/repos/' . GitHubClient::REPOSITORY . '/'));
        if ($path === 'releases/latest') { $body = $options['stable'] ?? $stable; }
        elseif (str_starts_with($path, 'pulls?')) { $body = $options['pulls'] ?? [$pull]; }
        elseif (str_starts_with($path, 'releases?')) { $body = $options['releases'] ?? [$alpha, $stable]; }
        elseif ($path === 'releases/tags/' . $alpha['tag_name'] && str_contains($alpha['tag_name'], '-alpha.')) { $body = $options['alpha'] ?? $alpha; }
        elseif ($path === 'releases/tags/' . $stable['tag_name']) { $body = $options['stable'] ?? $stable; }
        elseif ($path === 'releases/2') { $body = $options['alpha'] ?? $alpha; }
        elseif ($path === 'releases/1') { $body = $stable; }
        elseif (in_array($path, ['releases/assets/101', 'releases/assets/102'], true)) { return ['status' => 200, 'headers' => [], 'body' => $options['download'] ?? $bytes]; }
        else { return ['status' => 404, 'headers' => [], 'body' => '']; }
        return ['status' => 200, 'headers' => [], 'body' => json_encode($body, JSON_THROW_ON_ERROR)];
    };
}

function fixturePackage(string $target, string $version, string $channel = 'alpha'): array
{
    $repo = dirname(__DIR__, 2); $contents = [];
    foreach (['src', 'public_html', 'public_html/assets', 'data'] as $directory) {
        foreach (glob($repo . '/' . $directory . '/*') as $file) {
            $path = $directory . '/' . basename($file);
            if (is_file($file) && Updater::allowed($path)) { $contents[$path] = file_get_contents($file); }
        }
    }
    $contents['src/VERSION'] = $version . "\n";
    $manifest = ['format' => 'f125-update-v2', 'version' => $version,
        'build' => ['channel' => $channel, 'commit' => str_repeat('a', 40), 'pr' => 1, 'branch' => 'release/0.3.0'],
        'requires' => ['php' => 80000, 'schema' => 1], 'files' => []];
    foreach ($contents as $path => $data) { $manifest['files'][$path] = hash('sha256', $data); }
    $zip = new ZipArchive(); $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode($manifest));
    foreach ($contents as $path => $data) { $zip->addFromString($path, $data); }
    $zip->close();
    return $manifest;
}
