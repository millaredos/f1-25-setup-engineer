[English version](../releasing.md)

# Flujo de releases

Utiliza una rama `release/X.Y.Z` y una única PR hacia `main` para toda la release. Añade cada incremento Alpha a esa PR. Mantén su descripción actualizada: se convertirá en las notas de la release.

## Alpha

El flujo se ejecuta al abrir, reabrir o actualizar una PR de release del mismo repositorio.

1. Obtiene el commit de la rama de la PR y ejecuta las pruebas de dominio, actualización, publicación y navegador.
2. Genera `X.Y.Z-alpha.N`, donde N es el número de ejecución del flujo.
3. Crea una prerelease en borrador con el commit exacto y la identidad de la PR.
4. Sube el ZIP reproducible y comprueba su digest de GitHub.
5. Publica la prerelease solo si la PR sigue abierta en el mismo commit.

Un commit posterior deja obsoleta una ejecución anterior. Las instalaciones no reciben compilaciones fallidas o incompletas. Las PR de forks y las ramas que no son de release no pueden publicar mediante este flujo.

## Release estable

Fusiona la PR cuando esté preparada. El flujo prueba el commit de fusión y prepara un borrador para `vX.Y.Z`, con:

- El ZIP de actualización estable.
- La descripción de la PR como notas de la release.
- Los metadatos que relacionan versión, commit y PR.

Revisa el borrador y publícalo como release estable, marcándolo como última versión. La fusión por sí sola no lo ofrece al canal Release. Mantén CHANGELOG por separado; su traducción pertenece a la documentación de `docs/es-es/`. Las notas de release y los mensajes de commit permanecen en inglés.

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
