# Крок 1. Формат курсу та імпорт (F1, F2)

Дата: 2026-11-14 · Статус: **код готовий**; запуск на локалі — після дозволу автора.

## Узгоджено з автором

| # | Рішення | Де |
|---|---|---|
| 1 | Імпорт напряму в LMS4WP через WP-CLI, без 4wp-drive | ADR 0001 → ADR 0005 |
| 2 | WooCommerce не обов'язковий | ADR 0002 |
| 3 | Доступ гостя — налаштування курсу | ADR 0003 |
| 4 | Модуль — мета курсу | ADR 0004 |
| 5 | Git — джерело істини; `--replace`, `verify`, коміт хабу на курсі; правки — 4wp-drive | ADR 0005 |
| 6 | Плагін на локалі — симлінк на цей репо | `AGENTS.md` |

## Файли

| Дія | Файл |
|---|---|
| створено | `AGENTS.md`, `docs/SPEC.md`, `docs/COURSE-FORMAT.md`, `docs/decisions/*`, цей план |
| створено | `includes/Import/FrontMatter.php` — front-matter |
| створено | `includes/Import/MarkdownBlocks.php` — Markdown → core-блоки |
| створено | `includes/Import/CourseImporter.php` — курс, модулі, уроки; upsert за `_lms4wp_source` |
| створено | `includes/CLI/CourseCommand.php` — `wp lms4wp course import` / `verify` |
| змінено | `includes/Core/Loader.php`, `lms4wp.php` — підключення команди |

## Перевірено

- `php -l` — без помилок.
- Конвертер на `lesson-1-2-three-states.md` і `course-structure.md`: заголовки, списки, код, таблиці, інлайн — коректно.
- Розбір `git-basics`: 7 модулів, 26 уроків, назви й мета модулів.

## Запуск на локалі (потребує дозволу)

Сайт — Bedrock `~/Local Sites/4wpdev/app/4wp.dev`; `web/app/plugins/lms4wp` — симлінк на цей репо (з жовтня). WP-CLI — з `app/4wp.dev` (див. `AGENTS.md`). `public-local` — окрема інсталяція, не використовується.

1. Закомітити `git-basics` у хабі (автор).
2. `wp lms4wp course import …/git-basics --replace --status=publish --dry-run`
3. Той самий запуск без `--dry-run`.
4. `wp lms4wp course verify …/git-basics` → без розбіжностей.
