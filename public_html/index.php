<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?>
<!doctype html>
<html lang="<?= h(appTranslation()->language()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= h(t('F1 25 / 26 · Taller de reglajes')) ?></title>
  <link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>">
  <script type="module" src="assets/navigation.js?v=<?= appVersion() ?>"></script>
  <script type="module" src="assets/app.js?v=<?= appVersion() ?>"></script>
</head>
<body class="workshop-app">
  <a class="skip" href="#navigation"><?= h(t('Ir al menú')) ?></a>
  <?php appHeader(); ?>
  <main>
    <section data-page="inicio" class="home-content" aria-labelledby="home-heading">
      <header class="home-hero">
        <p class="home-label">F1 25 / 26 Setup Engineer</p>
        <h1 id="home-heading"><?= h(t('Aprende a trabajar con tus reglajes')) ?></h1>
        <p class="home-intro"><?= h(t('Esta aplicación pretende ayudarte a entender los ajustes de F1 25 y su 2026 Season Pack, explorar sus posibles efectos y guardar tus propios reglajes.')) ?></p>
      </header>

      <div class="home-features">
        <section><h2><?= h(t('Conoce el circuito')) ?></h2>
          <p><?= h(t('Cada circuito plantea retos diferentes. Consulta su ficha para conocer el trazado, sus curvas y rectas, la carga aerodinámica orientativa y otras características que pueden influir en la conducción.')) ?></p></section>
        <section><h2><?= h(t('Parte de un reglaje')) ?></h2>
          <p><?= h(t('Consulta los reglajes predeterminados de Codemasters y utiliza el que el juego asigna al circuito como punto de partida. También puedes recuperar un reglaje guardado y seguir trabajando con él. Los predeterminados del coche de 2025 están pendientes de recopilar.')) ?></p></section>
        <section><h2><?= h(t('Entiende los cambios')) ?></h2>
          <p><?= h(t('Cada ajuste incluye una explicación de cómo podría influir en el comportamiento del coche. Al modificarlo, puedes comparar sus valores con los de tu referencia y revisar los posibles efectos.')) ?></p>
          <p><?= h(t('Los ajustes no actúan siempre de forma aislada: un cambio puede reforzar, contrarrestar o dificultar el efecto de otro. Las explicaciones orientan tus pruebas; el resultado depende del conjunto del reglaje y de lo que notes al conducir.')) ?></p></section>
        <section><h2><?= h(t('Conserva tus reglajes')) ?></h2>
          <p><?= h(t('Guarda versiones en la base de datos con un nombre y notas para recordar qué querías probar o qué observaste. Puedes recuperar una versión anterior, crear otra a partir de ella y exportar tus reglajes en JSON.')) ?></p></section>
        <section class="home-car"><h2><?= h(t('El coche también cuenta')) ?></h2>
          <p><?= h(t('El rendimiento y el desarrollo del coche influyen en su comportamiento. Un reglaje puede ayudarte a adaptarlo a tu forma de conducir y al circuito, pero sus prestaciones y mejoras seguirán influyendo en el resultado.')) ?></p>
          <p><?= h(t('Ajustar el coche menos competitivo no lo convierte en el mejor de la parrilla: conserva sus propias limitaciones.')) ?></p></section>
      </div>
    </section>
    <div data-page="taller" hidden class="page-heading"><div><p class="eyebrow"><?= h(t('OBSERVA. AJUSTA. PRUEBA.')) ?></p><h1><?= h(t('Entiende cada cambio.')) ?></h1><p class="intro"><?= h(t('Parte del reglaje del juego. Ajusta con una idea, compruébala en pista.')) ?></p></div><span class="theory-badge"><?= h(t('Siempre teoría')) ?><small><?= h(t('Sin predicciones de rendimiento')) ?></small></span></div>
    <p id="status" class="status" role="status" aria-live="polite"></p>
    <noscript><p class="status error"><?= h(t('Activa JavaScript para utilizar el editor.')) ?></p></noscript>
    <p id="database-update" class="notice" hidden><?= h(t('Hay una actualización de la base de datos pendiente.')) ?> <a href="database.php"><?= h(t('Actualizar base de datos')) ?></a></p>
    <div id="recovery" class="notice" hidden><?= h(t('Hay un borrador anterior en este navegador.')) ?> <button id="recover" class="text-button"><?= h(t('Recuperarlo')) ?></button> <button id="dismiss-recovery" class="text-button"><?= h(t('Descartarlo')) ?></button></div>

    <section data-page="circuito" hidden class="panel context-panel" aria-labelledby="context-heading">
      <div class="section-heading"><h1 id="context-heading"><?= h(t('Circuito y referencia')) ?></h1><button id="open-circuit" class="text-button"><?= h(t('Añadir circuito')) ?></button></div>
      <div class="context-fields load-fields">
        <label><?= h(t('Temporada')) ?><select id="season"><option value="2026">2026</option><option value="2025">2025</option></select></label>
        <label><?= h(t('Circuito')) ?><select id="circuit"><option value=""><?= h(t('Selecciona un circuito')) ?></option></select></label>
        <div class="saved-load"><label><?= h(t('Reglaje')) ?><select id="saved"><option value=""><?= h(t('Todavía no hay versiones guardadas')) ?></option></select></label><button id="load" class="button secondary"><?= h(t('Abrir como borrador')) ?></button></div>
      </div>
      <p id="season-draft-note" class="source-note" hidden></p>
      <div id="circuit-card" hidden></div>
    </section>

    <section data-page="circuito" hidden class="panel setup-preview" aria-labelledby="setup-preview-heading">
      <h2 id="setup-preview-heading"><?= h(t('Reglaje seleccionado')) ?></h2>
      <div id="setup-preview" aria-live="polite"></div>
    </section>

    <div class="workspace separated-workspace">
      <section data-page="taller" hidden id="editor" aria-labelledby="editor-heading">
        <div class="reference-row"><div><strong id="reference-label"><?= h(t('Referencia pendiente')) ?></strong><p id="reference-help"></p></div><button id="set-reference" class="text-button"><?= h(t('Usar actuales como referencia')) ?></button></div>
        <div class="editor-heading"><div><p class="eyebrow"><?= h(t('02 / REGLAJES')) ?></p><h2 id="editor-heading"><?= h(t('Una variable, una idea.')) ?></h2></div><button id="restore" class="text-button"><?= h(t('Volver a la referencia')) ?></button></div>
        <p class="subtle" id="catalog-notice"></p>
        <div id="settings"></div>
      </section>
      <aside data-page="pruebas" hidden class="coach panel" aria-labelledby="coach-heading">
        <p class="eyebrow"><?= h(t('ASISTENTE DE PRUEBAS · SIN IA')) ?></p><h2 id="coach-heading"><?= h(t('¿Qué notas en el coche?')) ?></h2><p class="subtle"><?= h(t('Selecciona un síntoma. Son pruebas teóricas, no un diagnóstico.')) ?></p>
        <label><?= h(t('Comportamiento')) ?><select id="symptom"><option value=""><?= h(t('Elige un síntoma')) ?></option></select></label>
        <div id="advice"><p class="empty"><?= h(t('Primero identifica en qué fase aparece. Entrada, apoyo y salida no piden las mismas pruebas.')) ?></p></div>
        <p class="coach-foot"><?= h(t('Ten en cuenta las ayudas que utilizas. Mantén las mismas condiciones y prueba cada variable por separado.')) ?></p>
      </aside>
    </div>

    <section data-page="taller" hidden class="panel summary-panel" aria-labelledby="summary-heading">
      <div class="section-heading"><div><p class="eyebrow"><?= h(t('03 / LECTURA DE LOS CAMBIOS')) ?></p><h2 id="summary-heading"><?= h(t('Lo que has cambiado, en teoría.')) ?></h2></div><span id="change-count" class="count"><?= h(t('Referencia pendiente')) ?></span></div>
      <div id="summary"><p class="empty"><?= h(t('Fija una referencia para comparar.')) ?></p></div>
      <p class="notice"><?= h(t('No sumamos alerones como si fueran fuerzas equivalentes. Cada ajuste tiene su compromiso y los cambios pueden interactuar.')) ?></p>
    </section>

    <section data-page="versiones" hidden class="panel save-panel" aria-labelledby="save-heading">
      <div><p class="eyebrow"><?= h(t('04 / CONSERVA EL PUNTO DE PARTIDA')) ?></p><h2 id="save-heading"><?= h(t('Guarda una versión.')) ?></h2><p class="subtle"><?= h(t('Cada guardado crea una copia nueva, con referencia, valores actuales y ficha del circuito.')) ?></p></div>
      <div class="save-fields"><label><?= h(t('Nombre del reglaje')) ?><input id="setup-title" maxlength="120" placeholder="<?= h(t('Ej.: primera prueba en seco')) ?>"></label><label><?= h(t('Notas')) ?><textarea id="notes" rows="3" maxlength="4000" placeholder="<?= h(t('Qué quieres comprobar, combustible, compuesto…')) ?>"></textarea></label></div>
      <div class="actions"><button id="save" class="button"><?= h(t('Guardar versión')) ?></button><button id="open-setup" class="button secondary"><?= h(t('Importar reglaje personal')) ?></button><button id="export" class="button secondary"><?= h(t('Exportar JSON')) ?></button><button id="export-legacy" class="text-button"><?= h(t('JSON del comparador anterior')) ?></button></div>
    </section>
  </main>
  <footer class="site-footer"><div><strong>F1 25 / 26 SETUP ENGINEER</strong><span>© 2026 millaredos · <a href="https://github.com/millaredos/f1-25-setup-engineer" target="_blank" rel="noopener noreferrer">GitHub</a></span></div><p><?= h(t('Teoría orientativa. Valida los cambios en el juego.')) ?></p></footer>

  <dialog id="import-dialog" aria-labelledby="import-title">
    <form method="dialog"><button class="dialog-close text-button" aria-label="<?= h(t('Cerrar importación')) ?>"><?= h(t('Cerrar ×')) ?></button></form>
    <p class="eyebrow"><?= h(t('IMPORTACIÓN CON VISTA PREVIA')) ?></p><h2 id="import-title"><?= h(t('Pegar JSON')) ?></h2><p id="import-help" class="subtle"></p>
    <label><?= h(t('Seleccionar archivo JSON')) ?><input type="file" id="json-file" accept=".json,application/json"></label>
    <label><?= h(t('Contenido JSON')) ?><textarea id="json-input" rows="10" spellcheck="false"></textarea></label>
    <button id="validate-import" class="button secondary"><?= h(t('Validar y previsualizar')) ?></button>
    <p id="import-error" class="error-text" role="alert"></p><div id="import-preview"></div>
    <button id="apply-import" class="button" hidden><?= h(t('Aplicar importación')) ?></button>
  </dialog>
</body>
</html>
