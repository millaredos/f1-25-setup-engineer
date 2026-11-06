<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$domain = appDomain();
$legacy = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy-setup.json'), true, 64, JSON_THROW_ON_ERROR);
$base = $legacy['reference'];
$count = 0;

function check(bool $condition, string $message): void
{
    global $count;
    if (!$condition) { throw new RuntimeException('FALLO: ' . $message); }
    $count++;
}

function rejects(callable $operation, string $message): void
{
    try { $operation(); } catch (ValidationError $e) { check(true, $message); return; }
    check(false, $message);
}

check(count($domain->catalog['parameters']) === 20, 'veinte parámetros');
$gameDomain = appDomain([['id' => 'australia-albert-park', 'name' => 'Albert Park Circuit', 'location' => 'Australia']]);
$gameCircuit = $gameDomain->circuit(['id' => 'australia-albert-park', 'origin' => 'game-catalog', 'name' => 'Untrusted name', 'downforce' => 'high']);
check($gameCircuit['name'] === 'Albert Park Circuit' && $gameCircuit['downforce'] === 'unknown' && $gameCircuit['sources'] === [], 'catálogo resuelve identidad sin inventar características');
rejects(static fn() => $gameDomain->circuit(['id' => 'unknown', 'origin' => 'game-catalog']), 'catálogo rechaza identificadores desconocidos');
check($domain->circuit($gameCircuit) === $gameCircuit, 'snapshot del catálogo permite recuperación sin conexión');
check($domain->values($base) == $base, 'el JSON heredado conserva sus valores');
check($domain->compare($base, $base)['changes'] === [], 'comparar sin cambios');
$current = $base; $current['cam_del'] = -3.5;
$changes = $domain->compare($base, $current)['changes'];
check($changes[0]['delta'] === -0.1 && $changes[0]['label'] === 'Caída más negativa', 'signo de caída negativa');
check($base['cam_del'] === -3.4, 'referencia inmutable');
$current = $base; $current['ala_del']++; $current['ala_tra']--;
check(count($domain->compare($base, $current)['changes']) === 2, 'alerones opuestos no se cancelan');
foreach ($domain->catalog['parameters'] as $p) {
    foreach ([$p['min'], $p['max']] as $boundary) {
        $input = $base; $input[$p['id']] = $boundary;
        check($domain->values($input)[$p['id']] == $boundary, 'límites de ' . $p['id']);
    }
    $input = $base; $input[$p['id']] = $p['min'] - $p['step'];
    rejects(fn() => $domain->values($input), 'inferior fuera de rango');
    $input[$p['id']] = $p['max'] + $p['step'];
    rejects(fn() => $domain->values($input), 'superior fuera de rango');
    $input[$p['id']] = $p['min'] + $p['step'] / 2;
    rejects(fn() => $domain->values($input), 'no redondear pasos inválidos');
}
foreach ([null, '23', true, INF, NAN, []] as $invalid) {
    $input = $base; $input['ala_del'] = $invalid;
    rejects(fn() => $domain->values($input), 'tipo y finitud');
}
$input = $base; unset($input['ala_del']); rejects(fn() => $domain->values($input), 'parámetro ausente');
$input = $base; $input['extra'] = 1; rejects(fn() => $domain->values($input), 'parámetro desconocido');
foreach ([$legacy, ['values' => $base], $base, ['base' => $base, 'current' => $current]] as $input) {
    $imported = $domain->importSetup($input);
    check(count($imported['current']) === 20 && count($imported['reference']) === 20, 'compatibilidad heredada');
}
rejects(fn() => $domain->importSetup(['format' => 'otro', 'current' => $base]), 'formato desconocido');
rejects(fn() => $domain->importSetup(['format' => 'f125-setup-engineer', 'schemaVersion' => 2, 'current' => $base]), 'versión futura');
check($domain->importSetup(['current' => $base, 'season' => '2025'])['season'] === '2025', 'la temporada se conserva');
check($domain->importSetup(['current' => $base])['season'] === 'unknown', 'la temporada ausente no se inventa');
rejects(fn() => $domain->importSetup(['current' => $base, 'season' => '2024']), 'temporada inválida');
rejects(fn() => $domain->importSetup(array_replace($legacy, ['referenceIsCapture' => 'sí'])), 'booleano heredado');
$circuit = [
    'id' => 'circuito-de-prueba', 'name' => 'Circuito de prueba', 'layout' => 'Ficticio', 'downforce' => 'mixed',
    'summary' => 'Datos ficticios exclusivos de las pruebas, no importar para conducir.', 'characteristics' => [],
    'sources' => [['title' => 'Fuente ficticia de prueba', 'url' => 'https://example.com', 'accessedAt' => '2026-11-04']],
];
$circuit = $domain->circuit($circuit);
check($circuit['gameVersion'] === 'desconocida', 'no inventar versión');
$envelope = ['format' => 'f125-circuit', 'schemaVersion' => 1, 'circuit' => $circuit];
check($domain->importCircuit($envelope) === $circuit, 'circuito normalizado');
$bad = $circuit; $bad['sources'][0]['url'] = 'javascript:alert(1)'; rejects(fn() => $domain->circuit($bad), 'URL ejecutable');
$bad = $circuit; $bad['sources'][0]['accessedAt'] = '2026-02-30'; rejects(fn() => $domain->circuit($bad), 'fecha imposible');
$bad = $circuit; $bad['id'] = '../datos'; rejects(fn() => $domain->circuit($bad), 'id peligroso');
$bad = $circuit; $bad['sources'] = []; rejects(fn() => $domain->circuit($bad), 'sin fuentes');
$setup = ['format' => 'f125-setup-engineer', 'schemaVersion' => 1, 'title' => 'Prueba', 'circuit' => $circuit, 'reference' => $base, 'current' => $current, 'referenceSource' => 'game-assigned', 'conditions' => ['surface' => 'dry', 'session' => 'race']];
$snapshot = $domain->setup($setup);
check($domain->importSetup($snapshot)['current'] === $snapshot['current'], 'ida y vuelta JSON');
check(!array_key_exists('driver', $snapshot), 'no atribuir hardware ni ayudas no declarados');
$bad = $setup; unset($bad['reference']); rejects(fn() => $domain->setup($bad), 'no guardar sin referencia explícita');
$bad = $setup; $bad['circuit'] = null; rejects(fn() => $domain->setup($bad), 'no guardar sin circuito');
foreach ($domain->symptoms['items'] as $symptom) {
    foreach ($symptom['steps'] as $step) {
        check(array_key_exists($step['id'], $base), 'síntoma apunta a parámetro conocido');
        check(in_array($step['direction'], ['up', 'down', 'inspect'], true), 'dirección de prueba válida');
    }
    check($symptom['combined'] !== '', 'combinaciones explicadas');
}
$phpFiles = array_merge(glob(dirname(__DIR__) . '/public_html/*.php'), glob(dirname(__DIR__) . '/src/*.php'));
foreach ($phpFiles as $file) {
    $source = file_get_contents($file);
    check(!preg_match('/<style\b|\bstyle\s*=/i', $source), 'sin CSS inline en ' . basename($file));
}
$temporary = sys_get_temp_dir() . '/f125-state-' . bin2hex(random_bytes(8));
mkdir($temporary);
$statePath = $temporary . '/installation.json';
$installation = new InstallationState($statePath);
try {
    check(!$installation->isInstalled(), 'sin archivo requiere instalación');
    file_put_contents($statePath, '{"installed":false}');
    check(!$installation->isInstalled(), 'booleano false requiere instalación');
    $installation->withLock(static function () use ($installation): void { $installation->markInstalled(); });
    check($installation->isInstalled(), 'estado true persistido');
    check((new InstallationState($statePath))->isInstalled(), 'estado recuperable por otra instancia');
    check(json_decode(file_get_contents($statePath), true)['installed'] === true, 'booleano real en JSON');
    file_put_contents($statePath, '{"installed":"true"}');
    try { $installation->isInstalled(); check(false, 'rechazar estado ambiguo'); } catch (RuntimeException $e) { check(true, 'estado ambiguo rechazado'); }
    file_put_contents($statePath, '{');
    try { $installation->isInstalled(); check(false, 'rechazar JSON truncado'); } catch (JsonException $e) { check(true, 'JSON truncado rechazado'); }
    try { $installation->withLock(static function (): void { throw new RuntimeException('prueba'); }); } catch (RuntimeException $e) {}
    $installation->withLock(static function () use ($installation): void { $installation->markInstalled(); });
    check($installation->isInstalled(), 'bloqueo liberado tras error');
    $missing = new InstallationState($temporary . '/inexistente/state.json');
    check(!$missing->canWrite(), 'destino no disponible detectado');
    try { $missing->markInstalled(); check(false, 'fallo de escritura explícito'); } catch (RuntimeException $e) { check(true, 'fallo de escritura explícito'); }
} finally {
    if (is_file($statePath)) { unlink($statePath); }
    if (is_file($statePath . '.lock')) { unlink($statePath . '.lock'); }
    rmdir($temporary);
}
echo "OK: $count comprobaciones de dominio y contratos.\n";
