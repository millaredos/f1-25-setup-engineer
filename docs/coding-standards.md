[Versión en Español](es-es/convenciones-codigo.md)

# Coding conventions

Target PHP 8.0 syntax with strict types and explicit parameter, property and return types. Use four-space indentation and PSR-12 as the style reference. Describe array contracts with PHPDoc where native types cannot express the structure.

Controllers interpret HTTP and coordinate operations. Models own validation and domain invariants; repositories own PDO. Views receive prepared values and escape at output. Keep reusable browser controls in JavaScript modules and CSS in external files. Share actual repeated behavior without building unneeded future integrations.

Document each maintained function, class and module's responsibility. Explain significant preconditions, failures, side effects and non-obvious decisions. Keep comments beside the code they explain and update both together. Avoid restating obvious assignments or leaving disabled implementations behind.

Treat all imported data as untrusted. Validate complete setting contracts on the server; never silently repair invalid values. Use prepared SQL for variable values, contextual output escaping and CSRF for POST mutations. A CSRF token is not authentication. Keep secrets outside HTTP and Git. Log internal errors privately and show actionable failures without traces or credentials.

Changes include relevant contract tests, English/Spanish documentation and both changelogs. Report model, HTTP, browser, real database and minimum-runtime checks separately. Review the complete diff before integration. See [testing](testing.md) and [release conventions](releasing.md).
