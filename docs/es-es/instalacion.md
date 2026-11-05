[English version](../installation.md)

# Instalación

## Servidor y archivos

Se necesita Apache, PHP 8.0+ con JSON, sesiones, PDO y PDO_MYSQL, y una base MariaDB con InnoDB.

Desde el gestor de archivos del alojamiento, coloca las carpetas así:

```text
directorio-de-la-aplicacion/
├── public_html/   Única carpeta accesible por HTTP
├── src/
├── data/
└── config/
```

Si tu alojamiento llama a la carpeta pública `www`, `public` u otro nombre, renombra `public_html` para que coincida. Los puntos de entrada buscan `src/` un nivel por encima de esa carpeta. Las carpetas privadas deben quedar a su mismo nivel, fuera del acceso HTTP.

Las URL son relativas. Para instalar bajo una subcarpeta, asocia su ruta, por ejemplo `https://example.com/reglajes/`, con `public_html/`. Accede con barra final o mediante `index.php`.

## Conexión y permisos

Prepara una base y una cuenta MariaDB desde el panel del servidor. Copia `config/local.example.php` a `config/local.php` y completa `dsn`, `username` y `password`.

También se pueden configurar las variables `F125_DSN`, `F125_DB_USER` y `F125_DB_PASSWORD` en el entorno del PHP web.

La cuenta necesita CREATE para la instalación, y SELECT e INSERT para el uso habitual. PHP debe poder leer `src/` y `data/`, y escribir en `config/` para guardar el estado de instalación. Protege `config/local.php` para que solo puedan leerlo las cuentas que lo necesitan.

Comprueba las extensiones en el PHP utilizado por Apache. Configura `display_errors=Off`, `log_errors=On` y `register_argc_argv=Off` desde el panel del alojamiento.

## Instalar

1. Abre la URL de la aplicación.
2. Revisa los requisitos y la conexión.
3. Pulsa **Instalar**.

El instalador crea las tablas `f125_schema_meta`, `f125_circuits` y `f125_setups`. Al terminar guarda `installed: true` en `config/installation.json` y abre Inicio. Conserva ese archivo al actualizar.

| Estado | Comportamiento |
| --- | --- |
| Archivo ausente o `installed: false` | El inicio abre el instalador. |
| `installed: true` | El inicio abre la aplicación y el instalador redirige al inicio. |
| Archivo ilegible o JSON inválido | Se muestra un error para corregir el archivo. |
| MariaDB no disponible tras instalar | Se mantiene el editor, pero no se pueden guardar versiones. |

## Backup

Exporta la estructura y los datos de las tres tablas `f125_*` con una copia consistente para InnoDB.

Guarda también `config/`, `data/` y la versión del código utilizada. Mantén las copias fuera del directorio público y de Git.

Las exportaciones JSON permiten mover reglajes individuales. Para recuperar la instalación completa se necesita la copia de MariaDB y la configuración.

## Restauración

1. Crea una base vacía de recuperación.
2. Restaura la copia SQL.
3. Configura una copia de la aplicación para usar esa base.
4. Comprueba circuitos, versiones y los veinte valores de varios reglajes.
5. Guarda una versión nueva y verifica que las anteriores se conservan.

Comprueba la restauración en esa copia antes de sustituir una instalación en uso.
