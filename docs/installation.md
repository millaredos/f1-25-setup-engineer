[Versión en Español](es-es/instalacion.md)

# Installation

## Server and files

Requirements: Apache, PHP 8.0+ with JSON, sessions, PDO and PDO_MYSQL, and a MariaDB database with InnoDB.

Upload the directories using your hosting tools:

```text
application-directory/
├── public_html/   The only directory exposed over HTTP
├── src/
├── data/
└── config/
```

If your host uses `www`, `public` or another name for its public directory, rename `public_html` to match. Entry points look for `src/` one level above that directory. Keep the private directories alongside it, outside HTTP access.

URLs are relative. To install under a subdirectory, map its URL, such as `https://example.com/setups/`, to the public directory. Use a trailing slash or `index.php`.

## Connection and permissions

Create a MariaDB database and account using your server tools. Copy `config/local.example.php` to `config/local.php` and fill in `dsn`, `username` and `password`.

Alternatively, set `F125_DSN`, `F125_DB_USER` and `F125_DB_PASSWORD` in the web PHP environment.

The account needs CREATE during installation and additive database updates, and SELECT and INSERT for normal use. PHP must be able to read `src/` and `data/`, and write to `config/` to store the installation state. Restrict read access to `config/local.php` to the accounts that need it.

Check the extensions in the PHP instance used by Apache. Set `display_errors=Off`, `log_errors=On` and `register_argc_argv=Off` in the server's PHP configuration.

## Install

1. Open the application URL.
2. Review the requirements and connection.
3. Click **Instalar**.

The installer creates the base tables and applies pending [database migrations](database-migrations.md), including the five game presets and initial circuit-name catalog. It then writes `installed: true` to `config/installation.json` and opens the home screen. Keep this file when updating. Existing installations apply pending migrations from **Opciones → Actualizar base de datos** after updating the application files.

| State | Behavior |
| --- | --- |
| File missing or `installed: false` | The home page opens the installer. |
| `installed: true` | The home page opens the application; the installer redirects to the home page. |
| Unreadable file or invalid JSON | An error asks for the file to be corrected. |
| MariaDB unavailable after installation | The editor remains available, but versions cannot be saved. |

## Backup

Export the structure and data of all `f125_*` tables using a consistent InnoDB backup, including catalog tables and the migration ledger.

Also save `config/`, `data/` and the code version in use. Keep backups outside the public directory and Git.

JSON exports transfer individual setups. Restoring the entire installation requires the MariaDB backup and configuration.

## Restore

1. Create an empty recovery database.
2. Restore the SQL backup.
3. Point a copy of the application at that database.
4. Check circuits, saved versions and all twenty values in several setups.
5. Save a new version and verify that previous versions remain unchanged.

Test recovery in that copy before replacing an installation in use.
