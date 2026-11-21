[English version](../translations.md)

# Catálogos de traducción

La interfaz actual está en español. Solo está habilitado `es`; esta reconstrucción no ofrece soporte completo para un segundo idioma. Los mensajes de navegación utilizan el catálogo y los restantes conservan su texto fuente en español.

Edita `languages/es.po` y ejecuta `php dev/bin/translations.php`. El compilador PHP admite entradas singulares y la regla española de dos plurales, comprueba marcadores y rechaza entradas duplicadas, dudosas o con contextos no admitidos. Genera `src/Translation/catalog_es.php`, que solo contiene datos; no edites ese resultado.

Ejecuta `php dev/bin/translations.php --check` antes de empaquetar. CI comprueba que los archivos generados coincidan con su fuente. Los mensajes ausentes conservan el texto fuente. La herramienta actual no tiene extracción automática. Registra explícitamente los mensajes nuevos que deban utilizar el catálogo. Otro idioma requiere cobertura completa, su regla de plurales e integración en servidor y cliente; ampliar la lista de idiomas permitidos no basta.
