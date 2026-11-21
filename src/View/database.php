<?php
declare(strict_types=1);

/** Report storage compatibility without reinstalling or overwriting snapshots. */
?>
<section class="prose">
<h1>Base de datos</h1>
<p>Comprueba que las tablas personales sean compatibles. Los catálogos de temporadas se distribuyen separados de tus reglajes.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<button class="button">Comprobar almacenamiento</button>
</form>
</section>
