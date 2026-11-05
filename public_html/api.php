<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_name('f125_session');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

try {
    if (!appInstallation()->isInstalled()) {
        respond(['error' => 'Completa la instalación abriendo la aplicación.'], 409);
    }
    $action = $_GET['action'] ?? 'bootstrap';
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET' && $action === 'update-status') {
        // Network discovery does not need the session or an application read lock.
        session_write_close();
        Updater::releaseRequestLock();
        try {
            $channel = (new UpdateSettings(dirname(__DIR__)))->channel();
            $update = appUpdates()->check($channel);
            $available = $update['available'] ?? null;
            respond(['channel' => $channel, 'installed' => appVersion(), 'available' => $available,
                'newer' => is_string($available) && version_compare($available, appVersion(), '>')]);
        } catch (Throwable $error) {
            respond(['available' => null, 'newer' => false]);
        }
    }
    $domain = appDomain();
    if ($method === 'GET' && $action === 'bootstrap') {
        $circuits = []; $setups = []; $storageReady = false;
        try {
            $store = new Store(appConfig());
            $store->ready();
            $circuits = $store->circuits(); $setups = $store->setups(); $storageReady = true;
        } catch (Throwable $e) {
            error_log('F125 storage: ' . $e->getMessage());
        }
        respond(['csrf' => $_SESSION['csrf'], 'catalog' => $domain->catalog, 'symptoms' => $domain->symptoms, 'circuits' => $circuits, 'setups' => $setups, 'storageReady' => $storageReady]);
    }
    if ($method !== 'POST') { respond(['error' => 'Método no admitido.'], 405); }
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        respond(['error' => 'Sesión caducada. Recarga antes de continuar; exporta tu borrador si lo necesitas.'], 403);
    }
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        respond(['error' => 'Se necesita contenido application/json.'], 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 262145);
    if (strlen($raw) > 262144) { respond(['error' => 'El JSON supera 256 KiB.'], 413); }
    $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($body)) { throw new ValidationError('Se necesita un objeto JSON.'); }
    if ($action === 'compare') { respond($domain->compare($body['reference'] ?? null, $body['current'] ?? null)); }
    if ($action === 'import-setup') { respond($domain->importSetup($body)); }
    if ($action === 'import-circuit') { respond(['circuit' => $domain->importCircuit($body)]); }

    if (!in_array($action, ['save-circuit', 'save-setup', 'load-setup'], true)) {
        respond(['error' => 'Acción desconocida.'], 404);
    }
    $validated = null;
    if ($action === 'save-circuit') { $validated = $domain->importCircuit($body); }
    if ($action === 'save-setup') { $validated = $domain->setup($body); }
    $store = new Store(appConfig());
    $store->ready();
    if ($action === 'save-circuit') {
        $store->saveCircuit($validated);
        respond(['circuits' => $store->circuits()], 201);
    }
    if ($action === 'save-setup') {
        $id = $store->saveSetup($validated);
        respond(['id' => $id, 'circuits' => $store->circuits(), 'setups' => $store->setups()], 201);
    }
    if ($action === 'load-setup') {
        if (!is_string($body['id'] ?? null)) { throw new ValidationError('ID inválido.'); }
        respond($store->loadSetup($body['id']));
    }
    respond(['error' => 'Acción desconocida.'], 404);
} catch (ValidationError | JsonException $e) {
    respond(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('F125: ' . $e->getMessage());
    respond(['error' => 'No se pudo acceder al almacenamiento. Revisa configuración, inicialización y permisos; el borrador sigue en pantalla.'], 503);
}
