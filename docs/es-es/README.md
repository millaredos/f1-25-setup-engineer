[English version](../../README.md)

# F1 25 / 26 Setup Engineer

Aplicación PHP autoalojada para consultar circuitos, editar y comparar reglajes, preparar pruebas teóricas de conducción y guardar o exportar reglajes personales de F1 25 y su 2026 Season Pack.

Versión de desarrollo: **0.5.0-alpha.0**. Esta reconstrucción no es una versión publicada.

## Arquitectura

La aplicación utiliza MVC y PHP orientado a objetos, sin framework, paquetes Composer ni compilación del frontend. El controlador frontal público compone los controladores; los modelos validan coches, circuitos y reglajes; los repositorios contienen PDO; las vistas privadas muestran datos preparados. Los módulos del navegador separan borrador, transporte y coordinación de pantalla. El CSS separa variables comunes, componentes y disposición del taller.

```text
public_html/       Entradas HTTP y recursos del navegador
src/Controller/    Coordinación de peticiones
src/Model/         Reglas de coches, circuitos y reglajes
src/Application/   Importación, comparación, instalación y actualización
src/Persistence/   Repositorios MariaDB
src/Infrastructure/ Catálogos, estado privado, HTTPS y bloqueos
src/Http/          Peticiones, respuestas, sesiones y renderizado
src/View/          Plantillas privadas
data/             Catálogos versionados y descripciones con fuentes
languages/        Catálogos PO editables
dev/              Herramientas y pruebas PHP
config/           Configuración privada de instalación
```

## Instalación

Requiere PHP 8.0+, PDO_MYSQL y MariaDB. Las actualizaciones requieren ZIP y cURL. Publica **solo** `public_html/`; fuentes, datos, configuración y backups deben quedar fuera de HTTP. Se admite instalación en raíz y subcarpeta.

Copia `config/local.example.php` a `config/local.php` y configura DSN, usuario y contraseña. Como alternativa, proporciona `F125_DSN`, `F125_DB_USER` y `F125_DB_PASSWORD` en el entorno del servidor. Abre la aplicación y completa explícitamente la instalación desde el navegador. El usuario no necesita ejecutar comandos PHP.

El estado de instalación es independiente de la base. Una caída posterior no reinicia la instalación. CSRF protege mutaciones; la autenticación, si hace falta, corresponde al alojamiento.

## Uso

Selecciona temporada y circuito. El catálogo 2026 contiene cinco predeterminados conocidos y sus asignaciones. El de 2025 está vacío hasta recopilar sus valores. Por eso un borrador nuevo queda vacío cuando no hay un predeterminado confirmado.

El taller edita un borrador en memoria. Guardar, sustituir, eliminar, importar y exportar son acciones explícitas. Consultar circuitos y pruebas conserva el borrador. Salir del espacio de trabajo con cambios pendientes requiere decidir si descartarlos.

Se mantienen los veinte IDs publicados y los formatos JSON admitidos. Las referencias importadas siguen separadas de los valores actuales. La temporada ausente permanece desconocida hasta seleccionarla expresamente. Las explicaciones son teóricas: no simulan física ni predicen tiempos. El catálogo explicativo compartido no se ha verificado independientemente para las dos temporadas.

## Instalaciones existentes

Se conservan las tablas personales `f125_circuits`, `f125_setups` y el esquema 1. La reconstrucción lee sus datos JSON. Los datos distribuidos de temporada están separados de esas tablas; no se eliminan los catálogos antiguos de la base.

**La primera migración desde la arquitectura anterior es manual.** Guarda copias de archivos y MariaDB, conserva la configuración privada y el estado de instalación, sustituye el árbol de aplicación por estas fuentes y publica el nuevo directorio público. No instales un paquete MVC con el actualizador anterior. El nuevo actualizador solo admite paquetes completos `f125-update-v3` y no ofrece paquetes de la arquitectura antigua. Desarrollar estas fuentes no ha migrado ninguna instalación real.

## Desarrollo

Las herramientas del proyecto solo necesitan PHP. No hay paquetes Python, Node, npm ni de automatización de navegador.

```sh
php dev/bin/translations.php
php dev/bin/translations.php --check
php dev/tests/run.php
php dev/tests/http.php
```

La integración MariaDB real requiere una base vacía dedicada llamada `f125_test_*` y variables de prueba explícitas; consulta [verificación](verificacion.md). Revisa la interfaz con un servidor PHP temporal y un navegador:

```sh
php -d register_argc_argv=0 -d display_errors=0 -S 127.0.0.1:8125 -t public_html
```

[Arquitectura](arquitectura.md) · [Formatos](formatos-json.md) · [Publicación](publicar-versiones.md) · [Changelog](CHANGELOG.md)
