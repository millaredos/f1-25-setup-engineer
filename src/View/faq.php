<?php
declare(strict_types=1);

/** Explain current import contracts without exposing internal implementation. */
?>
<section class="prose">
<h1>Preguntas frecuentes</h1>
<h2>¿Cómo comienzo un reglaje?</h2>
<p>Selecciona temporada y circuito. Si hay un predeterminado confirmado, puedes usarlo; si no, introduce tus propios valores.</p>
<h2>¿Qué se guarda?</h2>
<p>La referencia, los valores actuales, el circuito, la temporada y tus notas. Importar o editar prepara un borrador: guardar requiere una acción explícita.</p>
<h2>¿Cómo añado un circuito?</h2>
<p>En Circuitos descarga la plantilla JSON, rellena el nombre y los datos que conozcas e importa el archivo. No se deduce un predeterminado a partir de la descripción.</p>
<h2>¿Cómo relleno el JSON de un circuito?</h2>
<p>Solo el nombre es obligatorio. Puedes dejar vacíos los datos que no conozcas. Conserva las llaves, comas, <code>format</code> y <code>schemaVersion</code> de la plantilla.</p>
<dl>
    <dt><code>nombre</code></dt>
    <dd>Nombre del circuito que aparecerá en la lista.</dd>
    <dt><code>ubicacion</code></dt>
    <dd>País, ciudad o lugar. Un circuito ficticio puede tener una ubicación imaginaria o dejarla vacía.</dd>
    <dt><code>longitud_km</code></dt>
    <dd>Longitud de una vuelta en kilómetros: número con punto decimal, por ejemplo 5.451, sin comillas ni unidad. Conserva <code>null</code> si la desconoces.</dd>
    <dt><code>curvas</code></dt>
    <dd>Número entero de curvas, por ejemplo 16, sin comillas. Conserva <code>null</code> si lo desconoces.</dd>
    <dt><code>categoria_carga</code></dt>
    <dd>Texto libre, por ejemplo Baja, Media-baja, Media, Media-alta o Alta. Puede quedar vacío y no asigna un predeterminado.</dd>
    <dt><code>curvas_y_rectas</code></dt>
    <dd>Describe rectas, curvas lentas o rápidas, horquillas, chicanes y desniveles. Por ejemplo: «Curvas enlazadas y una recta larga que termina en una horquilla».</dd>
    <dt><code>caracteristicas_conduccion</code></dt>
    <dd>Describe qué puede exigir el trazado al coche y al piloto. Por ejemplo: «Las curvas prolongadas requieren estabilidad y la recta da importancia a la velocidad punta».</dd>
</dl>
<p>Escribe los textos entre comillas. El circuito se importa sin reglaje predeterminado asignado.</p>
<h2>¿Las explicaciones predicen el rendimiento?</h2>
<p>No. Son tendencias teóricas; comprueba los cambios en el juego con condiciones comparables.</p>
</section>
