[Versión en Español](es-es/convenciones-codigo.md)

# Code conventions

## Responsibilities

Keep the existing PHP monolith and browser modules. HTTP entry points validate request envelopes and coordinate the domain and storage; they do not contain SQL. `Domain` validates product data and describes theoretical changes without HTTP or PDO. `Store` owns prepared SQL and transactions. Installation and updates own their locks, file validation and recovery. Browser code owns display and draft state, never authoritative validation.

Keep circuit browsing independent from workshop editing. Circuit selection for browsing must not replace a draft. Distinguish the selected season from the draft season and a saved setup ID from its editable values. Store title and notes with the draft, rather than in invisible form controls. Retain imported metadata even when no editor exists for it.

## Names and layout

- Use English names: `camelCase` for PHP/JavaScript functions and variables, `PascalCase` for classes, `UPPER_SNAKE_CASE` for constants and `snake_case` for Python functions. Preserve published JSON identifiers and database names.
- Name operations by responsibility: `render…` changes display, `load…` reads data, `save…` persists and `mark…` changes state. Avoid names that imply persistence when they only set a dirty flag.
- Use four spaces in PHP/Python and two in JavaScript/CSS. Keep control flow and separate statements on separate lines. Use braces in PHP and JavaScript. Follow PSR-12 layout for PHP; declare strict types after the opening tag and use PHP 8.0-compatible types.
- Each function has one responsibility. Review functions over 20–30 lines; extract meaningful operations or event handlers, rather than splitting to meet a line count. Avoid new modules or abstractions that do not reduce actual coupling or repetition.
- Keep all CSS in stylesheets. Group rules by component and document responsive overrides. Remove selectors only after checking server templates and dynamically constructed DOM.

## Comments and contracts

Document every maintained module, class and named function with its purpose. Use PHPDoc, JSDoc and Python docstrings. Explain why the operation exists, and add input/output shapes, mutations, exceptions and invariants where they matter. Document related callbacks at their registration block. Tests explain the behavior they protect and their fixtures; generated files identify their source and generator.

Comments describe intent rather than narrating obvious instructions. For example, a draft flag setter should explain that no browser persistence occurs. A save method should explain which record it changes and which identity must remain stable. An asynchronous preview should explain how stale responses are discarded.

```javascript
/** Mark the in-memory draft as changed so leaving the workshop requires a decision. */
function markDraftChanged() {
  state.dirty = true;
}
```

For PHP arrays, specify the useful shape with `@param` or `@return`; do not repeat types that the signature already explains. Describe validation failures and storage exceptions at the method that owns them. Maintain comments when code changes and remove obsolete explanations.

## Trust boundaries and errors

Validate external values in the domain before persistence. Validate HTTP method, CSRF, content type and request size at entry points. PDO placeholders bind data, not identifiers: dynamic identifiers require a closed allowlist. Escape HTML at output with the shared `h()` helper; validate URL schemes separately. Use DOM nodes and `textContent` for imported text, and serialize JSON for its actual destination.

Catch failures where recovery or an HTTP response is possible. Preserve exceptions elsewhere. Log technical context without secrets and show actionable public messages without SQL or traces. Explain intentional fallback behavior, such as offline snapshots or retained update notifications. Never treat a database outage as an uninstalled site.

Keep update and migration integrity contracts intact. Applied SQL migration files are immutable: even comments alter their checksums. Document the migration mechanism here or in its generator; a required SQL change needs a new migration.

## Cleanup and verification

Classify a candidate by its consumers: active behavior, supported compatibility, generated artifact or unreachable implementation. Check CodeGraph, direct and computed references, event registration, persistence and tests before removal. Preserve supported import formats and API response fields unless a deliberate contract change is documented. Remove obsolete PO entries during extraction so they cannot accumulate or return through a stale explicit-message list.

Test behavior rather than implementation names: create/load/edit/save/delete, unsaved decisions, distinct imported references, metadata round trips, invalid input and recovery. Run the relevant existing suites before and after a refactor; distinguish simulated storage from MariaDB integration. Update the English and Spanish changelogs using the [release categories](releasing.md#change-and-commit-categories).

## Development formatters

`dev/.php-cs-fixer.dist.php` selects non-risky PSR-12 layout rules and excludes generated catalogs and private configuration. Mixed PHP/HTML templates require reviewing both syntaxes and the rendered result.
