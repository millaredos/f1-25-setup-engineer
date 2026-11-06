<?php
declare(strict_types=1);

// Descripciones informativas independientes de los reglajes y de sus versiones guardadas.
$guide = ['title' => 'OverTake — F1 25 Track Guides', 'url' => 'https://www.overtake.gg/news/f1-25-track-guides.3245/?full=1', 'accessedAt' => '2026-11-06'];
$madrid = ['title' => 'EA SPORTS — Madring', 'url' => 'https://www.ea.com/games/f1/f1-25/news/f1-25-2026-season-edition-madring-tips-and-tricks', 'accessedAt' => '2026-11-06'];
$permanent = t('Permanente'); $street = t('Urbano'); $mixed = t('Semipermanente');
$pending = t('Pendiente de contrastar para el coche de 2026');
return [
    'australia-albert-park' => [$mixed, t('Curvas rápidas enlazadas y frenadas para giros más lentos.'), $pending, [$guide]],
    'china-shanghai' => [$permanent, t('Curvas largas de velocidad media y una horquilla lenta, junto a una recta extensa.'), $pending, [$guide]],
    'japan-suzuka' => [$permanent, t('Eses rápidas, cambios de dirección y una horquilla lenta.'), $pending, [$guide]],
    'bahrain' => [$permanent, t('Frenadas fuertes y curvas lentas alternan con tramos rápidos.'), $pending, [$guide]],
    'saudi-arabia-jeddah' => [$street, t('Curvas rápidas entre muros y algunas frenadas fuertes.'), $pending, [$guide]],
    'miami' => [$mixed, t('Rectas largas, curvas rápidas y una sección lenta y estrecha.'), $pending, [$guide]],
    'canada-montreal' => [$mixed, t('Chicanes de velocidad media y una horquilla lenta, separadas por rectas y frenadas.'), $pending, [$guide]],
    'monaco' => [$street, t('Curvas muy lentas y estrechas entre barreras, con tramos breves de aceleración.'), $pending, [$guide]],
    'catalunya-barcelona' => [$permanent, t('Curvas de velocidad variada, incluidas varias largas y rápidas.'), $pending, [$guide]],
    'austria-spielberg' => [$permanent, t('Frenadas fuertes y curvas lentas seguidas de curvas rápidas.'), $pending, [$guide]],
    'great-britain-silverstone' => [$permanent, t('Secuencias de curvas rápidas y cambios de dirección, con algunas curvas lentas.'), $pending, [$guide]],
    'belgium-spa' => [$permanent, t('Rectas largas, curvas rápidas y cambios de altura, con una chicane lenta.'), $pending, [$guide]],
    'hungary-budapest' => [$permanent, t('Curvas enlazadas de velocidad baja y media y rectas relativamente cortas.'), $pending, [$guide]],
    'netherlands-zandvoort' => [$permanent, t('Curvas rápidas y medias enlazadas, cambios de altura y dos curvas peraltadas.'), $pending, [$guide]],
    'monza' => [$permanent, t('Curvas rápidas y chicanes lentas, con rectas largas y frenadas fuertes.'), $pending, [$guide]],
    'madrid' => [$mixed, t('Curvas lentas en secciones estrechas y tramos rápidos, incluida la curva peraltada La Monumental. Requiere el paquete 2026 y sus coches.'), $pending, [$madrid]],
    'azerbaijan-baku' => [$street, t('Rectas largas y curvas lentas entre muros, incluida una zona estrecha.'), $pending, [$guide]],
    'singapore' => [$street, t('Curvas lentas y medias entre muros, con frenadas y cambios de dirección frecuentes.'), $pending, [$guide]],
    'texas-austin' => [$permanent, t('Eses rápidas, una recta larga y una sección final más lenta.'), $pending, [$guide]],
    'mexico' => [$permanent, t('Recta larga, frenadas fuertes y una sección técnica de curvas lentas.'), $pending, [$guide]],
    'brazil-interlagos' => [$permanent, t('Curvas lentas y medias enlazadas, cambios de altura y una subida prolongada hacia meta.'), $pending, [$guide]],
    'las-vegas' => [$street, t('Rectas muy largas y curvas lentas entre barreras.'), $pending, [$guide]],
    'qatar-lusail' => [$permanent, t('Curvas medias y rápidas enlazadas y una larga recta principal.'), $pending, [$guide]],
    'abu-dhabi' => [$permanent, t('Rectas largas, frenadas para curvas lentas y una sección final de curvas enlazadas.'), $pending, [$guide]],
    'imola' => [$permanent, t('Chicanes lentas y curvas rápidas, con cambios de altura y frenadas en bajada.'), $pending, [$guide]],
];
