[English version](../database-migrations.md)

# Migraciones de base de datos

Las instalaciones nuevas aplican las migraciones pendientes antes de marcar la instalación como terminada. Las existentes muestran un aviso en el taller y ofrecen **Opciones → Actualizar base de datos**. Abrir esa página consulta las migraciones pendientes; su botón POST las aplica con validación de sesión y CSRF. Un fallo de conexión no cambia el estado de instalación.

Guarda una copia de todas las tablas `f125_*` desde el panel del alojamiento antes de actualizar la base. Las copias de archivos del actualizador de paquetes no incluyen MariaDB.

## Catálogo inicial del juego

`001-game-catalog` añade cinco predeterminados con veinte ajustes numéricos, los nombres de los 24 circuitos oficiales en el orden del campeonato 2026 aportado y la asignación de Albert Park al predeterminado 3. Las demás asignaciones quedan en NULL. Estos registros no incluyen meteorología, tipos de sesión ni fichas técnicas de circuitos.

El catálogo utiliza `f125_game_presets` y `f125_game_circuits`; las versiones personales y las fichas importadas permanecen en sus tablas actuales. `Store::gameCatalog()` expone el catálogo para su posterior integración en la interfaz de carga. La migración no carga automáticamente un predeterminado en el borrador del editor.

## Ejecución y recuperación

`src/Migrations.php` serializa los intentos mediante un bloqueo MariaDB específico de la base. Crea las tablas InnoDB fuera de la transacción de datos porque el DDL confirma implícitamente. Las inserciones y su registro de finalización en `f125_migrations` se confirman juntos. Un fallo de inserción revierte los datos y deja la migración pendiente; pueden quedar tablas vacías creadas previamente, que se reutilizan al reintentar. Los conflictos no sustituyen registros existentes.

Las huellas del SQL aplicado son inmutables. Se rechazan las huellas distintas y las migraciones desconocidas. Volver a ejecutar la página tras completar no duplica el catálogo. La versión del esquema original sigue en 1 porque esta ampliación no modifica el contrato existente de circuitos y reglajes.

## Desarrollo

Edita las fuentes versionadas de `data/migrations/`. Cada migración tiene un archivo `.schema.sql` para DDL y otro `.data.sql` para sentencias transaccionales. Separa las sentencias con una línea `-- statement`. Añade cambios futuros como migraciones nuevas en lugar de modificar archivos ya aplicados.

Ejecuta `python bin/migrations.py` para generar `src/migration_catalog.php`; `--check` lo verifica. El PHP generado incorpora el SQL para distribuirlo mediante las rutas ya admitidas por los actualizadores existentes. Producción no necesita un analizador SQL ni Python. La creación del paquete rechaza un catálogo generado desactualizado.

`php tests/migrations.php` comprueba los valores distribuidos y el flujo de migración mediante una conexión PDO que registra operaciones. `tests/integration.php` prueba el SQL real, la repetición, los reglajes existentes, la reversión y el reintento solo cuando se configura expresamente una base MariaDB de pruebas vacía. Consulta [verificación](verificacion.md).
