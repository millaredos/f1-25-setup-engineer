<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Migrations.php';
require dirname(__DIR__) . '/src/Domain.php';

// A recording connection tests transaction boundaries and retry behavior without emulating SQL.
final class MigrationStatement extends PDOStatement
{
    private $run;
    private mixed $value = null;
    public function __construct(callable $run) { $this->run = $run; }
    public function execute(?array $params = null): bool { $this->value = ($this->run)($params); return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->value; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->value; }
}

final class MigrationConnection extends PDO
{
    public array $events = [];
    public array $ledger = [];
    public bool $exists = false;
    public bool $transaction = false;
    public bool $fail = false;
    public int $lockResult = 1;
    private array $before = [];
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $statement = new MigrationStatement(function () use ($query) {
            if (str_contains($query, 'DATABASE()')) { return 'isolated-test'; }
            if (!$this->exists) { $e = new PDOException('missing ledger'); $e->errorInfo = ['42S02', 1146, 'missing']; throw $e; }
            return $this->ledger;
        });
        $statement->execute(); return $statement;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new MigrationStatement(function ($params) use ($query) {
            $this->events[] = $query;
            if (str_contains($query, 'GET_LOCK')) { return $this->lockResult; }
            if (str_starts_with($query, 'INSERT INTO f125_migrations')) { $this->ledger[$params[0]] = $params[1]; }
            return 1;
        });
    }
    public function exec(string $statement): int|false
    {
        $this->events[] = $statement;
        if (str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS f125_migrations')) { $this->exists = true; }
        if (str_starts_with($statement, 'CREATE') && $this->transaction) { throw new LogicException('DDL inside transaction'); }
        if (str_starts_with($statement, 'INSERT') && !$this->transaction) { throw new LogicException('Seed outside transaction'); }
        if ($this->fail && str_starts_with($statement, 'INSERT INTO f125_game_circuits')) { throw new PDOException('injected failure'); }
        return 0;
    }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->before = $this->ledger; $this->events[] = 'BEGIN'; return $this->transaction = true; }
    public function commit(): bool { $this->events[] = 'COMMIT'; $this->transaction = false; return true; }
    public function rollBack(): bool { $this->events[] = 'ROLLBACK'; $this->ledger = $this->before; $this->transaction = false; return true; }
}

$count = 0;
function verifyMigration(bool $condition): void { global $count; if (!$condition) { throw new RuntimeException('Migration assertion failed.'); } $count++; }
$db = new MigrationConnection(); $migrations = new Migrations($db);
verifyMigration($migrations->pending() === ['001-game-catalog']);
$db->fail = true;
try { $migrations->apply(); throw new LogicException('Expected failure'); } catch (PDOException $expected) {}
verifyMigration(!$db->ledger && in_array('ROLLBACK', $db->events, true) && str_contains(end($db->events), 'RELEASE_LOCK'));
$db->fail = false; $migrations->apply();
verifyMigration(!$migrations->pending() && count($db->ledger) === 1 && in_array('COMMIT', $db->events, true));
$db->events = []; $migrations->apply();
verifyMigration(!array_filter($db->events, static fn($sql) => str_starts_with($sql, 'INSERT')));
$db->ledger['001-game-catalog'] = str_repeat('0', 64);
$rejected = false; try { $migrations->pending(); } catch (RuntimeException $expected) { $rejected = true; }
verifyMigration($rejected);
$db = new MigrationConnection(); $db->lockResult = 0;
$rejected = false; try { (new Migrations($db))->apply(); } catch (RuntimeException $expected) { $rejected = true; }
verifyMigration($rejected && count($db->events) === 1 && !$db->exists);

// Validate the actual bundled SQL's 100 numeric values with the production domain contract.
$catalog = require dirname(__DIR__) . '/src/migration_catalog.php';
$sql = $catalog['001-game-catalog']['data'][0];
preg_match('/\(id, name, (.*?)\) VALUES/s', $sql, $columns);
$keys = explode(', ', $columns[1]);
preg_match_all("/\(([1-5]), '([^']+)', ([0-9., -]+)\)/", $sql, $rows, PREG_SET_ORDER);
verifyMigration(count($rows) === 5 && count($keys) === 20);
$domain = new Domain(dirname(__DIR__) . '/data');
$expected = json_decode(file_get_contents(__DIR__ . '/fixtures/game-presets.json'), true, 64, JSON_THROW_ON_ERROR);
foreach ($rows as $row) {
    $values = array_combine($keys, array_map('floatval', explode(', ', $row[3])));
    verifyMigration(count($domain->values($values)) === 20);
    verifyMigration($domain->values($values) === $domain->values($expected[$row[1]]));
}
verifyMigration(!str_contains($sql, '?') && str_contains($sql, 'Máxima carga aerodinámica'));
$circuits = $catalog['001-game-catalog']['data'][1];
preg_match_all("/\('([a-z0-9-]+)', (\d+), '([^']+)', '([^']+)', (NULL|[1-5])\)/", $circuits, $circuitRows, PREG_SET_ORDER);
verifyMigration(count($circuitRows) === 24 && $circuitRows[0][5] === '3');
verifyMigration(!array_filter(array_slice($circuitRows, 1), static fn($c) => $c[5] !== 'NULL'));
echo "OK: $count migration checks (recording PDO; no live database).\n";
