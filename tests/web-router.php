<?php
declare(strict_types=1);
// Solo se copia al sitio temporal de pruebas para simular un montaje en subcarpeta.
$prefix = getenv('F125_TEST_BASE_PATH') ?: '/';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($path) || !str_starts_with($path, $prefix)) { http_response_code(404); exit; }
$relative = substr($path, strlen($prefix));
if (!preg_match('~^(?:index\.php|install\.php|api\.php|update\.php|options\.php|i18n\.php|assets/[a-zA-Z0-9_-]+\.(?:css|js))?$~D', $relative)) { http_response_code(404); exit; }
$file = __DIR__ . '/' . (getenv('F125_TEST_PUBLIC_NAME') ?: 'public_html') . '/' . ($relative ?: 'index.php');
if (!is_file($file)) { http_response_code(404); exit; }
if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_NAME'] = $prefix . ($relative ?: 'index.php');
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    require $file;
}
else {
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'text/javascript') . '; charset=utf-8');
    readfile($file);
}
