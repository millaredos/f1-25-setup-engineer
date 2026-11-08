<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; script-src 'self'; connect-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
session_name('f125_session');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = ''; $pending = null; $catalog = null;
try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
        http_response_code(405); header('Allow: GET, POST');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']))) {
        http_response_code(403); $error = t('La sesión del formulario no es válida. Recarga la página.');
    } else {
        $store = new Store(appConfig());
        $store->ready();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $store->migrate();
            header('Location: database.php', true, 303); exit;
        }
        $pending = $store->pendingMigrations();
        if (!$pending) { $catalog = $store->gameCatalog(); }
    }
} catch (Throwable $exception) {
    error_log('F125 database migration: ' . $exception->getMessage());
    http_response_code(503);
    $error = t('No se pudo preparar o completar la actualización. Revisa la conexión y los permisos de la base de datos y vuelve a intentarlo.');
}
?>
<!doctype html>
<html lang="<?= h(appTranslation()->language()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h(t('Base de datos')) ?> · F1 25 / 26</title><link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>"><script type="module" src="assets/navigation.js?v=<?= appVersion() ?>"></script></head>
<body>
<?php appHeader('database'); ?>
<main class="installation"><h1><?= h(t('Base de datos')) ?></h1>
<?php if ($error): ?><p class="status error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($pending): ?>
<section class="panel"><h2><?= h(t('Actualización pendiente')) ?></h2>
<p><?= h(t('Incorpora cinco predeterminados y sus asignaciones a 25 circuitos: los 24 del campeonato e Imola. Los reglajes personales se conservan.')) ?></p>
<p><?= h(t('Guarda una copia de la base de datos desde tu panel de alojamiento antes de continuar.')) ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button class="button" type="submit"><?= h(t('Actualizar base de datos')) ?></button></form>
</section>
<?php elseif ($catalog !== null): ?>
<p class="status" role="status"><?= h(t('Base de datos actualizada.')) ?></p>
<section class="panel"><h2><?= h(t('Predeterminados del juego')) ?></h2><ol>
<?php foreach ($catalog['presets'] as $preset): ?><li><?= h(t($preset['name'])) ?></li><?php endforeach; ?>
</ol><p><?= h(t('Catálogo: 25 circuitos con su predeterminado asignado, incluidos los 24 del campeonato e Imola.')) ?></p></section>
<?php endif; ?>
<p><a href="./#circuito"><?= h(t('Volver al taller')) ?></a></p>
</main></body></html>
