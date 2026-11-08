<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/ApacheProtection.php';
$root = sys_get_temp_dir() . '/f125-apache-test-' . bin2hex(random_bytes(6));
mkdir($root); mkdir($root . '/config'); mkdir($root . '/www');
$path = $root . '/www/.htaccess';
function verifyApache(bool $value, string $message): void { if (!$value) { throw new RuntimeException($message); } }
try {
    $protection = new ApacheProtection($root . '/www');
    verifyApache($protection->inspect()['state'] === 'missing', 'missing file');
    $protection->apply('missing');
    verifyApache($protection->inspect()['state'] === 'current', 'new block');
    verifyApache(str_contains(file_get_contents($path), 'ErrorDocument 404'), '404 rule');
    file_put_contents($path, "# admin configuration\n");
    $state = $protection->inspect();
    verifyApache($state['state'] === 'custom', 'custom file');
    try { $protection->apply(str_repeat('0', 64)); throw new RuntimeException('stale hash accepted'); }
    catch (RuntimeException $error) { verifyApache($error->getMessage() !== 'stale hash accepted', 'stale hash rejected'); }
    verifyApache(file_get_contents($path) === "# admin configuration\n", 'stale content preserved');
    $protection->apply($state['hash']);
    verifyApache(str_starts_with(file_get_contents($path), "# admin configuration\n"), 'custom content preserved');
    file_put_contents($path, str_replace('Options -Indexes', 'Options +Indexes', file_get_contents($path)));
    $state = $protection->inspect();
    verifyApache($state['state'] === 'outdated', 'outdated block');
    $protection->apply($state['hash']);
    verifyApache($protection->inspect()['state'] === 'current', 'outdated block replaced');
    verifyApache(str_starts_with(file_get_contents($path), "# admin configuration\n"), 'admin content preserved');
    file_put_contents($path, '# BEGIN F125 SETUP ENGINEER');
    try { $protection->inspect(); throw new RuntimeException('malformed block accepted'); }
    catch (RuntimeException $error) { verifyApache($error->getMessage() !== 'malformed block accepted', 'malformed block rejected'); }
    echo "OK: Apache protection creation, preservation and stale changes.\n";
} finally {
    if (is_file($path)) { unlink($path); }
    if (is_file($root . '/config/installation.json.lock')) { unlink($root . '/config/installation.json.lock'); }
    rmdir($root . '/www'); rmdir($root . '/config'); rmdir($root);
}
