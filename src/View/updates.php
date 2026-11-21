<?php
declare(strict_types=1);

/** The exact selected package is rechecked before any installation. */
?>
<section class="prose">
<h1>Actualizaciones</h1>
<p>Versión instalada: <strong>
<?= $escape($version) ?>
</strong>
</p>
<p>Canal: <?= $escape($channel) ?>
</p>
<form method="post">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="action" value="check">
<button class="button">Comprobar actualizaciones</button>
</form>
<?php if ($candidate): ?>
<section class="panel">
<h2>
<?= $escape($candidate['version']) ?>
</h2>
<p>Se descargará el paquete publicado y se verificará su integridad. Se conservará una copia privada de los archivos anteriores.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="action" value="install">
<input type="hidden" name="identity" value="<?= $escape($candidate['identity']) ?>">
<button class="button">Instalar esta actualización</button>
</form>
</section>
<?php endif; ?>
</section>
<?php if ($protection): ?>
<section class="panel prose">
<h2>Protección Apache</h2>
<p>Guarda un bloque gestionado en el archivo público .htaccess, conservando las reglas propias. Comprueba después que el alojamiento admite y aplica esas directivas.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="action" value="protect">
<input type="hidden" name="hash" value="<?= $escape($protection['hash']) ?>">
<button class="button button-secondary">Guardar protección</button>
</form>
</section>
<?php endif; ?>
