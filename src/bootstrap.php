<?php
declare(strict_types=1);

require_once __DIR__ . '/Updater.php';
if (PHP_SAPI !== 'cli') {
    try { Updater::guard(dirname(__DIR__)); }
    catch (Throwable $error) {
        error_log('F125 update guard: ' . $error->getMessage());
        http_response_code(503);
        header('Retry-After: 5');
        header('Cache-Control: no-store');
        // Do not load a catalog while an update may be replacing application files.
        exit('La aplicación se está actualizando o recuperando. Vuelve a intentarlo en unos instantes.');
    }
}
require_once __DIR__ . '/Translation.php';
require_once __DIR__ . '/Header.php';
require_once __DIR__ . '/Updates.php';
require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Store.php';
require_once __DIR__ . '/InstallationState.php';

function appInstallation(): InstallationState
{
    return new InstallationState(dirname(__DIR__) . '/config/installation.json');
}

function redirectForInstallation(bool $installer): void
{
    header('Cache-Control: no-store');
    try {
        $installed = appInstallation()->isInstalled();
    } catch (Throwable $e) {
        error_log('F125 estado de instalación: ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit(t('No se pudo leer el estado de instalación. Revisa config/installation.json.'));
    }
    $destination = null;
    if ($installer && $installed) { $destination = './'; }
    if (!$installer && !$installed) { $destination = 'install.php'; }
    if ($destination !== null) {
        header('Location: ' . $destination, true, $_SERVER['REQUEST_METHOD'] === 'POST' ? 303 : 302);
        exit;
    }
}

function completeInstallation(Store $store, InstallationState $state): void
{
    $state->withLock(static function () use ($store, $state): void {
        if ($state->isInstalled()) { return; }
        // Si las tablas ya estaban preparadas, basta con finalizar el estado local.
        try { $store->ready(); } catch (Throwable $e) { $store->initialize(); }
        $store->ready();
        $state->markInstalled();
    });
}

function appConfig(): array
{
    $defaults = [
        'dsn' => getenv('F125_DSN') ?: '',
        'username' => getenv('F125_DB_USER') ?: null,
        'password' => getenv('F125_DB_PASSWORD') ?: null,
        'github_token' => getenv('F125_GITHUB_TOKEN') ?: '',
    ];
    $local = dirname(__DIR__) . '/config/local.php';
    return is_file($local) ? array_replace($defaults, require $local) : $defaults;
}

function appDomain(): Domain
{
    return new Domain(dirname(__DIR__) . '/data', [appTranslation(), 'text']);
}

function appVersion(): string
{
    return trim(file_get_contents(__DIR__ . '/VERSION'));
}

function appUpdates(): GitHubUpdates
{
    $config = appConfig();
    return new GitHubUpdates(dirname(__DIR__), new GitHubClient($config['github_token'], $config['github_transport'] ?? null));
}
