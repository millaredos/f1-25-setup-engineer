<?php
declare(strict_types=1);

/** One navigation component for the workshop and maintenance pages. */
function appHeader(string $current = ''): void
{
    $sections = ['circuito' => 'Circuito y referencia', 'taller' => 'Taller', 'pruebas' => 'Pruebas', 'versiones' => 'Versiones'];
    ?>
    <header class="masthead">
      <a class="brand" href="<?= $current === '' ? '#inicio' : './#inicio' ?>"><span class="brand-mark" aria-hidden="true"></span><span>SETUP ENGINEER<small><?= h(t('Tu taller de F1 25 / 26')) ?></small></span></a>
      <nav id="navigation" class="app-nav" aria-label="<?= h(t('Apartados')) ?>">
        <?php foreach ($sections as $id => $label): ?>
          <a href="<?= $current === '' ? '#' : './#' ?><?= $id ?>"><?= h(t($label)) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="version-menu">
        <button type="button" id="version-toggle" class="driver version-toggle" aria-expanded="false" aria-controls="version-panel">
          <span class="version-line"><?= h(t('Versión {version}', ['version' => appVersion()])) ?><span class="version-chevron" aria-hidden="true">▾</span><span class="update-dot" data-update-dot hidden aria-hidden="true"></span></span>
          <span class="version-subtitle">F1 25 / 26 Setup Engineer</span>
          <span class="sr-only" data-update-announcement></span>
        </button>
        <div id="version-panel" class="version-panel" hidden>
          <a href="options.php"<?= $current === 'options' ? ' aria-current="page"' : '' ?>><?= h(t('Opciones')) ?></a>
          <a href="update.php"<?= $current === 'updates' ? ' aria-current="page"' : '' ?>><span><?= h(t('Actualizaciones')) ?></span><span class="update-dot" data-update-dot hidden aria-hidden="true"></span><small data-update-version hidden></small></a>
        </div>
        <span class="sr-only" data-update-live role="status" aria-live="polite"></span>
      </div>
    </header>
    <?php
}
