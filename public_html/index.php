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
  <title><?= h(t('F1 25 · Taller de reglajes')) ?></title>
  <link rel="stylesheet" href="assets/app.css?v=<?= appVersion() ?>">
  <script type="module" src="assets/navigation.js?v=<?= appVersion() ?>"></script>
  <script type="module" src="assets/app.js?v=<?= appVersion() ?>"></script>
</head>
<body>
  <a class="skip" href="#navigation"><?= h(t('Ir al menú')) ?></a>
  <?php appHeader(); ?>
  <main>
    <section data-page="inicio" class="home-content" aria-labelledby="home-heading">
      <header class="home-hero">
        <p class="home-label">F1 25 Setup Engineer</p>
        <h1 id="home-heading"><?= h(t('Prepara y compara tus reglajes')) ?></h1>
        <p class="home-intro"><?= h(t('Parte de un reglaje, conserva una referencia y entiende cada cambio antes de probarlo en pista.')) ?></p>
      </header>

      <div class="home-overview">
        <section aria-labelledby="home-purpose">
          <h2 id="home-purpose"><?= h(t('Tu espacio de trabajo')) ?></h2>
          <p><?= h(t('Edita los veinte ajustes, compara sus diferencias y guarda variantes con tus observaciones.')) ?></p>
          <p><?= h(t('Empieza con los valores del juego o un reglaje propio. La ficha del circuito y las condiciones dan contexto a tus pruebas.')) ?></p>
        </section>
        <aside class="home-limits" aria-labelledby="home-limits-heading">
          <h2 id="home-limits-heading"><?= h(t('La prueba está en la pista')) ?></h2>
          <p><?= h(t('Las explicaciones y propuestas son orientativas. La app no calcula un reglaje óptimo ni simula la física del coche.')) ?></p>
          <p><?= h(t('Cambia pocas variables y comprueba el resultado conduciendo en las mismas condiciones.')) ?></p>
        </aside>
      </div>

      <section class="home-manual" aria-labelledby="home-guide-heading">
        <div class="home-guide-heading">
          <h2 id="home-guide-heading"><?= h(t('Del reglaje inicial a tu siguiente prueba')) ?></h2>
          <p><?= h(t('Un recorrido por las herramientas de la aplicación.')) ?></p>
        </div>
        <ol class="home-guide">
          <li>
            <span class="home-step" aria-hidden="true">01</span>
            <div><h3><a href="#circuito"><?= h(t('Circuito y referencia')) ?></a></h3>
            <p><?= h(t('Importa la ficha y el reglaje inicial, o introduce sus valores en Taller. Indica las condiciones y fija la referencia que usarás para comparar.')) ?></p></div>
          </li>
          <li>
            <span class="home-step" aria-hidden="true">02</span>
            <div><h3><a href="#taller"><?= h(t('Taller')) ?></a></h3>
            <p><?= h(t('Edita los veinte ajustes por categorías. Consulta sus efectos habituales y el resumen de cambios respecto a tu referencia.')) ?></p></div>
          </li>
          <li>
            <span class="home-step" aria-hidden="true">03</span>
            <div><h3><a href="#pruebas"><?= h(t('Pruebas')) ?></a></h3>
            <p><?= h(t('Selecciona el problema que notas al conducir y revisa qué ajustes conviene ensayar. Prueba cada propuesta en pista.')) ?></p></div>
          </li>
          <li>
            <span class="home-step" aria-hidden="true">04</span>
            <div><h3><a href="#versiones"><?= h(t('Versiones')) ?></a></h3>
            <p><?= h(t('Guarda variantes con nombre y notas, abre una versión como borrador o expórtala en JSON. Cada guardado conserva las versiones anteriores.')) ?></p></div>
          </li>
          <li class="home-maintenance">
            <span class="home-step" aria-hidden="true">05</span>
            <div><h3><a href="options.php"><?= h(t('Opciones')) ?></a> <?= h(t('y')) ?> <a href="update.php"><?= h(t('Actualizaciones')) ?></a></h3>
            <p><?= h(t('Elige Alpha para probar novedades o Estable para versiones estables. Accede a Opciones y Actualizaciones desde el menú de la versión, arriba a la derecha.')) ?></p></div>
          </li>
        </ol>
        <p class="home-draft-note"><?= h(t('El borrador se conserva al cambiar de apartado. Guárdalo en Versiones para conservarlo en la base de datos.')) ?></p>
      </section>
    </section>
    <div data-page="taller" hidden class="page-heading"><div><p class="eyebrow"><?= h(t('OBSERVA. AJUSTA. PRUEBA.')) ?></p><h1><?= h(t('Entiende cada cambio.')) ?></h1><p class="intro"><?= h(t('Parte del reglaje del juego. Ajusta con una idea, compruébala en pista.')) ?></p></div><span class="theory-badge"><?= h(t('Siempre teoría')) ?><small><?= h(t('Sin predicciones de rendimiento')) ?></small></span></div>
    <p id="status" class="status" role="status" aria-live="polite"><?= h(t('Cargando el taller…')) ?></p>
    <noscript><p class="status error"><?= h(t('Activa JavaScript para utilizar el editor.')) ?></p></noscript>
    <div id="recovery" class="notice" hidden><?= h(t('Hay un borrador anterior en este navegador.')) ?> <button id="recover" class="text-button"><?= h(t('Recuperarlo')) ?></button> <button id="dismiss-recovery" class="text-button"><?= h(t('Descartarlo')) ?></button></div>

    <section data-page="circuito" hidden class="panel context-panel" aria-labelledby="context-heading">
      <div class="section-heading"><div><p class="eyebrow"><?= h(t('01 / PUNTO DE PARTIDA')) ?></p><h2 id="context-heading"><?= h(t('Circuito y referencia')) ?></h2></div><button id="open-circuit" class="button secondary"><?= h(t('Pegar ficha de circuito')) ?></button></div>
      <div class="context-fields">
        <label><?= h(t('Circuito')) ?><select id="circuit"><option value=""><?= h(t('Selecciona o importa una ficha')) ?></option></select></label>
        <label><?= h(t('Condiciones')) ?><select id="surface"><option value="unknown"><?= h(t('Sin indicar')) ?></option><option value="dry"><?= h(t('Seco')) ?></option><option value="wet"><?= h(t('Mojado')) ?></option></select></label>
        <label><?= h(t('Sesión')) ?><select id="session"><option value="unknown"><?= h(t('Sin indicar')) ?></option><option value="race"><?= h(t('Carrera')) ?></option><option value="time-trial"><?= h(t('Contrarreloj')) ?></option></select></label>
      </div>
      <div id="circuit-card" hidden></div>
      <div class="reference-row"><div><strong id="reference-label"><?= h(t('Referencia pendiente')) ?></strong><p id="reference-help"><?= h(t('Introduce los veinte valores que te asigna el juego o importa un reglaje.')) ?></p></div><div class="actions"><button id="open-setup" class="button secondary"><?= h(t('Importar reglaje')) ?></button><button id="set-reference" class="button"><?= h(t('Fijar actuales como referencia')) ?></button></div></div>
      <div class="reference-options"><label><?= h(t('Origen de los valores que vas a fijar')) ?><select id="reference-source"><option value="game-assigned"><?= h(t('Asignados por el juego (aportados por mí)')) ?></option><option value="personal"><?= h(t('Reglaje personal')) ?></option><option value="capture"><?= h(t('Capturas')) ?></option><option value="unknown"><?= h(t('Desconocido')) ?></option></select></label><button id="example" class="text-button"><?= h(t('Cargar ejemplo de las capturas')) ?></button></div>
    </section>

    <div class="workspace separated-workspace">
      <section data-page="taller" hidden id="editor" aria-labelledby="editor-heading">
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
      <div class="actions"><button id="save" class="button"><?= h(t('Guardar versión')) ?></button><button id="export" class="button secondary"><?= h(t('Exportar JSON')) ?></button><button id="export-legacy" class="text-button"><?= h(t('JSON del comparador anterior')) ?></button></div>
      <div class="library"><label><?= h(t('Versiones guardadas')) ?><select id="saved"><option value=""><?= h(t('Todavía no hay versiones guardadas')) ?></option></select></label><button id="load" class="button secondary"><?= h(t('Abrir como borrador')) ?></button></div>
    </section>
    <footer>F1 25 SETUP ENGINEER <span><?= h(t('Un taller para comprender y experimentar.')) ?></span><p><?= h(t('Teoría orientativa. Las fuentes pueden discrepar, especialmente sobre presiones. Valida en tu juego y sesión.')) ?></p></footer>
  </main>

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
