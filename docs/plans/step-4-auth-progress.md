# Крок 4. Авторизація, запис, прогрес (F5, F6)

Дата: 2026-11-14 · Статус: **виконано, на перевірці автора**

## Доступ (оновлено ADR 0003)

| Хто | Бачить |
|---|---|
| Гість | перші N уроків (`guest_lessons`) і «Free Lesson» |
| Увійшов, не записаний | те саме; на закритому уроці — «Join the course to continue» + «Join course» |
| Записаний | усі уроки курсу |
| Редактор | усе |

- Вхід — сторінка 4wp-account (`AccountMenu::get_login_url()`), повернення на урок; без 4wp-account — `wp_login_url()`. Фільтр `lms4wp_login_url`.
- Запис — `EnrollmentService::handleEnrollmentForm()` + `redirect_to` (назад на урок / курс). Платний курс (WooCommerce-продукт) — запис через форму заборонено, кнопка «Get the course» (ADR 0002).

## Прогрес

- Блок `lms4wp/lesson-complete` (шаблон уроку): «Mark as completed» → наступний урок; пройдений — «Lesson completed» + «Mark as not completed». Гість на відкритому уроці — «Sign in to save your progress».
- Таблиця `lms4wp_progress` (`status = completed`). Коли пройдено всі уроки — `lms4wp_enrollments.completed_at`, хук `lms4wp_course_completed`. Також `lms4wp_lesson_completed`.
- Сторінка курсу (записаний): «N of M lessons completed» + смуга, «Start / Continue learning» → перший непройдений, «Review course» після завершення.
- Програма: пройдені уроки — Lucide `circle-check`, закриті — `lock`.

## Перевірено (тимчасовий студент, видалено після тесту)

гість → урок 2.1 закритий → увійшов → «Join course» → назад на 2.1 з терміналом → «Mark as completed» → 2.2 → курс «1 of 26» → скасування → усі 26 → «Course completed», `completed_at`, хук.

## Файли

| Дія | Файл |
|---|---|
| створено | `includes/Content/Progress.php`, `includes/Frontend/Icons.php` |
| змінено | `includes/Content/LessonAccess.php`, `includes/Blocks/CourseBlocks.php`, `includes/Users/EnrollmentService.php`, `templates/single-lms_lesson.html`, `assets/scss/course.scss`, `assets/js/course-blocks-editor.js`, `includes/Core/Loader.php`, `lms4wp.php` |
