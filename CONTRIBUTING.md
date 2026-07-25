# Guía de contribución

Convenciones del proyecto **Rendición de Tickets**.

## Flujo de trabajo

1. Crear una rama desde `main`: `git checkout -b feat/mi-cambio`.
2. Realizar cambios atómicos y acompañados de tests.
3. Ejecutar la batería antes de abrir el Pull Request: `php test/run_tests.php`.
4. Abrir un Pull Request describiendo el qué y el porqué del cambio.

## Mensajes de commit — [Conventional Commits](https://www.conventionalcommits.org/es/)

```
<tipo>(ámbito opcional): <descripción en imperativo, minúscula, sin punto>
```

Tipos: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`.

Ejemplos:

```
feat(ocr): agrega recuperación difusa de CUIT contra el padrón
fix(escaneo): corrige la orientación en fotografías verticales
docs(readme): agrega diagrama de arquitectura
test(parser): regresión para importes en formato AR
```

## Regla del proyecto

**Cada defecto detectado incorpora un test de regresión** en `test/run_tests.php` para
impedir su reaparición.

## Estilo

- PHP 8.1+, indentación de 4 espacios (ver `.editorconfig`).
- JavaScript sin build. Validar la sintaxis (`node --check archivo.js`).
- Sin secretos ni datos reales en el repositorio (ver `.gitignore` y `SECURITY.md`).
