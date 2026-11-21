<?php
declare(strict_types=1);

/** Real MariaDB integration requires an explicitly writable, empty test database. */
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Application\Installation;
use App\Application\SetupCodec;
use App\Infrastructure\Catalog;
use App\Persistence\SetupRepository;

$dsn = getenv('F125_TEST_DSN');
if (!$dsn || getenv('F125_TEST_ALLOW_WRITE') !== 'yes') {
    fwrite(STDERR, "SKIPPED: configure an empty MariaDB database with F125_TEST_DSN and F125_TEST_ALLOW_WRITE=yes.\n");
    exit(2);
}
$username = getenv('F125_TEST_DB_USER') ?: '';
$password = getenv('F125_TEST_DB_PASSWORD') ?: '';
$db = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$name = $db->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($name) || !str_starts_with($name, 'f125_test_')) { throw new RuntimeException('Test database name must start with f125_test_.'); }
foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (str_starts_with($table, 'f125_')) { throw new RuntimeException('Refusing a database containing application tables.'); }
}
$temporary = sys_get_temp_dir() . '/f125-db-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700); mkdir($temporary . '/config', 0700);
try {
    file_put_contents($temporary . '/config/local.php', '<?php return ' . var_export(['dsn' => $dsn, 'username' => $username, 'password' => $password], true) . ';');
    $installation = new Installation($temporary);
    $installation->install();
    $repository = new SetupRepository($db);
    $repository->ready();
    $catalog = new Catalog(dirname(__DIR__, 2) . '/data');
    $base = $catalog->car('2026')->presets()[3];
    $codec = new SetupCodec($catalog);
    $setup = $codec->decode(['format' => 'f125-setup-engineer', 'schemaVersion' => 1, 'season' => '2026', 'reference' => $base, 'current' => $base, 'title' => 'DB contract', 'circuit' => ['id' => 'australia-albert-park', 'origin' => 'game-catalog', 'name' => 'ignored', 'location' => 'ignored']], true);
    $id = $repository->create($setup);
    if ($repository->find($id) != $setup) { throw new RuntimeException('Saved snapshot changed.'); }
    $setup['notes'] = '<script>not executable data</script>';
    $setup['current']['ala_del']++;
    $repository->update($id, $setup);
    $repository->update($id, $setup);
    if ($repository->find($id) != $setup || count($repository->list()) !== 1) { throw new RuntimeException('Update or no-op update failed.'); }
    $collision = $setup;
    $collision['circuit']['name'] = 'conflicting snapshot';
    try { $repository->create($collision); throw new LogicException('Expected identity rejection.'); } catch (\App\Model\ValidationError $expected) { }
    if (count($repository->list()) !== 1) { throw new RuntimeException('Failed transaction leaked a setup.'); }
    $repository->delete($id);
    if ($repository->list() !== []) { throw new RuntimeException('Deletion failed.'); }
    if (!$installation->installed()) { throw new RuntimeException('Installation state was lost.'); }
    echo "OK: real MariaDB create, reopen, update, no-op, collision rollback and delete.\n";
} finally {
    // All identifiers are fixed test tables; never derive DROP targets from external input.
    foreach (['f125_setups', 'f125_circuits', 'f125_schema_meta'] as $table) { $db->exec('DROP TABLE IF EXISTS ' . $table); }
    foreach (glob($temporary . '/config/*') as $file) { unlink($file); }
    rmdir($temporary . '/config'); rmdir($temporary);
}
