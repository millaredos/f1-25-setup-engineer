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
$settings = new UpdateSettings(dirname(__DIR__));
$error = ''; $channel = 'release'; $language = appTranslation()->language();
try {
    $channel = $settings->channel();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403); $error = t('La sesión del formulario no es válida. Recarga la página.');
        } else {
            $selected = $_POST['language'] ?? null;
            if (!is_string($selected) || !isset(Translation::LANGUAGES[$selected])) { throw new RuntimeException(t('Elige un idioma disponible.')); }
            if (!is_string($_POST['channel'] ?? null)) { throw new RuntimeException(t('Elige un canal de actualización.')); }
            $settings->save($_POST['channel']);
            $cookiePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $cookiePath = rtrim($cookiePath, '/.') . '/';
            setcookie('f125_language', $selected, ['expires' => time() + 31536000, 'path' => $cookiePath,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
            header('Location: options.php?saved=1', true, 303); exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); }
} catch (Throwable $e) {
    http_response_code(422); $error = $e instanceof RuntimeException ? t($e->getMessage()) : t('No se pudieron leer o guardar las opciones.');
}
?>
<!doctype html>
<html lang="<?= h($language) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h(t('Opciones')) ?> · F1 25</title><link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>"><script type="module" src="assets/navigation.js?v=<?= appVersion() ?>"></script></head>
<body>
<?php appHeader('options'); ?>
<main class="installation"><h1><?= h(t('Opciones')) ?></h1>
<?php if ($error): ?><p class="status error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if (($_GET['saved'] ?? '') === '1'): ?><p class="status" role="status"><?= h(t('Opciones guardadas.')) ?></p><?php endif; ?>
<form method="post" class="options-form">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
<section class="panel"><h2><?= h(t('Idioma')) ?></h2>
<label><?= h(t('Idioma de la aplicación')) ?><select name="language" id="language"><?php foreach (Translation::LANGUAGES as $code => $label): ?><option value="<?= h($code) ?>"<?= $language === $code ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<p class="subtle"><?= h(t('El idioma se guarda en este navegador.')) ?></p>
<p><a href="https://github.com/millaredos/f1-25-setup-engineer" target="_blank" rel="noopener noreferrer"><?= h(t('¿Quieres más idiomas? Apoya el proyecto con una estrella en GitHub.')) ?></a></p>
</section>
<section class="panel"><h2><?= h(t('Actualizaciones')) ?></h2>
<label><?= h(t('Canal')) ?><select name="channel" id="update-channel"><option value="release" <?= $channel === 'release' ? 'selected' : '' ?>><?= h(t('Estable')) ?></option><option value="alpha" <?= $channel === 'alpha' ? 'selected' : '' ?>>Alpha</option></select></label>
<p><?= h(t('Estable recibe versiones publicadas como estables. Alpha recibe versiones de prueba y puede incluir cambios sin terminar.')) ?></p>
<p class="subtle"><?= h(t('Cambiar de canal no instala una actualización ni vuelve a una versión anterior.')) ?></p>
<p><a href="update.php"><?= h(t('Buscar actualizaciones')) ?></a></p>
<p><a href="database.php"><?= h(t('Actualizar base de datos')) ?></a></p>
</section>
<button class="button" type="submit"><?= h(t('Guardar opciones')) ?></button>
</form>
</main></body></html>
