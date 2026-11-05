<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/fixtures/github.php';
if (!class_exists('ZipArchive')) { fwrite(STDERR, "ZIP is required for update tests.\n"); exit(2); }
$root = sys_get_temp_dir() . '/f125-channels-' . bin2hex(random_bytes(6));
mkdir($root, 0700); mkdir($root . '/config'); $checks = 0;
function testUpdate(bool $ok, string $message): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
function rejectUpdate(callable $call, string $message): void { try { $call(); } catch (Throwable $e) { testUpdate(true, $message); return; } testUpdate(false, $message); }
try {
    $settings = new UpdateSettings($root);
    testUpdate($settings->channel() === 'release', 'Release is the default');
    $settings->save('alpha'); testUpdate((new UpdateSettings($root))->channel() === 'alpha', 'Channel is persistent');
    rejectUpdate(fn() => $settings->save('main'), 'Unknown channel rejected');
    foreach (['0.3.0-alpha.1', '0.3.0-alpha.12', '1.2.3'] as $version) { testUpdate(Updater::validVersion($version), 'Version accepted'); }
    foreach (['v0.3.0', '0.3', '0.3.0-beta.1', '0.3.0-alpha.01', '../main'] as $version) { testUpdate(!Updater::validVersion($version), 'Version rejected'); }
    testUpdate(version_compare('0.3.0-alpha.12', '0.3.0-alpha.2', '>'), 'Alpha ordering is numeric');
    testUpdate(version_compare('0.3.0', '0.3.0-alpha.12', '>'), 'Stable supersedes its alpha');
    fixturePackage($root . '/package.zip', '0.3.0-alpha.2');
    $log = (object) ['requests' => []];
    $client = new GitHubClient('test-token', fixtureGitHub($root . '/package.zip', [], $log));
    $service = new GitHubUpdates($root, $client);
    $alpha = $service->check('alpha');
    testUpdate($alpha['candidate']['version'] === '0.3.0-alpha.2', 'Alpha follows release PR builds');
    $requests = count($log->requests);
    $service->check('alpha'); $service->check('alpha', true);
    testUpdate(count($log->requests) === $requests, 'Shared cache and forced-check throttle');
    $stable = $service->check('release');
    testUpdate($stable['candidate'] === null && $stable['available'] === '0.2.0', 'Older stable does not query GitHub or offer a downgrade');
    $candidate = $alpha['candidate'];
    $makeRelease = static function () use ($candidate): array {
        return ['id' => 2, 'tag_name' => 'v0.3.0-alpha.2', 'draft' => false, 'prerelease' => true, 'published_at' => '2026-11-05',
            'body' => '<!-- f125-update ' . json_encode(['channel' => 'alpha', 'commit' => $candidate['commit'], 'pr' => 1, 'branch' => 'release/0.3.0']) . ' -->',
            'assets' => [['id' => 102, 'name' => 'f125-update-0.3.0-alpha.2.zip', 'state' => 'uploaded', 'size' => $candidate['size'], 'digest' => 'sha256:' . $candidate['sha256']]]];
    };
    foreach (['draft', 'missingDigest', 'missingAsset', 'wrongChannel', 'badTag', 'unpublished'] as $case) {
        $release = $makeRelease();
        if ($case === 'draft') { $release['draft'] = true; }
        if ($case === 'missingDigest') { unset($release['assets'][0]['digest']); }
        if ($case === 'missingAsset') { $release['assets'] = []; }
        if ($case === 'wrongChannel') { $release['prerelease'] = false; }
        if ($case === 'badTag') { $release['tag_name'] = 'main'; }
        if ($case === 'unpublished') { $release['published_at'] = null; }
        rejectUpdate(fn() => $service->candidate($release, 'alpha'), $case . ' rejected');
    }
    $redirectLog = [];
    $redirectClient = new GitHubClient('secret-test-token', static function ($url, $headers) use (&$redirectLog) {
        $redirectLog[] = $headers;
        return count($redirectLog) === 1 ? ['status' => 302, 'headers' => ['location' => 'https://release-assets.githubusercontent.com/example'], 'body' => ''] : ['status' => 200, 'headers' => [], 'body' => 'zip'];
    });
    $redirectClient->get('/repos/' . GitHubClient::REPOSITORY . '/releases/assets/102', true);
    testUpdate(str_contains(implode(' ', $redirectLog[0]), 'secret-test-token') && !str_contains(implode(' ', $redirectLog[1]), 'secret-test-token'), 'Token never forwarded to storage hosts');
    $badRedirect = new GitHubClient('', static fn() => ['status' => 302, 'headers' => ['location' => 'http://127.0.0.1/secret'], 'body' => '']);
    rejectUpdate(fn() => $badRedirect->get('/repos/' . GitHubClient::REPOSITORY . '/releases/assets/102', true), 'Unsafe redirect rejected');
    foreach ([403, 429, 500] as $status) {
        $errorRoot = $root . '/error-' . $status; mkdir($errorRoot); mkdir($errorRoot . '/config');
        $errorLog = (object) ['requests' => []];
        $failed = new GitHubUpdates($errorRoot, new GitHubClient('', fixtureGitHub('', ['status' => $status, 'stableVersion' => '0.3.0'], $errorLog)));
        $failedCheck = $failed->check('release');
        testUpdate(isset($failedCheck['error']), 'GitHub failure is reported without changing installation');
        if ($status === 429) {
            testUpdate($failedCheck['retryAt'] > time(), 'Rate-limit cooldown persisted');
            $failed->check('release', true);
            testUpdate(count($errorLog->requests) === 2, 'Manual checks respect rate-limit cooldown');
        }
    }
    // Primary limits use the reset time; secondary limits use Retry-After.
    foreach ([
        [403, ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string) (time() + 120)], 120],
        [429, ['retry-after' => '30', 'x-ratelimit-reset' => (string) (time() + 3600)], 30],
        [429, [], 60],
    ] as [$status, $headers, $seconds]) {
        $limited = new GitHubClient('', static fn() => ['status' => $status, 'headers' => $headers, 'body' => '']);
        $started = time();
        try { $limited->get('/repos/' . GitHubClient::REPOSITORY . '/releases/latest'); testUpdate(false, 'Rate limit must throw'); }
        catch (GitHubRetry $error) {
            testUpdate(abs($error->retryAt - ($started + $seconds)) <= 2, 'GitHub retry time honored without an extra hour');
            testUpdate(str_contains($error->getMessage(), 'request limit'), 'Rate limit has a specific message');
        }
    }
    $denied = new GitHubClient('', static fn() => ['status' => 403, 'headers' => [], 'body' => '{"message":"Resource not accessible"}']);
    try { $denied->get('/repos/' . GitHubClient::REPOSITORY . '/releases/latest'); testUpdate(false, 'Denied access must throw'); }
    catch (RuntimeException $error) { testUpdate(!$error instanceof GitHubRetry && str_contains($error->getMessage(), 'denied access'), 'Permission denial is not mislabeled as a rate limit'); }
    $retryRoot = $root . '/expired-retry'; mkdir($retryRoot); mkdir($retryRoot . '/config');
    $retryClient = new GitHubClient('', fixtureGitHub($root . '/package.zip'));
    Updater::atomicJson($retryRoot . '/config/update-cache.json', [$retryClient->cacheKey() . ':alpha' =>
        ['checked' => time(), 'installed' => appVersion(), 'retryAt' => time() - 1, 'error' => 'Previous rate limit', 'candidate' => null]]);
    $retried = (new GitHubUpdates($retryRoot, $retryClient))->check('alpha');
    testUpdate(($retried['candidate']['version'] ?? '') === '0.3.0-alpha.2', 'Expired cooldown retries immediately instead of waiting for the error cache');
    testUpdate(GitHubClient::parseVersions("alpha=0.3.0-alpha.10\r\nrelease=\r\n") === ['alpha' => '0.3.0-alpha.10', 'release' => null], 'CRLF version service accepted');
    foreach (["", "alpha=0.3.0-alpha.2\n", "alpha=0.3.0-alpha.2\nalpha=\n", "alpha=0.3.0\nrelease=\n",
        "alpha=\nrelease=0.3.0-alpha.2\n", "alpha=0.3.0-alpha.02\nrelease=\n", "alpha=../bad\nrelease=\n",
        "alpha=\nrelease=\nother=x\n", str_repeat('x', 2049)] as $badFeed) {
        rejectUpdate(fn() => GitHubClient::parseVersions($badFeed), 'Malformed version service rejected');
    }
    $feedRoot = $root . '/feed'; mkdir($feedRoot); mkdir($feedRoot . '/config'); mkdir($feedRoot . '/src');
    file_put_contents($feedRoot . '/src/VERSION', "0.3.0-alpha.2\n");
    $feedLog = (object) ['requests' => []];
    $feedClient = new GitHubClient('private-token', fixtureGitHub($root . '/package.zip', [], $feedLog));
    $feedService = new GitHubUpdates($feedRoot, $feedClient);
    $upToDate = $feedService->check('alpha');
    testUpdate($upToDate['candidate'] === null && $upToDate['available'] === '0.3.0-alpha.2', 'Current installation reads only public versions');
    testUpdate(count($feedLog->requests) === 1 && $feedLog->requests[0]['url'] === GitHubClient::VERSION_FEED, 'No GitHub API call when current');
    testUpdate(!str_contains(implode(' ', $feedLog->requests[0]['headers']), 'private-token'), 'GitHub token never sent to version service');
    $feedService->check('release');
    testUpdate(count($feedLog->requests) === 2, 'Older release checks also make no GitHub call');
    $cacheFile = $feedRoot . '/config/update-cache.json';
    $oldKey = hash('sha256', GitHubClient::REPOSITORY . ':private-token') . ':alpha';
    Updater::atomicJson($cacheFile, [$oldKey => ['checked' => time(), 'retryAt' => time() + 3600, 'error' => 'Old GitHub cooldown']]);
    testUpdate(!isset($feedService->check('alpha')['error']), 'Migration ignores legacy API cooldown cache');
    file_put_contents($feedRoot . '/src/VERSION', "0.3.0-alpha.1\n");
    $newer = $feedService->check('alpha');
    testUpdate($newer['candidate']['version'] === '0.3.0-alpha.2', 'New release resolved after installed-version change');
    $apiRequests = array_values(array_filter($feedLog->requests, fn($request) => str_starts_with($request['url'], 'https://api.github.com/')));
    testUpdate(count($apiRequests) === 1 && str_contains($apiRequests[0]['url'], '/releases/tags/v0.3.0-alpha.2'), 'Newer version needs one exact-release API lookup');
    $cache = json_decode(file_get_contents($cacheFile), true); foreach ($cache as &$entry) { $entry['checked'] = time() - 301; } unset($entry);
    Updater::atomicJson($cacheFile, $cache);
    $beforeRefresh = count($feedLog->requests); $feedService->check('alpha');
    testUpdate(count($feedLog->requests) === $beforeRefresh + 1, 'Known release metadata reused across version-feed refreshes');
    foreach ([['feedStatus' => 503], ['feedStatus' => 302], ['feed' => "invalid\n"]] as $options) {
        $failureRoot = $root . '/feed-failure-' . bin2hex(random_bytes(3)); mkdir($failureRoot); mkdir($failureRoot . '/config');
        $failureLog = (object) ['requests' => []];
        $failure = (new GitHubUpdates($failureRoot, new GitHubClient('', fixtureGitHub('', $options, $failureLog))))->check('alpha');
        testUpdate(isset($failure['error']) && count($failureLog->requests) === 1, 'Feed outage/redirect/malformed response never falls back to GitHub polling');
    }
    $changedFeed = "alpha=0.3.0-alpha.2\nrelease=\n";
    $baseTransport = fixtureGitHub($root . '/package.zip');
    $switchingClient = new GitHubClient('switching', static function ($url, $headers, $limit) use (&$changedFeed, $baseTransport) {
        return $url === GitHubClient::VERSION_FEED ? ['status' => 200, 'headers' => [], 'body' => $changedFeed] : $baseTransport($url, $headers, $limit);
    });
    $switching = new GitHubUpdates($feedRoot, $switchingClient);
    $switchingCandidate = $switching->check('alpha')['candidate'];
    $changedFeed = "alpha=0.3.0-alpha.3\nrelease=\n";
    rejectUpdate(fn() => $switching->install('alpha', $switchingCandidate['identity'], 'www'), 'Superseded announcement rejected before installation');
    rejectUpdate(fn() => $service->install('alpha', str_repeat('0', 64), 'www'), 'Stale or forged selection rejected');
    $damaged = new GitHubUpdates($root, new GitHubClient('damaged-test', fixtureGitHub($root . '/package.zip', ['download' => 'altered'])));
    $damagedCandidate = $damaged->check('alpha')['candidate'];
    rejectUpdate(fn() => $damaged->install('alpha', $damagedCandidate['identity'], 'www'), 'Corrupt GitHub download rejected');
    // Install into a renamed public directory, preserving private state.
    foreach (['src', 'data', 'www', 'www/assets'] as $dir) { mkdir($root . '/' . $dir, 0700, true); }
    $zip = new ZipArchive(); $zip->open($root . '/package.zip');
    foreach (json_decode($zip->getFromName('manifest.json'), true)['files'] as $path => $hash) {
        $target = $root . '/' . (str_starts_with($path, 'public_html/') ? 'www' . substr($path, 11) : $path);
        file_put_contents($target, $zip->getFromName($path));
    }
    $zip->close();
    file_put_contents($root . '/src/VERSION', "0.2.0\n");
    file_put_contents($root . '/config/installation.json', '{"installed":true}');
    foreach (['schema', 'php', 'commit'] as $field) {
        $badManifest = fixturePackage($root . '/incompatible.zip', '0.3.0-alpha.2');
        if ($field === 'schema') { $badManifest['requires']['schema'] = 999; }
        if ($field === 'php') { $badManifest['requires']['php'] = PHP_VERSION_ID + 10000; }
        if ($field === 'commit') { $badManifest['build']['commit'] = str_repeat('b', 40); }
        $badZip = new ZipArchive(); $badZip->open($root . '/incompatible.zip');
        $badZip->addFromString('manifest.json', json_encode($badManifest)); $badZip->close();
        rejectUpdate(fn() => (new Updater($root, 'www'))->apply($root . '/incompatible.zip', $candidate), 'Incompatible ' . $field . ' rejected');
        testUpdate(trim(file_get_contents($root . '/src/VERSION')) === '0.2.0', 'Rejected package leaves installation untouched');
    }
    $backup = $service->install('alpha', $candidate['identity'], 'www');
    testUpdate(trim(file_get_contents($root . '/src/VERSION')) === '0.3.0-alpha.2', 'GitHub package installed');
    testUpdate(is_file($root . '/www/options.php') && !is_dir($root . '/public_html'), 'Renamed public directory honored');
    testUpdate($settings->channel() === 'alpha', 'Channel preserved');
    testUpdate(file_get_contents($root . '/config/installation.json') === '{"installed":true}', 'Installation state preserved');
    testUpdate(trim(file_get_contents($root . '/backups/' . $backup . '/src/VERSION')) === '0.2.0', 'Original version backed up');
    rejectUpdate(fn() => $service->install('alpha', $candidate['identity'], 'www'), 'Reinstallation rejected');
    testUpdate(!is_file($root . '/config/update-journal.json'), 'Completed journal cleaned');
    fixturePackage($root . '/stable.zip', '0.3.0', 'release');
    $fallback = new GitHubUpdates($root, new GitHubClient('fallback-test', fixtureGitHub($root . '/stable.zip', ['pulls' => [], 'stableVersion' => '0.3.0'])));
    $stableCandidate = $fallback->check('alpha')['candidate'];
    testUpdate($stableCandidate['version'] === '0.3.0' && $stableCandidate['channel'] === 'release', 'Alpha falls back to the stable release after its PR closes');
    $fallback->install('alpha', $stableCandidate['identity'], 'www');
    testUpdate(trim(file_get_contents($root . '/src/VERSION')) === '0.3.0', 'Alpha installation can advance to stable');
    testUpdate($settings->channel() === 'alpha', 'Installing stable does not silently change the selected channel');
    // Simulate a process stopping halfway through replacement.
    $backupName = 'update-20261105-120000-1234abcd';
    mkdir($root . '/backups/' . $backupName . '/src', 0700, true);
    file_put_contents($root . '/backups/' . $backupName . '/src/VERSION', "0.2.0\n");
    file_put_contents($root . '/src/new-file.php', 'partial');
    Updater::atomicJson($root . '/config/update-journal.json', ['phase' => 'replacing', 'public' => 'www', 'backup' => $backupName,
        'files' => ['src/VERSION' => ['existed' => true, 'oldHash' => hash('sha256', "0.2.0\n"), 'temp' => ''], 'src/new-file.php' => ['existed' => false, 'temp' => '']]]);
    Updater::guard($root); Updater::releaseRequestLock();
    testUpdate(trim(file_get_contents($root . '/src/VERSION')) === '0.2.0' && !is_file($root . '/src/new-file.php'), 'Interrupted update recovered before loading application');
    $lock = fopen($root . '/config/update.lock', 'c'); flock($lock, LOCK_EX);
    rejectUpdate(fn() => Updater::guard($root), 'Requests blocked during replacement');
    flock($lock, LOCK_UN); fclose($lock);
    echo "OK: $checks channel, GitHub, integrity, recovery and renamed-directory checks.\n";
} finally {
    Updater::releaseRequestLock();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
