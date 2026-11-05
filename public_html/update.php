<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
session_name('f125_session');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$error = ''; $channel = 'release'; $result = []; $candidate = null;
$message = $_SESSION['update_result'] ?? ''; unset($_SESSION['update_result']);
// Do not hold browser sessions or application read locks during remote requests.
session_write_close();
Updater::releaseRequestLock();
try {
    $channel = (new UpdateSettings(dirname(__DIR__)))->channel();
    $service = appUpdates();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
            http_response_code(403); $error = 'The form session is invalid. Reload the page.';
        } elseif (($_POST['action'] ?? '') === 'install') {
            if (!is_string($_POST['identity'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $_POST['identity'])) { throw new RuntimeException('Check for an available build first.'); }
            $backup = $service->install($channel, $_POST['identity'], basename(__DIR__));
            session_start();
            $_SESSION['update_result'] = 'Update installed. Previous files: ' . $backup . '.';
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
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Cannot complete the update. The installed application has been preserved.';
}
function updateText(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$newer = is_array($candidate) && version_compare($candidate['version'], appVersion(), '>');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Updates · F1 25</title><link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>"></head>
<body>
<header class="masthead"><a class="brand" href="./">F1 25 SETUP ENGINEER</a><nav class="app-nav" aria-label="Sections"><a href="./">Home</a><a href="update.php" aria-current="page">Updates</a><a href="options.php">Options</a></nav><span class="driver">Version <?= appVersion() ?></span></header>
<main class="installation"><h1>Updates</h1>
<p>Installed: <strong id="installed-version"><?= appVersion() ?></strong> · Channel: <strong id="current-channel"><?= updateText(ucfirst($channel)) ?></strong> · <a href="options.php">Change channel</a></p>
<?php if ($message): ?><p class="status" role="status"><?= updateText($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="status error" role="alert"><?= updateText($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= updateText($csrf) ?>"><button class="button secondary" name="action" value="check">Check for updates</button></form>
<section class="panel">
<?php if (is_array($candidate)): ?>
<h2>Available: <span id="available-version"><?= updateText($candidate['version']) ?></span></h2>
<p>Commit <?= updateText(substr($candidate['commit'], 0, 12)) ?> · PR #<?= $candidate['pr'] ?></p>
<pre class="release-notes"><?= updateText($candidate['notes']) ?></pre>
<?php if ($newer): ?>
<form method="post" class="update-form"><input type="hidden" name="csrf" value="<?= updateText($csrf) ?>"><input type="hidden" name="identity" value="<?= updateText($candidate['identity']) ?>">
<label><input type="checkbox" required> I have saved my draft and finished work in other tabs.</label><button class="button" name="action" value="install">Update</button></form>
<?php else: ?><p>The channel has no newer build. Automatic downgrades are disabled.</p><?php endif; ?>
<?php elseif (!$error): ?><p>No published build is available for this channel. For a private repository, check the server token configuration.</p><?php endif; ?>
<p class="subtle">Checks are cached for five minutes on Alpha and six hours on Release. Installation starts only when you click Update.</p>
</section></main></body></html>
