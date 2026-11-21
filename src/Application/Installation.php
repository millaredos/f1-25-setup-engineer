<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Database;
use App\Infrastructure\JsonFile;
use App\Persistence\SetupRepository;

/** Installation state is independent of subsequent MariaDB availability. */
final class Installation
{
    private string $root;

    /** Locate private state outside the webroot. */
    public function __construct(string $root)
    {
        $this->root = $root;
    }

    /** Corrupt state is an error, never permission to reinstall over existing data. */
    public function installed(): bool
    {
        $state = JsonFile::read($this->root . '/config/installation.json', ['installed' => false]);
        if (!is_bool($state['installed'] ?? null)) {
            throw new \RuntimeException('Invalid installation state.');
        }
        return $state['installed'];
    }

    /** Serialize explicit setup; DDL is idempotent and remains outside MariaDB transactions. */
    public function install(): void
    {
        $lock = fopen($this->root . '/config/installation.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock installation.');
        }
        try {
            if ($this->installed()) {
                return;
            }
            $db = Database::connect($this->root);
            $db->exec('CREATE TABLE IF NOT EXISTS f125_schema_meta (version INTEGER PRIMARY KEY) ENGINE=InnoDB');
            $db->exec('CREATE TABLE IF NOT EXISTS f125_circuits (id VARCHAR(80) COLLATE utf8mb4_bin PRIMARY KEY,payload MEDIUMTEXT NOT NULL,created_at VARCHAR(30) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->exec('CREATE TABLE IF NOT EXISTS f125_setups (id VARCHAR(40) PRIMARY KEY,circuit_id VARCHAR(80) COLLATE utf8mb4_bin NOT NULL,title VARCHAR(120) NOT NULL,payload MEDIUMTEXT NOT NULL,created_at VARCHAR(30) NOT NULL,FOREIGN KEY (circuit_id) REFERENCES f125_circuits(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            if ((int) $db->query('SELECT COUNT(*) FROM f125_schema_meta')->fetchColumn() === 0) {
                $db->exec('INSERT INTO f125_schema_meta (version) VALUES (1)');
            }
            (new SetupRepository($db))->ready();
            JsonFile::write($this->root . '/config/installation.json', ['installed' => true]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
