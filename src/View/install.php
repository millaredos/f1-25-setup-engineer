<?php
declare(strict_types=1);

/** Installation requires an explicit POST and never resets an installed site. */
?>
<section class="prose">
<h1>Instalación</h1>
<p>Configura la conexión MariaDB en <code>config/local.php</code>, fuera del directorio público, y comprueba los requisitos.</p>
<ul class="checks">
<?php foreach ($checks as $name => $ok): ?>
<li>
<span>
<?= $escape($name) ?>
</span>
<strong>
<?= $ok ? 'Correcto' : 'Pendiente' ?>
</strong>
</li>
<?php endforeach; ?>
</ul>
<form method="post">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<button class="button" type="submit">Crear almacenamiento e instalar</button>
</form>
<p class="muted">Los datos personales existentes no se sustituyen. Una instalación completada bloquea esta pantalla.</p>
</section>
