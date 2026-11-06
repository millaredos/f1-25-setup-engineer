<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

// Exclusivamente una base de pruebas vacía indicada por el operador.
$dsn = getenv('F125_TEST_DSN');
if (!$dsn || getenv('F125_TEST_ALLOW_WRITE') !== 'yes') {
    fwrite(STDERR, "Omitido: configura F125_TEST_DSN y F125_TEST_ALLOW_WRITE=yes para una base desechable.\n"); exit(2);
}
$config = ['dsn' => $dsn, 'username' => getenv('F125_TEST_DB_USER') ?: null, 'password' => getenv('F125_TEST_DB_PASSWORD') ?: null];
$db = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['f125_schema_meta', 'f125_circuits', 'f125_setups', 'f125_migrations', 'f125_game_presets', 'f125_game_circuits'] as $table) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    if ($stmt->fetchColumn() != 0) { throw new RuntimeException('La base no está vacía de tablas f125_; no se tocará.'); }
}
$domain = appDomain();
$base = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy-setup.json'), true)['reference'];
$circuit = $domain->circuit(['id' => 'test', 'name' => 'Prueba ficticia', 'downforce' => 'mixed', 'summary' => 'Solo pruebas', 'sources' => [['title' => 'Prueba', 'url' => 'https://example.com', 'accessedAt' => '2026-11-04']]]);
$setup = $domain->setup(['title' => 'Versión uno', 'circuit' => $circuit, 'reference' => $base, 'current' => $base]);
try {
    $store = new Store($config); $store->initialize();
    $game = $store->gameCatalog();
    if (count($game['presets']) !== 5 || count($game['circuits']) !== 25 || $store->pendingMigrations()) { throw new RuntimeException('Incomplete game catalog.'); }
    foreach ($game['presets'] as $preset) { $domain->values($preset['values']); }
    $assignments = json_decode(file_get_contents(__DIR__ . '/fixtures/circuit-presets.json'), true, 64, JSON_THROW_ON_ERROR);
    $assignments['australia-albert-park'] = 3;
    foreach ($game['circuits'] as $circuit) {
        if ($circuit['preset_id'] !== ($assignments[$circuit['id']] ?? null)) { throw new RuntimeException('Incorrect preset assignment.'); }
    }
    $first = $store->saveSetup($setup);
    $setup['current']['ala_tra']++;
    $second = $store->saveSetup($setup);
    $reopened = new Store($config); $reopened->ready();
    if ($reopened->loadSetup($first)['current']['ala_tra'] !== 23 || $reopened->loadSetup($second)['current']['ala_tra'] !== 24 || count($reopened->setups()) !== 2) { throw new RuntimeException('Falló preservación de versiones o reapertura.'); }
    try { $store->saveCircuit($circuit); throw new RuntimeException('No rechazó circuito duplicado.'); } catch (ValidationError $e) {}
    $bad = $setup; $bad['circuit']['summary'] = 'Otra revisión con el mismo ID';
    try { $store->saveSetup($bad); throw new RuntimeException('No rechazó conflicto.'); } catch (ValidationError $e) {}
    if (count($store->setups()) !== 2) { throw new RuntimeException('La transacción dejó una versión parcial.'); }
    $store->migrate();
    if ($store->gameCatalog() !== $game || count($store->setups()) !== 2) { throw new RuntimeException('Migration repeat changed existing data.'); }
    // Reproduce an Alpha 14 catalog: only Albert Park has an assignment and migration 001 is applied.
    $db->exec("UPDATE f125_game_circuits SET preset_id=NULL WHERE id <> 'australia-albert-park'");
    $db->exec("DELETE FROM f125_game_circuits WHERE id='imola'");
    $db->exec("DELETE FROM f125_migrations WHERE id='002-circuit-presets'");
    if ($store->pendingMigrations() !== ['002-circuit-presets']) { throw new RuntimeException('Follow-up migration not detected.'); }
    $store->migrate();
    if ($store->gameCatalog() !== $game || count($store->setups()) !== 2) { throw new RuntimeException('Follow-up migration damaged existing data.'); }
    // Simulate an existing schema-1 installation; personal versions must survive catalog migration.
    $db->exec('DROP TABLE f125_game_circuits');
    $db->exec('DROP TABLE f125_game_presets');
    $db->exec('DROP TABLE f125_migrations');
    $store->ready();
    if (!$store->pendingMigrations()) { throw new RuntimeException('Missing migration not detected.'); }
    $store->migrate();
    if ($store->gameCatalog() !== $game || $reopened->loadSetup($first)['current']['ala_tra'] !== 23) { throw new RuntimeException('Existing installation migration damaged data.'); }
    // Force a data failure after preset insertion; data and completion marker must roll back together.
    $db->exec('DELETE FROM f125_game_circuits');
    $db->exec('DELETE FROM f125_game_presets');
    $db->exec('DELETE FROM f125_migrations');
    $db->exec('ALTER TABLE f125_game_circuits ADD CONSTRAINT migration_failure CHECK (round_order < 2)');
    try { $store->migrate(); throw new LogicException('Expected seed failure.'); } catch (PDOException $expected) {}
    if ($db->query('SELECT COUNT(*) FROM f125_game_presets')->fetchColumn() != 0 || $db->query('SELECT COUNT(*) FROM f125_migrations')->fetchColumn() != 0) { throw new RuntimeException('Seed failure did not roll back.'); }
    $db->exec('ALTER TABLE f125_game_circuits DROP CONSTRAINT migration_failure');
    $store->migrate();
    if ($store->gameCatalog() !== $game) { throw new RuntimeException('Migration retry failed.'); }
    $ledger = $db->query('SELECT id, checksum FROM f125_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $db->exec("UPDATE f125_migrations SET checksum = REPEAT('0',64)");
    $rejected = false;
    try { $store->pendingMigrations(); } catch (RuntimeException $expected) { $rejected = true; }
    if (!$rejected) { throw new RuntimeException('Expected checksum rejection.'); }
    $restore = $db->prepare('UPDATE f125_migrations SET checksum=? WHERE id=?');
    foreach ($ledger as $id => $checksum) { $restore->execute([$checksum, $id]); }
    echo "OK: MariaDB, guardado, reapertura, versiones, duplicados y rollback.\n";
} finally {
    // Solo las tablas comprobadas como inexistentes al inicio, en orden de dependencias.
    $db->exec('DROP TABLE IF EXISTS f125_game_circuits');
    $db->exec('DROP TABLE IF EXISTS f125_game_presets');
    $db->exec('DROP TABLE IF EXISTS f125_migrations');
    $db->exec('DROP TABLE IF EXISTS f125_setups');
    $db->exec('DROP TABLE IF EXISTS f125_circuits');
    $db->exec('DROP TABLE IF EXISTS f125_schema_meta');
}
