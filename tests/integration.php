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
foreach (['f125_schema_meta', 'f125_circuits', 'f125_setups'] as $table) {
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
    $first = $store->saveSetup($setup);
    $setup['current']['ala_tra']++;
    $second = $store->saveSetup($setup);
    $reopened = new Store($config); $reopened->ready();
    if ($reopened->loadSetup($first)['current']['ala_tra'] !== 23 || $reopened->loadSetup($second)['current']['ala_tra'] !== 24 || count($reopened->setups()) !== 2) { throw new RuntimeException('Falló preservación de versiones o reapertura.'); }
    try { $store->saveCircuit($circuit); throw new RuntimeException('No rechazó circuito duplicado.'); } catch (ValidationError $e) {}
    $bad = $setup; $bad['circuit']['summary'] = 'Otra revisión con el mismo ID';
    try { $store->saveSetup($bad); throw new RuntimeException('No rechazó conflicto.'); } catch (ValidationError $e) {}
    if (count($store->setups()) !== 2) { throw new RuntimeException('La transacción dejó una versión parcial.'); }
    echo "OK: MariaDB, guardado, reapertura, versiones, duplicados y rollback.\n";
} finally {
    // Solo las tres tablas comprobadas como inexistentes al inicio.
    $db->exec('DROP TABLE IF EXISTS f125_setups');
    $db->exec('DROP TABLE IF EXISTS f125_circuits');
    $db->exec('DROP TABLE IF EXISTS f125_schema_meta');
}
