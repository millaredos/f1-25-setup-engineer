<?php
declare(strict_types=1);

require_once __DIR__ . '/Migrations.php';

final class Store
{
    private PDO $db;

    public function __construct(array $config)
    {
        $driver = explode(':', $config['dsn'], 2)[0];
        if ($driver !== 'mysql' || !in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('Configura MariaDB mediante PDO_MYSQL en config/local.php.');
        }
        $this->db = new PDO($config['dsn'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function initialize(): void
    {
        // DDL separado de las transacciones de datos: MySQL hace commit implícito.
        $this->db->exec('CREATE TABLE IF NOT EXISTS f125_schema_meta (version INTEGER PRIMARY KEY) ENGINE=InnoDB');
        $this->db->exec('CREATE TABLE IF NOT EXISTS f125_circuits (id VARCHAR(80) COLLATE utf8mb4_bin PRIMARY KEY, payload MEDIUMTEXT NOT NULL, created_at VARCHAR(30) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->db->exec('CREATE TABLE IF NOT EXISTS f125_setups (id VARCHAR(40) PRIMARY KEY, circuit_id VARCHAR(80) COLLATE utf8mb4_bin NOT NULL, title VARCHAR(120) NOT NULL, payload MEDIUMTEXT NOT NULL, created_at VARCHAR(30) NOT NULL, FOREIGN KEY (circuit_id) REFERENCES f125_circuits(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if ($this->db->query('SELECT COUNT(*) FROM f125_schema_meta')->fetchColumn() == 0) {
            $this->db->exec('INSERT INTO f125_schema_meta (version) VALUES (1)');
        }
        $this->ready();
        $this->migrate();
    }

    public function ready(): void
    {
        if ($this->db->query('SELECT version FROM f125_schema_meta')->fetchAll(PDO::FETCH_COLUMN) != [1]) {
            throw new RuntimeException('Versión de almacenamiento no compatible.');
        }
        $this->db->query('SELECT id, payload FROM f125_circuits LIMIT 0');
        $this->db->query('SELECT id, circuit_id, title, payload, created_at FROM f125_setups LIMIT 0');
    }

    public function circuits(): array
    {
        return array_map(static fn(array $row) => json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR), $this->db->query('SELECT payload FROM f125_circuits ORDER BY id')->fetchAll());
    }

    public function pendingMigrations(): array
    {
        return (new Migrations($this->db))->pending();
    }

    public function migrate(): void
    {
        $this->ready();
        (new Migrations($this->db))->apply();
    }

    public function gameCatalog(): array
    {
        if ($this->pendingMigrations()) { throw new RuntimeException('Actualiza la base de datos para cargar el catálogo del juego.'); }
        $presets = $this->db->query('SELECT * FROM f125_game_presets ORDER BY id')->fetchAll();
        foreach ($presets as &$preset) {
            $id = (int) $preset['id']; $name = $preset['name'];
            unset($preset['id'], $preset['name']);
            $values = array_map(static fn($value) => (float) $value, $preset);
            $preset = ['id' => $id, 'name' => $name, 'values' => $values];
        }
        unset($preset);
        $circuits = $this->db->query('SELECT id, round_order, location, name, preset_id FROM f125_game_circuits ORDER BY round_order')->fetchAll();
        foreach ($circuits as &$circuit) {
            $circuit['round_order'] = (int) $circuit['round_order'];
            $circuit['preset_id'] = $circuit['preset_id'] === null ? null : (int) $circuit['preset_id'];
        }
        return ['presets' => $presets, 'circuits' => $circuits];
    }

    public function saveCircuit(array $circuit): void
    {
        $stmt = $this->db->prepare('INSERT INTO f125_circuits (id,payload,created_at) VALUES (?,?,?)');
        try {
            $stmt->execute([$circuit['id'], $this->encode($circuit), gmdate('c')]);
        } catch (PDOException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw new ValidationError('Ya existe ese ID de circuito. No se ha sobrescrito; usa otro ID para una ficha revisada.');
            }
            throw $e;
        }
    }

    public function saveSetup(array $data): string
    {
        $id = bin2hex(random_bytes(16));
        $this->db->beginTransaction();
        try {
            $exists = $this->db->prepare('SELECT payload FROM f125_circuits WHERE id = ?');
            $exists->execute([$data['circuit']['id']]);
            $row = $exists->fetch();
            if (!$row) {
                $this->saveCircuit($data['circuit']);
            } elseif (json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR) !== $data['circuit']) {
                throw new ValidationError('Ese ID corresponde a otra ficha del circuito. Usa un ID nuevo para la revisión.');
            }
            $exists->closeCursor();
            $stmt = $this->db->prepare('INSERT INTO f125_setups (id,circuit_id,title,payload,created_at) VALUES (?,?,?,?,?)');
            $stmt->execute([$id, $data['circuit']['id'], $data['title'], $this->encode($data), gmdate('c')]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $id;
    }

    public function setups(): array
    {
        $rows = $this->db->query('SELECT id,circuit_id,title,created_at,payload FROM f125_setups ORDER BY created_at DESC,id DESC')->fetchAll();
        foreach ($rows as &$row) {
            $payload = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
            $row['season'] = $payload['season'] ?? 'unknown';
            unset($row['payload']);
        }
        return $rows;
    }

    public function loadSetup(string $id): array
    {
        $stmt = $this->db->prepare('SELECT payload FROM f125_setups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new ValidationError('No existe ese reglaje guardado.');
        }
        return json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
