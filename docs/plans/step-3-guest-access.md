# Крок 3. Доступ гостя (F3, F4)

Дата: 2026-11-14 · Статус: **виконано, на перевірці автора**

## Правила (ADR 0003)

| Хто | Бачить |
|---|---|
| Гість | перші N уроків курсу (`guest_lessons`) і уроки з позначкою «Free Lesson» |
| Увійшов | усі уроки (запис на курс — F5, фільтр `lms4wp_can_view_lesson`) |
| Редактор | усе |

N — front-matter курсу `guest_lessons` або поле «Guest access» у налаштуваннях курсу; за замовчуванням 1. Для `git-basics` — 3 (модуль 1).

## Закритий урок

- Контент (разом з терміналом і FAQ) замінено заглушкою: «This lesson is available after sign-in», кнопки «Sign in to continue» і «Back to course».
- REST `wp/v2/lms_lesson`: `content` / `excerpt` порожні, `protected: true`.
- `noindex, follow` (на локалі весь сайт `noindex` — перевірити на проді).
- Програма курсу: закриті уроки з замком.
- Кнопка курсу для гостя: «First N lessons are free without sign-in.»

## Файли

| Дія | Файл |
|---|---|
| створено | `includes/Content/LessonAccess.php` |
| змінено | `includes/Blocks/CourseBlocks.php`, `includes/PostTypes/Course.php` (поле Guest access), `includes/Import/CourseImporter.php` (`guest_lessons`), `includes/Core/Loader.php`, `lms4wp.php`, `assets/scss/course.scss` |
| хаб | `course-structure.md`: `guest_lessons: 3` |

## Відкрито (F5)

- Вхід — поки `wp_login_url()`; фільтр `lms4wp_login_url` для 4wp-account.
- Профіль терміналу в REST не закривається за правами: JS терміналу не передає nonce. Закритий урок термінал не рендерить.
