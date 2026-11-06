[Versión en Español](es-es/migraciones-base-datos.md)

# Database migrations

New installations apply pending migrations before setting the installation state to complete. Existing installations show a workshop notice and provide **Opciones → Actualizar base de datos**. Opening that page checks the pending migrations; its POST button applies them with session and CSRF validation. A connection failure leaves the installation state unchanged.

Back up all `f125_*` tables from the hosting panel before applying a database update. File backups made by the package updater do not include MariaDB.

## Initial game catalog

`001-game-catalog` adds five presets with twenty numeric settings, 24 official circuit names in the supplied 2026 championship order, and Albert Park's assignment to preset 3. Other assignments are NULL. These records do not include weather, session types or technical circuit profiles.

The catalog uses `f125_game_presets` and `f125_game_circuits`; personal versions and imported profiles remain in their existing tables. `Store::gameCatalog()` exposes the catalog for subsequent loading-interface integration. The migration does not automatically load a preset into an editor draft.

## Execution and recovery

`src/Migrations.php` serializes migration attempts using a database-specific MariaDB advisory lock. It creates InnoDB tables outside the data transaction because DDL commits implicitly. Inserts and their `f125_migrations` completion record commit together. A failed insert rolls back the data and leaves the migration pending; empty tables created earlier can remain and are reused on retry. Existing records are not replaced on conflicts.

Applied SQL checksums are immutable. A checksum mismatch or an unknown migration is rejected. Running the page again after success does not insert the catalog twice. The original schema version remains 1 because this addition does not alter the existing circuit/setup contract.

## Development

Edit versioned sources under `data/migrations/`. Each migration has a `.schema.sql` file for DDL and a `.data.sql` file for transactional statements. Separate statements using a `-- statement` line. Add future changes as new migrations instead of changing an applied file.

Run `python bin/migrations.py` to generate `src/migration_catalog.php`; `--check` verifies it. The generated PHP embeds SQL so existing updaters can distribute it through their existing path allowlist. No SQL parser or Python runtime is required in production. Package creation rejects a stale generated catalog.

`php tests/migrations.php` checks bundled values and migration control flow through a recording PDO connection. `tests/integration.php` exercises the actual SQL, reapplication, existing setups, rollback and retry only when an empty MariaDB test database is explicitly configured. See [testing](testing.md).
