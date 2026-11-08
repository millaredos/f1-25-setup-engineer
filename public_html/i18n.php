<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$translation = appTranslation();
echo 'const catalog = ' . json_encode($translation->catalog(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . ";\n";
echo 'export const locale = ' . json_encode($translation->language()) . ";\n";
?>
function interpolate(message, values) {
  return message.replace(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g, (token, key) => Object.hasOwn(values, key) ? String(values[key]) : token);
}
export function t(message, values = {}) { return interpolate(catalog.messages[message] ?? message, values); }
// The shipped Spanish catalog uses two forms. Adding another plural rule is explicit.
export function tn(one, many, count, values = {}) {
  const forms = catalog.plurals[one] ?? [one, many];
  return interpolate(forms[count === 1 ? 0 : 1], { ...values, count });
}
