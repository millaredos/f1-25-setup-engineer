[English version](../releasing.md)

# Publicar versiones

Mantén una rama `release/X.Y.Z` y una PR por versión objetivo. Un arreglo compatible usa parche; una función o rediseño importante usa menor. Antes de 1.0, una incompatibilidad exige nueva menor y procedimiento de actualización explícito. Publicar una estable requiere aprobación expresa.

## Categorías de cambios y commits

| Tipo | Significado |
| --- | --- |
| `fix` / `bugfix` | Corregir un fallo. |
| `hotfix` | Corrección urgente de un fallo crítico en producción. |
| `feat` / `feature` | Añadir funcionalidad. |
| `refactor` | Cambiar estructura sin cambiar comportamiento externo. |
| `chore` | Mantenimiento rutinario. |
| `docs` | Solo documentación. |
| `test` | Cambios en pruebas. |
| `style` | Formato sin cambios de lógica. |
| `perf` | Mejorar rendimiento. |
| `build` | Cambios de construcción. |
| `ci` | Integración continua. |
| `revert` | Deshacer un cambio. |

Explica comportamientos cambiados o retirados en lugar de ocultarlos bajo refactor. Separa Sin publicar de versiones entregadas. Una versión local de desarrollo no es una publicación.

## Pipeline PHP

El workflow obtiene con Git el commit exacto de PR o fusión, instala extensiones PHP, ejecuta pruebas PHP y llama a `dev/bin/publish.php`. No utiliza Actions basadas en Node ni herramientas Python. La numeración Alpha consulta releases y etiquetas de la versión objetivo. Solo publica Alpha si la PR sigue apuntando al commit comprobado. Una PR fusionada crea o verifica un borrador estable; no lo publica.

La documentación aislada no debe provocar Alpha. Usa la marca de PR `<!-- stable-release-only -->` si solo preparas el borrador estable; en otros casos acumula la documentación con la siguiente entrega funcional.

El contenido MVC usa `f125-update-v3`, con versión, canal, commit, PR, rama, mínimo PHP/esquema y SHA-256 por archivo. `buildReleasePackage` envuelve ese ZIP en el transporte v2 que admite el actualizador original de 0.4. Su envoltura JSON limitada contiene el digest interior y la misma identidad. Los metadatos de la release identifican format v2 y payloadFormat v3. GitHub confirma el digest del ZIP exterior antes de publicar. No se sustituye contenido publicado y solo se anuncian paquetes completos verificados.

Para un paquete local de pruebas, indica destino e identidad:

```sh
php dev/bin/package.php --version=0.5.0-alpha.1 --channel=alpha --commit=SHA_COMPLETO --pr=1 --branch=release/0.5.0 --output=/RUTA/ABSOLUTA/f125-update-0.5.0-alpha.1.zip
```

El comando genera un paquete MVC de prueba sin envoltura y no publica. El workflow construye automáticamente el transporte compatible. Su envoltura está limitada por debajo del máximo por archivo de 0.4; un contenido excesivo falla antes de publicar. El paquete original 0.5.0-alpha.1 carecía de ese transporte y queda sustituido para actualizar desde 0.4.0.
