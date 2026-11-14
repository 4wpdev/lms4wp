# Формат курсу (хаб → LMS4WP)

Контракт між хабом `~/4wp-publish-hub/LMS4WP/courses/` і імпортом LMS4WP (ADR 0005). Зразок — `courses/git-basics/`.

## Флоу

```mermaid
flowchart LR
  PKG["Пакет курсу<br/>courses/&lt;slug&gt;/"] --> GIT[("git хабу<br/>джерело істини")]
  GIT --> IMP["import --replace"]
  IMP --> SITE[("Курс на сайті")]
  SITE --> VER{"verify"}
  VER -- розбіжності --> GIT
  GIT -- "дрібні правки" --> DRV["4wp-drive<br/>оновлює урок"] --> SITE
```

1. Пакет курсу закомічений у хабі.
2. `import --replace` — стерти курс на сайті й імпортувати заново.
3. `verify` — сайт = пакет.
4. Дрібні правки — у git, на сайт — через 4wp-drive (за `_lms4wp_source`).

## Структура

```
courses/<course-slug>/
├── course-structure.md          → Курс (lms_course)
└── module-<N>-<slug>/           → Модуль (мета курсу, ADR 0004)
    ├── requirements.md          → назва і мета модуля
    ├── lesson-plan.md           → не імпортується
    ├── terminal.json            → термінал чаптера (мета курсу, ADR 0006)
    └── lesson-<N>-<M>-<slug>.md → Урок (lms_lesson)
```

## Front-matter

```yaml
---
title: "Урок 1.1. …"
slug: "lesson-1-1-…"
type: course | lesson | draft-note
category: "Module N"
level: "Beginner"            # → lms_level (Beginner / Intermediate / Advanced)
tags: ["git", "vcs"]         # → lms_tag
status: draft
---
```

`level` і `tags` — для курсу (`course-structure.md`) і уроків. Показуються в шаблонах курсу й уроку.

Лише для курсу: `guest_lessons: 3` — скільки перших уроків відкрито без входу (ADR 0003) → `_lms4wp_guest_lessons`. Без поля — значення з налаштувань курсу (за замовчуванням 1).

## Відповідність

| Хаб | LMS4WP | Правило |
|---|---|---|
| `<course-slug>` (тека) | `lms_course.post_name` | |
| `course-structure.md` → `title` | `post_title` курсу | частина до ` — ` |
| `course-structure.md` → тіло | `post_content` курсу | Markdown → блоки, без H1 |
| `module-<N>-<slug>` (тека) | ключ модуля | номер `N` — порядок |
| `requirements.md` → `title` | назва модуля | без `Модуль N. ` і ` — вимоги` |
| `requirements.md` → `## Мета модуля` | мета модуля | перший абзац |
| `lesson-*.md` → `title` | `post_title` уроку | |
| `<course-slug>-` + `slug` | `post_name` уроку | префікс — від колізій між курсами |
| `lesson-*.md` → тіло | `post_content` уроку | Markdown → блоки, без H1 |
| `N`, `M` з імені файлу | `menu_order`, `_lms4wp_order` | `N * 100 + M` |
| `status` | `post_status` | `draft` → `draft`; перевизначається `--status` |

## Мета-ключі

| Ключ | Де | Значення |
|---|---|---|
| `_lms4wp_source` | курс, урок | шлях відносно `courses/` — ключ для повторного імпорту |
| `_lms4wp_source_hash` | курс, урок | sha1 файлу — пропуск незмінених, `verify` |
| `_lms4wp_source_commit` | курс | коміт хабу; `-dirty` / `uncommitted` — якщо є незакомічені зміни |
| `_lms4wp_modules` | курс | `[{key, number, title, goal}]` |
| `_lms4wp_course_id` | урок | ID курсу (наявний ключ) |
| `_lms4wp_module` | урок | ключ модуля |
| `_lms4wp_order` | урок | порядок (наявний ключ) |
| `lms_level`, `lms_tag` | курс, урок | таксономії з front-matter `level`, `tags` |

## Секції уроку

Шаблон уроку в хабі однаковий: `Мета`, `Ключові поняття`, `Команди / артефакти`, `Типові помилки`, `Практичне завдання`, `Питання з інтерв'ю`.

### Практичне завдання → термінал чаптера (ADR 0006)

В уроці — лише тег:

````markdown
```terminal
```
````

→ блок `lms4wp/lesson-terminal` → `forwp-advanced-code/terminal` з профілем `lms4wp-lesson-<ID>`, зібраним з `terminal.json` модуля:

```json
{
  "version": 1,
  "title": "git — Модуль 1. Основи Git",
  "prompt": "$",
  "welcome": "Привітання за замовчуванням",
  "commands": [
    { "command": "git status", "slug": "status", "output": "On branch main…", "tip": "…" },
    { "pattern": "git add", "slug": "add", "output": "…" }
  ],
  "lessons": {
    "lesson-1-2-three-states": { "welcome": "Практика 1.2 — …", "commands": ["status", "add"] }
  }
}
```

- `commands` — формат профілю 4wp-advanced-code (`command` або `pattern`, непорожній `output`).
- Ключ у `lessons` — ім'я файлу уроку без `.md`.
- Сайдбар: «This lesson» — команди уроку, «Chapter» — решта; працюють усі команди чаптера.
- ```` ```terminal ```` з `profile: <slug>` — звичайний профіль 4wp-advanced-code (без чаптера).

### Код-блоки

Мова — з fence (```` ```bash ````; `sh`/`shell`/`zsh` → `bash`, `js` → `javascript`, …). Без мови або невідома — `plaintext`. Автовизначення 4wp-advanced-code не використовується.

### Питання з інтерв'ю → FAQ (4wp-faq)

```markdown
## Питання з інтерв'ю

### Питання?
Відповідь (будь-які блоки до наступного ### / ##).
```

→ `forwp/faq` + core accordion. Категорія FAQ: `<курс>` › `<Модуль N. Назва>` (slug `<course>-<module-key>`), створюється при імпорті. Питання потрапляють у базу знань після сканування 4wp-faq (cron `forwp_faq_scan`).
Старий формат (список `- питання` без відповідей) лишається звичайним списком.

## Markdown

Підтримується: заголовки, абзаци, списки, код-блоки (```` ```lang ````), таблиці, цитати, `---`; інлайн — `**жирний**`, `*курсив*`, `` `код` ``, `[посилання](url)`.

## Команди

```bash
wp lms4wp course import <path> [--replace] [--status=<draft|publish>] [--force] [--dry-run]
wp lms4wp course verify <path>
```

- `--replace` — видаляє курс і його уроки на сайті (за `_lms4wp_source`), потім імпортує. Нові ID — лише до запуску курсу для студентів.
- `--status` — перевизначає `status` з front-matter.
- `--force` — оновлює і незмінені файли.
- `--dry-run` — лише показує, що буде зроблено.
- `verify` — нічого не пише; показує `missing`, `changed`, `orphan`, `course`, `module`, `modules`, `commit`. Код виходу 1 при розбіжностях.
