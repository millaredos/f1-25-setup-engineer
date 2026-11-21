<?php
declare(strict_types=1);

namespace App\Controller;

use App\Application\Installation;
use App\Http\Response;
use App\Http\Session;
use App\Http\View;
use App\Infrastructure\Catalog;
use App\Infrastructure\JsonFile;
use App\Model\Fields;
use App\Persistence\SetupRepository;
use App\Infrastructure\Database;

/** Coordinates HTML pages while views remain unaware of PDO and request validation. */
final class PageController
{
    private string $root;
    private Catalog $catalog;
    private Installation $installation;
    private string $public;

    /** Share product data and installation state across page actions. */
    public function __construct(string $root, Catalog $catalog, Installation $installation, string $public = 'public_html')
    {
        $this->root = $root;
        $this->catalog = $catalog;
        $this->installation = $installation;
        $this->public = $public;
    }

    /** Dispatch a closed set of templates and explicit POST operations. */
    public function show(string $page): void
    {
        $allowed = ['home', 'circuits', 'workshop', 'tests', 'options', 'updates', 'database', 'faq', 'install'];
        if (!in_array($page, $allowed, true)) {
            http_response_code(404);
            View::render('error', ['message' => 'Página no encontrada.', 'page' => '']);
            return;
        }
        $error = '';
        $message = '';
        $candidate = null;
        $channel = JsonFile::read($this->root . '/config/update-settings.json', ['channel' => 'release'])['channel'] ?? 'release';
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                Session::verify($_POST['csrf'] ?? null);
                switch ($page) {
                    case 'install':
                        $this->installation->install();
                        Response::redirect('./');
                        return;
                    case 'options':
                        Fields::choice($_POST['language'] ?? 'es', ['es'], 'Idioma');
                        $channel = Fields::choice($_POST['channel'] ?? null, ['release', 'alpha'], 'Canal');
                        JsonFile::write($this->root . '/config/update-settings.json', ['channel' => $channel]);
                        $message = 'Opciones guardadas.';
                        break;
                    case 'database':
                        (new SetupRepository(Database::connect($this->root)))->ready();
                        $message = 'Almacenamiento compatible. Los reglajes personales se conservan.';
                        break;
                    case 'updates':
                        [$message, $candidate] = (new UpdatesController($this->root, $this->public))->submit($channel);
                        break;
                    default:
                        throw new \App\Model\ValidationError('Esta página no admite esa operación.');
                }
            } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                http_response_code(405);
                header('Allow: GET, POST');
                $error = 'Método no admitido.';
            }
        } catch (\App\Model\ValidationError $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
        } catch (\Throwable $exception) {
            error_log('Setup page: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'No se pudo completar la operación. Revisa la configuración privada y los permisos.';
        }
        $checks = ['PHP 8.0+' => PHP_VERSION_ID >= 80000, 'PDO_MYSQL' => in_array('mysql', \PDO::getAvailableDrivers(), true), 'JSON' => extension_loaded('json'), 'ZIP (actualizaciones)' => class_exists('ZipArchive'), 'cURL (actualizaciones)' => extension_loaded('curl'), 'Configuración privada' => is_file($this->root . '/config/local.php') || (bool) getenv('F125_DSN'), 'Directorio de configuración escribible' => is_writable($this->root . '/config')];
        $protection = $page === 'updates' && stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false ? (new \App\Infrastructure\PublicProtection($this->root . '/' . $this->public))->inspect() : null;
        View::render($page, ['page' => $page, 'editions' => $this->catalog->editions(), 'csrf' => $_SESSION['csrf'], 'error' => $error, 'message' => $message, 'channel' => $channel, 'checks' => $checks, 'candidate' => $candidate, 'version' => trim(file_get_contents($this->root . '/src/VERSION')), 'protection' => $protection]);
    }
}
