<?php
declare(strict_types=1);

/** One live workspace preserves the draft while switching its three views. */
?>
<p id="status" class="alert" role="status" hidden>
</p>
<section id="recovery" class="panel" hidden>
<p>Hay un borrador anterior en este navegador.</p>
<button id="recover-draft" class="button button-secondary">Recuperar borrador</button>
<button id="discard-recovery" class="button button-secondary">Descartar borrador anterior</button>
</section>
<div class="toolbar panel">
    <label>Temporada<select id="season">
<?php foreach (array_reverse($editions, true) as $id => $edition): ?>
<option value="<?= $escape((string) $id) ?>">
<?= $escape($edition['name']) ?>
</option>
<?php endforeach; ?>
</select>
</label>
    <label>Circuito<select id="circuit">
<option value="">Selecciona un circuito</option>
</select>
</label>
    <button id="new-setup" class="button">Nuevo reglaje</button>
    <button id="import-setup" class="button button-secondary">Importar reglaje</button>
    <input id="setup-file" type="file" accept="application/json,.json" hidden>
</div>
<section data-panel="circuits" <?= $page === 'circuits' ? '' : 'hidden' ?>>
    <h1>Circuitos</h1>
<div id="circuit-profile" class="panel prose">
<p>Selecciona un circuito para consultar sus reglajes.</p>
</div>
    <label>Reglaje seleccionado<select id="browse-setup">
<option value="">Selecciona un reglaje</option>
</select>
</label>
<div id="browse-values" class="stack">
</div>
    <div class="toolbar">
<button id="circuit-template" class="button button-secondary">Descargar plantilla</button>
<button id="import-circuit" class="button button-secondary">Añadir circuito</button>
<input id="circuit-file" type="file" accept="application/json,.json" hidden>
</div>
    <h2>Reglajes guardados</h2>
<div id="saved-setups" class="card-grid">
</div>
</section>
<section data-panel="workshop" <?= $page === 'workshop' ? '' : 'hidden' ?>>
    <div class="section-heading">
<h1>Taller</h1>
<span id="draft-context" class="muted">Sin borrador</span>
</div>
    <div id="draft-empty" class="panel">Crea un reglaje o abre uno guardado para comenzar.</div>
    <div id="editor" hidden>
        <div class="toolbar">
<button id="save-setup" class="button">Guardar</button>
<button id="save-copy" class="button button-secondary">Guardar copia</button>
<button id="export-setup" class="button button-secondary">Exportar JSON</button>
</div>
        <div class="panel fields">
<label>Nombre<input id="setup-title" maxlength="120">
</label>
<label>Notas<textarea id="setup-notes" maxlength="4000">
</textarea>
</label>
<label>Superficie<select id="surface">
<option value="unknown">Sin indicar</option>
<option value="dry">Seco</option>
<option value="wet">Mojado</option>
</select>
</label>
<label>Sesión<select id="session">
<option value="unknown">Sin indicar</option>
<option value="race">Carrera</option>
<option value="time-trial">Contrarreloj</option>
</select>
</label>
</div>
        <nav id="category-tabs" class="tabs" aria-label="Categorías del reglaje">
</nav>
        <div class="editor-layout">
<div id="parameter-groups" class="stack">
</div>
<aside id="parameter-info" class="panel" aria-live="polite">
</aside>
</div>
        <section class="panel">
<h2>Comparación con la referencia</h2>
<p id="comparison-notice" class="muted">
</p>
<div id="comparison">
</div>
</section>
    </div>
</section>
<section data-panel="tests" <?= $page === 'tests' ? '' : 'hidden' ?>>
<h1>Pruebas de conducción</h1>
<p class="prose">Elige un síntoma para preparar una prueba teórica. Mantén las condiciones, modifica pocas cosas y comprueba el resultado en el juego.</p>
<label>Síntoma<select id="symptom">
<option value="">Selecciona un síntoma</option>
</select>
</label>
<div id="advice" class="stack">
</div>
</section>
<dialog id="import-dialog">
<form method="dialog">
<button class="dialog-close" aria-label="Cerrar">×</button>
</form>
<h2>Revisar importación</h2>
<p id="import-info">
</p>
<div id="import-values" class="table-scroll">
</div>
<label>Temporada<select id="import-season">
<option value="">Selecciona una temporada</option>
<?php foreach ($editions as $id => $edition): ?>
<option value="<?= $escape((string) $id) ?>">
<?= $escape($edition['name']) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Circuito<select id="import-track">
</select>
</label>
<div class="toolbar">
<button id="apply-import" class="button">Usar como borrador</button>
</div>
</dialog>
