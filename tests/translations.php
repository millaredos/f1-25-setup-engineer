<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Translation.php';
require dirname(__DIR__) . '/src/Domain.php';
$checks = 0;
function translationCheck(bool $condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
$translation = new Translation('es');
translationCheck($translation->language() === 'es', 'Spanish is available');
translationCheck($translation->text('The form session is invalid. Reload the page.') === 'La sesión del formulario no es válida. Recarga la página.', 'Updater errors use the selected catalog');
translationCheck($translation->text('Nueva versión disponible: {version}', ['version' => '0.3.0-alpha.11']) === 'Nueva versión disponible: 0.3.0-alpha.11', 'Named placeholders');
translationCheck($translation->text('{value}', ['value' => '{other}', 'other' => 'changed']) === '{other}', 'Interpolation never evaluates substituted content');
translationCheck($translation->plural('{count} de 20 modificado', '{count} de 20 modificados', 1) === '1 de 20 modificado', 'Singular');
translationCheck($translation->plural('{count} de 20 modificado', '{count} de 20 modificados', 0) === '0 de 20 modificados', 'Zero plural');
translationCheck($translation->plural('{count} de 20 modificado', '{count} de 20 modificados', 2) === '2 de 20 modificados', 'Plural');
translationCheck((new Translation('../../outside'))->language() === 'es', 'Language input never controls catalog paths');
translationCheck($translation->text('Missing source message') === 'Missing source message', 'Missing message falls back to source');
translationCheck(h('<script>"&') === '&lt;script&gt;&quot;&amp;', 'HTML translations are escaped at output');

$root = sys_get_temp_dir() . '/f125-translations-' . bin2hex(random_bytes(5));
mkdir($root);
try {
    file_put_contents($root . '/locale_es.php', "<?php return ['messages' => ['quoted' => \"Un 'texto' con \\\"comillas\\\" y \\\\ barras\"], 'plurals' => []];");
    translationCheck((new Translation('es', $root))->text('quoted') === 'Un \'texto\' con "comillas" y \\ barras', 'Quotes and slashes survive generated catalog loading');
    file_put_contents($root . '/locale_es.php', '<?php invalid !');
    translationCheck((new Translation('es', $root))->text('Texto de respaldo') === 'Texto de respaldo', 'Broken PHP catalog does not break application');
    file_put_contents($root . '/locale_es.php', "<?php return ['messages' => ['x' => []], 'plurals' => []];");
    translationCheck((new Translation('es', $root))->text('x') === 'x', 'Invalid entry falls back safely');
    unlink($root . '/locale_es.php');
    translationCheck((new Translation('es', $root))->text('Ausente') === 'Ausente', 'Missing catalog falls back safely');
} finally {
    if (is_file($root . '/locale_es.php')) { unlink($root . '/locale_es.php'); }
    rmdir($root);
}

$source = new Domain(dirname(__DIR__) . '/data');
$localized = new Domain(dirname(__DIR__) . '/data', static fn(string $text, array $values = []): string => 'TEST: ' . $text);
translationCheck($localized->catalog['parameters'][0]['name'] === 'TEST: ' . $source->catalog['parameters'][0]['name'], 'Parameter descriptions are translated');
translationCheck($localized->catalog['parameters'][0]['id'] === $source->catalog['parameters'][0]['id'], 'Parameter identifiers stay stable');
translationCheck($localized->catalog['parameters'][0]['min'] === $source->catalog['parameters'][0]['min'], 'Numeric rules stay stable');
translationCheck($localized->symptoms['items'][0]['steps'][0]['direction'] === $source->symptoms['items'][0]['steps'][0]['direction'], 'Advice directions stay stable');
translationCheck($localized->symptoms['items'][0]['steps'][0]['why'] === 'TEST: ' . $source->symptoms['items'][0]['steps'][0]['why'], 'Advice explanation is translated');
echo "OK: $checks translation checks.\n";
