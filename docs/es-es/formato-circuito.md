[English version](../json-formats.md)

# Formatos JSON

## Ficha de circuito

Formato `f125-circuit`, versión de esquema 1. Ejemplo con datos ficticios:

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

## Reglajes

Nuevo: `format: f125-setup-engineer`, `schemaVersion: 1`, `reference`, `current`, `referenceSource`, `circuit`, `conditions`, `title`, `notes`. Mapas con veinte IDs heredados. Origen: `unknown`, `game-assigned`, `capture`, `personal`. Condiciones: `surface` (`unknown`, `dry`, `wet`) y `session` (`unknown`, `race`, `time-trial`). La aplicación exporta este formato.

También admite `f125-comparador-v2`, mapas planos, `values` y `base/current`. Si falta referencia, la vista previa propone copiar los valores importados y marca origen desconocido. No deduce circuito ni condiciones. Tipos, rangos, pasos y versiones incorrectos se rechazan sin corrección silenciosa.
