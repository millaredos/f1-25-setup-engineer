[English version](../coding-standards.md)

# Convenciones de código

Utiliza sintaxis compatible con PHP 8.0, tipos estrictos y tipos explícitos en parámetros, propiedades y retornos. Emplea cuatro espacios y PSR-12 como referencia de formato. Describe los contratos de arrays mediante PHPDoc cuando los tipos nativos no expresen su estructura.

Los controladores interpretan HTTP y coordinan operaciones. Los modelos validan y conservan invariantes del dominio; los repositorios contienen PDO. Las vistas reciben valores preparados y escapan al emitirlos. Mantén los controles reutilizables en módulos JavaScript y el CSS en archivos externos. Comparte comportamiento realmente repetido sin construir integraciones futuras innecesarias.

Documenta la responsabilidad de cada función, clase y módulo mantenido. Explica precondiciones, errores, efectos secundarios y decisiones no evidentes relevantes. Mantén los comentarios junto al código y actualiza ambos a la vez. Evita repetir asignaciones obvias o conservar implementaciones desactivadas.

Trata los datos importados como no fiables. Valida los contratos completos de ajustes en servidor sin reparar valores inválidos silenciosamente. Utiliza SQL preparado para valores variables, escapado según contexto y CSRF para mutaciones POST. Un token CSRF no autentica al usuario. Conserva secretos fuera de HTTP y Git. Registra errores internos en privado y muestra fallos comprensibles sin trazas ni credenciales.

Los cambios incluyen pruebas pertinentes de contratos, documentación en inglés y español y ambos changelogs. Informa por separado de modelos, HTTP, navegador, base de datos real y versión mínima de PHP. Revisa el diff completo antes de integrar. Consulta [verificación](verificacion.md) y [publicación](publicar-versiones.md).
