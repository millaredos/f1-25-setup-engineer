<?php
declare(strict_types=1);

/** Additive catalog migrations; the existing setup schema remains compatible. */
final class Migrations
{
    private PDO $db;
    private array $catalog;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->catalog = require __DIR__ . '/migration_catalog.php';
    }

    public function pending(): array
    {
        try {
            $rows = $this->db->query('SELECT id, checksum FROM f125_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $error) {
            // Only a missing ledger means no migrations; connection/permission errors propagate.
            if (($error->errorInfo[1] ?? null) !== 1146) { throw $error; }
            return array_keys($this->catalog);
        }
        foreach ($rows as $id => $checksum) {
            if (!isset($this->catalog[$id]) || !hash_equals($this->catalog[$id]['checksum'], $checksum)) {
                throw new RuntimeException('El historial de migraciones no coincide con esta versión.');
            }
        }
        return array_values(array_diff(array_keys($this->catalog), array_keys($rows)));
    }

    public function apply(): void
    {
        if ($this->db->inTransaction()) { throw new RuntimeException('No se puede migrar dentro de otra transacción.'); }
        $lockName = 'f125-migrations-' . sha1((string) $this->db->query('SELECT DATABASE()')->fetchColumn());
        $lock = $this->db->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) { throw new RuntimeException('Hay otra actualización de la base de datos en curso. Reinténtalo.'); }
        try {
            $this->db->exec('CREATE TABLE IF NOT EXISTS f125_migrations (id VARCHAR(100) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at VARCHAR(30) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            foreach ($this->pending() as $id) {
                $migration = $this->catalog[$id];
                // MariaDB commits DDL implicitly. Empty tables can remain after failure and be reused.
                foreach ($migration['schema'] as $sql) { $this->db->exec($sql); }
                $this->db->beginTransaction();
                try {
                    foreach ($migration['data'] as $sql) { $this->db->exec($sql); }
                    $record = $this->db->prepare('INSERT INTO f125_migrations (id, checksum, applied_at) VALUES (?, ?, ?)');
                    $record->execute([$id, $migration['checksum'], gmdate('c')]);
                    $this->db->commit();
                } catch (Throwable $error) {
                    if ($this->db->inTransaction()) { $this->db->rollBack(); }
                    throw $error;
                }
            }
        } finally {
            $release = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }
}
