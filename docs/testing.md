[Versión en Español](es-es/verificacion.md)

# Testing

Run from the project root with PHP CLI:

```sh
php dev/bin/translations.php --check
php dev/tests/run.php
php dev/tests/http.php
```

The first suite checks car boundaries, format adapters, reference preservation, edition availability, PO compilation, reproducible packages, integrity, recovery and PHP syntax. ZIP is required; missing ZIP fails rather than reporting omitted package checks as success.

HTTP tests start isolated temporary PHP sites at root and `/tools/setups/`. They check installation gating, CSRF, comparison, offline bootstrap, page rendering and exclusion of private source files. They explicitly use an unreachable test database endpoint and never copy private credentials.

Real MariaDB tests are separate:

```text
F125_TEST_DSN=mysql:host=HOST;dbname=f125_test_example;charset=utf8mb4
F125_TEST_DB_USER=TEST_USER
F125_TEST_DB_PASSWORD=CONFIGURE_PRIVATELY
F125_TEST_ALLOW_WRITE=yes
```

Run `php dev/tests/integration.php`. It refuses databases whose name does not start with `f125_test_` or which contain existing application tables. It creates only the three personal test tables and removes them afterwards. Missing configuration exits 2: skipped, not passed.

Use the available browser with a temporary PHP site to inspect desktop/mobile layouts, imports, category controls, unsaved decisions and circuit source links. Browser checks and live MariaDB results must be reported separately from model and HTTP checks. No browser automation package is installed in this repository.

Also check PHP 8.0 compatibility; a pass on a newer development runtime does not prove minimum-version behavior. GitHub publication, real installation upgrades and power-loss recovery are distinct from offline package tests.
