<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
session_name('f125_session');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$settings = new UpdateSettings(dirname(__DIR__)); $error = ''; $saved = false; $channel = 'release';
try {
    $channel = $settings->channel();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403); $error = 'The form session is invalid. Reload the page.';
        } else {
            if (!is_string($_POST['channel'] ?? null)) { throw new RuntimeException('Choose an update channel.'); }
            $settings->save($_POST['channel']);
            header('Location: options.php?saved=1', true, 303); exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); }
    $saved = ($_GET['saved'] ?? '') === '1';
} catch (Throwable $e) {
    http_response_code(422); $error = $e instanceof RuntimeException ? $e->getMessage() : 'Cannot read or save the update settings.';
}
function optionText(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Options · F1 25</title><link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>"></head>
<body>
<header class="masthead"><a class="brand" href="./">F1 25 SETUP ENGINEER</a><nav class="app-nav" aria-label="Sections"><a href="./">Home</a><a href="update.php">Updates</a><a href="options.php" aria-current="page">Options</a></nav><span class="driver">Version <?= appVersion() ?></span></header>
<main class="installation"><h1>Options</h1>
<?php if ($error): ?><p class="status error" role="alert"><?= optionText($error) ?></p><?php endif; ?>
<?php if ($saved): ?><p class="status" role="status">Update channel saved.</p><?php endif; ?>
<section class="panel"><h2>Updates</h2>
<form method="post" class="update-form">
<input type="hidden" name="csrf" value="<?= optionText($_SESSION['csrf']) ?>">
<label>Channel<select name="channel" id="update-channel"><option value="release" <?= $channel === 'release' ? 'selected' : '' ?>>Release</option><option value="alpha" <?= $channel === 'alpha' ? 'selected' : '' ?>>Alpha</option></select></label>
<p>Release receives published stable versions. Alpha receives tested builds from the active release PR and may contain unfinished changes.</p>
<button class="button" type="submit">Save options</button>
</form>
<p><a href="update.php">Check for updates</a></p>
<p class="subtle">Changing channels does not install or downgrade the application. A private GitHub repository needs a read-only token configured on the server.</p>
</section></main></body></html>
