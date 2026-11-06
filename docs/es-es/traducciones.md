[English version](../translations.md)

# Traducciones

La interfaz se distribuye actualmente en español. **Opciones → Idioma de la aplicación** muestra los idiomas disponibles y guarda la selección en una cookie del navegador limitada a esta instalación. El canal de actualización sigue siendo una opción compartida por la instalación.

## Catálogos

Edita `languages/es.po` con un editor PO como Poedit. Es la fuente editable; `src/locale_es.php` se genera y no debe editarse manualmente. Su nombre encaja en las rutas que admiten los actualizadores Alpha existentes. No se necesita la extensión gettext ni instalar idiomas en el sistema operativo.

PHP y JavaScript comparten el catálogo generado mediante `Translation` y `public_html/i18n.php`. La salida se escapa en HTML o se inserta con `textContent`. Las descripciones de circuitos importadas, los nombres de reglajes, las notas y las notas originales de las releases conservan su contenido.

Las descripciones de ajustes y las propuestas de pruebas incluidas pasan por el traductor; los identificadores, rangos numéricos, reglas y formatos de importación/exportación no cambian. `Domain` recibe una función de traducción opcional y funciona independientemente del estado del navegador.

## Flujo de desarrollo

Instala una vez la dependencia de desarrollo:

```sh
python -m pip install -r requirements-dev.txt
```

Utiliza mensajes literales con comillas simples en `t('Mensaje')` en PHP y JavaScript, o `$this->tr('Mensaje')` dentro de `Domain`. Para los valores variables utiliza marcadores con nombre; por ejemplo, `t('Versión {version}', ['version' => appVersion()])` en PHP. Mantén las frases completas. Usa `tn(singular, plural, cantidad)` para mensajes con plural.

```sh
python bin/translations.py --extract
# Traduce las entradas nuevas en languages/es.po.
python bin/translations.py
python bin/translations.py --check
php tests/translations.php
```

La extracción actualiza el catálogo español, conserva las traducciones existentes y marca como obsoletas las entradas que ya no se usan. La herramienta recoge explícitamente los mensajes dinámicos de los datos incluidos y los errores del actualizador. El generador de paquetes rechaza catálogos generados desactualizados, mensajes ausentes, traducciones incompletas o pendientes de revisión, marcadores distintos y reglas de plural no admitidas. Polib comprueba la sintaxis PO y los duplicados.

Versiona juntos el PO y el catálogo PHP generado. El servidor carga los archivos preparados; no ejecuta Python, polib ni comandos de conversión. Un catálogo ausente o no válido permite volver a los mensajes originales; una entrada sin traducción utiliza el español y después su texto original.

## Añadir un idioma

1. Copia `languages/es.po` con el código del nuevo idioma, por ejemplo `languages/en.po`, y actualiza su cabecera `Language`.
2. Traduce las entradas conservando los identificadores de mensaje y los marcadores con nombre. Mantén los catálogos completos al añadir mensajes.
3. Genera y comprueba los catálogos con los comandos anteriores.
4. Registra el código y el nombre nativo en `Translation::LANGUAGES`.
5. Revisa toda la interfaz, los errores, los formatos de números y fechas y la presentación móvil; comprueba la cookie guardada y la recuperación del borrador.

La implementación actual de plurales en PHP y JavaScript admite la regla de español e inglés `nplurals=2; plural=(n != 1);`. Un idioma con otra regla requiere modificar explícitamente el motor antes de generar el paquete. Añadir un idioma no requiere cambiar el esquema de la base de datos.
