<?php
declare(strict_types=1);

/** Serve the same allowlisted catalog to browser modules with JSON-safe source serialization. */
require dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$translation = appTranslation();
echo 'const catalog = ' . json_encode($translation->catalog(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . ";\n";
echo 'export const locale = ' . json_encode($translation->language()) . ";\n";
?>
/** Substitute named values as plain text; the rendering caller owns context-specific escaping. */
function interpolate(message, values) {
  return message.replace(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g, (token, key) => Object.hasOwn(values, key) ? String(values[key]) : token);
}
/** Use the server catalog with source-text fallback so both runtimes display the same messages. */
export function t(message, values = {}) { return interpolate(catalog.messages[message] ?? message, values); }
// The shipped Spanish catalog uses two forms. Adding another plural rule is explicit.
/** Select the supported plural form before interpolating the explicit count. */
export function tn(one, many, count, values = {}) {
  const forms = catalog.plurals[one] ?? [one, many];
  return interpolate(forms[count === 1 ? 0 : 1], { ...values, count });
}
