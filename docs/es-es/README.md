[English version](../../README.md)

![MillasGamin — Simracing, F1, gameplay y competición](../../media/MillasGamin%20banner.png)

<p align="center">
  <img src="../../media/logo.png" alt="Logo de MillasGamin" width="112">
</p>

# F1 25 / 26 Setup Engineer

Aplicación web para editar, comparar y guardar reglajes de **F1 25** y su **2026 Season Pack**. Parte del predeterminado del juego cuando esté disponible y consulta los cambios antes de probarlos en pista.

Desarrollada en PHP, MariaDB, JavaScript nativo y CSS puro.

[Instalación](#instalación) · [Uso](#uso) · [Actualización](#actualización) · [Documentación](#documentación) · [Desarrollo](#desarrollo)

## Funciones

- Veinte ajustes organizados por categoría, con explicación de sus efectos.
- Comparación con los valores cargados en el taller.
- Propuestas de pruebas para ocho síntomas de conducción.
- Importación de fichas de circuito y reglajes en JSON.
- Reglajes guardados con comentarios por grupo y ficha del circuito.
- Importación y exportación JSON.
- Selector de temporada 2025 y 2026; los valores predeterminados de 2025 están pendientes de recopilar y verificar.
- Migraciones versionadas con cinco predeterminados del coche de 2026 y asignaciones confirmadas para los 24 circuitos del campeonato e Imola.

El selector incluye el catálogo del juego y las fichas importadas para circuitos adicionales. Las explicaciones son orientativas y deben comprobarse en el juego.

**Versión de desarrollo: 0.4.0-alpha.0.** Consulta los cambios en [CHANGELOG.md](CHANGELOG.md).

## Requisitos

- Apache con PHP 8.0 o posterior, en una rama mantenida.
- Extensiones JSON, sesiones, PDO y PDO_MYSQL.
- MariaDB con InnoDB.
- Navegador moderno con JavaScript.
- Extensiones ZIP y cURL de PHP para utilizar el actualizador.

La aplicación no incluye cuentas de usuario. Para permitir acceso por Internet, configura autenticación en el servidor o proxy.

## Instalación

La instalación se completa desde el navegador. Los archivos, permisos y la base de datos se preparan con las herramientas de tu alojamiento.

1. Descarga el código y coloca las carpetas con esta estructura. Publica únicamente `public_html/`:

   ```text
   directorio-de-la-aplicacion/
   ├── public_html/
   ├── src/
   ├── data/
   └── config/
   ```

   Si tu alojamiento llama a la carpeta pública `www`, `public` u otro nombre, renombra `public_html` para que coincida. Mantén `src/`, `data/` y `config/` como carpetas hermanas, fuera de la carpeta pública.

2. Prepara una base MariaDB y una cuenta. Copia `config/local.example.php` a `config/local.php` y completa la conexión:

   ```php
   <?php
   return [
       'dsn' => 'mysql:host=127.0.0.1;dbname=f125;charset=utf8mb4',
       'username' => 'usuario_de_la_aplicacion',
       'password' => 'SUSTITUIR_EN_EL_SERVIDOR',
   ];
   ```

3. Da a PHP permiso de escritura en `config/` y abre la URL de la aplicación.
4. Revisa los requisitos y pulsa **Instalar**. El instalador prepara las tablas y abre Inicio.

Funciona en la raíz de un sitio o bajo una subcarpeta, como `https://example.com/reglajes/`. Utiliza la URL de la carpeta con barra final o `index.php`; el servidor debe asociar esa ruta con `public_html/`.

La [guía de instalación](instalacion.md) detalla los permisos, la configuración por variables de entorno y la recuperación de datos.

### Nginx

La instalación documentada utiliza Apache; el proyecto aún no ha verificado la instalación con Nginx. Nginx no lee los archivos `.htaccess` de Apache. Para un servidor virtual dedicado a esta aplicación, añade lo siguiente **dentro del bloque `server` existente**, antes de otros bloques `location` con expresiones regulares:

```nginx
autoindex off;

# Deniega archivos y directorios ocultos.
location ~ (^|/)\. {
    deny all;
}

# Deniega restos habituales de edición y copias.
location ~* (\.(bak|old|orig|save|swp|sql|log)|~)$ {
    deny all;
}

error_page 404 = @f125_not_found;
location @f125_not_found {
    default_type text/plain;
    return 404 "Página no encontrada.\n";
}
```

Conserva el gestor PHP existente y publica únicamente la carpeta pública de la aplicación; `src/`, `data/`, `config/` y las copias deben quedar fuera de todas las rutas públicas. El fragmento afecta a todo el servidor virtual. Revisa los bloques existentes de coincidencia exacta y `^~`, los `location` anidados, los desafíos ACME bajo `.well-known` y la interceptación de errores de `error_page`, FastCGI o proxy: pueden alterar qué reglas se aplican o sustituir los errores JSON de la API. Después de añadirlo, valida la configuración y comprueba la ejecución de PHP, el estado 404, los errores JSON de la API, los archivos bloqueados y las rutas privadas. Si la aplicación comparte servidor virtual o funciona en una subcarpeta, comprueba esa configuración por separado.

## Uso

1. En **Circuitos**, selecciona la temporada y un circuito, y consulta su predeterminado o una versión guardada por categoría. Los valores predeterminados de 2025 aún no están disponibles.
2. Usa **Añadir circuito** para descargar una plantilla JSON e importar [fichas adicionales](formato-circuito.md).
3. En **Taller**, elige **Nuevo reglaje** y selecciona temporada y circuito, o usa **Editar reglaje** para abrir uno guardado. El nuevo reglaje parte del predeterminado del juego si está disponible; en caso contrario, introduce todos los valores.
4. Modifica los valores, añade comentarios por categoría y guarda o elimina el reglaje en el taller. El resumen compara con los valores cargados. Usa **Importar reglaje** para cargar un archivo JSON, revisarlo y guardarlo como un reglaje nuevo.
5. En **Pruebas**, selecciona el comportamiento que notas en el coche.

Usa **Exportar JSON** en **Taller** para exportar el reglaje activo sin salir del editor.

Abre el menú de la versión, arriba a la derecha, para acceder a **Opciones** y **Actualizaciones**. Un punto indica una versión posterior en el canal seleccionado. **Opciones → Idioma de la aplicación** ofrece actualmente **Español** y recuerda la selección en este navegador.

Al salir del taller con cambios pendientes puedes guardarlos, descartarlos o seguir editando. Guardar un reglaje existente lo actualiza en MariaDB.

Consultar circuitos no modifica los reglajes guardados. El diálogo de edición muestra los reglajes del circuito y temporada seleccionados; los predeterminados del juego solo están disponibles para 2026. Las condiciones existentes en importaciones y reglajes guardados se conservan en su JSON.

Se admiten JSON `f125-setup-engineer` y del comparador anterior `f125-comparador-v2`. La importación incluye validación y vista previa.

## Actualización

Elige **Alpha** o **Estable** en **Opciones → Actualizaciones → Canal**. Alpha recibe compilaciones correctas de la PR de la release activa; Estable recibe estables publicadas. La aplicación detecta las nuevas versiones cuando se publican y muestra las actualizaciones disponibles en segundo plano. Pulsa **Actualizar** para descargar, verificar e instalar, con copia privada y registro de recuperación.

Consulta la [guía de actualización](actualizacion.md). Los repositorios públicos no necesitan token; los privados requieren un token de lectura de GitHub configurado una vez en el servidor. No hay contraseña adicional del actualizador ni subida manual de ZIP.

Para una copia completa, respalda también MariaDB y `config/`. Las instrucciones de [backup y restauración](instalacion.md#backup) están en la guía de instalación.

## Documentación

| Guía | Contenido |
| --- | --- |
| [Instalación](instalacion.md) | Configuración, permisos, backup y restauración. |
| [Formatos JSON](formato-circuito.md) | Fichas de circuito y reglajes compatibles. |
| [Fichas de circuitos](fichas-circuitos.md) | Características y fuentes de los circuitos incluidos. |
| [Actualización](actualizacion.md) | Paquetes y recuperación de archivos. |
| [Flujo de releases](publicar-versiones.md) | Una PR por release, compilaciones Alpha y publicación estable. |
| [Arquitectura](arquitectura.md) | Stack, capas, dependencias y almacenamiento. |
| [Traducciones](traducciones.md) | Catálogos PO y alta de idiomas. |
| [Pruebas](verificacion.md) | Ejecución, resultados y limitaciones. |
| [Hoja de ruta](ROADMAP.md) | Ideas para el desarrollo futuro. |

## Desarrollo

Monolito PHP con separación entre presentación, dominio y persistencia. El frontend usa HTML, JavaScript nativo y CSS en archivos separados.

```text
public_html/   Interfaz, API HTTP y archivos estáticos
src/           Clases PHP y arranque
data/          Catálogos de ajustes y pruebas de conducción
languages/     Catálogos PO editables de traducción
config/        Configuración de la instalación
tests/         Pruebas y fixtures
bin/           Herramientas de desarrollo
docs/          Documentación; versiones en español en es-es/
media/         Logo y banner
```

Para ejecutar las pruebas de dominio y del actualizador:

```sh
python -m pip install -r requirements-dev.txt
python bin/translations.py --check
php tests/translations.php
php tests/run.php
php tests/updater.php
php tests/updates.php
python -m unittest discover -s tests -p 'test_*.py'
```

Las pruebas PHP de actualización requieren ZIP. Las de navegador utilizan Node, Playwright y Edge o Chromium. Las de persistencia necesitan una base MariaDB vacía de pruebas; consulta [verificación](verificacion.md).

Servidor local de desarrollo:

```sh
php -d register_argc_argv=0 -d display_errors=0 -S 127.0.0.1:8125 -t public_html
```

Abre `http://127.0.0.1:8125/` y completa el instalador. Node y Python se usan para pruebas y empaquetado, respectivamente; no se necesitan en producción.

### Contribuir

Al comunicar un fallo, indica versión, entorno, pasos y resultado esperado. Aporta ejemplos sin credenciales ni datos privados.

Los cambios en ajustes y explicaciones deben incluir fuentes y conservar los identificadores y formatos JSON existentes.

## Licencia

[MIT](../../LICENSE). Proyecto independiente, sin afiliación a EA, Codemasters ni Formula 1.
