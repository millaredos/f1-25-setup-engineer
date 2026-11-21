<?php
declare(strict_types=1);

/** The only application composition point: route HTTP to controllers, not to model methods. */
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Application\Installation;
use App\Controller\ApiController;
use App\Controller\PageController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Infrastructure\Catalog;
use App\Infrastructure\RequestLock;

ini_set('display_errors', '0');
$root = dirname(__DIR__);
Response::headers();
$installation = new Installation($root);
$isApi = isset($_GET['api']);
try {
    RequestLock::acquire($root, basename(__DIR__));
    Session::start();
    $installed = $installation->installed();
    $page = Request::route('page', 'home');
    if (!$installed && $isApi) {
        Response::json(['error' => 'Completa primero la instalación.'], 409);
        return;
    }
    if (!$installed && $page !== 'install') {
        Response::redirect('install.php');
        return;
    }
    if ($installed && $page === 'install') {
        Response::redirect('./');
        return;
    }
    $catalog = new Catalog($root . '/data');
    if ($isApi) {
        (new ApiController($root, $catalog))->handle(Request::route('api', 'bootstrap'));
    } else {
        (new PageController($root, $catalog, $installation, basename(__DIR__)))->show($page);
    }
} catch (\Throwable $error) {
    error_log('Setup request: ' . $error->getMessage());
    if ($isApi) {
        Response::json(['error' => 'No se pudo abrir la aplicación. Revisa el estado de instalación.'], 503);
    } else {
        http_response_code(503);
        echo 'No se pudo abrir la aplicación. Revisa el estado de instalación y los registros del servidor.';
    }
}
