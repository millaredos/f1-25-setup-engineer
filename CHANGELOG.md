[Versión en Español](docs/es-es/CHANGELOG.md)

# Changelog

## Unreleased

### 0.3.0

- Use the supplied logo in the application header, including Options and Updates.
- Compact single-row header with navigation between the brand and version; horizontally scrollable navigation on smaller screens.
- Alpha and Release update channels, selected from web Options and stored per installation.
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
