[English version](../coding-standards.md)

# Convenciones de código

## Responsabilidades

Mantener el monolito PHP y los módulos del navegador existentes. Los puntos de entrada HTTP validan la petición y coordinan dominio y almacenamiento; no contienen SQL. `Domain` valida datos del producto y describe cambios teóricos sin HTTP ni PDO. `Store` concentra SQL preparado y transacciones. Instalación y actualización gestionan sus bloqueos, validación de archivos y recuperación. El navegador controla presentación y borrador; su validación no sustituye la del servidor.

Separar consulta de circuitos y edición del taller. Consultar otro circuito no debe sustituir el borrador. Distinguir temporada consultada de temporada del borrador e ID guardado de valores editables. Guardar nombre y notas en el estado del borrador, sin controles invisibles. Conservar metadatos importados aunque no tengan editor.

## Nombres y formato

- Nombres ingleses: `camelCase` para funciones y variables PHP/JavaScript, `PascalCase` para clases, `UPPER_SNAKE_CASE` para constantes y `snake_case` para funciones Python. Conservar identificadores JSON publicados y nombres de base de datos.
- Nombrar por responsabilidad: `render…` cambia presentación, `load…` lee, `save…` persiste y `mark…` cambia estado. No sugerir persistencia cuando solo se activa una marca de cambios.
- Cuatro espacios en PHP/Python y dos en JavaScript/CSS. Separar instrucciones y control de flujo en líneas legibles. Usar llaves en PHP y JavaScript. Seguir el formato PSR-12 en PHP, declarar tipos estrictos tras la apertura y usar tipos compatibles con PHP 8.0.
- Cada función tiene una responsabilidad. Revisar las que superen 20–30 líneas; extraer operaciones o manejadores con sentido, sin dividir para cumplir una cifra. No crear módulos o abstracciones que no reduzcan acoplamiento o repetición reales.
- Todo el CSS permanece en hojas de estilo. Agrupar reglas por componente y explicar variantes adaptables. Antes de quitar selectores comprobar plantillas y DOM construido dinámicamente.

## Comentarios y contratos

Documentar el propósito de cada módulo, clase y función mantenidos mediante PHPDoc, JSDoc y docstrings Python. Explicar por qué existe la operación y, cuando sean relevantes, estructuras de entrada y salida, mutaciones, excepciones e invariantes. Documentar callbacks relacionados junto a su registro. Las pruebas explican qué comportamiento protegen y sus fixtures; los archivos generados indican fuente y generador.

Los comentarios explican intención, sin narrar instrucciones obvias. Una función que marca el borrador debe aclarar que no persiste en el navegador. Un guardado debe indicar qué registro cambia y qué identidad conserva. Una vista previa asíncrona debe explicar cómo descarta respuestas antiguas.

```javascript
/** Mark the in-memory draft as changed so leaving the workshop requires a decision. */
function markDraftChanged() {
  state.dirty = true;
}
```

Para arrays PHP, precisar la estructura útil con `@param` o `@return`, sin repetir tipos ya explicados en la firma. Describir rechazos de validación y errores de almacenamiento donde se gestionan. Actualizar los comentarios al cambiar código y retirar explicaciones caducadas.

## Límites de confianza y errores

Validar valores externos en el dominio antes de persistir. Validar método HTTP, CSRF, contenido y tamaño en los puntos de entrada. Los parámetros PDO representan datos, no identificadores: los identificadores dinámicos requieren una lista cerrada. Escapar HTML al emitir mediante `h()` y validar esquemas URL por separado. Insertar texto importado mediante nodos DOM y `textContent`; serializar JSON según su destino.

Capturar fallos donde sea posible recuperar o producir una respuesta HTTP; en otros lugares conservar la excepción. Registrar contexto técnico sin secretos y mostrar acciones útiles sin SQL ni trazas. Explicar alternativas intencionales, como copias sin conexión o avisos de actualización conservados. Una caída de la base no equivale a una instalación pendiente.

Conservar los contratos de integridad de actualizaciones y migraciones. Los archivos SQL de migraciones aplicadas son inmutables: incluso los comentarios cambian sus huellas. Documentar el mecanismo aquí o en su generador; un cambio SQL necesario exige otra migración.

## Limpieza y verificación

Clasificar cada candidato por sus consumidores: comportamiento activo, compatibilidad soportada, archivo generado o implementación inaccesible. Comprobar CodeGraph, referencias directas y calculadas, eventos, persistencia y pruebas antes de eliminar. Conservar formatos de importación y campos de respuesta de API soportados salvo cambio de contrato deliberado y documentado. Eliminar entradas PO obsoletas al extraer para impedir que se acumulen o reaparezcan desde listas explícitas desactualizadas.

Probar comportamiento, no nombres internos: crear/cargar/editar/guardar/eliminar, decisiones al salir, referencias importadas diferentes, conservación de metadatos, entrada inválida y recuperación. Ejecutar las suites pertinentes antes y después; distinguir almacenamiento simulado de MariaDB real. Actualizar ambos changelogs según las [categorías de release](publicar-versiones.md#categorías-de-cambios-y-commits).

## Formateadores de desarrollo

`dev/.php-cs-fixer.dist.php` selecciona reglas de formato PSR-12 no arriesgadas y excluye catálogos generados y configuración privada. Las plantillas PHP/HTML requieren revisar ambas sintaxis y el resultado renderizado.
