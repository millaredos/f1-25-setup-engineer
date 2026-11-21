[English version](../testing.md)

# Verificación

Ejecuta las comprobaciones PHP desde la raíz del proyecto:

```sh
php dev/bin/migrations.php --check
php dev/tests/run.php
php dev/tests/translations.php
php dev/tests/apache-protection.php
php dev/tests/migrations.php
php dev/tests/updater.php
php dev/tests/updates.php
```

Las pruebas del actualizador requieren la extensión ZIP de PHP. Las pruebas de migraciones usan un PDO que registra operaciones y no ejecutan SQL en MariaDB. Para comprobar la interfaz se necesita un servidor PHP temporal y un navegador. El empaquetado de releases y las pruebas automatizadas de navegador no están disponibles actualmente.

`dev/tests/integration.php` prueba la persistencia real únicamente en una base MariaDB vacía configurada expresamente. Define `F125_TEST_DSN`, `F125_TEST_DB_USER`, `F125_TEST_DB_PASSWORD` y `F125_TEST_ALLOW_WRITE=yes`. La prueba rechaza una base con tablas `f125_*` existentes. Sin esa configuración se omite.