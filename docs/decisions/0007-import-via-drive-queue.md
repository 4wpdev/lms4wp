# 0007. Імпорт курсу через 4wp-drive, у фоновій черзі
Дата: 2026-11-14 · Статус: Прийнято · Частково замінює ADR 0005 (п. 2 — шлях імпорту)

## Контекст
Курс — це пакет на десятки файлів і постів. 4wp-drive уже працює з пакетами `meta.json` (джерело GitHub, Inbox, запис статусу назад у `meta.json`), тож окремий інструментарій імпорту не потрібен. Один HTTP-запит такий обсяг не витримує.

## Рішення
1. **Пакет курсу** — тека `LMS4WP/courses/<slug>/` з `meta.json`: `package.type = "lms-course"`, `original.file = "course-structure.md"`. Структура — у теках (`COURSE-FORMAT.md`), маніфест лише оголошує пакет і зберігає стан.
2. **4wp-drive** імпортує `original` у пост курсу (тип «Course» в Inbox). Пакет не переноситься в `published/` — стан у `meta.json`. Drive про LMS не знає; єдина зміна в Drive — читання файлів у підтеках пакета (`forwp_drive_package_file(s)`).
3. **LMS4WP** (якщо встановлений): на `forwp_drive_document_imported` ставить курс у чергу, читає пакет через фільтри Drive, пише результат у `meta.json` → `lms` (`state`, `course_id`, `lessons`, `commit`, `verify`, `errors`).
4. **Черга — Action Scheduler** (бібліотека в `vendor/`): fan-out (курс → задача на модуль) / fan-in (`finalize` → `verify`), ідемпотентні задачі (upsert за `_lms4wp_source`), повтори до 3 разів, остаточно невдалі — «failed» в Action Scheduler.
5. **CLI** лишається для розробки: `import` (синхронно) і `import --queue`, `status`, `verify`.
6. Дубль курсу не створюється: Drive оновлює наявний курс того самого пакета (`forwp_drive_import_postarr`).

## Наслідки
- Деплой LMS4WP: `composer install --no-dev` (Action Scheduler) і `npm run build:css`.
- Коміт пакета рахується без `meta.json` (його пише Drive).
- На проді — лише оновлення (без `--replace`), щоб не втратити прогрес студентів.
