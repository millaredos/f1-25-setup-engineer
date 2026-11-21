<?php
declare(strict_types=1);

/** Run real HTTP requests against a temporary PHP site; no production configuration is copied. */
$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/f125-http-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);

/** Copy only application payload, excluding credentials and links. */
function copyHttpTree(string $source, string $target): void
{
    mkdir($target, 0700);
    foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isLink()) { throw new RuntimeException('Test source has a symlink.'); }
        if ($file->isDir()) { copyHttpTree($file->getPathname(), $target . '/' . $file->getFilename()); }
        else { copy($file->getPathname(), $target . '/' . $file->getFilename()); }
    }
}

/** Remove the test-owned tree after its server has stopped. */
function removeHttpTree(string $path): void
{
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isDir() && !$file->isLink()) { removeHttpTree($file->getPathname()); } else { unlink($file->getPathname()); }
    }
    rmdir($path);
}

/** Use explicit status parsing and cookie handling so failed HTTP responses cannot masquerade as success. */
function httpCall(string $url, string $method = 'GET', ?array $body = null, string $cookie = '', string $csrf = ''): array
{
    $headers = "Content-Type: application/json\r\n";
    if ($cookie !== '') { $headers .= 'Cookie: ' . $cookie . "\r\n"; }
    if ($csrf !== '') { $headers .= 'X-CSRF-Token: ' . $csrf . "\r\n"; }
    $options = ['http' => ['method' => $method, 'header' => $headers, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]];
    if ($body !== null) { $options['http']['content'] = json_encode($body, JSON_THROW_ON_ERROR); }
    $bytes = file_get_contents($url, false, stream_context_create($options));
    $status = 0; $newCookie = $cookie;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('~^HTTP/\S+ (\d+)~', $header, $match)) { $status = (int) $match[1]; }
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) { $newCookie = $match[1]; }
    }
    return ['status' => $status, 'body' => $bytes, 'cookie' => $newCookie];
}

$checks = 0;
$process = null;
try {
    foreach (['src', 'public_html', 'data'] as $directory) { copyHttpTree($root . '/' . $directory, $temp . '/' . $directory); }
    mkdir($temp . '/config');
    file_put_contents($temp . '/config/local.php', "<?php return ['dsn'=>'mysql:host=127.0.0.1;port=1;dbname=never','username'=>'','password'=>''];");
    foreach (['/', '/tools/setups/'] as $prefix) {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if (!$socket) { throw new RuntimeException('Cannot reserve local test port.'); }
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $url = 'http://' . $address . $prefix;
        $env = getenv(); $env['F125_TEST_ROOT'] = $temp; $env['F125_TEST_BASE_PATH'] = $prefix;
        $process = proc_open([PHP_BINARY, '-d', 'register_argc_argv=0', '-d', 'display_errors=0', '-S', $address, $root . '/dev/serve-router.php'], [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes, $root, $env);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start PHP test server.'); }
        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) { $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1); if ($connection) { fclose($connection); $ready = true; break; } usleep(100000); }
        if (!$ready) { throw new RuntimeException('PHP server did not start.'); }
        @unlink($temp . '/config/installation.json');
        $response = httpCall($url . '?api=bootstrap');
        if ($response['status'] !== 409) { throw new RuntimeException('API must be blocked before installation.'); } $checks++;
        $response = httpCall($url);
        if ($response['status'] !== 302) { throw new RuntimeException('Missing state must redirect to installer.'); } $checks++;
        file_put_contents($temp . '/config/installation.json', '{"installed":true}');
        $response = httpCall($url . '?api=bootstrap');
        $data = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        if ($response['status'] !== 200 || count($data['editions']['2026']['parameters']) !== 20 || $data['storageReady'] !== false) { throw new RuntimeException('Offline bootstrap must retain product data.'); } $checks++;
        $cookie = $response['cookie']; $token = $data['csrf'];
        if (httpCall($url . '?api=compare', 'POST', [], $cookie)['status'] !== 403) { throw new RuntimeException('Missing CSRF must be rejected.'); } $checks++;
        $base = $data['editions']['2026']['presets'][3];
        $response = httpCall($url . '?api=compare', 'POST', ['season' => '2026', 'reference' => $base, 'current' => $base], $cookie, $token);
        $result = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        if ($response['status'] !== 200 || $result['changes'] !== []) { throw new RuntimeException('Comparison contract failed.'); } $checks++;
        foreach (['home', 'circuits', 'workshop', 'tests', 'options', 'updates', 'database', 'faq'] as $page) {
            $response = httpCall($url . '?page=' . $page, 'GET', null, $cookie);
            if ($response['status'] !== 200 || !str_contains($response['body'], '<main')) { throw new RuntimeException('Page failed: ' . $page); } $checks++;
        }
        if (httpCall($url . 'src/bootstrap.php')['status'] !== 404) { throw new RuntimeException('Private source exposed.'); } $checks++;
        if (httpCall($url . 'assets/workshop.js')['status'] !== 200) { throw new RuntimeException('Module route failed.'); } $checks++;
        proc_terminate($process); proc_close($process); $process = null;
    }
    echo 'OK: ' . $checks . " HTTP checks in root and subdirectory.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    removeHttpTree($temp);
}
