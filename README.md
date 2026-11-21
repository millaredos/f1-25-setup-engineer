[Versión en Español](docs/es-es/README.md)

# F1 25 / 26 Setup Engineer

A self-hosted PHP application to consult circuits, edit and compare setups, prepare theoretical handling tests, and save or export personal setups for F1 25 and its 2026 Season Pack.

Development version: **0.5.0-alpha.0**. This source rewrite is not a published release.

## Architecture

The application uses MVC and object-oriented PHP without a framework, Composer packages or a frontend build step. The public front controller composes controllers; models validate cars, circuits and setups; repositories own PDO queries; private views render prepared values. Browser modules separate draft ownership, transport and screen coordination. CSS separates shared tokens, components and workshop layout.

```text
public_html/       HTTP entry points and browser assets
src/Controller/    Request coordination
src/Model/         Car, circuit and setup rules
src/Application/   Import, comparison, installation and update operations
src/Persistence/   MariaDB repositories
src/Infrastructure/ Catalogs, private state, HTTPS and locking
src/Http/          Request, response, sessions and rendering
src/View/          Private templates
data/             Versioned catalogs and source descriptions
languages/        Editable PO catalogs
dev/              PHP development tools and tests
config/           Private installation configuration
```

## Installation

Requires PHP 8.0+, PDO_MYSQL and MariaDB. ZIP and cURL are required for updates. Serve **only** `public_html/`; source, data, configuration and backups must remain outside the webroot. Root and subdirectory installs are supported.

Copy `config/local.example.php` to `config/local.php` and set the database DSN, username and password. Alternatively provide `F125_DSN`, `F125_DB_USER` and `F125_DB_PASSWORD` in the server environment. Open the application and explicitly complete installation in the browser. No PHP command is required for the end user.

Installation state is stored separately from the database. A later database outage does not restart installation. CSRF protects mutations; authentication, if needed, remains the hosting server's responsibility.

## Use

Select a season and circuit. The 2026 catalog contains five known presets and their assignments. The 2025 preset catalog is empty until values are collected. New drafts therefore remain empty when no confirmed preset exists.

The workshop edits an in-memory draft. Save, replace, delete, import and export are explicit actions. Browsing circuits and theoretical tests preserves the draft. Leaving the workspace with pending edits requires a discard decision.

The twenty published setting IDs and supported JSON formats remain accepted. Imported references remain distinct from current values. Missing seasons remain unknown until explicitly selected. Explanations are theoretical and are not physical simulation or lap-time predictions. The current shared explanatory catalog has not been independently verified for both seasons.

## Existing installations

The personal `f125_circuits`, `f125_setups` and schema-1 tables are retained. The rewrite reads their existing payloads. Distributed season data is now separate from those personal tables; older catalog tables are not deleted.

**The first migration from the previous flat architecture is manual.** Back up files and MariaDB, preserve private configuration and installation state, replace the application tree with this source, and expose the new public directory. Do not install an MVC package through the previous updater. The new updater accepts only complete `f125-update-v3` packages and does not offer older architecture packages. No live migration has been performed by developing this source.

## Development

Only PHP is needed for project tools. There are no Python, Node, npm or browser automation packages in the project.

```sh
php dev/bin/translations.php
php dev/bin/translations.php --check
php dev/tests/run.php
php dev/tests/http.php
```

Real MariaDB integration requires a dedicated empty database named `f125_test_*` and explicit test environment variables; see [testing](docs/testing.md). Inspect the UI with a temporary PHP server and a browser:

```sh
php -d register_argc_argv=0 -d display_errors=0 -S 127.0.0.1:8125 -t public_html
```

[Architecture](docs/architecture.md) · [Formats](docs/json-formats.md) · [Release procedure](docs/releasing.md) · [Changelog](CHANGELOG.md)
