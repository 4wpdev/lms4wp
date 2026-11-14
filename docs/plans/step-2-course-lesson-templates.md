# Крок 2. Шаблони курсу й уроку, блоки LMS4WP (F3, F4 — прототип)

Дата: 2026-11-14 · Статус: **виконано, на перевірці автора**

## Що зроблено

| Що | Файл |
|---|---|
| Шаблон курсу (hero: мета, назва, кнопка → опис → програма → кнопка) | `templates/single-lms_course.html` |
| Шаблон уроку (контекст, контент, попередній / наступний; збоку — програма) | `templates/single-lms_lesson.html` |
| Реєстрація шаблонів (`register_block_template`, тема може перевизначити тим самим slug) | `includes/Frontend/CourseTemplates.php` |
| Модель курсу: модулі → уроки, контекст уроку | `includes/Content/CourseStructure.php` |
| Блоки `lms4wp/course-meta`, `course-join`, `course-outline`, `lesson-context`, `lesson-nav` | `includes/Blocks/CourseBlocks.php` |
| Редактор: прев'ю блоків через ServerSideRender, без збірки | `assets/js/course-blocks-editor.js` |
| Стилі (токени теми з fallback) | `assets/scss/course.scss` → `assets/css/course.css` (у `.gitignore`, збірка `npm run build:css`) |
| Стара кнопка запису через `the_content` вимкнена — її замінює `course-join` | `includes/Frontend/CourseEnrollment.php` |

## Кнопка `course-join`

| Користувач | Кнопка |
|---|---|
| Гість | «Start course» → перший урок |
| Увійшов, не записаний | «Join course» (форма `EnrollmentService`) + «Preview first lesson» |
| Записаний | «Continue learning» → перший урок (прогрес — F6) |

## Відкрито

- Статична таблиця модулів у контенті курсу лишається (з хабу).
- Рядки UI англійською через i18n (локаль сайту `en_US`); переклад `uk` — (уточнити).
- Обмеження гостя, авторизація через 4wp-account, прогрес — F4–F6.
