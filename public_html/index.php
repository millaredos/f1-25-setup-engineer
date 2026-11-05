<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>F1 25 · Taller de reglajes</title>
  <link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>">
  <script type="module" src="assets/app.js?v=<?= appVersion() ?>"></script>
</head>
<body>
  <a class="skip" href="#navigation">Ir al menú</a>
  <header class="masthead">
    <a class="brand" href="./"><span class="brand-mark" aria-hidden="true"></span><span>SETUP ENGINEER<small>Tu taller de F1 25</small></span></a>
  <nav id="navigation" class="app-nav" aria-label="Apartados">
    <a href="#inicio">Inicio</a><a href="#circuito">Circuito y referencia</a><a href="#taller">Taller</a><a href="#pruebas">Pruebas</a><a href="#versiones">Versiones</a><a href="update.php">Updates</a><a href="options.php">Options</a>
  </nav>
    <span class="driver">Versión <?= appVersion() ?><span>F1 25 Setup Engineer</span></span>
  </header>
  <main>
    <section data-page="inicio" aria-labelledby="home-heading">
      <p class="eyebrow">F1 25 SETUP ENGINEER</p><h1 id="home-heading">Tu punto de partida.</h1>
      <p class="intro">Elige qué quieres preparar para tu próxima prueba en pista.</p>
      <div class="home-grid">
        <a class="panel home-link" href="#circuito"><h2>Circuito y referencia</h2><p>Importa la ficha y fija el reglaje del juego como punto de partida.</p></a>
        <a class="panel home-link" href="#taller"><h2>Taller</h2><p>Edita los veinte ajustes y compara sus efectos teóricos.</p></a>
        <a class="panel home-link" href="#pruebas"><h2>Pruebas</h2><p>Consulta propuestas según lo que notas en el coche.</p></a>
        <a class="panel home-link" href="#versiones"><h2>Versiones</h2><p>Guarda, recupera y exporta tus reglajes.</p></a>
        <a class="panel home-link" href="update.php"><h2>Updates</h2><p>Check the installed version and download the latest build for your channel.</p></a>
      </div>
    </section>
    <div data-page="taller" hidden class="page-heading"><div><p class="eyebrow">OBSERVA. AJUSTA. PRUEBA.</p><h1>Entiende cada cambio.</h1><p class="intro">Parte del reglaje del juego. Ajusta con una idea, compruébala en pista.</p></div><span class="theory-badge">Siempre teoría<small>Sin predicciones de rendimiento</small></span></div>
    <p id="status" class="status" role="status" aria-live="polite">Cargando el taller…</p>
    <noscript><p class="status error">Activa JavaScript para utilizar el editor.</p></noscript>
    <div id="recovery" class="notice" hidden>Hay un borrador anterior en este navegador. <button id="recover" class="text-button">Recuperarlo</button> <button id="dismiss-recovery" class="text-button">Descartarlo</button></div>

    <section data-page="circuito" hidden class="panel context-panel" aria-labelledby="context-heading">
      <div class="section-heading"><div><p class="eyebrow">01 / PUNTO DE PARTIDA</p><h2 id="context-heading">Circuito y referencia</h2></div><button id="open-circuit" class="button secondary">Pegar ficha de circuito</button></div>
      <div class="context-fields">
        <label>Circuito<select id="circuit"><option value="">Selecciona o importa una ficha</option></select></label>
        <label>Condiciones<select id="surface"><option value="unknown">Sin indicar</option><option value="dry">Seco</option><option value="wet">Mojado</option></select></label>
        <label>Sesión<select id="session"><option value="unknown">Sin indicar</option><option value="race">Carrera</option><option value="time-trial">Contrarreloj</option></select></label>
      </div>
      <div id="circuit-card" hidden></div>
      <div class="reference-row"><div><strong id="reference-label">Referencia pendiente</strong><p id="reference-help">Introduce los veinte valores que te asigna el juego o importa un reglaje.</p></div><div class="actions"><button id="open-setup" class="button secondary">Importar reglaje</button><button id="set-reference" class="button">Fijar actuales como referencia</button></div></div>
      <div class="reference-options"><label>Origen de los valores que vas a fijar<select id="reference-source"><option value="game-assigned">Asignados por el juego (aportados por mí)</option><option value="personal">Reglaje personal</option><option value="capture">Capturas</option><option value="unknown">Desconocido</option></select></label><button id="example" class="text-button">Cargar ejemplo de las capturas</button></div>
    </section>

    <div class="workspace separated-workspace">
      <section data-page="taller" hidden id="editor" aria-labelledby="editor-heading">
        <div class="editor-heading"><div><p class="eyebrow">02 / REGLAJES</p><h2 id="editor-heading">Una variable, una idea.</h2></div><button id="restore" class="text-button">Volver a la referencia</button></div>
        <p class="subtle" id="catalog-notice"></p>
        <div id="settings"></div>
      </section>
      <aside data-page="pruebas" hidden class="coach panel" aria-labelledby="coach-heading">
        <p class="eyebrow">ASISTENTE DE PRUEBAS · SIN IA</p><h2 id="coach-heading">¿Qué notas en el coche?</h2><p class="subtle">Selecciona un síntoma. Son pruebas teóricas, no un diagnóstico.</p>
        <label>Comportamiento<select id="symptom"><option value="">Elige un síntoma</option></select></label>
        <div id="advice"><p class="empty">Primero identifica en qué fase aparece. Entrada, apoyo y salida no piden las mismas pruebas.</p></div>
        <p class="coach-foot">Ten en cuenta las ayudas que utilizas. Mantén las mismas condiciones y prueba cada variable por separado.</p>
      </aside>
    </div>

    <section data-page="taller" hidden class="panel summary-panel" aria-labelledby="summary-heading">
      <div class="section-heading"><div><p class="eyebrow">03 / LECTURA DE LOS CAMBIOS</p><h2 id="summary-heading">Lo que has cambiado, en teoría.</h2></div><span id="change-count" class="count">Referencia pendiente</span></div>
      <div id="summary"><p class="empty">Fija una referencia para comparar.</p></div>
      <p class="notice">No sumamos alerones como si fueran fuerzas equivalentes. Cada ajuste tiene su compromiso y los cambios pueden interactuar.</p>
    </section>

    <section data-page="versiones" hidden class="panel save-panel" aria-labelledby="save-heading">
      <div><p class="eyebrow">04 / CONSERVA EL PUNTO DE PARTIDA</p><h2 id="save-heading">Guarda una versión.</h2><p class="subtle">Cada guardado crea una copia nueva, con referencia, valores actuales y ficha del circuito.</p></div>
      <div class="save-fields"><label>Nombre del reglaje<input id="setup-title" maxlength="120" placeholder="Ej.: primera prueba en seco"></label><label>Notas<textarea id="notes" rows="3" maxlength="4000" placeholder="Qué quieres comprobar, combustible, compuesto…"></textarea></label></div>
      <div class="actions"><button id="save" class="button">Guardar versión</button><button id="export" class="button secondary">Exportar JSON</button><button id="export-legacy" class="text-button">JSON del comparador anterior</button></div>
      <div class="library"><label>Versiones guardadas<select id="saved"><option value="">Todavía no hay versiones guardadas</option></select></label><button id="load" class="button secondary">Abrir como borrador</button></div>
    </section>
    <footer>F1 25 SETUP ENGINEER <span>Un taller para comprender y experimentar.</span><p>Teoría orientativa. Las fuentes pueden discrepar, especialmente sobre presiones. Valida en tu juego y sesión.</p></footer>
  </main>

  <dialog id="import-dialog" aria-labelledby="import-title">
    <form method="dialog"><button class="dialog-close text-button" aria-label="Cerrar importación">Cerrar ×</button></form>
    <p class="eyebrow">IMPORTACIÓN CON VISTA PREVIA</p><h2 id="import-title">Pegar JSON</h2><p id="import-help" class="subtle"></p>
    <label>Seleccionar archivo JSON<input type="file" id="json-file" accept=".json,application/json"></label>
    <label>Contenido JSON<textarea id="json-input" rows="10" spellcheck="false"></textarea></label>
    <button id="validate-import" class="button secondary">Validar y previsualizar</button>
    <p id="import-error" class="error-text" role="alert"></p><div id="import-preview"></div>
    <button id="apply-import" class="button" hidden>Aplicar importación</button>
  </dialog>
</body>
</html>
