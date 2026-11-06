[Versión en Español](docs/es-es/CHANGELOG.md)

# Changelog

## Unreleased

### 0.3.0

- Populate the circuit selector from the game catalog and offer its assigned preset alongside saved personal setups.
- Compact Circuito y referencia, remove the routine ready banner and manual preset-import section, and retain additional circuit import through Añadir circuito.
- Keep the footer at the bottom of short pages, with © 2026 millaredos and a GitHub link.

- Complete the 24 championship circuit-to-preset assignments and add Imola with preset 3 through a follow-up catalog migration.

- Add versioned SQL catalog migrations, automatic initial seeding and a CSRF-protected database update page for existing installations.
- Include five game presets, 24 official circuit names and the confirmed Albert Park → preset 3 assignment, separate from personal setups.

- Remove the duplicate Inicio navigation link; the logo opens the home screen without reloading the draft.
- Place saved-setup loading next to circuit selection and filter saved setups by the selected circuit.
- Remove weather/session selectors from loading while preserving existing imported and saved metadata.
- Move Opciones and Actualizaciones into a shared version dropdown with hover, click, touch and keyboard navigation, plus a new-version notification dot.
- Unify the interface in Spanish and add editable PO catalogs with generated PHP translations shared by PHP and JavaScript.
- Add a visible language selector in Opciones, initially offering Español and remembering the browser preference.
- Validate translation catalogs and placeholders before packaging; preserve fallback messages when a runtime catalog cannot be loaded.

- Fixed: replaced periodic GitHub API update checks with the application's version service.
- Respect GitHub rate-limit reset times, distinguish denied access, and show when update checks can resume.
- Refine the home guide with a centered introduction, balanced columns, readable headings and numbered steps across desktop and mobile.
- Replace duplicate home menu cards with an introduction, application limits and a short guide to each section.
- Use the supplied logo in the application header, including Opciones and Actualizaciones.
- Compact single-row header with navigation between the brand and version; horizontally scrollable navigation on smaller screens.
- Alpha and Estable update channels, selected from web Opciones and stored per installation.
- Background GitHub checks, shared cache, rate-limit backoff and verified package download without an updater password or ZIP upload.
- Alpha builds from one active release PR; merging runs tests and prepares a stable draft with the PR summary as release notes.
- Reproducible packages with version, commit, PR, channel, PHP and schema metadata.
- Private GitHub repository support through an optional read-only server token.
- Update locking, a persistent recovery journal and support for renamed public directories.
- Offline GitHub fixtures, package/publication tests and web channel/update tests.

### 0.2.0 — 2026-11-05

#### Added

- Home screen with a menu for circuit and reference, workshop, tests, saved versions and updates.
- Version displayed in the header, including on mobile; versioned CSS and JavaScript URLs for cache refresh.
- ZIP package updater with an administrator key, CSRF protection, path checks, size limits, hashes and version validation.
- Backup of previous files and recovery from replacement errors detected during the request.
- Development package builder and update and recovery guide.
- README covering requirements, installation, a first setup, JSON formats, backups and development; MillasGamin logo and banner.
- English documentation with Spanish translations in `docs/es-es/`.

#### Improved

- Section navigation preserves the draft.
- Relative paths verified at the site root and under a subdirectory with different hosts and ports.
- New saves no longer assign undeclared hardware or driving assists.
- Test fixtures are independent of local reference material.
- Distributed documentation focuses on installation, usage, architecture and testing.

### Initial version

- Editor with twenty settings grouped by category, an independent reference and a summary of expected effects.
- Eight handling symptoms with suggested tests, trade-offs, combinations and limits.
- Circuit profile import and support for current and legacy setup JSON, with previews.
- Immutable saved versions in MariaDB through PDO and tables prefixed with `f125_`.
- JSON export and explicit browser draft recovery.
- Server-side type, range and step validation, sessions and CSRF protection.
- Browser installer with requirement checks, table creation through POST and a lock after installation.
- Private installation state written atomically: a later MariaDB outage does not allow reinstallation.
- `public_html/` as the only public directory, with configuration, data and backups outside HTTP access.
- HTML, vanilla JavaScript and external CSS, with no additional production dependencies.
- MIT license.
