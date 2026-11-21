[English version](../json-formats.md)

# Formatos JSON

Los reglajes exportan `format: f125-setup-engineer`, `schemaVersion: 1`, `reference`, `current`, `referenceSource`, `season`, `circuit`, `conditions`, `title`, `notes` y `groupNotes`. Los mapas numéricos conservan los veinte IDs publicados. El origen es `unknown`, `game-assigned`, `capture` o `personal`. Superficie: `unknown`, `dry` o `wet`; sesión: `unknown`, `race` o `time-trial`.

También se importan `f125-comparador-v2`, mapas planos, `values` y `base/current`. Se rechazan esquemas desconocidos y tipos, rangos o pasos inválidos. Sin referencia se propone copiar los actuales con aviso y sin atribuirlos al juego. Sin temporada se mantiene `unknown`; guardar requiere seleccionar una admitida. Referencias distintas, notas y metadatos sobreviven a la exportación e importación.

Los circuitos utilizan `format: f125-circuit`. La versión 2 acepta `nombre`, `ubicacion`, `longitud_km`, `curvas`, `categoria_carga`, `curvas_y_rectas` y `caracteristicas_conduccion`. Solo el nombre es obligatorio; los números desconocidos se dejan en null. Longitud positiva hasta 1000 km; curvas enteras de 1 a 1000. Las fichas nuevas reciben un ID `custom-` generado y `origin: custom-profile`.

Las fichas de versión 1 conservan su evidencia: entre una y veinte fuentes HTTP(S), títulos y fechas reales `AAAA-MM-DD`. Las identidades distribuidas usan `origin: game-catalog`; al guardar se resuelven contra la edición seleccionada. Un ID nunca sustituye silenciosamente otra descripción guardada.

La importación tiene un límite de 256 KiB. El contenido del usuario se mantiene como texto; el HTML importado no se ejecuta.
