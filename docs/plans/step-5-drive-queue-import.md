# Крок 5. Імпорт через 4wp-drive у фоновій черзі

Дата: 2026-11-14 · Статус: **код готовий; наскрізний тест через Drive Inbox — після push `meta.json` у хаб**

## Узгоджено з автором

| # | Рішення |
|---|---|
| 1 | Пакет курсу = тека з `meta.json` (як `Videos/*`), нічого нового |
| 2 | Пакет не переноситься в `published/`, стан — у `meta.json` |
| 3 | LMS4WP обробляє пакет, лише якщо встановлений; Drive про LMS не знає |
| 4 | Черга — Action Scheduler (залежність через Composer) |
| 5 | ADR 0007 |
| 6 | Серія постів / відео про черги — окремо; кут у хабі: `Queues - in progress/00-angle.md` |

## Зроблено

| Репо | Файл | Що |
|---|---|---|
| хаб | `LMS4WP/courses/git-basics/meta.json` | маніфест `lms-course` |
| хаб | `Queues - in progress/00-angle.md` | кут серії, in progress |
| 4wp-drive | `src/Package/Package_Store.php` | `forwp_drive_package_file` — шляхи з підтеками (без `..`); `forwp_drive_package_files` — 3-й аргумент `$recursive` |
| lms4wp | `composer.json` | `woocommerce/action-scheduler` ^4.2 → `vendor/woocommerce/action-scheduler/` |
| lms4wp | `includes/Import/PackageReader.php`, `FilesystemPackageReader.php`, `DrivePackageReader.php` | читання пакета з диска / через Drive |
| lms4wp | `includes/Import/CourseImporter.php` | reader, кроки `all` / `course` / `module:<key>`, `findCourseId`, `moduleKeys` |
| lms4wp | `includes/Queue/ImportQueue.php` | Action Scheduler: fan-out / fan-in, повтори, стан `lms4wp_import_state_<slug>` |
| lms4wp | `includes/Integrations/Drive.php` | family plugin, без дубля курсу, черга після імпорту, запис `lms` у `meta.json` |
| lms4wp | `includes/CLI/CourseCommand.php` | `--queue`, `status`; коміт пакета без `meta.json` |
| lms4wp | `lms4wp.php`, `includes/Core/Loader.php` | підключення |

## Перевірено

- Синхронний імпорт після рефакторингу: `skipped 27`, `verify` — ок.
- `import --queue` → 9 задач (курс, 7 модулів, finalize) → `status`: done, 7/7, verify без розбіжностей.
- Reader через фільтри Drive (імітація в процесі): 26 уроків, 0 розбіжностей.
- Повтор: помилка кроку → задача знову в черзі через 60 с (тестову задачу скасовано).

## Наступне (автор)

1. Закомітити й запушити `meta.json` курсу і кут у хабі.
2. Drive Inbox → пакет `git-basics` → тип «Course» → Import → `wp lms4wp course status git-basics` / `meta.json` → `lms`.

## Деплой

`composer install --no-dev` і `npm run build:css` у lms4wp (обидва результати в `.gitignore`).
