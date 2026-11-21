[English version](../testing.md)

# Verificación

Ejecuta desde la raíz con PHP CLI:

```sh
php dev/bin/translations.php --check
php dev/tests/run.php
php dev/tests/http.php
```

La primera suite comprueba límites del coche, adaptadores, conservación de referencias, disponibilidad por edición, compilación PO, paquetes reproducibles, integridad, recuperación y sintaxis PHP. ZIP es obligatorio: si falta, la suite falla en lugar de presentar comprobaciones omitidas como éxitos.

Las pruebas HTTP levantan sitios PHP temporales aislados en raíz y `/tools/setups/`. Comprueban instalación, CSRF, comparación, arranque sin base, páginas y exclusión de fuentes privadas. Utilizan un destino MariaDB de pruebas inaccesible y nunca copian credenciales privadas.

La integración MariaDB real es independiente:

```text
F125_TEST_DSN=mysql:host=HOST;dbname=f125_test_example;charset=utf8mb4
F125_TEST_DB_USER=TEST_USER
F125_TEST_DB_PASSWORD=CONFIGURAR_EN_PRIVADO
F125_TEST_ALLOW_WRITE=yes
```

Ejecuta `php dev/tests/integration.php`. Rechaza nombres que no comiencen por `f125_test_` y bases con tablas de aplicación existentes. Crea únicamente las tres tablas personales de prueba y las elimina al terminar. Sin configuración termina con código 2: omitida, no aprobada.

Utiliza el navegador disponible con un sitio PHP temporal para revisar escritorio/móvil, importaciones, categorías, decisiones sobre cambios y enlaces de fuentes. Se deben informar por separado las pruebas de navegador y MariaDB real frente a las de modelo y HTTP. El repositorio no instala paquetes de automatización de navegador.

Comprueba también PHP 8.0: pasar en un runtime posterior no demuestra el comportamiento de la versión mínima. Publicación en GitHub, actualización real y recuperación por corte eléctrico son comprobaciones distintas de las pruebas de paquetes sin conexión.

## Regresión de la transición

Ejecuta `php dev/tests/transition.php` tras obtener el commit público fijo de 0.4 `c166bf04d50cd12c640434ade7fa7cda16919f48`. La prueba carga sus clases reales de actualización y descubrimiento, sin modificarlas, desde Git como fixtures temporales de regresión. CI obtiene explícitamente ese commit. Esas fuentes no se incluyen en paquetes de producción.

La prueba recorre la instalación con el servicio de 0.4 y la redirección que instala MVC, en raíz y bajo una subcarpeta con el directorio público renombrado. Verifica ambos formatos de diario, un fallo de sustitución provocado y su reintento, retirada explícita de archivos, conservación de configuración privada y archivos del alojamiento y una actualización MVC posterior con el mismo transporte. Las respuestas HTTPS son fixtures deterministas; la migración real de la base y las pruebas de pérdida de energía son verificaciones separadas.
