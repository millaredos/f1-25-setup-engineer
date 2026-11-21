<?php
declare(strict_types=1);

/** Escape public errors; technical details remain in server logs. */
?>
<h1>No se pudo abrir la página</h1>
<p>
<?= $escape($message ?? 'Operación no disponible.') ?>
</p>
