<?php
declare(strict_types=1);

/** Installation-wide channel preference; language catalog support is separate. */
?>
<section class="prose">
<h1>Opciones</h1>
<form class="panel stack" method="post">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<label>Idioma<select name="language">
<option value="es">Español</option>
</select>
</label>
<label>Canal de actualización<select name="channel">
<option value="release" <?= $channel === 'release' ? 'selected' : '' ?>>Estable</option>
<option value="alpha" <?= $channel === 'alpha' ? 'selected' : '' ?>>Alpha</option>
</select>
</label>
<button class="button" type="submit">Guardar opciones</button>
</form>
</section>
