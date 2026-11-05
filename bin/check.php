<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'PDO: ' . (class_exists('PDO') ? implode(', ', PDO::getAvailableDrivers()) : 'no disponible') . PHP_EOL;
echo 'JSON: ' . (extension_loaded('json') ? 'disponible' : 'no disponible') . PHP_EOL;
echo 'Sesiones: ' . (extension_loaded('session') ? 'disponible' : 'no disponible') . PHP_EOL;
echo 'Este diagnóstico CLI no demuestra qué extensiones carga Apache.' . PHP_EOL;
