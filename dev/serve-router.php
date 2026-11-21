<?php
declare(strict_types=1);

/** Test-only router mounts a public directory at a configurable prefix without exposing private data. */
$prefix = getenv('F125_TEST_BASE_PATH') ?: '/';
$root = getenv('F125_TEST_ROOT');
$public = getenv('F125_TEST_PUBLIC') ?: 'public_html';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($root) || !is_string($path) || !str_starts_with($path, $prefix)) { http_response_code(404); exit; }
$relative = substr($path, strlen($prefix));
if ($relative === '') { $relative = 'index.php'; }
if (!preg_match('/^[A-Za-z0-9_-]+$/D', $public) || !preg_match('~^(?:index\.php|api\.php|install\.php|update\.php|assets/[A-Za-z0-9_-]+\.(?:js|css))$~D', $relative)) { http_response_code(404); exit; }
$file = $root . '/' . $public . '/' . $relative;
if (!is_file($file)) { http_response_code(404); exit; }
if (str_ends_with($relative, '.php')) {
    $_SERVER['SCRIPT_NAME'] = $prefix . $relative;
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
} else {
    header('Content-Type: ' . (str_ends_with($relative, '.css') ? 'text/css' : 'text/javascript') . '; charset=utf-8');
    readfile($file);
}
