<?php
declare(strict_types=1);

/** Behavior and packaging checks use independent temporary data, never an installed database. */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
require dirname(__DIR__) . '/bin/package.php';
require_once dirname(__DIR__) . '/bin/translations.php';

use App\Infrastructure\Catalog;
use App\Model\ValidationError;
use App\Application\Comparison;
use App\Application\SetupCodec;
use App\Application\UpdateInstaller;
use App\Application\Installation;
use App\Infrastructure\JsonFile;

$root = dirname(__DIR__, 2);
$checks = 0;

/** Stop on a contract failure, rather than printing success for an omitted check. */
function check(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($description); }
    $checks++;
}

/** Expect rejection only; an operation that silently accepts bad input fails the suite. */
function rejects(callable $operation, string $description): void
{
    try { $operation(); } catch (Throwable $error) { check(true, $description); return; }
    throw new RuntimeException($description);
}

/** Remove only a test-owned temporary tree and never follow a linked directory. */
function removeTestTree(string $path): void
{
    if (!is_dir($path)) { return; }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { removeTestTree($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($path);
}

$catalog = new Catalog($root . '/data');
$car25 = $catalog->car('2025');
$car26 = $catalog->car('2026');
check(count($car25->parameters()) === 20, 'Twenty published setting identifiers');
check($car25->presets() === [], 'No invented 2025 presets');
check(count($car26->presets()) === 5, 'Five known 2026 presets');
check(!in_array('madrid', array_column($catalog->circuits('2025'), 'id'), true), 'Madring is edition-specific');
check(in_array('madrid', array_column($catalog->circuits('2026'), 'id'), true), 'Madring available in 2026');
$base = $car26->presets()[3];
foreach ($car26->presets() as $preset) { check(count($car26->validate($preset)) === 20, 'Known preset accepted'); }
foreach ($car26->parameters() as $parameter) {
    foreach ([$parameter['min'], $parameter['max']] as $value) { $input = $base; $input[$parameter['id']] = $value; check($car26->validate($input)[$parameter['id']] == $value, 'Range boundary'); }
    $input = $base; $input[$parameter['id']] = $parameter['min'] - $parameter['step'];
    rejects(static fn () => $car26->validate($input), 'Below-range rejected');
    $input[$parameter['id']] = $parameter['max'] + $parameter['step'];
    rejects(static fn () => $car26->validate($input), 'Above-range rejected');
    $input[$parameter['id']] = $parameter['min'] + $parameter['step'] / 2;
    rejects(static fn () => $car26->validate($input), 'Off-step rejected');
    $input[$parameter['id']] = '25';
    rejects(static fn () => $car26->validate($input), 'Numeric strings rejected');
}
$input = $base; $input['unknown'] = 1;
rejects(static fn () => $car26->validate($input), 'Unknown setting rejected');
$comparison = new Comparison();
check($comparison->compare($car26, $base, $base)['changes'] === [], 'No fabricated differences');
$changed = $base; $changed['ala_del']++; $changed['ala_tra']--;
check(count($comparison->compare($car26, $base, $changed)['changes']) === 2, 'Opposed wings do not cancel');
$codec = new SetupCodec($catalog);
$legacy = $codec->decode($base);
check($legacy['season'] === 'unknown' && $legacy['reference'] === $legacy['current'], 'Legacy metadata stays unknown');
$capture = $codec->decode(['format' => 'f125-comparador-v2', 'reference' => $base, 'current' => $changed, 'referenceIsCapture' => true]);
check($capture['referenceSource'] === 'capture' && $capture['reference'] !== $capture['current'], 'Comparator capture provenance survives conversion');
$record = ['format' => 'f125-setup-engineer', 'schemaVersion' => 1, 'season' => '2026', 'current' => $changed, 'reference' => $base, 'referenceSource' => 'capture', 'circuit' => ['id' => 'australia-albert-park', 'origin' => 'game-catalog', 'name' => '<script>bad()</script>', 'location' => 'bad'], 'title' => 'Test', 'notes' => 'notes', 'groupNotes' => ['aero' => 'keep'], 'conditions' => ['surface' => 'wet', 'session' => 'race']];
$decoded = $codec->decode($record, true);
check($decoded['reference'] !== $decoded['current'], 'Import preserves distinct reference');
check($decoded['referenceSource'] === 'capture' && $decoded['groupNotes']['aero'] === 'keep', 'Metadata roundtrip');
check($decoded['circuit']['name'] === 'Albert Park Circuit', 'Stored catalog identity is resolved authoritatively');
$partial = $record; $partial['current'] = ['ala_del' => 26]; $partial['reference'] = null;
$recovered = $codec->recover($partial);
check($recovered['current']['ala_del'] === 26 && $recovered['current']['ala_tra'] === null && $recovered['reference'] === null, 'Partial recovery never persists or displays validation fillers');
$record['season'] = 'unknown';
rejects(static fn () => $codec->decode($record, true), 'Saving needs explicit season');
$record['season'] = '2026'; $record['schemaVersion'] = 999;
rejects(static fn () => $codec->decode($record), 'Unknown schema rejected');

$temporary = sys_get_temp_dir() . '/f125-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    mkdir($temporary . '/config'); mkdir($temporary . '/src');
    $installation = new Installation($temporary);
    check(!$installation->installed(), 'Missing state means pending');
    JsonFile::write($temporary . '/config/installation.json', ['installed' => true]);
    check($installation->installed(), 'Installed state independent of database');
    JsonFile::write($temporary . '/config/installation.json', ['installed' => 'yes']);
    rejects(static fn () => $installation->installed(), 'Corrupt state never reinstalls');
    $po = $temporary . '/es.po';
    file_put_contents($po, "msgid \"\"\nmsgstr \"\"\n\"Language: es\\n\"\n\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\nmsgid \"Hello {name}\"\nmsgstr \"Hola {name}\"\n");
    check(str_contains(compilePo($po, 'es'), 'Hola {name}'), 'PO compiler runs in PHP');
    file_put_contents($po, str_replace('Hola {name}', 'Hola', file_get_contents($po)));
    rejects(static fn () => compilePo($po, 'es'), 'Lost translation placeholder rejected');
    if (!class_exists('ZipArchive')) { throw new RuntimeException('ZIP is required; package tests cannot be skipped.'); }
    $identity = ['channel' => 'alpha', 'commit' => str_repeat('a', 40), 'pr' => 1, 'branch' => 'release/9.0.0'];
    $zip = $temporary . '/package.zip';
    buildPackage($root, $zip, '9.0.0-alpha.1', $identity);
    $digest = hash_file('sha256', $zip);
    buildPackage($root, $zip, '9.0.0-alpha.1', $identity);
    check(hash_equals($digest, hash_file('sha256', $zip)), 'Reproducible package');
    file_put_contents($temporary . '/src/VERSION', "0.4.0\n");
    file_put_contents($temporary . '/config/local.php', 'private-secret-test');
    $installer = new UpdateInstaller($temporary);
    $contents = $installer->inspect($zip, '9.0.0-alpha.1');
    check(isset($contents['src/Model/Car.php'], $contents['public_html/index.php']), 'Nested MVC files included');
    check(!isset($contents['config/local.php'], $contents['dev/tests/run.php']), 'Private and development files excluded');
    foreach (['../src/a.php', 'src/../config/local.php', 'src//a.php', 'config/local.php', 'public_html/assets/a.php', 'src/a.php/evil'] as $path) { check(!UpdateInstaller::allowed($path), 'Unsafe path rejected'); }
    $installer->install($zip, '9.0.0-alpha.1');
    check(trim(file_get_contents($temporary . '/src/VERSION')) === '9.0.0-alpha.1', 'Package installed');
    check(file_get_contents($temporary . '/config/local.php') === 'private-secret-test', 'Private configuration preserved');
    rejects(static fn () => $installer->install($zip, '9.0.0-alpha.1'), 'Downgrade or repeat rejected');
    mkdir($temporary . '/backups/update-recovery', 0700, true);
    mkdir($temporary . '/backups/update-recovery/src');
    file_put_contents($temporary . '/backups/update-recovery/src/VERSION', "0.4.0\n");
    JsonFile::write($temporary . '/config/update-journal.json', ['backup' => 'update-recovery', 'files' => ['src/VERSION' => true]]);
    $installer->recover();
    check(trim(file_get_contents($temporary . '/src/VERSION')) === '0.4.0', 'Interrupted update recovers prior version');
    JsonFile::write($temporary . '/config/update-journal.json', ['backup' => 'update-recovery', 'files' => ['src/VERSION' => 'yes']]);
    rejects(static fn () => $installer->recover(), 'Corrupt recovery state is rejected');
    unlink($temporary . '/config/update-journal.json');
    $renamed = new UpdateInstaller($temporary, 'www');
    $renamed->install($zip, '9.0.0-alpha.1');
    check(is_file($temporary . '/www/index.php'), 'Update supports a renamed public directory');
    $protection = new \App\Infrastructure\PublicProtection($temporary . '/www');
    file_put_contents($temporary . '/www/.htaccess', "# Custom hosting rule\n");
    $original = $protection->inspect();
    $protection->apply($original['hash']);
    check(str_contains(file_get_contents($temporary . '/www/.htaccess'), '# Custom hosting rule'), 'Protection preserves hosting directives');
    rejects(static fn () => $protection->apply($original['hash']), 'Stale protection form cannot overwrite a new revision');
    file_put_contents($temporary . '/src/VERSION', "0.4.0\n");
    $remote = (object) ['feed' => "alpha=9.0.0-alpha.1\nrelease=\n", 'draft' => false, 'digest' => $digest, 'marker' => true];
    $http = new \App\Infrastructure\HttpClient(static function (string $url, bool $binary) use ($remote, $identity, $zip): string {
        if (str_contains($url, 'versions.txt')) { return $remote->feed; }
        if ($binary) { return file_get_contents($zip); }
        return json_encode(['tag_name' => 'v9.0.0-alpha.1', 'draft' => $remote->draft, 'prerelease' => true, 'body' => $remote->marker ? '<!-- f125-update ' . json_encode($identity + ['format' => 'f125-update-v3']) . ' -->' : '', 'assets' => [['id' => 1, 'name' => 'f125-update-9.0.0-alpha.1.zip', 'size' => filesize($zip), 'digest' => 'sha256:' . $remote->digest, 'state' => 'uploaded']]], JSON_THROW_ON_ERROR);
    });
    $discovery = new \App\Application\UpdateDiscovery($temporary, $http);
    $candidate = $discovery->check('alpha');
    check($candidate['version'] === '9.0.0-alpha.1', 'Exact published release resolved');
    check($discovery->download('alpha', $candidate['identity'])['bytes'] === file_get_contents($zip), 'Downloaded bytes match announced package digest');
    $remote->draft = true;
    rejects(static fn () => $discovery->check('alpha'), 'Draft release never announced');
    $remote->draft = false; $remote->digest = str_repeat('b', 64);
    rejects(static fn () => $discovery->download('alpha', $candidate['identity']), 'Changed asset identity needs a new confirmation');
    $remote->digest = $digest; $remote->marker = false;
    check($discovery->check('alpha') === null, 'Previous architecture releases are not offered to MVC installs');
    $remote->feed = "alpha=invalid\nrelease=\n";
    rejects(static fn () => $discovery->check('alpha'), 'Malformed feed rejected');
} finally { removeTestTree($temporary); }

// Lint new maintained PHP files using this exact runtime, including generated catalogs.
foreach (['src', 'public_html', 'dev'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
        $process = proc_open([PHP_BINARY, '-l', $file->getPathname()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot lint PHP.'); }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0, $output);
    }
}
echo 'OK: ' . $checks . " PHP domain, format, package and recovery checks.\n";
