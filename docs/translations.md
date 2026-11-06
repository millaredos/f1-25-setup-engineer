[Versión en Español](es-es/traducciones.md)

# Translations

The interface currently ships in Spanish. **Opciones → Idioma de la aplicación** shows the available languages and saves the selection in a browser cookie scoped to this installation. The update channel remains an installation-wide setting.

## Catalogs

Edit `languages/es.po` with a PO editor such as Poedit. It is the editable source; `src/locale_es.php` is generated and must not be edited manually. Its filename fits the paths accepted by existing Alpha updaters. No gettext extension or operating-system locale installation is required.

PHP and JavaScript share the generated catalog through `Translation` and `public_html/i18n.php`. Output is escaped in HTML or inserted with `textContent`. Imported circuit descriptions, setup names, notes and original release notes retain their content.

Bundled setting descriptions and handling advice pass through the translator while identifiers, numeric ranges, rules and import/export formats stay unchanged. `Domain` receives an optional translation callable and works independently of browser state.

## Development workflow

Install the development dependency once:

```sh
python -m pip install -r requirements-dev.txt
```

Use literal single-quoted messages in `t('Message')` in PHP and JavaScript, or `$this->tr('Message')` inside `Domain`. Use named placeholders for changing values, for example `t('Versión {version}', ['version' => appVersion()])` in PHP. Keep complete sentences together. Use `tn(one, many, count)` for plural messages.

```sh
python bin/translations.py --extract
# Translate the new entries in languages/es.po.
python bin/translations.py
python bin/translations.py --check
php tests/translations.php
```

Extraction updates the Spanish source catalog, preserving existing translations and marking unused entries obsolete. Dynamic messages from bundled data and updater errors are collected explicitly by the extraction tool. The package builder rejects stale generated catalogs, missing messages, incomplete or fuzzy translations, mismatched placeholders and unsupported plural rules. PO syntax and duplicate entries are checked with polib.

Commit the PO source and generated PHP catalog together. The server loads the prepared files; it does not run Python, polib or conversion commands. A missing or invalid catalog falls back to source messages; a missing translated entry falls back to Spanish, then its source.

## Add a language

1. Copy `languages/es.po` to the new language code, for example `languages/en.po`, and update its `Language` header.
2. Translate the entries, retaining message identifiers and named placeholders. Keep the catalogs complete when adding new messages.
3. Generate and verify the catalogs with the commands above.
4. Register the code and native display name in `Translation::LANGUAGES`.
5. Review the complete interface, errors, number/date formatting and mobile layout; verify the saved cookie and draft recovery.

The current PHP and JavaScript plural implementation supports the Spanish/English rule `nplurals=2; plural=(n != 1);`. A language with another rule requires an explicit runtime change before it can be built. Adding a language does not require changing the database schema.
