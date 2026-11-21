[Versión en Español](docs/es-es/README.md)

![MillasGamin — Simracing, F1, gameplay and competition](media/MillasGamin%20banner.png)

<p align="center">
  <img src="media/logo.png" alt="MillasGamin logo" width="112">
</p>

# F1 25 / 26 Setup Engineer

A self-hosted web application for editing, comparing and saving setups for **F1 25** and its **2026 Season Pack**. Start from the game preset when available and review changes before testing them on track.

Built with PHP, MariaDB, vanilla JavaScript and plain CSS.

[Installation](#installation) · [Usage](#usage) · [Updating](#updating) · [Documentation](#documentation) · [Development](#development)

## Features

- Twenty settings grouped by category, with explanations of their effects.
- Compare edits with the values loaded into the workshop.
- Suggested tests for eight handling symptoms.
- JSON import for circuit profiles and setups.
- Saved setups with group comments and a circuit profile.
- JSON import and export.
- Season selection for 2025 and 2026; 2025 game preset values are pending collection and verification.
- Versioned database migrations with five 2026 car game presets and confirmed assignments for the 24 championship circuits plus Imola.

The circuit selector includes the bundled game catalog and imported profiles for additional circuits. Setup explanations are guidance to test in the game.

**Development version: 0.4.0-alpha.0.** See [CHANGELOG.md](CHANGELOG.md).

## Requirements

- Apache with PHP 8.0 or later, using a maintained PHP branch.
- JSON, sessions, PDO and PDO_MYSQL extensions.
- MariaDB with InnoDB.
- A modern browser with JavaScript.
- The PHP ZIP and cURL extensions to use the updater.

The application has no user accounts. Configure authentication on the web server or proxy before allowing access over the Internet.

## Installation

Installation is completed in the browser. Prepare the files, permissions and database using your hosting tools.

1. Download the source and arrange the directories as follows. Only expose `public_html/` over HTTP:

   ```text
   application-directory/
   ├── public_html/
   ├── src/
   ├── data/
   └── config/
   ```

   If your host calls its public directory `www`, `public` or another name, rename `public_html` to match. Keep `src/`, `data/` and `config/` as sibling directories outside the public directory.

2. Create a MariaDB database and account. Copy `config/local.example.php` to `config/local.php` and fill in the connection details:

   ```php
   <?php
   return [
       'dsn' => 'mysql:host=127.0.0.1;dbname=f125;charset=utf8mb4',
       'username' => 'application_user',
       'password' => 'REPLACE_ON_THE_SERVER',
   ];
   ```

3. Give PHP write access to `config/` and open the application URL.
4. Review the requirements and click **Instalar**. The installer prepares the tables and opens the home screen.

The application works at a site root or under a subdirectory such as `https://example.com/setups/`. Use the directory URL with a trailing slash or `index.php`; the server must map that location to the public directory.

The [installation guide](docs/installation.md) covers permissions, environment variables and data recovery.

### Nginx

The documented installation uses Apache; this project has not yet verified installation with Nginx. Nginx does not read Apache `.htaccess` files. For a virtual host dedicated to this application, add the following **inside its existing `server` block**, before other regular-expression `location` blocks:

```nginx
autoindex off;

# Deny hidden files and directories.
location ~ (^|/)\. {
    deny all;
}

# Deny common editor and backup files.
location ~* (\.(bak|old|orig|save|swp|sql|log)|~)$ {
    deny all;
}

error_page 404 = @f125_not_found;
location @f125_not_found {
    default_type text/plain;
    return 404 "Page not found.\n";
}
```

Keep the existing PHP handler and expose only the application’s public directory; `src/`, `data/`, `config/` and backups must stay outside every public mapping. The fragment applies to the whole virtual host. Review existing exact-match and `^~` locations, nested locations, ACME challenges under `.well-known`, and `error_page` or FastCGI/proxy error interception: they can alter which rules run or replace API JSON errors. After adding it, validate the configuration and verify PHP execution, 404 status, API JSON errors, denied files and private paths. Test separately if the application shares a virtual host or runs under a subdirectory.

## Usage

The interface is currently in Spanish. The names below match its menus and buttons.

1. In **Circuitos**, select a season and circuit, then browse the game preset or a saved version by category. The 2025 game preset values are not yet available.
2. Use **Añadir circuito** to download a JSON template and import [additional circuit profiles](docs/json-formats.md).
3. In **Taller**, choose **Nuevo reglaje** and select a season and circuit, or choose **Editar reglaje** to open a saved setup. A new setup starts with the game preset where available; otherwise enter all values.
4. Edit values, add comments for each category and save or delete the setup in the workshop. The summary compares against the values loaded. Use **Importar reglaje** to load a JSON file for review and save it as a new setup.
5. In **Pruebas**, select the handling symptom you notice.

Use **Exportar JSON** in **Taller** to export the active setup without leaving the editor.

Open the version menu at the top right for **Opciones** and **Actualizaciones**. A dot marks a newer version for the selected channel. **Opciones → Idioma de la aplicación** currently offers **Español** and remembers the selection in this browser.

Leaving the workshop with unsaved changes offers to save, discard or continue editing. Saving an existing setup updates it in MariaDB.

Circuit browsing does not modify saved setups. The editing dialog lists setups for the selected circuit and season; game presets are available for 2026 only. Existing imported or saved conditions remain in their JSON.

Imports support `f125-setup-engineer` and `f125-comparador-v2` JSON formats and validate the values before opening them in the workshop.

## Updating

Choose **Alpha** or **Estable** in **Opciones → Actualizaciones → Canal**. Alpha receives successful builds from the active release PR; Estable receives published stable versions. The application detects new versions after publication and displays available updates in the background. Click **Actualizar** to download, verify and install a build, with a private backup and recovery journal.

See the [updating guide](docs/updating.md). Public repositories need no token; private repositories require a read-only GitHub token configured once on the server. There is no separate updater password or manual ZIP upload.

For a complete backup, also back up MariaDB and `config/`. See [backup and restore](docs/installation.md#backup).

## Documentation

| Guide | Contents |
| --- | --- |
| [Installation](docs/installation.md) | Configuration, permissions, backup and restore. |
| [JSON formats](docs/json-formats.md) | Circuit profiles and supported setup formats. |
| [Circuit profiles](docs/circuit-profiles.md) | Track characteristics and sources for the bundled circuits. |
| [Updating](docs/updating.md) | Packages and file recovery. |
| [Release workflow](docs/releasing.md) | One PR per release, Alpha builds and stable publication. |
| [Architecture](docs/architecture.md) | Stack, layers, dependencies and storage. |
| [Code conventions](docs/coding-standards.md) | Responsibilities, names, comments, security and maintenance. |
| [Translations](docs/translations.md) | PO catalogs and adding a language. |
| [Testing](docs/testing.md) | Commands, coverage and limitations. |
| [Roadmap](ROADMAP.md) | Ideas for future development. |

## Development

A PHP monolith with separate presentation, domain and persistence layers. The frontend uses HTML, vanilla JavaScript and CSS in separate files.

```text
public_html/   Interface, HTTP API and static assets
src/           PHP classes and bootstrap
data/          Settings and handling-test catalogs
languages/     Editable PO translation catalogs
config/        Installation configuration
dev/           Development tools, tests and fixtures
docs/          Documentation; Spanish versions in es-es/
media/         Logo and banner
```

Run the domain and updater tests:

```sh
php dev/bin/migrations.php --check
php dev/tests/translations.php
php dev/tests/run.php
php dev/tests/updater.php
php dev/tests/updates.php
```

The PHP update tests require ZIP. Persistence tests require an empty test MariaDB database; see [testing](docs/testing.md). Translation generation and release packaging are being replaced and are not currently available.

Local development server:

```sh
php -d register_argc_argv=0 -d display_errors=0 -S 127.0.0.1:8125 -t public_html
```

Open `http://127.0.0.1:8125/` and complete the installer.

### Contributing

When reporting a bug, include the version, environment, steps and expected result. Remove credentials and private data from examples.

Changes to settings and explanations should include sources and preserve the existing identifiers and JSON formats.

## License

[MIT](LICENSE). An independent project, not affiliated with EA, Codemasters or Formula 1.
