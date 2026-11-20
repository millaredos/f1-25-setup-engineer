[Versión en Español](es-es/formato-circuito.md)

# JSON formats

## Circuit profile

The downloadable template uses `f125-circuit`, schema version 2. Only `nombre` is required; use `null` for unknown length or corner count. Length is a JSON number in kilometres, with a decimal point. The application generates the internal identifier. For example:

```json
{
  "format": "f125-circuit",
  "schemaVersion": 2,
  "circuit": {
    "nombre": "Example Circuit",
    "ubicacion": "Example location",
    "longitud_km": 5.451,
    "curvas": 16,
    "categoria_carga": "Medium-high",
    "curvas_y_rectas": "Linked corners and a long straight.",
    "caracteristicas_conduccion": "Long corners place sustained load on the tyres."
  }
}
```

Version 1 remains supported for existing imports and saved setups. Its historical format is:

```json
{
  "format": "f125-circuit",
  "schemaVersion": 1,
  "circuit": {
    "id": "fictional-example-v1",
    "name": "Structure example (not a real circuit)",
    "layout": "Layout name",
    "gameVersion": "unknown",
    "downforce": "mixed",
    "summary": "Description of the layout's demands.",
    "characteristics": {
      "corners": "Corner types and relevant sections.",
      "straights": "Straights where top speed matters.",
      "braking": "Braking zones.",
      "traction": "Exits that demand traction.",
      "kerbs": "Kerb use and surface bumps.",
      "aero": "Reasons for the downforce category and its trade-offs."
    },
    "hypotheses": "Tests still to run in the game.",
    "sources": [{
      "title": "Fictional example: replace with a real source",
      "url": "https://example.com",
      "accessedAt": "2026-11-04"
    }]
  }
}
```

`downforce`: `low`, `medium`, `high` or `mixed`, translated in the interface. IDs allow lowercase letters, numbers and hyphens, up to 80 characters. To revise a saved profile, use another ID; the existing profile is not replaced. Names allow up to 120 bytes; summaries and hypotheses, 4000 bytes; each characteristic, 2000 bytes. Include between one and twenty HTTP/HTTPS sources dated `YYYY-MM-DD`.

The twenty setup values are supplied separately. Each saved version keeps a copy of the profile.

Bundled circuits use an identity snapshot with `origin: game-catalog`, `id`, `name` and `location`. The application resolves the identifier and name from the installed catalog before saving. The snapshot retains `downforce: unknown`, empty technical fields and no external sources; sourced circuit descriptions are displayed separately and are not embedded in saved setups. Offline recovery and export validate the snapshot's structure. Imported custom profiles retain the format and source requirements above.

## Setups

Current format: `format: f125-setup-engineer`, `schemaVersion: 1`, `reference`, `current`, `referenceSource`, `circuit`, `conditions`, `title`, `notes` and optional `groupNotes`. Value maps use the twenty legacy identifiers. The keys of `groupNotes` are `aero`, `transmission`, `geometry`, `suspension`, `brakes` and `tyres`; each value is text of at most 4000 bytes. Sources: `unknown`, `game-assigned`, `capture`, `personal`. Conditions: `surface` (`unknown`, `dry`, `wet`) and `session` (`unknown`, `race`, `time-trial`). The application exports this format.

New saved setups require `season` to be `2025` or `2026`. Importing a file without a season preserves it as unknown until the user selects a season.

It also accepts `f125-comparador-v2`, flat value maps, `values` and `base/current`. If the reference is missing, the preview proposes a copy of the imported values and marks the source as unknown. Circuit and conditions are not inferred. Invalid types, ranges, steps and versions are rejected without automatic correction.
