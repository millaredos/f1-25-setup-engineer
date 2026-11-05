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
    testUpdate($stable['candidate']['version'] === '0.2.0', 'Release excludes alpha builds');
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
        $failed = new GitHubUpdates($errorRoot, new GitHubClient('', fixtureGitHub('', ['status' => $status], $errorLog)));
        $failedCheck = $failed->check('release');
        testUpdate(isset($failedCheck['error']), 'GitHub failure is reported without changing installation');
        if ($status !== 500) {
            testUpdate($failedCheck['retryAt'] > time(), 'Rate-limit cooldown persisted');
            $failed->check('release', true);
            testUpdate(count($errorLog->requests) === 1, 'Manual checks respect rate-limit cooldown');
        }
    }
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
