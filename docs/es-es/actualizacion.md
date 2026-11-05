[English version](../updating.md)

# Actualización

## Elegir canal

Abre **Options → Updates → Channel** y guarda:

| Canal | Versiones disponibles |
| --- | --- |
| **Release** (predeterminado) | Releases estables publicadas con un paquete de actualización válido. |
| **Alpha** | Compilaciones correctas de la PR de la release activa, con las estables como alternativa. |

La selección se guarda en el servidor y se aplica a la instalación. Cambiar de canal no instala nada. Nunca se instala automáticamente una versión anterior; una instalación Alpha puede volver a Release cuando haya una estable posterior.

La aplicación comprueba las novedades en segundo plano mientras está abierta. La caché de versiones dura cinco minutos en Alpha y seis horas en Release. **Check for updates** permite renovarla, con un intervalo mínimo de un minuto y respetando los límites de GitHub.

GitHub permite 60 consultas sin autenticación por hora e IP del servidor, compartidas con otras aplicaciones que utilicen esa IP. Este límite afecta a la consulta o descarga de una release nueva; las comprobaciones habituales usan el servicio de versiones de la app sin consultar la API de GitHub. Si se alcanza el límite, Updates indica la hora UTC de reintento y pausa las consultas hasta entonces; recargar no evita esa espera. Un HTTP 403 sin indicios de límite se informa como acceso denegado.

## Requisitos del servidor

- Extensiones ZIP y cURL de PHP, acceso HTTPS al servicio de versiones y a GitHub, con certificados CA válidos.
- Escritura en los archivos de la aplicación, el estado privado de `config/` y `backups/`.
- Para un repositorio privado, un token de acceso detallado con **Contents: read** y **Pull requests: read** para ese repositorio. Configura `github_token` en `config/local.php` o `F125_GITHUB_TOKEN` en el entorno del PHP web. Un repositorio público no necesita token.

No hay una contraseña adicional del actualizador ni un formulario para subir ZIP. Las descargas se limitan al repositorio del proyecto.

## Instalar una actualización

1. Guarda o exporta el borrador y termina las operaciones de otras pestañas.
2. Abre **Updates** y revisa la versión disponible, el commit y las notas.
3. Pulsa **Update**.
4. Comprueba la versión instalada después de recargar la página.

Antes de instalar, la aplicación vuelve a comprobar la release seleccionada, verifica el SHA-256 de GitHub y compara versión, canal, PR y commit con el manifiesto del paquete. Comprueba los requisitos de PHP y el esquema de la base de datos antes de sustituir archivos.

Se conservan la configuración, el canal, el estado de instalación y los datos de MariaDB. No se admiten migraciones: se rechazan los paquetes que necesitan otro esquema.

## Primera actualización desde 0.2.x

El actualizador anterior no puede descargar compilaciones de GitHub. Incorpora una vez los nuevos archivos de `src/`, `data/` y la carpeta pública con las herramientas de tu alojamiento. Conserva `config/local.php` y `config/installation.json`. Después se actualiza desde la web. La antigua opción `update_key` deja de utilizarse.

El paquete utiliza `public_html/` como ruta pública lógica. El instalador la adapta a la carpeta pública real, incluida `www/` o `public/`.

## Límites y recuperación

La descarga se limita a 8 MB; el contenido descomprimido a 16 MB, con un máximo de 2 MB por archivo.

Antes de sustituir archivos, se guarda su contenido anterior en `backups/update-FECHA-ID/` y se escribe un registro privado de recuperación. Durante la sustitución, las peticiones simultáneas reciben una respuesta temporal 503. Si el proceso se interrumpe, la siguiente petición intenta recuperar la instalación antes de cargar la aplicación.

Conserva la copia si falla la recuperación automática. Restaura los archivos en sus destinos originales; el contenido de `public_html/` de la copia corresponde a tu carpeta pública real. Elimina `config/update-journal.json` solo después de completar y comprobar la restauración manual. Las copias de MariaDB se realizan por separado.

## Generar y publicar

Consulta [el flujo de releases](publicar-versiones.md). Los paquetes se generan con `bin/package.py` y se adjuntan a las releases de GitHub; el ZIP genérico del código fuente no sirve como paquete de actualización.
