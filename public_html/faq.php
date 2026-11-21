<?php
declare(strict_types=1);

/** Read-only user documentation for the circuit JSON format; no draft state is mutated. */
require dirname(__DIR__) . '/src/bootstrap.php';
redirectForInstallation(false);
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?>
<!doctype html>
<html lang="<?= h(appTranslation()->language()) ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h(t('Preguntas frecuentes')) ?> · F1 25 / 26</title>
    <link rel="stylesheet" href="assets/app.css?v=<?= h(appVersion()) ?>" />
    <script type="module" src="assets/navigation.js?v=<?= h(appVersion()) ?>"></script>
  </head>
  <body>
    <?php appHeader('faq'); ?>
    <main class="installation faq-content">
      <h1><?= h(t('Docs')) ?></h1>
      <div class="docs-layout">
        <nav class="docs-menu" aria-label="<?= h(t('Documentación')) ?>">
          <a href="faq.php" aria-current="page"><?= h(t('FAQ')) ?></a>
        </nav>
        <div class="docs-content">
          <h2><?= h(t('Preguntas frecuentes')) ?></h2>
          <section id="ficha-circuito" class="panel" aria-labelledby="ficha-heading">
            <h2 id="ficha-heading"><?= h(t('¿Cómo relleno el JSON de un circuito?')) ?></h2>
            <p><?= h(t('Descarga la plantilla desde Añadir circuito y edita sus valores con un editor de texto. Solo el nombre es obligatorio; los demás datos se pueden dejar vacíos si no los conoces.')) ?></p>
            <dl class="faq-fields">
              <dt>
                <code>format</code>
                y
                <code>schemaVersion</code>
              </dt>
              <dd><?= h(t('Identifican el formato del archivo. Conserva los valores incluidos en la plantilla.')) ?></dd>
              <dt><code>nombre</code></dt>
              <dd><?= h(t('Nombre del circuito que aparecerá en la lista. Es obligatorio.')) ?></dd>
              <dt><code>ubicacion</code></dt>
              <dd><?= h(t('País, ciudad o lugar del circuito. En un circuito ficticio puedes indicar su ubicación imaginaria o dejarlo vacío.')) ?></dd>
              <dt><code>longitud_km</code></dt>
              <dd><?= h(t('Longitud de una vuelta en kilómetros. Escribe un número con punto decimal, por ejemplo 5.451, sin comillas ni unidad. Si no la conoces, conserva null.')) ?></dd>
              <dt><code>curvas</code></dt>
              <dd><?= h(t('Número total de curvas: escribe un número entero, por ejemplo 16, sin comillas. Si no lo conoces, conserva null.')) ?></dd>
              <dt><code>categoria_carga</code></dt>
              <dd><?= h(t('Orientación sobre la carga aerodinámica que puede favorecer el trazado. Es texto libre: por ejemplo Baja, Media-baja, Media, Media-alta o Alta. Si no lo sabes, déjalo vacío. Esta categoría no asigna un reglaje predeterminado.')) ?></dd>
              <dt><code>curvas_y_rectas</code></dt>
              <dd><?= h(t('Describe cómo es el trazado: rectas largas, curvas lentas o rápidas, horquillas, chicanes y desniveles. Por ejemplo: «Curvas enlazadas y una recta larga que termina en una horquilla».')) ?></dd>
              <dt><code>caracteristicas_conduccion</code></dt>
              <dd><?= h(t('Describe qué puede exigir el trazado al coche y al piloto. Por ejemplo: «Las curvas prolongadas requieren estabilidad y la recta da importancia a la velocidad punta».')) ?></dd>
            </dl>
            <p><?= h(t('Escribe los textos entre comillas y conserva las llaves y las comas de la plantilla. El circuito se importa sin reglaje predeterminado asignado.')) ?></p>
          </section>
        </div>
      </div>
    </main>
  </body>
</html>
