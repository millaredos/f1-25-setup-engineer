[Versión en Español](es-es/verificacion.md)

# Testing

Run the PHP checks from the project root:

```sh
php dev/bin/migrations.php --check
php dev/tests/run.php
php dev/tests/translations.php
php dev/tests/apache-protection.php
php dev/tests/migrations.php
php dev/tests/updater.php
php dev/tests/updates.php
```

The updater tests require the PHP ZIP extension. Migration tests use a recording PDO and do not execute SQL against MariaDB. Browser checks require a temporary PHP server and a browser. Release packaging and automated browser tests are currently unavailable.

`dev/tests/integration.php` exercises real persistence only against an explicitly configured empty MariaDB database. Set `F125_TEST_DSN`, `F125_TEST_DB_USER`, `F125_TEST_DB_PASSWORD` and `F125_TEST_ALLOW_WRITE=yes`. It refuses a database containing existing `f125_*` tables. Without those settings, the test is skipped.