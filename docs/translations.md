[Versión en Español](es-es/traducciones.md)

# Translation catalogs

The current interface is Spanish. Only `es` is enabled; this rewrite does not claim complete support for a second language. Navigation messages use the catalog and remaining messages use their Spanish source text.

Edit `languages/es.po`, then run `php dev/bin/translations.php`. The PHP compiler accepts singular entries and the Spanish two-form plural rule, checks placeholders and rejects duplicate, fuzzy or unsupported context entries. It generates the data-only `src/Translation/catalog_es.php`; do not edit generated output.

Run `php dev/bin/translations.php --check` before packaging. CI checks that generated files match their source. Missing messages fall back to the source string. There is no automatic extraction command in the current tool. Register new messages explicitly when adding catalog-backed text. Adding another language requires complete message coverage, its plural rule and server/client integration; changing the allowed language list alone is insufficient.
