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
          <p><?= h(t('Cada ajuste incluye una explicación de cómo podría influir en el comportamiento del coche. Al modificarlo, puedes comparar sus valores con los iniciales y revisar los posibles efectos.')) ?></p>
          <p><?= h(t('Los ajustes no actúan siempre de forma aislada: un cambio puede reforzar, contrarrestar o dificultar el efecto de otro. Las explicaciones orientan tus pruebas; el resultado depende del conjunto del reglaje y de lo que notes al conducir.')) ?></p></section>
        <section><h2><?= h(t('Conserva tus reglajes')) ?></h2>
          <p><?= h(t('Guarda tus reglajes con un nombre y comentarios por grupo. Después puedes abrirlos, modificarlos o eliminarlos desde el taller y exportarlos en JSON.')) ?></p></section>
        <section class="home-car"><h2><?= h(t('El coche también cuenta')) ?></h2>
          <p><?= h(t('El rendimiento y el desarrollo del coche influyen en su comportamiento. Un reglaje puede ayudarte a adaptarlo a tu forma de conducir y al circuito, pero sus prestaciones y mejoras seguirán influyendo en el resultado.')) ?></p>
          <p><?= h(t('Ajustar el coche menos competitivo no lo convierte en el mejor de la parrilla: conserva sus propias limitaciones.')) ?></p></section>
      </div>
    </section>
    <div data-page="taller" hidden class="workshop-heading"><div class="workshop-heading-row"><h1><?= h(t('Taller')) ?></h1><div class="workshop-heading-actions"><button id="new-setup" class="text-button" type="button"><?= h(t('Nuevo reglaje')) ?></button><button id="open-saved-setup" class="text-button" type="button"><?= h(t('Editar reglaje')) ?></button><button id="open-setup" class="text-button" type="button"><?= h(t('Importar reglaje')) ?></button></div></div><p><?= h(t('Crea o edita un reglaje y consulta los efectos teóricos de cada cambio.')) ?></p></div>
    <p id="status" class="status" role="status" aria-live="polite"></p>
    <noscript><p class="status error"><?= h(t('Activa JavaScript para utilizar el editor.')) ?></p></noscript>
    <p id="database-update" class="notice" hidden><?= h(t('Hay una actualización de la base de datos pendiente.')) ?> <a href="database.php"><?= h(t('Actualizar base de datos')) ?></a></p>
    <div id="recovery" class="notice" hidden><?= h(t('Hay un borrador anterior en este navegador.')) ?> <button id="recover" class="text-button"><?= h(t('Recuperarlo')) ?></button> <button id="dismiss-recovery" class="text-button"><?= h(t('Descartarlo')) ?></button></div>

    <section data-page="circuito" hidden class="panel context-panel" aria-labelledby="context-heading">
      <div class="section-heading"><h1 id="context-heading"><?= h(t('Circuitos')) ?></h1><button id="open-circuit" class="text-button"><?= h(t('Añadir circuito')) ?></button></div>
      <div class="context-fields load-fields">
        <label><?= h(t('Temporada')) ?><select id="season"><option value="2026">2026</option><option value="2025">2025</option></select></label>
        <label><?= h(t('Circuito')) ?><select id="circuit"><option value=""><?= h(t('Selecciona un circuito')) ?></option></select></label>
        <div class="saved-load"><label><?= h(t('Reglaje')) ?><select id="saved"><option value=""><?= h(t('Todavía no hay versiones guardadas')) ?></option></select></label></div>
      </div>
      <div id="circuit-card" hidden></div>
    </section>

    <section data-page="circuito" hidden class="panel setup-preview" aria-labelledby="setup-preview-heading">
      <h2 id="setup-preview-heading"><?= h(t('Reglaje seleccionado')) ?></h2>
      <div id="setup-preview" aria-live="polite"></div>
    </section>

    <div class="workspace separated-workspace">
      <section data-page="taller" hidden id="editor" class="panel workshop-editor" aria-labelledby="editor-heading">
        <h2 id="editor-heading" class="sr-only"><?= h(t('Reglajes')) ?></h2>
        <div class="workshop-meta"><strong id="reference-label" hidden></strong><span id="draft-context"></span><button id="set-reference" class="text-button" hidden></button><button id="restore" class="text-button" hidden></button></div>
        <p id="reference-help" hidden></p>
        <p id="catalog-notice" hidden></p>
        <div class="workshop-toolbar"><nav id="settings-menu" class="workshop-menu" aria-label="<?= h(t('Categorías del reglaje')) ?>"></nav><div class="actions"><button id="save" class="button" type="button"><?= h(t('Guardar cambios')) ?></button><button id="delete-setup" class="button secondary" type="button" hidden><?= h(t('Eliminar reglaje')) ?></button></div></div>
        <input id="setup-title" type="hidden"><input id="notes" type="hidden">
        <button id="export-workshop" class="text-button" type="button"><?= h(t('Exportar JSON')) ?></button>
        <div id="settings-layout" class="settings-layout">
          <div id="settings"></div>
          <aside id="parameter-info" class="parameter-info" aria-live="polite"></aside>
        </div>
        <section id="summary-panel" class="summary-panel" aria-labelledby="summary-heading" hidden>
          <div class="section-heading"><h2 id="summary-heading"><?= h(t('Resumen de cambios')) ?></h2><span id="change-count" class="count"><?= h(t('Referencia pendiente')) ?></span></div>
          <div id="summary"><p class="empty"><?= h(t('Fija una referencia para comparar.')) ?></p></div>
        </section>
      </section>
      <aside data-page="pruebas" hidden class="coach panel" aria-labelledby="coach-heading">
        <p class="eyebrow"><?= h(t('ASISTENTE DE PRUEBAS · SIN IA')) ?></p><h2 id="coach-heading"><?= h(t('¿Qué notas en el coche?')) ?></h2><p class="subtle"><?= h(t('Selecciona un síntoma. Son pruebas teóricas, no un diagnóstico.')) ?></p>
        <label><?= h(t('Comportamiento')) ?><select id="symptom"><option value=""><?= h(t('Elige un síntoma')) ?></option></select></label>
        <div id="advice"><p class="empty"><?= h(t('Primero identifica en qué fase aparece. Entrada, apoyo y salida no piden las mismas pruebas.')) ?></p></div>
        <p class="coach-foot"><?= h(t('Ten en cuenta las ayudas que utilizas. Mantén las mismas condiciones y prueba cada variable por separado.')) ?></p>
      </aside>
    </div>

  </main>
  <footer class="site-footer"><div><strong>F1 25 / 26 SETUP ENGINEER</strong><span>© 2026 millaredos · <a href="https://github.com/millaredos/f1-25-setup-engineer" target="_blank" rel="noopener noreferrer">GitHub</a></span></div><p><?= h(t('Teoría orientativa. Valida los cambios en el juego.')) ?></p></footer>

  <dialog id="saved-setup-dialog" aria-labelledby="saved-setup-title">
    <form method="dialog"><button class="dialog-close text-button" aria-label="<?= h(t('Cerrar')) ?>"><?= h(t('Cerrar ×')) ?></button></form>
    <h2 id="saved-setup-title"><?= h(t('Cargar reglaje')) ?></h2>
    <label><?= h(t('Temporada')) ?><select id="load-season"><option value=""><?= h(t('Selecciona una temporada')) ?></option><option value="2025">2025</option><option value="2026">2026</option></select></label>
    <label><?= h(t('Circuito')) ?><select id="load-circuit"></select></label>
    <label id="load-setup-label"><?= h(t('Reglaje')) ?><select id="load-setup"></select></label>
    <p id="load-setup-help" class="subtle" role="status"></p>
    <div class="actions"><button id="apply-saved-setup" class="button" type="button"><?= h(t('Cargar reglaje')) ?></button><button id="use-manual-circuit" class="button secondary" type="button"><?= h(t('Usar circuito para valores manuales')) ?></button></div>
  </dialog>
  <dialog id="unsaved-dialog" aria-labelledby="unsaved-title"><h2 id="unsaved-title"><?= h(t('Cambios sin guardar')) ?></h2><p><?= h(t('Guarda los cambios antes de salir o se perderán.')) ?></p><div class="actions"><button id="unsaved-save" class="button" type="button"><?= h(t('Guardar cambios')) ?></button><button id="unsaved-discard" class="button secondary" type="button"><?= h(t('Salir sin guardar')) ?></button><button id="unsaved-cancel" class="text-button" type="button"><?= h(t('Seguir editando')) ?></button></div></dialog>
  <dialog id="save-dialog" aria-labelledby="save-dialog-title"><form method="dialog" id="save-dialog-form"><h2 id="save-dialog-title"><?= h(t('Guardar reglaje')) ?></h2><label><?= h(t('Nombre del reglaje')) ?><input id="save-name" maxlength="120" required></label><label id="save-season-label"><?= h(t('Temporada')) ?><select id="save-season"><option value=""><?= h(t('Selecciona una temporada')) ?></option><option value="2025">2025</option><option value="2026">2026</option></select></label><label id="save-circuit-label"><?= h(t('Circuito')) ?><select id="save-circuit"></select></label><p id="save-dialog-error" class="error-text" role="alert"></p><div class="actions"><button class="button" type="submit"><?= h(t('Guardar')) ?></button><button id="save-cancel" class="button secondary" type="button"><?= h(t('Cancelar')) ?></button></div></form></dialog>
  <dialog id="import-dialog" aria-labelledby="import-title">
    <form method="dialog"><button class="dialog-close text-button" aria-label="<?= h(t('Cerrar importación')) ?>"><?= h(t('Cerrar ×')) ?></button></form>
    <p class="eyebrow"><?= h(t('IMPORTACIÓN CON VISTA PREVIA')) ?></p><h2 id="import-title"><?= h(t('Pegar JSON')) ?></h2><p id="import-help" class="subtle"></p>
    <button type="button" id="circuit-template" class="text-button" hidden><?= h(t('Descargar plantilla JSON')) ?></button>
    <p id="circuit-faq" class="subtle" hidden><?= h(t('¿Necesitas ayuda para rellenar el JSON?')) ?> <a href="faq.php#ficha-circuito" target="_blank" rel="noopener noreferrer"><?= h(t('Visita la FAQ')) ?></a></p>
    <p id="circuit-template-help" class="subtle" hidden><?= h(t('Indica el nombre. La ubicación, la longitud en kilómetros, el número de curvas, la carga y las descripciones son opcionales. Usa un punto como separador decimal en la longitud; deja null si no la conoces.')) ?></p>
    <label><?= h(t('Seleccionar archivo JSON')) ?><input type="file" id="json-file" accept=".json,application/json"></label>
    <label><?= h(t('Contenido JSON')) ?><textarea id="json-input" rows="10" spellcheck="false"></textarea></label>
    <button id="validate-import" class="button secondary"><?= h(t('Validar y previsualizar')) ?></button>
    <p id="import-error" class="error-text" role="alert"></p><div id="import-preview"></div>
    <button id="apply-import" class="button" hidden><?= h(t('Aplicar importación')) ?></button>
  </dialog>
</body>
</html>
