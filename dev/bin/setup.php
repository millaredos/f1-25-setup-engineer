<?php

declare(strict_types=1);

/** Development CLI initialization; end-user installation remains the browser installer. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2) . '/src/bootstrap.php';
try {
    $config = appConfig();
    $store = new Store($config);
    completeInstallation($store, appInstallation());
    echo "Almacenamiento inicializado; no se han añadido circuitos ni reglajes.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
