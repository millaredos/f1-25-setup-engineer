<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Updater.php';
if (!class_exists('ZipArchive')) { fwrite(STDERR, "ZIP requerido para esta prueba.\n"); exit(2); }
$root = sys_get_temp_dir() . '/f125-update-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$count = 0;
function expectUpdate(bool $ok, string $message): void {
    global $count;
    if (!$ok) { throw new RuntimeException($message); } $count++;
}
function writePackage(string $target, array $contents, array $extra = [], bool $tamper = false): void {
    $manifest = ['format' => 'f125-update-v1', 'version' => '0.2.0', 'files' => []];
    foreach ($contents as $name => $content) { $manifest['files'][$name] = hash('sha256', $content); }
    $zip = new ZipArchive(); $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    foreach (array_merge($contents, $extra) as $name => $content) { $zip->addFromString($name, $content . ($tamper ? 'tamper' : '')); }
    $zip->close();
}
try {
    $repository = dirname(__DIR__); $contents = [];
    foreach (['src', 'public_html', 'public_html/assets', 'data'] as $directory) {
        mkdir($root . '/' . $directory, 0700, true);
        foreach (glob($repository . '/' . $directory . '/*') as $file) {
            $path = $directory . '/' . basename($file);
            if (is_file($file) && Updater::allowed($path)) {
                $contents[$path] = file_get_contents($file);
                copy($file, $root . '/' . $path);
            }
        }
    }
    $contents['src/VERSION'] = "0.2.0\n";
    file_put_contents($root . '/src/VERSION', "0.1.0\n");
    mkdir($root . '/config');
    file_put_contents($root . '/config/local.php', 'private-sentinel');
    file_put_contents($root . '/config/installation.json', '{"installed":true}');
    $updater = new Updater($root);
    foreach (['../outside.php', 'config/local.php', 'public_html/assets/../../evil.php', 'public_html/.htaccess', 'src/sub/evil.php', 'public_html/assets/evil.PHP'] as $path) {
        expectUpdate(!Updater::allowed($path), 'ruta insegura rechazada: ' . $path);
    }
    foreach ([['../outside.php' => 'bad'], ['config/local.php' => 'bad'], ['unlisted.txt' => 'bad']] as $extra) {
        writePackage($root . '/package.zip', $contents, $extra);
        $rejected = false;
        try { $updater->apply($root . '/package.zip'); } catch (RuntimeException $e) { $rejected = true; }
        expectUpdate($rejected, 'rechazar paquete con rutas adicionales');
        expectUpdate(trim(file_get_contents($root . '/src/VERSION')) === '0.1.0', 'rechazo sin cambiar versión');
    }
    foreach (['src/Translation.php', 'src/Header.php', 'src/locale_es.php', 'public_html/i18n.php', 'public_html/assets/navigation.js', 'src/Migrations.php', 'src/migration_catalog.php', 'public_html/database.php'] as $required) {
        writePackage($root . '/package.zip', array_diff_key($contents, [$required => true]));
        $rejected = false;
        try { $updater->apply($root . '/package.zip'); } catch (RuntimeException $e) { $rejected = true; }
        expectUpdate($rejected && trim(file_get_contents($root . '/src/VERSION')) === '0.1.0', 'reject missing translation/navigation dependency: ' . $required);
    }
    writePackage($root . '/package.zip', $contents, [], true);
    $rejected = false;
    try { $updater->apply($root . '/package.zip'); } catch (RuntimeException $e) { $rejected = true; }
    expectUpdate($rejected, 'rechazar hashes incorrectos');
    writePackage($root . '/package.zip', $contents);
    $backup = $updater->apply($root . '/package.zip');
    expectUpdate(trim(file_get_contents($root . '/src/VERSION')) === '0.2.0', 'versión aplicada');
    expectUpdate(trim(file_get_contents($root . '/backups/' . $backup . '/src/VERSION')) === '0.1.0', 'copia de versión previa');
    foreach ($contents as $path => $content) { expectUpdate(file_get_contents($root . '/' . $path) === $content, 'contenido aplicado: ' . $path); }
    expectUpdate(file_get_contents($root . '/config/local.php') === 'private-sentinel', 'configuración conservada');
    expectUpdate(file_get_contents($root . '/config/installation.json') === '{"installed":true}', 'instalación conservada');
    $rejected = false;
    try { $updater->apply($root . '/package.zip'); } catch (RuntimeException $e) { $rejected = true; }
    expectUpdate($rejected, 'rechazar versión igual o anterior');
    echo "OK: $count comprobaciones del actualizador en copia temporal.\n";
} finally {
    // Solo se elimina el directorio temporal aleatorio creado por esta prueba.
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
