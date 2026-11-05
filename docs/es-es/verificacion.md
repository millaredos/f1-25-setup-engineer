[English version](../testing.md)

# Pruebas y verificación

## Resultados de desarrollo de 0.3.0

PHP 8.5.12: 194 comprobaciones de dominio y contratos, 34 de paquetes y 51 de canales, GitHub, integridad y recuperación. Python: ocho pruebas de empaquetado y publicación. Los recorridos de navegador comprueban selección web del canal, CSRF, descarga verificada, copias y conservación del estado privado, en raíz y bajo una subcarpeta con la carpeta pública renombrada a `www`. Options y Updates también se comprueban en ancho móvil.

Las respuestas automatizadas de GitHub son fixtures; las pruebas no necesitan token ni modifican releases remotas. La interrupción se simula mediante un registro de recuperación y archivos parcialmente cambiados. No se han probado cortes eléctricos ni fallos de disco reales.

El recorrido de navegador también comprueba los veinte controles, comparación con la referencia, síntomas, importación y exportación JSON, recuperación del borrador, navegación, rechazo de XSS, CSRF y diseño adaptable a 1440, 768 y 390 px. Las pruebas utilizan copias temporales sin credenciales de producción. La simulación de subcarpeta usa `tests/web-router.php`; no cambia la configuración de Apache.

La verificación real también incluyó detección y descarga sin autenticación desde el repositorio público de GitHub, integridad del paquete y una actualización web correcta en una instalación existente. La interrupción de archivos se simula; la restauración completa de backups sigue sin verificarse.

## Ejecutar pruebas de dominio y actualización

```sh
php tests/run.php
php tests/updater.php
php tests/updates.php
python -m unittest discover -s tests -p 'test_*.py'
```

Estas pruebas no requieren base de datos. Las pruebas PHP del actualizador necesitan ZIP y escriben exclusivamente en sus propios directorios temporales.

## Ejecutar pruebas de navegador

Se necesitan PHP CLI, Node, Playwright y Microsoft Edge o Chromium como herramientas de desarrollo. No son requisitos adicionales del servidor de producción.

```sh
node tests/installation-browser.cjs
```

Para comprobar descarga e instalación, genera primero un paquete de prueba con `python bin/package.py --pr 1 --branch release/0.3.0` e indica su ruta absoluta en `F125_TEST_PACKAGE`. Usa `F125_TEST_PUBLIC_NAME=www` para probar la carpeta pública renombrada o `F125_TEST_BROWSER=chromium` para Chromium. El flujo de release ejecuta estas comprobaciones antes de publicar.

Este recorrido crea su propio sitio temporal y ejecuta también `tests/browser.cjs`. Comprueba estados de instalación ausente, pendiente, completo e inválido; redirecciones; rechazo CSRF; bloqueo de la API antes de instalar y comportamiento cuando MariaDB no responde. Al terminar elimina únicamente su copia temporal.

| Variable opcional | Uso |
| --- | --- |
| `F125_TEST_PHP` | Ruta al ejecutable PHP; por defecto `php`. |
| `F125_PLAYWRIGHT` | Ruta al módulo Playwright; por defecto `playwright`. |
| `F125_SCREENSHOTS` | Directorio donde guardar capturas. |
| `F125_TEST_BASE_PATH` | Montaje simulado; por ejemplo `/herramientas/reglajes/`. |
| `F125_TEST_HOST` | Host de loopback de la prueba; por ejemplo `localhost`. |
| `F125_TEST_ZIP_DIR` | Directorio de extensiones del PHP de prueba, para cargar ZIP. |
| `F125_TEST_PACKAGE` | Paquete generado con `python bin/package.py`, para probar su descarga e instalación mediante fixtures. |

Para ejecutar `tests/browser.cjs` por separado contra una instalación de pruebas ya preparada, indica su URL en `F125_TEST_URL`. No lo ejecutes contra una instalación con datos de trabajo.

## Pruebas de persistencia

`tests/integration.php` verifica guardado, reapertura, versiones inmutables, conflictos y rollback. Exige una base MariaDB vacía indicada explícitamente:

```text
F125_TEST_DSN=mysql:host=HOST;dbname=BASE_DE_PRUEBAS;charset=utf8mb4
F125_TEST_DB_USER=USUARIO
F125_TEST_DB_PASSWORD=CONFIGURAR_FUERA_DEL_REPOSITORIO
F125_TEST_ALLOW_WRITE=yes
```

Si ya existe alguna tabla `f125_*`, la prueba se niega a ejecutarse. En una base de pruebas vacía crea las tablas y las elimina al terminar. Nunca toma automáticamente las credenciales de la aplicación. Sin las variables necesarias termina con código 2: prueba omitida, no superada.

## Límites de la verificación

- La versión 0.3.0 no se ha vuelto a probar en PHP 8.0.
- La integración automatizada con una MariaDB aislada y la restauración completa de backups siguen pendientes.
- No se han provocado fallos de disco ni cortes eléctricos durante las actualizaciones.
- Los rangos y las explicaciones requieren contraste con la versión instalada del juego; no hay validación en pista.
- Cada instalación debe comprobar por separado extensiones PHP, permisos y recuperación en su alojamiento.
