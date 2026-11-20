[English version](../json-formats.md)

# Formatos JSON

## Ficha de circuito

La plantilla descargable usa `f125-circuit`, versión de esquema 2. Solo `nombre` es obligatorio; usa `null` si no conoces la longitud o el número de curvas. La longitud es un número JSON en kilómetros, con punto decimal. La aplicación genera el identificador interno. Ejemplo:

```json
{
  "format": "f125-circuit",
  "schemaVersion": 2,
  "circuit": {
    "nombre": "Circuito de ejemplo",
    "ubicacion": "Ubicación de ejemplo",
    "longitud_km": 5.451,
    "curvas": 16,
    "categoria_carga": "Media-alta",
    "curvas_y_rectas": "Curvas enlazadas y una recta larga.",
    "caracteristicas_conduccion": "Las curvas largas cargan los neumáticos."
  }
}
```

La versión 1 se sigue admitiendo en importaciones y reglajes guardados. Su formato histórico es:

```json
{
  "format": "f125-circuit",
  "schemaVersion": 1,
  "circuit": {
    "id": "ejemplo-no-real-v1",
    "name": "Ejemplo de estructura (no es un circuito real)",
    "layout": "Nombre del trazado",
    "gameVersion": "desconocida",
    "downforce": "mixed",
    "summary": "Descripción de las exigencias del trazado.",
    "characteristics": {
      "corners": "Tipos de curvas y zonas relevantes.",
      "straights": "Rectas donde importa la velocidad punta.",
      "braking": "Zonas de frenada.",
      "traction": "Salidas que exigen tracción.",
      "kerbs": "Uso de pianos e irregularidades.",
      "aero": "Motivos de la categoría de carga y compromisos."
    },
    "hypotheses": "Pruebas pendientes en el juego.",
    "sources": [{
      "title": "Ejemplo ficticio: sustituir por fuente real",
      "url": "https://example.com",
      "accessedAt": "2026-11-04"
    }]
  }
}
```

`downforce`: `low`, `medium`, `high` o `mixed`, traducidos en pantalla. ID: minúsculas, números y guiones, máximo 80 caracteres. Para revisar una ficha guardada usar otro ID; no se sustituye la anterior. Nombre máximo 120 bytes; resumen e hipótesis 4000 bytes; características 2000 bytes cada una. Entre una y veinte fuentes HTTP/HTTPS con fecha `AAAA-MM-DD`.

Los veinte valores del reglaje se aportan por separado. Cada versión guardada conserva una copia de la ficha.

Los circuitos distribuidos utilizan una copia de identidad con `origin: game-catalog`, `id`, `name` y `location`. La aplicación resuelve el identificador y el nombre en el catálogo instalado antes de guardar. La copia conserva `downforce: unknown`, campos técnicos vacíos y ninguna fuente externa; las descripciones con fuentes se muestran por separado y no se incluyen en los reglajes guardados. La recuperación y exportación sin conexión validan la estructura de la copia. Las fichas personalizadas importadas conservan el formato y los requisitos de fuentes anteriores.

## Reglajes

Formato actual: `format: f125-setup-engineer`, `schemaVersion: 1`, `reference`, `current`, `referenceSource`, `circuit`, `conditions`, `title`, `notes` y `groupNotes` opcional. Mapas con veinte IDs heredados. Las claves de `groupNotes` son `aero`, `transmission`, `geometry`, `suspension`, `brakes` y `tyres`; cada valor es texto de hasta 4000 bytes. Origen: `unknown`, `game-assigned`, `capture`, `personal`. Condiciones: `surface` (`unknown`, `dry`, `wet`) y `session` (`unknown`, `race`, `time-trial`). La aplicación exporta este formato.

Los reglajes nuevos guardados requieren `season` con valor `2025` o `2026`. Por compatibilidad, al importar un archivo antiguo sin temporada se mantiene sin indicar hasta que el usuario seleccione una. La exportación antigua `f125-comparador-v2` no conserva la temporada.

También admite `f125-comparador-v2`, mapas planos, `values` y `base/current`. Si falta referencia, la vista previa propone copiar los valores importados y marca origen desconocido. No deduce circuito ni condiciones. Tipos, rangos, pasos y versiones incorrectos se rechazan sin corrección silenciosa.
