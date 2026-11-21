<?php
declare(strict_types=1);

namespace App\Persistence;

use App\Model\ValidationError;

/** PDO storage preserves the original personal tables and JSON payload contract. */
final class SetupRepository
{
    private \PDO $db;

    /** Share one configured connection for atomic setup/circuit writes. */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Verify schema without mistaking a database outage for an uninstalled application. */
    public function ready(): void
    {
        if ($this->db->query('SELECT version FROM f125_schema_meta')->fetchAll(\PDO::FETCH_COLUMN) != [1]) {
            throw new \RuntimeException('Unsupported storage schema.');
        }
        $this->db->query('SELECT id,payload FROM f125_circuits LIMIT 0');
        $this->db->query('SELECT id,circuit_id,title,payload,created_at FROM f125_setups LIMIT 0');
    }

    /** Read circuit snapshots separately from the distributed edition catalogs. */
    public function circuits(): array
    {
        return array_map(static fn (array $row): array => json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR), $this->db->query('SELECT payload FROM f125_circuits ORDER BY id')->fetchAll());
    }

    /** Keep saved identifiers and unknown seasons visible without rewriting old payloads. */
    public function list(): array
    {
        $result = [];
        foreach ($this->db->query('SELECT id,circuit_id,title,payload,created_at FROM f125_setups ORDER BY created_at DESC,id DESC')->fetchAll() as $row) {
            $payload = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
            $row['season'] = $payload['season'] ?? 'unknown';
            unset($row['payload']);
            $result[] = $row;
        }
        return $result;
    }

    /** Retrieve the original snapshot; missing IDs produce a user-facing rejection. */
    public function find(string $id): array
    {
        $query = $this->db->prepare('SELECT payload FROM f125_setups WHERE id=?');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) {
            throw new ValidationError('No existe ese reglaje guardado.');
        }
        return json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
    }

    /** Store a new immutable circuit identity; collisions never silently change its description. */
    public function addCircuit(array $circuit): void
    {
        $query = $this->db->prepare('INSERT INTO f125_circuits (id,payload,created_at) VALUES (?,?,?)');
        try {
            $query->execute([$circuit['id'], json_encode($circuit, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), gmdate('c')]);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new ValidationError('Ese ID de circuito ya existe.');
            }
            throw $error;
        }
    }

    /** Commit the setup and any new circuit together, or leave both unchanged on failure. */
    public function create(array $setup): string
    {
        $id = bin2hex(random_bytes(16));
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('SELECT payload FROM f125_circuits WHERE id=?');
            $query->execute([$setup['circuit']['id']]);
            $existing = $query->fetch();
            $query->closeCursor();
            if (!$existing) {
                $this->addCircuit($setup['circuit']);
            } elseif (json_decode($existing['payload'], true, 64, JSON_THROW_ON_ERROR) != $setup['circuit']) {
                throw new ValidationError('El ID corresponde a otra ficha. Conserva la original o utiliza otro ID.');
            }
            $query = $this->db->prepare('INSERT INTO f125_setups (id,circuit_id,title,payload,created_at) VALUES (?,?,?,?,?)');
            $query->execute([$id, $setup['circuit']['id'], $setup['title'], json_encode($setup, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), gmdate('c')]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
        return $id;
    }

    /** Update only the explicitly selected setup; its circuit identity cannot be replaced. */
    public function update(string $id, array $setup): void
    {
        $existing = $this->find($id);
        if (($existing['circuit']['id'] ?? null) !== $setup['circuit']['id']) {
            throw new ValidationError('No se puede cambiar el circuito del reglaje guardado.');
        }
        $query = $this->db->prepare('UPDATE f125_setups SET title=?,payload=? WHERE id=? AND circuit_id=?');
        $query->execute([$setup['title'], json_encode($setup, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $id, $setup['circuit']['id']]);
        if ($query->rowCount() === 0 && $this->find($id) != $setup) {
            throw new ValidationError('No se pudo guardar el reglaje.');
        }
    }

    /** Delete one existing setup only after the user's explicit action. */
    public function delete(string $id): void
    {
        $query = $this->db->prepare('DELETE FROM f125_setups WHERE id=?');
        $query->execute([$id]);
        if ($query->rowCount() !== 1) {
            throw new ValidationError('No existe ese reglaje guardado.');
        }
    }
}
