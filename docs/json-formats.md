[Versión en Español](es-es/formatos-json.md)

# JSON formats

Setup exports use `format: f125-setup-engineer`, `schemaVersion: 1`, `reference`, `current`, `referenceSource`, `season`, `circuit`, `conditions`, `title`, `notes` and `groupNotes`. Numeric maps retain the twenty published setting IDs. Source values are `unknown`, `game-assigned`, `capture` or `personal`. Surface is `unknown`, `dry` or `wet`; session is `unknown`, `race` or `time-trial`.

Imports also accept `f125-comparador-v2`, plain value maps, `values`, and `base/current`. Unknown schemas and incorrect types, ranges and steps are rejected. Missing reference produces a proposed current-value copy with a warning and no game attribution. Missing season remains `unknown`; saving requires an explicit supported season. Reference/current differences, notes and metadata survive round trips.

Circuit imports use `format: f125-circuit`. Version 2 accepts the Spanish template fields `nombre`, `ubicacion`, `longitud_km`, `curvas`, `categoria_carga`, `curvas_y_rectas` and `caracteristicas_conduccion`. Only name is required; unknown numeric values are null. Length must be positive and at most 1000 km; corner count is an integer from 1 to 1000. New profiles receive a generated `custom-` identity and use `origin: custom-profile`.

Version-1 circuit snapshots retain evidence: between one and twenty HTTP(S) sources with titles and actual `YYYY-MM-DD` dates. Distributed catalog snapshots use `origin: game-catalog`; saves resolve their identities against the selected edition. Circuit IDs never silently overwrite a different stored description.

Import size is bounded to 256 KiB. User content remains text; imported HTML is never executed.
