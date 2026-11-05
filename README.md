[Versión en Español](docs/es-es/README.md)

![MillasGamin — Simracing, F1, gameplay and competition](media/MillasGamin%20banner.png)

<p align="center">
  <img src="media/logo.png" alt="MillasGamin logo" width="112">
</p>

# F1 25 Setup Engineer

A self-hosted web application for editing, comparing and saving **F1 25** setups. Enter the values assigned by the game, set a reference and review the effect of each change before testing it on track.

Built with PHP, MariaDB, vanilla JavaScript and plain CSS.

[Installation](#installation) · [Usage](#usage) · [Updating](#updating) · [Documentation](#documentation) · [Development](#development)

## Features

- Twenty settings grouped by category, with explanations of their effects.
- Draft comparison against a reference that stays unchanged while editing.
- Suggested tests for eight handling symptoms.
- JSON import for circuit profiles and setups.
- Saved versions containing session conditions, notes and a circuit profile.
- JSON export and browser draft recovery.

Circuits are added through imported profiles. Setup explanations are guidance to test in the game.

**Development version: 0.3.0-alpha.0.** See [CHANGELOG.md](CHANGELOG.md).

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

## Usage

The interface is currently in Spanish. The names below match its menus and buttons.

1. In **Circuito y referencia**, import a [circuit profile](docs/json-formats.md) and select the session conditions.
2. Enter the twenty starting values in **Taller**, or import a setup from **Circuito y referencia**.
3. Set the current values as the reference and specify their source.
4. Edit settings in **Taller** and review the change summary.
5. In **Pruebas**, select the handling symptom you notice.
6. In **Versiones**, add a name and notes, save a version or export JSON.

Switching sections keeps the draft. Each save creates a new version in MariaDB. Browser drafts are recovered separately and do not replace database saves.

Both `f125-setup-engineer` and the previous comparator's `f125-comparador-v2` JSON formats are supported. Imports include validation and a preview.

## Updating

Choose **Alpha** or **Release** in **Options → Updates → Channel**. Alpha receives successful builds from the active release PR; Release receives published stable versions. The application detects new versions after publication and displays available updates in the background. Click **Update** to download, verify and install a build, with a private backup and recovery journal.

See the [updating guide](docs/updating.md). Public repositories need no token; private repositories require a read-only GitHub token configured once on the server. There is no separate updater password or manual ZIP upload.

For a complete backup, also back up MariaDB and `config/`. See [backup and restore](docs/installation.md#backup).

## Documentation

| Guide | Contents |
| --- | --- |
| [Installation](docs/installation.md) | Configuration, permissions, backup and restore. |
| [JSON formats](docs/json-formats.md) | Circuit profiles and supported setup formats. |
| [Updating](docs/updating.md) | Packages and file recovery. |
| [Release workflow](docs/releasing.md) | One PR per release, Alpha builds and stable publication. |
| [Architecture](docs/architecture.md) | Stack, layers, dependencies and storage. |
| [Testing](docs/testing.md) | Commands, results and limitations. |

## Development

A PHP monolith with separate presentation, domain and persistence layers. The frontend uses HTML, vanilla JavaScript and CSS in separate files.

```text
public_html/   Interface, HTTP API and static assets
src/           PHP classes and bootstrap
data/          Settings and handling-test catalogs
config/        Installation configuration
tests/         Tests and fixtures
bin/           Development tools
docs/          Documentation; Spanish versions in es-es/
media/         Logo and banner
```

Run the domain and updater tests:

```sh
php tests/run.php
php tests/updater.php
php tests/updates.php
python -m unittest discover -s tests -p 'test_*.py'
```

The PHP update tests require ZIP. Browser tests use Node, Playwright and Edge or Chromium. Persistence tests require an empty test MariaDB database; see [testing](docs/testing.md).

Local development server:

```sh
php -d register_argc_argv=0 -d display_errors=0 -S 127.0.0.1:8125 -t public_html
```

Open `http://127.0.0.1:8125/` and complete the installer. Node and Python are used for testing and packaging respectively; neither is required in production.

### Contributing

When reporting a bug, include the version, environment, steps and expected result. Remove credentials and private data from examples.

Changes to settings and explanations should include sources and preserve the existing identifiers and JSON formats.

## License

[MIT](LICENSE). An independent project, not affiliated with EA, Codemasters or Formula 1.
