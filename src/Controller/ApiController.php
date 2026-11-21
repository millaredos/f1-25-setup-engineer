<?php
declare(strict_types=1);

namespace App\Controller;

use App\Application\Comparison;
use App\Application\SetupCodec;
use App\Infrastructure\Catalog;
use App\Infrastructure\Database;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Model\Circuit;
use App\Model\Fields;
use App\Model\ValidationError;
use App\Persistence\SetupRepository;

/** HTTP adapter for the editor; pure operations remain usable while MariaDB is offline. */
final class ApiController
{
    private string $root;
    private Catalog $catalog;
    private SetupCodec $codec;

    /** Compose adapters once; repositories are opened lazily only for persistence requests. */
    public function __construct(string $root, Catalog $catalog)
    {
        $this->root = $root;
        $this->catalog = $catalog;
        $this->codec = new SetupCodec($catalog);
    }

    /** Dispatch supported actions, enforcing mutation boundaries before interpreting their payload. */
    public function handle(string $action): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'update-status') {
                session_write_close();
                \App\Infrastructure\RequestLock::release();
                $channel = \App\Infrastructure\JsonFile::read($this->root . '/config/update-settings.json', ['channel' => 'release'])['channel'];
                $discovery = new \App\Application\UpdateDiscovery($this->root, new \App\Infrastructure\HttpClient());
                $result = (new \App\Application\UpdateStatus($this->root, $discovery))->check($channel);
                Response::json(['known' => !isset($result['error']), 'available' => $result['candidate']['version'] ?? null]);
                return;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'bootstrap') {
                Response::json($this->bootstrap());
                return;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                header('Allow: GET, POST');
                Response::json(['error' => 'Método no admitido.'], 405);
                return;
            }
            try {
                Session::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            } catch (ValidationError $error) {
                Response::json(['error' => $error->getMessage()], 403);
                return;
            }
            $body = Request::json();
            $result = $this->execute($action, $body);
            Response::json($result, in_array($action, ['save-setup', 'save-circuit'], true) ? 201 : 200);
        } catch (ValidationError | \JsonException $error) {
            Response::json(['error' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            error_log('Setup API: ' . $error->getMessage());
            Response::json(['error' => 'No se pudo acceder al almacenamiento. El borrador sigue en pantalla.'], 503);
        }
    }

    /** Return catalogs even during an outage, exposing availability separately from product data. */
    private function bootstrap(): array
    {
        $rows = [];
        $circuits = [];
        $ready = false;
        try {
            $repository = $this->repository();
            $rows = $repository->list();
            $circuits = $repository->circuits();
            $ready = true;
        } catch (\Throwable $error) {
            error_log('Setup storage: ' . $error->getMessage());
        }
        $editions = [];
        foreach ($this->catalog->editions() as $id => $edition) {
            $car = $this->catalog->car((string) $id);
            $editions[$id] = ['name' => $edition['name'], 'parameters' => $car->parameters(), 'presets' => $car->presets(), 'circuits' => $this->catalog->circuits((string) $id), 'theory' => $edition['theory']];
        }
        return ['csrf' => $_SESSION['csrf'], 'editions' => $editions, 'setups' => $rows, 'circuits' => $circuits, 'storageReady' => $ready, 'symptoms' => $this->catalog->read('symptoms'), 'circuitProfiles' => $this->catalog->read('circuit-profiles')];
    }

    /** Select application operations without exposing database construction to the model. */
    private function execute(string $action, array $body): array
    {
        switch ($action) {
            case 'compare':
                $car = isset($body['season']) ? $this->catalog->car(Fields::choice($body['season'], array_map('strval', array_keys($this->catalog->editions())), 'Temporada')) : $this->catalog->unidentifiedCar();
                return (new Comparison())->compare($car, $body['reference'] ?? null, $body['current'] ?? null);
            case 'import-setup':
                return $this->codec->decode($body);
            case 'recover-setup':
                return $this->codec->recover($body);
            case 'import-circuit':
                return ['circuit' => $this->importCircuit($body)];
            case 'save-circuit':
                $repository = $this->repository();
                $repository->addCircuit($this->importCircuit($body));
                return ['circuits' => $repository->circuits()];
            case 'save-setup':
                $setup = $this->codec->decode($body, true);
                $repository = $this->repository();
                $id = $repository->create($setup);
                return ['id' => $id, 'setups' => $repository->list(), 'circuits' => $repository->circuits()];
            case 'load-setup':
                return $this->repository()->find($this->identifier($body));
            case 'update-setup':
                $id = $this->identifier($body);
                $setup = $this->codec->decode($body['setup'] ?? null, true);
                $repository = $this->repository();
                $repository->update($id, $setup);
                return ['setups' => $repository->list(), 'circuits' => $repository->circuits()];
            case 'delete-setup':
                $repository = $this->repository();
                $repository->delete($this->identifier($body));
                return ['setups' => $repository->list(), 'circuits' => $repository->circuits()];
            default:
                throw new ValidationError('Acción desconocida.');
        }
    }

    /** Require stable application identifiers before loading or mutating a record. */
    private function identifier(array $body): string
    {
        $id = $body['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new ValidationError('ID inválido.');
        }
        return $id;
    }

    /** Adapt the published Spanish v2 template; generated IDs belong only to new profiles. */
    private function importCircuit(array $body): array
    {
        if (($body['format'] ?? null) !== 'f125-circuit' || !in_array($body['schemaVersion'] ?? null, [1, 2], true) || !is_array($body['circuit'] ?? null)) {
            throw new ValidationError('Formato de circuito inválido.');
        }
        $input = $body['circuit'];
        if ($body['schemaVersion'] === 2 && isset($input['origin']) && $input['origin'] !== 'custom-profile') {
            throw new ValidationError('Origen de ficha personalizado inválido.');
        }
        if ($body['schemaVersion'] === 2 && !isset($input['origin'])) {
            $keys = ['nombre', 'ubicacion', 'longitud_km', 'curvas', 'categoria_carga', 'curvas_y_rectas', 'caracteristicas_conduccion'];
            if (array_diff(array_keys($input), $keys)) {
                throw new ValidationError('Campos de circuito desconocidos.');
            }
            $input = ['id' => 'custom-' . bin2hex(random_bytes(16)), 'origin' => 'custom-profile', 'name' => $input['nombre'] ?? '', 'location' => $input['ubicacion'] ?? '', 'lengthKm' => $input['longitud_km'] ?? null, 'cornerCount' => $input['curvas'] ?? null, 'downforce' => $input['categoria_carga'] ?? '', 'cornersAndStraights' => $input['curvas_y_rectas'] ?? '', 'drivingCharacteristics' => $input['caracteristicas_conduccion'] ?? ''];
        }
        return (new Circuit($input))->data();
    }

    /** Open storage only when required and reject incompatible installations. */
    private function repository(): SetupRepository
    {
        $repository = new SetupRepository(Database::connect($this->root));
        $repository->ready();
        return $repository;
    }
}
