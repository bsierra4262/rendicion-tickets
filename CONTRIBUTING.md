# Guía de contribución

Gracias por colaborar en **Rendición de Tickets**. Estas son las convenciones del proyecto.

## Flujo de trabajo

1. Creá una rama desde `main`: `git checkout -b feat/mi-cambio`.
2. Hacé cambios atómicos y con tests.
3. Corré la batería antes de abrir el PR: `php test/run_tests.php`.
4. Abrí un Pull Request describiendo el qué y el por qué.

## Mensajes de commit — [Conventional Commits](https://www.conventionalcommits.org/es/)

```
<tipo>(ámbito opcional): <descripción en imperativo, minúscula, sin punto>
```

Tipos: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`.

Ejemplos:

```
feat(ocr): agrega recuperación difusa de CUIT contra el padrón
fix(escaneo): corrige la orientación en fotos verticales
docs(readme): agrega diagrama de arquitectura
test(parser): regresión para importes en formato AR
```

## Regla de oro del proyecto

**Cada bug encontrado agrega un test de regresión** en `test/run_tests.php` para que no vuelva a aparecer.

## Estilo

- PHP 8.2, 4 espacios de indentación (ver `.editorconfig`).
- JavaScript vanilla, sin build. Validá la sintaxis (`node --check archivo.js`).
- Nada de secretos ni datos reales en el repo (ver `.gitignore` y `SECURITY.md`).
