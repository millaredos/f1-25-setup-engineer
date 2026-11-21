<?php
declare(strict_types=1);

/** Generated transport bootstrap: completes only the update already authorized through the 0.4 POST. */
$root = dirname(__DIR__);
$public = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? $root . '/public_html/index.php'));
session_name('f125_session');
session_start(['use_strict_mode' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
session_write_close();
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
header('Content-Type: text/html; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // The redirect is read-only; an external script submits the CSRF-protected continuation POST.
    $safe = htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Completando actualización</title><p role="status">Completando actualización…</p><form id="mvc-transition-form" method="post" action="./index.php"><input type="hidden" name="csrf" value="' . $safe . '"><button>Continuar</button></form><script src="assets/navigation.js" defer></script></html>';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
    http_response_code(403);
    echo 'Sesión inválida. Recarga la página de actualización.';
    exit;
}
$lock = fopen($root . '/config/update.lock', 'c');
$archive = $root . '/config/mvc-transition.zip';
$receipt = $root . '/config/mvc-transition.json';
try {
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Cannot acquire transition lock.');
    }
    // A concurrent request may have finished while this request waited for exclusive ownership.
    if (is_file(__FILE__)) {
        if (!is_file($receipt)) {
            $data = json_decode(file_get_contents($root . '/data/parameters.json'), true, 64, JSON_THROW_ON_ERROR)['mvcPackage'] ?? null;
            if (!is_array($data) || !is_string($data['archive'] ?? null) || strlen($data['archive']) > 2000000 || !preg_match('/^[a-f0-9]{64}$/D', $data['sha256'] ?? '')) {
                throw new RuntimeException('Invalid transition data.');
            }
            $bytes = base64_decode($data['archive'], true);
            if (!is_string($bytes) || !hash_equals($data['sha256'], hash('sha256', $bytes))) {
                throw new RuntimeException('Transition digest mismatch.');
            }
            if (is_link($archive) || is_link($receipt) || file_put_contents($archive, $bytes) !== strlen($bytes) || !chmod($archive, 0600)) {
                throw new RuntimeException('Cannot stage private transition archive.');
            }
            unset($data['archive']);
            $temp = tempnam($root . '/config', '.mvc-state-');
            if (!$temp || file_put_contents($temp, json_encode($data, JSON_THROW_ON_ERROR)) === false || !chmod($temp, 0600) || !rename($temp, $receipt)) {
                throw new RuntimeException('Cannot save transition receipt.');
            }
        }
        $data = json_decode(file_get_contents($receipt), true, 32, JSON_THROW_ON_ERROR);
        if (is_link($archive) || !hash_equals($data['sha256'], hash_file('sha256', $archive))) {
            throw new RuntimeException('Staged transition archive changed.');
        }
        // The complete archive was authenticated by 0.4 before this bootstrap was installed.
        require_once 'zip://' . $archive . '#src/Infrastructure/JsonFile.php';
        require_once 'zip://' . $archive . '#src/Application/LegacyPackage.php';
        require_once 'zip://' . $archive . '#src/Application/UpdateInstaller.php';
        (new App\Application\UpdateInstaller($root, $public))->completeLegacyTransition($archive, $data['version'], $data['identity']);
        if (!unlink($receipt) || !unlink($archive)) {
            throw new RuntimeException('Cannot remove completed transition staging.');
        }
    }
    header('Cache-Control: no-store');
    header('Location: ./?page=updates', true, 303);
} catch (Throwable $error) {
    // If recovery restored 0.4, a later update must not inherit this attempt's receipt.
    if (!is_file(__FILE__) && is_file($receipt)) {
        if (is_file($archive)) { unlink($archive); }
        unlink($receipt);
    }
    error_log('Setup MVC transition: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo completar la actualización. Los datos personales se conservan. Recarga para reintentar; si persiste, revisa espacio y permisos.';
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
exit;
