<?php
declare(strict_types=1);

/** Exercise the released 0.4 updater unchanged, then follow its real HTTP redirect into MVC. */
require dirname(__DIR__) . '/bin/package.php';

use App\Application\LegacyPackage;
use App\Application\UpdateInstaller;

$root = dirname(__DIR__, 2);
$checks = 0;
$temporary = sys_get_temp_dir() . '/f125-transition-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);

/** Read the fixed public 0.4 commit as a regression fixture, never the working tree or private reference files. */
function releasedSource(string $path): string
{
    global $root;
    $process = proc_open(['git', 'show', 'c166bf04d50cd12c640434ade7fa7cda16919f48:' . $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) { throw new RuntimeException('Cannot load released fixture.'); }
    $source = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Fetch the pinned 0.4 commit before transition tests: ' . $error); }
    return $source;
}

/** Fail immediately on a contract regression and count only executed assertions. */
function transitionCheck(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($description); }
    $checks++;
}

/** Clean only a test-owned tree, without following symlinks. */
function removeTransitionTree(string $directory): void
{
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { removeTransitionTree($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    rmdir($directory);
}

/** Fetch without redirect following so the transition and final MVC page are checked separately. */
function transitionGet(string $url, string $csrf = '', string $cookie = ''): array
{
    $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10, 'header' => 'Cookie: ' . $cookie . "\r\n"];
    if ($csrf !== '') {
        $options['method'] = 'POST';
        $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = http_build_query(['csrf' => $csrf]);
    }
    $bytes = file_get_contents($url, false, stream_context_create(['http' => $options]));
    preg_match('~HTTP/\S+ (\d+)~', $http_response_header[0] ?? '', $match);
    return ['status' => (int) ($match[1] ?? 0), 'body' => $bytes, 'headers' => $http_response_header ?? []];
}

/** Follow the same protected POST that the temporary browser script submits, without executing JavaScript. */
function transitionFinish(string $url): array
{
    $form = transitionGet($url . 'update.php');
    transitionCheck($form['status'] === 200 && preg_match('/name="csrf" value="([a-f0-9]{64})"/', $form['body'], $token) === 1, 'Redirect only presents a protected continuation form');
    $cookie = '';
    foreach ($form['headers'] as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    return transitionGet($url . 'index.php', $token[1], $cookie);
}

$server = null;
try {
    // Execute the actual public classes, unmodified; the transport alone is deterministic.
    foreach (['Updater', 'Updates'] as $class) {
        $fixture = $temporary . '/' . $class . '.php';
        file_put_contents($fixture, releasedSource('src/' . $class . '.php'));
        require $fixture;
    }
    $identity = ['channel' => 'alpha', 'commit' => str_repeat('b', 40), 'pr' => 4, 'branch' => 'release/9.0.0'];
    $version = '9.0.0-alpha.1';
    $package = $temporary . '/release.zip';
    buildReleasePackage($root, $package, $version, $identity);
    $digest = hash_file('sha256', $package);
    buildReleasePackage($root, $package, $version, $identity);
    transitionCheck(hash_equals($digest, hash_file('sha256', $package)), 'Compatibility package is reproducible');
    $packageBytes = file_get_contents($package);
    foreach ([['public_html', '/', false], ['www', '/tools/setups/', true]] as [$public, $prefix, $failFirst]) {
        $site = $temporary . '/' . $public;
        mkdir($site); mkdir($site . '/config'); mkdir($site . '/backups');
        foreach (array_unique(array_merge(LegacyPackage::required(), ['public_html/faq.php'])) as $path) {
            $target = $site . '/' . (str_starts_with($path, 'public_html/') ? $public . substr($path, 11) : $path);
            if (!is_dir(dirname($target))) { mkdir(dirname($target), 0700, true); }
            file_put_contents($target, releasedSource($path));
        }
        // The published stable package stamps its version independently of the source placeholder.
        file_put_contents($site . '/src/VERSION', "0.4.0\n");
        file_put_contents($site . '/config/local.php', "<?php return ['dsn'=>'mysql:host=127.0.0.1;port=1;dbname=never','username'=>'','password'=>'private-test-only'];");
        file_put_contents($site . '/config/installation.json', '{"installed":true}');
        file_put_contents($site . '/config/update-settings.json', '{"channel":"alpha"}');
        file_put_contents($site . '/' . $public . '/hosting.txt', 'keep-owner-file');
        file_put_contents($site . '/' . $public . '/.htaccess', '# keep hosting configuration');
        file_put_contents($site . '/backups/personal.txt', 'keep-backup');
        mkdir($site . '/data/migrations');
        file_put_contents($site . '/data/migrations/001-game-catalog.schema.sql', '-- retired application migration');
        file_put_contents($site . '/data/migrations/owner.sql', '-- keep owner file');
        $private = hash_file('sha256', $site . '/config/local.php');
        $release = ['id' => 1, 'tag_name' => 'v' . $version, 'draft' => false, 'prerelease' => true, 'published_at' => '2026-11-22T00:00:00Z', 'body' => '<!-- f125-update ' . json_encode($identity + ['format' => 'f125-update-v2', 'payloadFormat' => 'f125-update-v3']) . ' -->', 'assets' => [['id' => 1, 'name' => 'f125-update-' . $version . '.zip', 'size' => strlen($packageBytes), 'digest' => 'sha256:' . $digest, 'state' => 'uploaded']]];
        $client = new GitHubClient('', static function (string $url) use ($release, $packageBytes, $version): array {
            if (str_contains($url, 'versions.txt')) { $body = 'alpha=' . $version . "\nrelease=0.4.0\n"; }
            elseif (str_contains($url, '/assets/')) { $body = $packageBytes; }
            else { $body = json_encode($release, JSON_THROW_ON_ERROR); }
            return ['status' => 200, 'headers' => [], 'body' => $body];
        });
        $service = new GitHubUpdates($site, $client);
        $candidate = $service->check('alpha', true)['candidate'];
        transitionCheck($candidate['version'] === $version, 'Released service discovers compatible Alpha');
        $backup = $service->install('alpha', $candidate['identity'], $public);
        transitionCheck(trim(file_get_contents($site . '/backups/' . $backup . '/src/VERSION')) === '0.4.0', 'Released updater backs up the original installation');
        transitionCheck(is_file($site . '/src/MvcTransition.php'), 'Released updater accepts the complete flat transport');
        if (!$failFirst) {
            // Simulate process loss after the outer bootstrap changed, before 0.4 marked its journal committed.
            $outer = new ZipArchive(); $outer->open($package);
            $manifest = json_decode($outer->getFromName('manifest.json'), true, 64, JSON_THROW_ON_ERROR); $outer->close();
            $entries = [];
            foreach ($manifest['files'] as $path => $hash) {
                $saved = $site . '/backups/' . $backup . '/' . $path;
                $entries[$path] = ['existed' => is_file($saved), 'oldHash' => is_file($saved) ? hash_file('sha256', $saved) : null, 'temp' => ''];
            }
            file_put_contents($site . '/config/update-journal.json', json_encode(['phase' => 'replacing', 'backup' => $backup, 'public' => $public, 'files' => $entries], JSON_THROW_ON_ERROR));
        }
        if ($failFirst) { file_put_contents($site . '/src/View', 'test-owned obstacle'); }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $env = getenv(); $env['F125_TEST_ROOT'] = $site; $env['F125_TEST_PUBLIC'] = $public; $env['F125_TEST_BASE_PATH'] = $prefix;
        $server = proc_open([PHP_BINARY, '-d', 'register_argc_argv=0', '-d', 'display_errors=0', '-S', $address, $root . '/dev/serve-router.php'], [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $site . '/server.log', 'a'], 2 => ['file', $site . '/server.log', 'a']], $pipes, $root, $env);
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($connection) { fclose($connection); break; }
            usleep(100000);
        }
        $url = 'http://' . $address . $prefix;
        if (!$failFirst) {
            transitionCheck(transitionFinish($url)['status'] === 503, 'Interrupted outer transport reports recovery instead of success');
            transitionCheck(trim(file_get_contents($site . '/src/VERSION')) === '0.4.0' && !is_file($site . '/src/MvcTransition.php'), 'Original 0.4 journal restores the pre-update application');
            transitionCheck(!is_file($site . '/config/mvc-transition.json') && !is_file($site . '/config/mvc-transition.zip'), 'Rollback to 0.4 clears the old attempt receipt');
            $service->install('alpha', $candidate['identity'], $public);
        }
        if ($failFirst) {
            transitionCheck(transitionGet($url . 'index.php', 'invalid')['status'] === 403, 'Continuation rejects a forged POST without touching application files');
            $failed = transitionFinish($url);
            transitionCheck($failed['status'] === 503, 'Failed replacement is reported as failure');
            transitionCheck(is_file($site . '/src/MvcTransition.php') && !is_file($site . '/src/Model/Setup.php'), 'Partial replacement rolls back to the resumable transport');
            transitionCheck(!is_file($site . '/config/update-journal.json') && is_file($site . '/config/mvc-transition.zip'), 'Failed transition retains its authenticated retry archive');
            unlink($site . '/src/View');
        }
        $response = transitionFinish($url);
        if ($response['status'] !== 303) { throw new RuntimeException('Transition redirect failed: ' . $response['body'] . "\n" . file_get_contents($site . '/server.log')); }
        $checks++;
        $response = transitionGet($url . '?page=updates');
        transitionCheck($response['status'] === 200 && str_contains($response['body'], $version), 'Redirect serves the actual installed MVC update page');
        $response = transitionGet($url . '?api=bootstrap');
        $bootstrap = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        transitionCheck($response['status'] === 200 && count($bootstrap['editions']['2026']['parameters']) === 20, 'MVC bootstrap and catalogs work after real transition');
        transitionCheck(hash_equals($private, hash_file('sha256', $site . '/config/local.php')), 'Database credentials remain byte-identical');
        transitionCheck(file_get_contents($site . '/config/installation.json') === '{"installed":true}' && file_get_contents($site . '/config/update-settings.json') === '{"channel":"alpha"}', 'Installation state and channel survive');
        foreach (['src/Domain.php', 'src/Updater.php', 'src/MvcTransition.php', $public . '/update.php', $public . '/assets/app.js', $public . '/assets/app.css', 'data/migrations/001-game-catalog.schema.sql'] as $retired) {
            transitionCheck(!file_exists($site . '/' . $retired), 'Known obsolete file removed: ' . $retired);
        }
        transitionCheck(file_get_contents($site . '/' . $public . '/hosting.txt') === 'keep-owner-file' && file_get_contents($site . '/data/migrations/owner.sql') === '-- keep owner file', 'Unrelated owner files are preserved');
        transitionCheck(file_get_contents($site . '/' . $public . '/.htaccess') === '# keep hosting configuration' && file_get_contents($site . '/backups/personal.txt') === 'keep-backup', 'Hosting directives and existing backups survive');
        transitionCheck(!is_file($site . '/config/mvc-transition.zip') && !is_file($site . '/config/mvc-transition.json'), 'Completed transition removes private staging');
        proc_terminate($server); proc_close($server); $server = null;
        buildReleasePackage($root, $package, '9.0.0-alpha.2', $identity);
        (new UpdateInstaller($site, $public))->install($package, '9.0.0-alpha.2', $identity);
        transitionCheck(trim(file_get_contents($site . '/src/VERSION')) === '9.0.0-alpha.2' && !is_file($site . '/src/MvcTransition.php'), 'Subsequent MVC update unwraps without installing temporary shims');
        // Restore the first package for the independent next installation fixture.
        buildReleasePackage($root, $package, $version, $identity);
    }
    echo 'OK: ' . $checks . " released-0.4 migration, rollback, cleanup and subsequent-update checks.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    removeTransitionTree($temporary);
}
