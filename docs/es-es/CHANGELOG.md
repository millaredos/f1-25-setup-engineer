[English version](../../CHANGELOG.md)

# Changelog

## 0.3.0

### Sin publicar

- Consultar los valores del reglaje seleccionado por categoría sin abrirlo en el taller.

### 0.3.0-alpha.16

- Mostrar los circuitos del catálogo y ofrecer su predeterminado junto a los reglajes personales guardados.
- Compactar Circuito y referencia, retirar el aviso rutinario de taller listo y la importación manual de predeterminados, y conservar Añadir circuito para fichas adicionales.
- Mantener el footer al pie en páginas cortas, con © 2026 millaredos y enlace a GitHub.

### 0.3.0-alpha.15

- Completar las asignaciones de los 24 circuitos del campeonato y añadir Imola con el predeterminado 3 mediante una migración adicional.

### 0.3.0-alpha.14

- Añadir migraciones SQL versionadas, carga inicial automática y una página de actualización de la base con protección CSRF para instalaciones existentes.
- Incluir cinco predeterminados del juego, 24 nombres oficiales de circuitos y la asignación confirmada Albert Park → predeterminado 3, separados de los reglajes personales.

- Se retira el enlace duplicado Inicio; el logo abre esa pantalla sin recargar el borrador.
- La carga de reglajes guardados pasa junto al selector de circuito y se filtra por el circuito elegido.
- Se retiran los selectores de clima y sesión de la carga, conservando los metadatos existentes en importaciones y versiones guardadas.

### 0.3.0-alpha.13

- Opciones y Actualizaciones pasan al desplegable compartido de la versión, con apertura por ratón, clic, toque y teclado y un punto de aviso de nueva versión.
- Interfaz unificada en español y catálogos PO editables con traducciones PHP generadas y compartidas con JavaScript.
- Selector de idioma visible en Opciones, inicialmente con Español y preferencia guardada por navegador.
- Validación de catálogos y marcadores antes de empaquetar; mensajes de respaldo si no se puede cargar un catálogo al ejecutar la aplicación.

### 0.3.0-alpha.10

- Corregido: las consultas periódicas de actualización a la API de GitHub se sustituyen por el servicio propio de versiones.

### 0.3.0-alpha.9

- Respeta la hora de reinicio del límite de GitHub, distingue accesos denegados e indica cuándo reanudar las comprobaciones.
### 0.3.0-alpha.8

- Inicio incorpora una introducción centrada, columnas equilibradas, títulos legibles y pasos numerados en escritorio y móvil.

### 0.3.0-alpha.7

- Inicio sustituye las tarjetas del menú por una introducción, los límites de la app y una guía breve de cada apartado.

### 0.3.0-alpha.5

- Logo proporcionado en la cabecera de la aplicación, incluidas Opciones y Actualizaciones.

### 0.3.0-alpha.4

- Cabecera compacta con el menú entre la marca y la versión; navegación con desplazamiento horizontal en pantallas pequeñas.

### 0.3.0-alpha.1

- Canales Alpha y Estable seleccionables desde Opciones y guardados por instalación.
- Consultas de GitHub en segundo plano, caché compartida, espera ante límites y descarga verificada sin contraseña del actualizador ni subida de ZIP.
- Compilaciones Alpha desde una única PR activa de release; al fusionar, se ejecutan pruebas y se prepara un borrador estable con el resumen de la PR como notas.
- Paquetes reproducibles con versión, commit, PR, canal y requisitos de PHP y esquema.
- Repositorios privados mediante un token de servidor opcional de solo lectura.
- Bloqueo de actualización, registro persistente de recuperación y carpetas públicas renombradas.
- Fixtures de GitHub sin conexión, pruebas de empaquetado y publicación, y pruebas web de canales y actualización.

## 0.2.0 — 2026-11-05

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
