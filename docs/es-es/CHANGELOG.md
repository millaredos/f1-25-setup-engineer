[English version](../../CHANGELOG.md)

# Changelog

## Sin publicar

### 0.3.0

- Cabecera compacta con el menú entre la marca y la versión; navegación con desplazamiento horizontal en pantallas pequeñas.
- Canales Alpha y Release seleccionables desde Options y guardados por instalación.
- Consultas de GitHub en segundo plano, caché compartida, espera ante límites y descarga verificada sin contraseña del actualizador ni subida de ZIP.
- Compilaciones Alpha desde una única PR activa de release; al fusionar, se ejecutan pruebas y se prepara un borrador estable con el resumen de la PR como notas.
- Paquetes reproducibles con versión, commit, PR, canal y requisitos de PHP y esquema.
- Repositorios privados mediante un token de servidor opcional de solo lectura.
- Bloqueo de actualización, registro persistente de recuperación y carpetas públicas renombradas.
- Fixtures de GitHub sin conexión, pruebas de empaquetado y publicación, y pruebas web de canales y actualización.

### 0.2.0 — 2026-11-05

#### Añadido

- Inicio con menú: Circuito y referencia, Taller, Pruebas, Versiones y Actualización.
- Versión visible en la cabecera, también en móvil; recursos CSS y JavaScript con URL versionada para renovar la caché.
- Actualizador de paquetes ZIP con clave de administración, protección CSRF, validación de rutas, límites, hashes y versión.
- Copia de los archivos anteriores y recuperación ante errores de sustitución detectados durante la petición.
- Generador de paquetes para desarrollo y guía de actualización y recuperación.
- README con requisitos, instalación, primer reglaje, formatos JSON, backups y desarrollo; logo y banner de MillasGamin.
- Documentación en inglés con traducciones al español en `docs/es-es/`.

#### Mejorado

- Navegación entre apartados conservando el borrador.
- Rutas relativas verificadas en raíz y subcarpeta con distintos hosts y puertos.
- Los nuevos guardados no atribuyen hardware ni ayudas de conducción no declarados.
- Fixtures independientes de los materiales de referencia locales.
- Documentación distribuida centrada en instalación, uso, arquitectura y pruebas.

### Primera versión

- Editor de veinte ajustes por categoría, referencia independiente y resumen de efectos teóricos.
- Ocho síntomas con propuestas de pruebas, compromisos, combinaciones y límites.
- Importación de fichas de circuito y compatibilidad con JSON de reglajes nuevos y heredados, con vista previa.
- Versiones inmutables en MariaDB mediante PDO y tablas con prefijo `f125_`.
- Exportación JSON y recuperación explícita del borrador del navegador.
- Validación de tipos, rangos y pasos en el servidor, sesiones y protección CSRF.
- Instalador web con comprobación de requisitos, creación de tablas mediante POST y bloqueo tras completar la instalación.
- Estado privado de instalación con escritura atómica: un fallo posterior de MariaDB no permite reinstalar.
- Estructura con `public_html/` como único directorio público y configuración, datos y copias fuera de HTTP.
- HTML, JavaScript nativo y CSS externo, sin dependencias adicionales de producción.
- Licencia MIT.
