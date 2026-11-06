[English version](../releasing.md)

# Flujo de releases

Utiliza una rama `release/X.Y.Z` y una única PR hacia `main` para toda la release. Añade cada incremento Alpha a esa PR. Mantén su descripción actualizada: se convertirá en las notas de la release.

## Política de versiones

Las versiones siguen `MAYOR.MENOR.PARCHE`, con `-alpha.N` para las compilaciones de prueba. El objetivo actual es `0.3.0`; las Alpha como `0.3.0-alpha.5` pertenecen a ese objetivo. El número de compilación identifica una entrega, no su grado de madurez.

| Cambio posterior a una estable | Siguiente objetivo desde `0.3.0` | Ejemplos |
| --- | --- | --- |
| Corrección compatible o ajuste visual pequeño | `0.3.1` | Corregir un fallo al guardar, una etiqueta incorrecta o un defecto de maquetación. |
| Nueva funcionalidad o rediseño importante de interfaz o flujo | `0.4.0` | Añadir comparación de sesiones o rediseñar la preparación y prueba de reglajes. |
| Cambio incompatible | Nueva menor antes de `1.0.0`; nueva mayor desde `1.0.0` | Cambiar un contrato de importación o exigir una migración. Documentar compatibilidad y recuperación antes de publicar. |
| Solo documentación o mantenimiento de desarrollo | Sin versión de la app | Actualizar una guía o eliminar notas locales ignoradas. |

Una nueva menor reinicia PARCHE a cero. Las correcciones anteriores a la publicación estable siguen siendo Alpha del mismo objetivo. No convierten `0.3.0-alpha.N` en `0.3.1`. Se elige el siguiente objetivo antes de abrir su rama y PR de release. El trabajo destinado a otro objetivo espera a su PR.

La primera `1.0.0` es un hito decidido expresamente: se consideran estables el flujo principal y los contratos de datos soportados. No aparece automáticamente tras un número de versiones menores. El actualizador rechaza versiones distintas del esquema base. Las migraciones compatibles que añaden catálogos se distribuyen en el paquete y se aplican por separado desde el navegador; consulta [migraciones de base de datos](migraciones-base-datos.md).

## Cuándo una Alpha pasa a estable

Para publicar una estable deben cumplirse todos estos puntos:

1. El alcance acordado está terminado y no hay fallos bloqueantes conocidos, como pérdida de datos, errores de guardado o importación, o actualizaciones fallidas.
2. Las comprobaciones automáticas pertinentes pasan en el commit final y los flujos modificados se han probado en una instalación real. Se revisan los límites de verificación pendientes.
3. El resumen de la PR, changelog, guías inglesas y copias españolas describen lo entregado. Se indican los requisitos de actualización o compatibilidad.
4. El responsable aprueba expresamente cerrar la release y publicarla como estable. Ni el tiempo transcurrido, ni una actualización Alpha correcta, ni la cantidad de Alpha equivalen a esa aprobación.

Tras aprobarla, se fusiona la PR. CI prueba el commit de fusión y prepara un borrador estable. Se revisa el paquete y se publica el borrador como última estable. Solo esa publicación ofrece `0.3.0` al canal Estable. El canal Alpha también puede avanzar a esa estable; la selección de canal no cambia.

La PR actual permanece abierta mientras se prueba `0.3.0-alpha.N`. Tras publicar `0.3.0`, se abre otra PR para `0.3.1-alpha.N` o `0.4.0-alpha.N`, según la tabla. No se fusiona una PR de release para publicar otra Alpha.

## Cambios que no publican Alpha

Los cambios exclusivos de README, CHANGELOG, guías, mantenimiento del repositorio o archivos locales ignorados no justifican una compilación de la app. La documentación se acumula con el siguiente cambio de aplicación. Si una entrega combina código y documentación, genera una sola Alpha tras superar las comprobaciones. El texto visible de la aplicación forma parte del código: cambiar el contenido de Inicio puede justificar una Alpha.

El flujo actual responde a cada actualización de la PR y todavía no filtra subidas exclusivamente documentales. Hasta implementar ese filtro, los cambios solo de documentación se mantienen locales para la siguiente subida de aplicación. Borrar un archivo ignorado no requiere commit ni push.

## Alpha

El flujo se ejecuta al abrir, reabrir o actualizar una PR de release del mismo repositorio.

1. Obtiene el commit de la rama de la PR y ejecuta las pruebas de dominio, actualización, publicación y navegador.
2. Genera `X.Y.Z-alpha.N`, donde N es el número de ejecución del flujo.
3. Crea una prerelease en borrador con el commit exacto y la identidad de la PR.
4. Sube el ZIP reproducible y comprueba su digest de GitHub.
5. Publica la prerelease solo si la PR sigue abierta en el mismo commit.

Un commit posterior deja obsoleta una ejecución anterior. Las instalaciones no reciben compilaciones fallidas o incompletas. Las PR de forks y las ramas que no son de release no pueden publicar mediante este flujo.

La variable opcional del repositorio `ALPHA_BUILD_OFFSET` suma un entero no negativo al número de ejecución. Su valor predeterminado es cero. Permite continuar la numeración Alpha por encima de las versiones ya distribuidas al trasladar el proyecto a otro repositorio.

## Release estable

Fusiona la PR cuando esté preparada. El flujo prueba el commit de fusión y prepara un borrador para `vX.Y.Z`, con:

- El ZIP de actualización estable.
- La descripción de la PR como notas de la release.
- Los metadatos que relacionan versión, commit y PR.

Revisa el borrador y publícalo como release estable, marcándolo como última versión. La fusión por sí sola no lo ofrece al canal Estable. Mantén CHANGELOG por separado; su traducción pertenece a la documentación de `docs/es-es/`. Las notas de release y los mensajes de commit permanecen en inglés.

No sustituyas paquetes publicados ni reutilices tags para otros commits. Una corrección necesita otra versión. La inmutabilidad de releases de GitHub permite imponer esta regla después de adjuntar todos los archivos.

## Acceso al repositorio

El flujo utiliza el token proporcionado por GitHub con escritura en el contenido de las releases y lectura de PR. Las instalaciones consultan el repositorio fijo del proyecto; los repositorios privados necesitan un token de servidor con permisos de lectura.

## Identidad del paquete

`src/VERSION` declara la versión de desarrollo, por ejemplo `0.3.0-alpha.0`. La rama debe llamarse `release/0.3.0`. El generador introduce en el paquete la versión Alpha o estable exacta que se instalará.

Cada manifiesto `f125-update-v2` registra:

- Versión, canal, SHA completo del commit, número de PR y rama de release.
- Versión mínima de PHP y esquema de base de datos requerido.
- SHA-256 de cada archivo de la aplicación.

Una marca legible por el actualizador en las notas relaciona el archivo de GitHub con esa identidad. Las instalaciones comprueban tanto el digest del ZIP de GitHub como el manifiesto.

Para generar un paquete local de pruebas:

```sh
python bin/package.py --pr 1 --branch release/0.3.0
```

Se crea en `dist/`, excluido de Git; este comando no publica nada.
