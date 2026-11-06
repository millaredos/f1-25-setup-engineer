<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(true);
header("Content-Security-Policy: default-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
session_name('f125_session');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function escapeInstall(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$requirements = [
    t('PHP 8.0 o posterior') => PHP_VERSION_ID >= 80000,
    'JSON' => extension_loaded('json'),
    t('Sesiones') => extension_loaded('session'),
    t('PDO_MYSQL para MariaDB') => class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers(), true),
    t('Guardar estado de instalación') => appInstallation()->canWrite(),
];
$store = null;
$error = '';
$config = [];
$databaseLabel = '';
try {
    $config = appConfig();
    if (is_string($config['dsn']) && preg_match('/(?:^|;)dbname=([^;]+)/i', substr($config['dsn'], 6), $match)) {
        $databaseLabel = $match[1];
    }
    if (!is_string($config['dsn']) || !str_starts_with($config['dsn'], 'mysql:') || empty($config['username'])) {
        $error = t('Completa la conexión en config/local.php.');
    } elseif (!in_array(false, $requirements, true)) {
        $store = new Store($config);
    }
} catch (Throwable $e) {
    error_log('F125 instalación: ' . $e->getMessage());
    $error = t('No se pudo conectar. Revisa los datos de config/local.php.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        $error = t('La sesión del formulario no es válida. Recarga esta página e inténtalo de nuevo.');
    } elseif ($store === null || in_array(false, $requirements, true)) {
        http_response_code(422);
        if ($error === '') { $error = t('Completa los requisitos antes de instalar.'); }
    } else {
        try {
            // GET solo comprueba. Las tablas se crean exclusivamente al pulsar el botón.
            completeInstallation($store, appInstallation());
            header('Location: ./', true, 303);
            exit;
        } catch (Throwable $e) {
            http_response_code(503);
            error_log('F125 creación de tablas: ' . $e->getMessage());
            $error = t('No se pudo completar la instalación. Revisa los permisos de la base y de config/ e inténtalo de nuevo.');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    $error = t('Método no admitido.');
}
?>
<!doctype html>
<html lang="<?= h(appTranslation()->language()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(t('Instalar · F1 25 Setup Engineer')) ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="installation">
  <p class="eyebrow">F1 25 SETUP ENGINEER</p>
  <h1><?= h(t('Instalación')) ?></h1>
  <?php if ($error !== ''): ?><p class="status error" role="alert"><?= escapeInstall($error) ?></p><?php endif; ?>
  <section class="panel">
    <h2><?= h(t('Comprobaciones previas')) ?></h2>
    <p class="subtle">PHP <?= escapeInstall(PHP_VERSION) ?></p>
    <?php if ($databaseLabel !== ''): ?><p><?= h(t('Base de datos configurada:')) ?> <strong><?= escapeInstall($databaseLabel) ?></strong>.</p><?php endif; ?>
    <ul class="installation-checks">
      <?php foreach ($requirements as $name => $ok): ?>
        <li><strong><?= escapeInstall($ok ? t('Correcto') : t('Pendiente')) ?></strong> · <?= escapeInstall(t($name)) ?></li>
      <?php endforeach; ?>
      <li><strong><?= escapeInstall($store !== null ? t('Correcto') : t('Pendiente')) ?></strong> <?= h(t('· Conexión con MariaDB')) ?></li>
    </ul>
    <?php if ($store !== null && !in_array(false, $requirements, true)): ?>
      <form method="post" action="install.php">
        <input type="hidden" name="csrf" value="<?= escapeInstall($_SESSION['csrf']) ?>">
        <button class="button" type="submit"><?= h(t('Instalar')) ?></button>
      </form>
    <?php else: ?>
      <p><?= h(t('Resuelve los puntos pendientes y recarga la página.')) ?></p>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
