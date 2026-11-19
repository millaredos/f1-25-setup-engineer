<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; script-src 'self'; connect-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
session_name('f125_session');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$error = ''; $channel = 'release'; $result = []; $candidate = null;
$message = $_SESSION['update_result'] ?? ''; unset($_SESSION['update_result']);
$apache = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false;
$protection = new ApacheProtection(__DIR__);
$protectionState = null; $protectionError = '';
if ($apache) {
    try { $protectionState = $protection->inspect(); }
    catch (Throwable $e) { $protectionError = $e->getMessage(); }
}
// Do not hold browser sessions or application read locks during remote requests.
session_write_close();
Updater::releaseRequestLock();
try {
    $channel = (new UpdateSettings(dirname(__DIR__)))->channel();
    $service = appUpdates();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
            http_response_code(403); $error = 'The form session is invalid. Reload the page.';
        } elseif (($_POST['action'] ?? '') === 'protect' && $apache) {
            if (!is_string($_POST['protection_hash'] ?? null)) { throw new RuntimeException('Reload the page before changing Apache protection.'); }
            $protection->apply($_POST['protection_hash']);
            session_start(); $_SESSION['update_result'] = t('Archivo .htaccess guardado. Comprueba que Apache aplica las reglas.'); session_write_close();
            header('Location: update.php', true, 303); exit;
        } elseif (($_POST['action'] ?? '') === 'install') {
            if (!is_string($_POST['identity'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $_POST['identity'])) { throw new RuntimeException('Check for an available build first.'); }
            $backup = $service->install($channel, $_POST['identity'], basename(__DIR__));
            session_start();
            $_SESSION['update_result'] = t('Actualización instalada. Archivos anteriores: {backup}.', ['backup' => $backup]);
            session_write_close();
            header('Location: update.php', true, 303); exit;
        } elseif (($_POST['action'] ?? '') === 'check') { $result = $service->check($channel, true); }
        else { http_response_code(422); $error = 'Unknown update action.'; }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); }
    if (!$result) { $result = $service->check($channel); }
    $candidate = $result['candidate'] ?? null;
    if ($error === '' && isset($result['error'])) { $error = $result['error']; }
} catch (Throwable $e) {
    http_response_code(422);
    error_log('F125 updates: ' . $e->getMessage());
    for ($cause = $e->getPrevious(); $cause !== null; $cause = $cause->getPrevious()) {
        error_log('F125 update cause: ' . $cause->getMessage());
    }
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Cannot complete the update. The installed application has been preserved.';
}
function updateText(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$retryAt = is_int($result['retryAt'] ?? null) && $result['retryAt'] > time() ? $result['retryAt'] : null;
$newer = is_array($candidate) && version_compare($candidate['version'], appVersion(), '>');
?>
<!doctype html>
<html lang="<?= h(appTranslation()->language()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h(t('Actualizaciones · F1 25 / 26')) ?></title><link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>"><script type="module" src="assets/navigation.js?v=<?= appVersion() ?>"></script></head>
<body>
<?php appHeader('updates'); ?>
<main class="installation"><h1><?= h(t('Actualizaciones')) ?></h1>
<p><?= h(t('Instalada:')) ?> <strong id="installed-version"><?= appVersion() ?></strong> <?= h(t('· Canal:')) ?> <strong id="current-channel"><?= updateText(t($channel === 'alpha' ? 'Alpha' : 'Estable')) ?></strong> · <a href="options.php"><?= h(t('Cambiar canal')) ?></a></p>
<?php if ($message): ?><p class="status" role="status"><?= updateText($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="status error" role="alert"><?= updateText(t($error)) ?></p><?php endif; ?>
<?php if ($apache && ($protectionError !== '' || ($protectionState['state'] ?? 'current') !== 'current')): ?>
<section class="panel"><h2><?= h(t('Protección de Apache')) ?></h2>
<?php if ($protectionError !== ''): ?><p class="status error"><?= h(t($protectionError)) ?></p>
<?php else: ?>
<p><?= h(t('El archivo .htaccess no contiene el bloque de protección actual. Puedes añadirlo conservando el resto del archivo.')) ?></p>
<pre class="release-notes"><?= h(ApacheProtection::block()) ?></pre>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="protection_hash" value="<?= h($protectionState['hash']) ?>"><button class="button secondary" name="action" value="protect"><?= h(t('Guardar protección de Apache')) ?></button></form>
<p class="subtle"><?= h(t('Comprueba que Apache permite y aplica estas directivas.')) ?></p>
<?php endif; ?></section>
<?php endif; ?>
<?php if ($retryAt !== null): ?><p class="status" role="status"><?= h(t('Próxima comprobación a partir de')) ?> <time datetime="<?= gmdate('Y-m-d\TH:i:s\Z', $retryAt) ?>"><?= gmdate('Y-m-d H:i:s', $retryAt) ?> UTC</time><?= h(t('. Recarga esta página después de esa hora. La versión instalada se conserva.')) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= updateText($csrf) ?>"><button class="button secondary" name="action" value="check"<?= $retryAt !== null ? ' disabled' : '' ?>><?= h(t('Buscar actualizaciones')) ?></button></form>
<section class="panel">
<?php if (is_array($candidate)): ?>
<h2><?= h(t('Disponible:')) ?> <span id="available-version"><?= updateText($candidate['version']) ?></span></h2>
<p>Commit <?= updateText(substr($candidate['commit'], 0, 12)) ?> · PR #<?= $candidate['pr'] ?></p>
<pre class="release-notes"><?= updateText($candidate['notes']) ?></pre>
<?php if ($newer): ?>
<form method="post" class="update-form"><input type="hidden" name="csrf" value="<?= updateText($csrf) ?>"><input type="hidden" name="identity" value="<?= updateText($candidate['identity']) ?>">
<label><input type="checkbox" required> <?= h(t('He guardado mi borrador y terminado el trabajo en otras pestañas.')) ?></label><button class="button" name="action" value="install"><?= h(t('Actualizar')) ?></button></form>
<?php else: ?><p><?= h(t('El canal no tiene una versión posterior. No se vuelve automáticamente a una versión anterior.')) ?></p><?php endif; ?>
<?php elseif (!$error && is_string($result['available'] ?? null)): ?><p><?= h(t('El canal no tiene una versión posterior. No se vuelve automáticamente a una versión anterior.')) ?></p>
<?php elseif (!$error): ?><p><?= h(t('No hay una versión publicada para este canal.')) ?></p><?php endif; ?>
<p class="subtle"><?= h(t('Las comprobaciones se conservan cinco minutos en Alpha y seis horas en Estable. La instalación empieza al pulsar Actualizar.')) ?></p>
</section></main></body></html>
