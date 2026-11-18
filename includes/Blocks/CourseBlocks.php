<?php
/**
 * Dynamic course / lesson blocks used by the course and lesson templates.
 *
 * - lms4wp/course-meta     — modules, lessons, level, free lessons (icons)
 * - lms4wp/course-media    — promo video / cover (featured image or placeholder) + "This course includes"
 * - lms4wp/course-join     — start / join / continue button
 * - lms4wp/course-outline  — modules → lessons (course page, lesson sidebar)
 * - lms4wp/lesson-context  — course › module, "lesson N of M"
 * - lms4wp/lesson-nav      — previous / next lesson
 * - lms4wp/lesson-terminal — chapter terminal for this lesson (4wp-advanced-code, ADR 0006)
 * - lms4wp/lesson-ide — IDE practice for this lesson from the chapter ide.json (4wp-advanced-code, ADR 0010)
 * - lms4wp/lesson-complete — "mark as complete" / completed state (F6)
 * - lms4wp/module-context  — module page: course › "Module N of M"
 * - lms4wp/module-lessons  — module page: lessons of the module with progress
 * - lms4wp/module-media    — module page aside: short video, YouTube, continue button, other modules
 *
 * @package ForWP\LMS\Blocks
 */

namespace ForWP\LMS\Blocks;

use ForWP\LMS\Content\CourseStructure;
use ForWP\LMS\Content\LessonAccess;
use ForWP\LMS\Content\Progress;
use ForWP\LMS\Content\PromoVideo;
use ForWP\LMS\Frontend\Breadcrumbs;
use ForWP\LMS\Frontend\Icons;
use ForWP\LMS\Integrations\AdvancedCodeIde;
use ForWP\LMS\Integrations\AdvancedCodeTerminal;
use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Lesson;
use ForWP\LMS\PostTypes\Module;
use ForWP\LMS\Users\EnrollmentService;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseBlocks
{
	const STYLE  = 'lms4wp-course';
	const EDITOR = 'lms4wp-course-blocks-editor';

	public static function init(): void
	{
		add_action('init', [self::class, 'register'], 20);
	}

	public static function register(): void
	{
		$css = 'assets/css/course.css';
		wp_register_style(
			self::STYLE,
			LMS4WP_URL . $css,
			[],
			file_exists(LMS4WP_PATH . $css) ? (string) filemtime(LMS4WP_PATH . $css) : LMS4WP_VERSION
		);

		$js = 'assets/js/course-blocks-editor.js';
		wp_register_script(
			self::EDITOR,
			LMS4WP_URL . $js,
			['wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n'],
			file_exists(LMS4WP_PATH . $js) ? (string) filemtime(LMS4WP_PATH . $js) : LMS4WP_VERSION,
			true
		);

		$blocks = [
			'course-meta'    => [__('Course Meta', 'lms4wp'), [], 'renderCourseMeta'],
			'course-media'   => [__('Course Media', 'lms4wp'), [], 'renderCourseMedia'],
			'course-join'    => [__('Course Join Button', 'lms4wp'), [], 'renderCourseJoin'],
			'course-outline' => [
				__('Course Outline', 'lms4wp'),
				[
					'heading' => ['type' => 'string', 'default' => ''],
					'compact' => ['type' => 'boolean', 'default' => false],
				],
				'renderCourseOutline',
			],
			'lesson-context' => [__('Lesson Context', 'lms4wp'), [], 'renderLessonContext'],
			'lesson-nav'     => [__('Lesson Navigation', 'lms4wp'), [], 'renderLessonNav'],
			'lesson-complete' => [__('Lesson Complete', 'lms4wp'), [], 'renderLessonComplete'],
			'lesson-terminal' => [
				__('Lesson Terminal', 'lms4wp'),
				['welcome' => ['type' => 'string', 'default' => '']],
				'renderLessonTerminal',
			],
			'lesson-ide' => [__('Lesson IDE Practice', 'lms4wp'), [], 'renderLessonIde'],
			'module-context' => [__('Module Context', 'lms4wp'), [], 'renderModuleContext'],
			'module-lessons' => [
				__('Module Lessons', 'lms4wp'),
				['heading' => ['type' => 'string', 'default' => '']],
				'renderModuleLessons',
			],
			'module-media'   => [__('Module Media', 'lms4wp'), [], 'renderModuleMedia'],
			'lesson-media'   => [__('Lesson Media', 'lms4wp'), [], 'renderLessonMedia'],
		];

		foreach ($blocks as $name => [$title, $attributes, $callback]) {
			register_block_type('lms4wp/' . $name, [
				'api_version'     => 3,
				'title'           => $title,
				'category'        => 'widgets',
				'icon'            => 'welcome-learn-more',
				'attributes'      => $attributes,
				'supports'        => ['html' => false, 'align' => ['wide', 'full']],
				'uses_context'    => ['postId', 'postType'],
				'style_handles'   => [self::STYLE],
				'editor_script_handles' => [self::EDITOR],
				'render_callback' => [self::class, $callback],
			]);
		}
	}

	/**
	 * Course ID for the current context: the course itself or a lesson's course.
	 */
	private static function courseId(\WP_Block $block): int
	{
		$post_id = self::postId($block);
		$type = get_post_type($post_id);
		if ($type === Course::POST_TYPE) {
			return $post_id;
		}
		if ($type === Lesson::POST_TYPE) {
			return CourseStructure::courseIdForLesson($post_id);
		}
		if ($type === Module::POST_TYPE) {
			$parent = (int) wp_get_post_parent_id($post_id);
			return $parent > 0 && get_post_type($parent) === Course::POST_TYPE ? $parent : 0;
		}
		return 0;
	}

	private static function postId(\WP_Block $block): int
	{
		$post_id = (int) ($block->context['postId'] ?? 0);
		$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
		if ($post_id > 0 && in_array(get_post_type($post_id), [Course::POST_TYPE, Lesson::POST_TYPE, Module::POST_TYPE], true)) {
			return $post_id;
		}

		// Site Editor / template preview: no real course or lesson — show a real sample instead of an empty block.
		if (self::isEditorPreview()) {
			$type = (string) ($block->context['postType'] ?? '');
			if ($type === Course::POST_TYPE) {
				return self::sampleCourseId();
			}
			return $type === Module::POST_TYPE ? self::sampleModuleId() : self::sampleLessonId();
		}

		return $post_id;
	}

	private static function isEditorPreview(): bool
	{
		$rest = function_exists('wp_is_serving_rest_request') ? wp_is_serving_rest_request() : (defined('REST_REQUEST') && REST_REQUEST);
		return $rest && current_user_can('edit_posts');
	}

	/**
	 * Latest published course that has lessons (editor preview).
	 */
	private static function sampleCourseId(): int
	{
		static $id = null;
		if ($id !== null) {
			return $id;
		}
		$id = 0;
		$courses = get_posts([
			'post_type'      => Course::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]);
		foreach ($courses as $course_id) {
			if (CourseStructure::lessons((int) $course_id)) {
				$id = (int) $course_id;
				break;
			}
		}
		return $id;
	}

	/**
	 * A lesson of the sample course with both neighbours (editor preview).
	 */
	private static function sampleLessonId(): int
	{
		$lessons = CourseStructure::lessons(self::sampleCourseId());
		$lesson = $lessons[1] ?? $lessons[0] ?? null;
		return $lesson ? (int) $lesson->ID : 0;
	}

	/**
	 * First module with lessons of the sample course (editor preview).
	 */
	private static function sampleModuleId(): int
	{
		foreach (CourseStructure::modules(self::sampleCourseId()) as $module) {
			if ($module['id'] > 0 && $module['lessons']) {
				return $module['id'];
			}
		}
		return 0;
	}

	/**
	 * Module page context: course, the module within the course structure, its neighbours.
	 *
	 * @return array{course_id: int, module: ?array, modules: array, index: int}
	 */
	private static function moduleContext(\WP_Block $block): array
	{
		$context = ['course_id' => 0, 'module' => null, 'modules' => [], 'index' => -1];
		$module_id = self::postId($block);
		if (get_post_type($module_id) !== Module::POST_TYPE) {
			return $context;
		}
		$course_id = self::courseId($block);
		if ($course_id === 0) {
			return $context;
		}
		$modules = array_values(array_filter(CourseStructure::modules($course_id), static fn(array $m): bool => $m['id'] > 0));
		foreach ($modules as $i => $module) {
			if ($module['id'] === $module_id) {
				$context['module'] = $module;
				$context['index'] = $i;
				break;
			}
		}
		$context['course_id'] = $course_id;
		$context['modules'] = $modules;
		return $context;
	}

	private static function wrapper(string $class, string $html, string $tag = 'div'): string
	{
		return sprintf('<%1$s %2$s>%3$s</%1$s>', $tag, get_block_wrapper_attributes(['class' => $class]), $html);
	}

	private static function button(string $label, string $url, string $style = ''): string
	{
		$class = 'wp-block-button' . ($style !== '' ? ' is-style-' . $style : '');
		return sprintf(
			'<div class="%s"><a class="wp-block-button__link wp-element-button" href="%s">%s</a></div>',
			esc_attr($class),
			esc_url($url),
			esc_html($label)
		);
	}

	public static function renderCourseMeta(array $attributes, string $content, \WP_Block $block): string
	{
		$course_id = self::courseId($block);
		if ($course_id === 0) {
			return '';
		}

		$modules = count(array_filter(CourseStructure::modules($course_id), static fn(array $m): bool => $m['key'] !== ''));
		$lessons = count(CourseStructure::lessons($course_id));
		$levels = wp_get_post_terms($course_id, 'lms_level', ['fields' => 'names']);
		$free = min(LessonAccess::guestLessons($course_id), $lessons);

		$items = [];
		if ($modules) {
			/* translators: %d: number of modules */
			$items[] = ['layers', sprintf(_n('%d module', '%d modules', $modules, 'lms4wp'), $modules)];
		}
		/* translators: %d: number of lessons */
		$items[] = ['book-open', sprintf(_n('%d lesson', '%d lessons', $lessons, 'lms4wp'), $lessons)];
		if (!is_wp_error($levels) && $levels) {
			$items[] = ['signal', implode(', ', $levels)];
		}
		if ($free > 0) {
			/* translators: %d: number of lessons open without sign-in */
			$items[] = ['lock-open', sprintf(_n('%d free lesson', '%d free lessons', $free, 'lms4wp'), $free)];
		}

		$html = '';
		foreach ($items as [$icon, $label]) {
			$html .= '<span class="lms4wp-course-meta__item">' . Icons::svg($icon, 16) . '<span>' . esc_html($label) . '</span></span>';
		}
		return self::wrapper('lms4wp-course-meta', $html);
	}

	public static function renderCourseMedia(array $attributes, string $content, \WP_Block $block): string
	{
		$course_id = self::courseId($block);
		if ($course_id === 0) {
			return '';
		}

		// Promo video (poster = featured image) → featured image → placeholder.
		$media = PromoVideo::renderMedia($course_id, 'lms4wp-course-media__image');
		if ($media === '') {
			$media = '<div class="lms4wp-course-media__placeholder">' . Icons::svg('graduation-cap', 56) . '<span>' . esc_html(get_the_title($course_id)) . '</span></div>';
		}
		$youtube = PromoVideo::renderYoutube($course_id);

		// What the course includes.
		$lessons = count(CourseStructure::lessons($course_id));
		$modules = count(array_filter(CourseStructure::modules($course_id), static fn(array $m): bool => $m['key'] !== ''));
		$terminals = array_filter(
			\ForWP\LMS\PostTypes\Module::forCourse($course_id),
			static fn(\WP_Post $m): bool => is_array(get_post_meta($m->ID, '_lms4wp_terminal', true))
		);
		$free = min(LessonAccess::guestLessons($course_id), $lessons);

		$includes = [];
		/* translators: 1: lessons, 2: modules */
		$includes[] = ['book-open', sprintf(__('%1$d lessons in %2$d modules', 'lms4wp'), $lessons, $modules)];
		if (is_array($terminals) && $terminals) {
			$includes[] = ['terminal', __('Practice terminal in lessons', 'lms4wp')];
		}
		$includes[] = ['circle-help', __('Interview questions with answers', 'lms4wp')];
		$includes[] = ['circle-check', __('Progress tracking', 'lms4wp')];
		if ($free > 0) {
			/* translators: %d: number of lessons open without sign-in */
			$includes[] = ['lock-open', sprintf(_n('First %d lesson without sign-in', 'First %d lessons without sign-in', $free, 'lms4wp'), $free)];
		}

		$list = '';
		foreach ($includes as [$icon, $label]) {
			$list .= '<li>' . Icons::svg($icon, 16) . '<span>' . esc_html($label) . '</span></li>';
		}

		// Card actions: guest — sign in / create account (back to the course); signed in — join / progress.
		if (!is_user_logged_in()) {
			$actions = '<p class="lms4wp-course-media__hint">' . esc_html__('Sign in or create a free account to save your progress.', 'lms4wp') . '</p>'
				. '<div class="wp-block-buttons">' . LessonAccess::authButtons($course_id) . '</div>';
		} else {
			$actions = self::renderCourseJoin([], '', $block);
		}

		return self::wrapper(
			'lms4wp-course-media',
			'<div ' . PromoVideo::frameAttributes($course_id) . '>' . $media . '</div>'
			. '<div class="lms4wp-course-media__body">'
			. $youtube
			. '<p class="lms4wp-course-media__title">' . esc_html__('This course includes', 'lms4wp') . '</p>'
			. '<ul class="lms4wp-course-media__includes">' . $list . '</ul>'
			. '<div class="lms4wp-course-media__actions">' . $actions . '</div></div>',
			'aside'
		);
	}

	public static function renderCourseJoin(array $attributes, string $content, \WP_Block $block): string
	{
		$course_id = self::courseId($block);
		$lessons = $course_id ? CourseStructure::lessons($course_id) : [];
		if (!$lessons) {
			return '';
		}
		$first_url = (string) get_permalink($lessons[0]);

		// Guest: start without sign-in (ADR 0003).
		if (!is_user_logged_in()) {
			$html = '<div class="wp-block-buttons">' . self::button(__('Start course', 'lms4wp'), $first_url) . '</div>';
			$free = min(LessonAccess::guestLessons($course_id), count($lessons));
			if ($free > 0) {
				$html .= '<p class="lms4wp-course-join__note">' . esc_html(sprintf(
					/* translators: %d: number of lessons open without sign-in */
					_n('First %d lesson is free without sign-in.', 'First %d lessons are free without sign-in.', $free, 'lms4wp'),
					$free
				)) . '</p>';
			}
			return self::wrapper('lms4wp-course-join', $html);
		}

		$user_id = get_current_user_id();
		$enrolled = class_exists(EnrollmentService::class) && EnrollmentService::isEnrolled($user_id, $course_id);

		// Enrolled: progress + continue with the next lesson (F6).
		if ($enrolled) {
			$summary = Progress::summary($user_id, $course_id);
			$done = $summary['next'] === null;
			$html = self::progressBar($summary['completed'], $summary['total'], $summary['percent'])
				. '<div class="wp-block-buttons">'
				. self::button(
					$done ? __('Review course', 'lms4wp') : ($summary['completed'] > 0 ? __('Continue learning', 'lms4wp') : __('Start course', 'lms4wp')),
					$done ? $first_url : (string) get_permalink($summary['next'])
				)
				. '</div>'
				. '<p class="lms4wp-course-join__note">' . esc_html($done ? __('Course completed. Well done!', 'lms4wp') : __('You are enrolled in this course.', 'lms4wp')) . '</p>';
			return self::wrapper('lms4wp-course-join is-enrolled' . ($done ? ' is-completed' : ''), $html);
		}

		// Paid course: buy (ADR 0002).
		$product_id = LessonAccess::productId($course_id);
		if ($product_id > 0) {
			$html = '<div class="wp-block-buttons">'
				. self::button(__('Get the course', 'lms4wp'), (string) get_permalink($product_id))
				. self::button(__('Preview first lesson', 'lms4wp'), $first_url, 'outline')
				. '</div>';
			return self::wrapper('lms4wp-course-join', $html);
		}

		// Signed in, not enrolled: EnrollmentService::handleEnrollmentForm().
		$html = '<div class="wp-block-buttons">'
			. LessonAccess::joinForm($course_id, (string) get_permalink($course_id), __('Join course', 'lms4wp'))
			. self::button(__('Preview first lesson', 'lms4wp'), $first_url, 'outline')
			. '</div>';
		return self::wrapper('lms4wp-course-join', $html);
	}

	public static function renderCourseOutline(array $attributes, string $content, \WP_Block $block): string
	{
		$course_id = self::courseId($block);
		if ($course_id === 0) {
			return '';
		}
		$modules = CourseStructure::modules($course_id);
		if (!$modules) {
			return '';
		}

		$compact = !empty($attributes['compact']);
		$completed_ids = Progress::completedLessonIds(get_current_user_id(), $course_id);
		$current_id = get_post_type(self::postId($block)) === Lesson::POST_TYPE ? self::postId($block) : 0;
		$heading = (string) ($attributes['heading'] ?? '');
		if ($heading === '') {
			$heading = $compact ? get_the_title($course_id) : __('Course program', 'lms4wp');
		}

		$html = $compact
			? sprintf('<p class="lms4wp-outline__heading"><a href="%s">%s</a></p>', esc_url((string) get_permalink($course_id)), esc_html($heading))
			: '<h2 class="lms4wp-outline__heading">' . esc_html($heading) . '</h2>';

		// Lesson sidebar: the learner's progress right under the course title.
		$user_id = get_current_user_id();
		if ($compact && $user_id > 0) {
			$summary = Progress::summary($user_id, $course_id);
			$html .= self::progressBar($summary['completed'], $summary['total'], $summary['percent']);
		}

		$html .= '<ol class="lms4wp-outline__modules">';
		foreach ($modules as $module) {
			$ids = wp_list_pluck($module['lessons'], 'ID');
			$is_current = $current_id && in_array($current_id, $ids, true);
			$open = !$compact || $is_current;
			$count = count($module['lessons']);

			$html .= '<li class="lms4wp-module' . ($is_current ? ' is-current' : '') . '">';
			$html .= '<details' . ($open ? ' open' : '') . '><summary class="lms4wp-module__summary">';
			if ($module['number'] > 0) {
				/* translators: %d: module number */
				$label = esc_html(sprintf(__('Module %d', 'lms4wp'), $module['number']));
				// Module page link (the rest of the summary still toggles the lessons).
				$html .= $module['id'] > 0
					? '<a class="lms4wp-module__number" href="' . esc_url((string) get_permalink($module['id'])) . '">' . $label . '</a>'
					: '<span class="lms4wp-module__number">' . $label . '</span>';
			}
			$html .= '<span class="lms4wp-module__title">' . esc_html($module['title'] !== '' ? $module['title'] : __('Lessons', 'lms4wp')) . '</span>';
			$done = count(array_intersect($ids, $completed_ids));
			$count_label = $user_id > 0 && $done > 0
				/* translators: 1: completed lessons, 2: lessons in the module */
				? sprintf(__('%1$d/%2$d', 'lms4wp'), $done, $count)
				/* translators: %d: number of lessons */
				: sprintf(_n('%d lesson', '%d lessons', $count, 'lms4wp'), $count);
			$html .= '<span class="lms4wp-module__count' . ($done === $count && $count > 0 ? ' is-done' : '') . '">' . esc_html($count_label) . '</span>';
			$html .= Icons::svg('chevron-down', 18, 'lms4wp-module__chevron');
			$html .= '</summary>';

			if (!$compact && $module['goal'] !== '') {
				$html .= '<p class="lms4wp-module__goal">' . esc_html($module['goal']) . '</p>';
			}

			$html .= '<ol class="lms4wp-module__lessons">';
			foreach ($module['lessons'] as $lesson) {
				$current = $lesson->ID === $current_id;
				$locked = !LessonAccess::canView($lesson->ID);
				$completed = in_array($lesson->ID, $completed_ids, true);
				$html .= sprintf(
					'<li class="lms4wp-lesson-item%s%s%s"><a href="%s"%s>%s%s</a></li>',
					$current ? ' is-current' : '',
					$locked ? ' is-locked' : '',
					$completed ? ' is-completed' : '',
					esc_url((string) get_permalink($lesson)),
					$current ? ' aria-current="page"' : '',
					esc_html(get_the_title($lesson)),
					$locked
						? Icons::svg('lock', 14, 'lms4wp-lesson-item__lock') . '<span class="screen-reader-text">' . esc_html__('(locked)', 'lms4wp') . '</span>'
						: ($completed ? Icons::svg('circle-check', 16, 'lms4wp-lesson-item__done') . '<span class="screen-reader-text">' . esc_html__('(completed)', 'lms4wp') . '</span>' : '')
				);
			}
			$html .= '</ol></details></li>';
		}
		$html .= '</ol>';

		return self::wrapper('lms4wp-outline' . ($compact ? ' is-compact' : ''), $html, 'nav');
	}

	private static function progressBar(int $completed, int $total, int $percent): string
	{
		return '<div class="lms4wp-progress">'
			. '<div class="lms4wp-progress__label">' . esc_html(sprintf(
				/* translators: 1: completed lessons, 2: total lessons */
				__('%1$d of %2$d lessons completed', 'lms4wp'),
				$completed,
				$total
			)) . '<span class="lms4wp-progress__percent">' . esc_html($percent . '%') . '</span></div>'
			. '<div class="lms4wp-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr((string) $percent) . '">'
			. '<span class="lms4wp-progress__bar" style="width:' . esc_attr((string) $percent) . '%"></span></div>'
			. '</div>';
	}

	public static function renderLessonComplete(array $attributes, string $content, \WP_Block $block): string
	{
		$lesson_id = self::postId($block);
		if (get_post_type($lesson_id) !== Lesson::POST_TYPE || CourseStructure::courseIdForLesson($lesson_id) === 0) {
			return '';
		}

		// Guest on an open lesson: sign in / create account to save progress (back to this lesson).
		if (!is_user_logged_in()) {
			if (!LessonAccess::canView($lesson_id)) {
				return '';
			}
			$html = '<div class="lms4wp-auth">'
				. '<div class="lms4wp-auth__text"><p class="lms4wp-auth__title">' . esc_html__('Save your progress', 'lms4wp') . '</p>'
				. '<p class="lms4wp-auth__desc">' . esc_html__('Sign in or create a free account to mark lessons as completed and continue the whole course. You will come back to this lesson.', 'lms4wp') . '</p></div>'
				. '<div class="wp-block-buttons">' . LessonAccess::authButtons($lesson_id) . '</div>'
				. '</div>';
			return self::wrapper('lms4wp-lesson-complete', $html);
		}

		$user_id = get_current_user_id();
		if (!LessonAccess::canView($lesson_id, $user_id)) {
			return '';
		}

		$nonce = wp_nonce_field('lms4wp_progress_' . $lesson_id, 'lms4wp_progress_nonce', true, false);

		if (Progress::isCompleted($user_id, $lesson_id)) {
			$html = '<div class="lms4wp-lesson-complete__done">' . Icons::svg('circle-check', 20)
				. '<span>' . esc_html__('Lesson completed', 'lms4wp') . '</span></div>'
				. '<form method="post" class="lms4wp-lesson-complete__undo">' . $nonce
				. '<button type="submit" name="' . esc_attr(Progress::ACTION_UNCOMPLETE) . '" value="1" class="lms4wp-link-button">'
				. esc_html__('Mark as not completed', 'lms4wp') . '</button></form>';
			return self::wrapper('lms4wp-lesson-complete is-completed', $html);
		}

		$html = '<form method="post" class="wp-block-buttons">' . $nonce
			. '<div class="wp-block-button"><button type="submit" name="' . esc_attr(Progress::ACTION_COMPLETE) . '" value="1" class="wp-block-button__link wp-element-button">'
			. Icons::svg('check', 18) . '<span>' . esc_html__('Mark as completed', 'lms4wp') . '</span></button></div>'
			. '</form>';
		return self::wrapper('lms4wp-lesson-complete', $html);
	}

	public static function renderLessonContext(array $attributes, string $content, \WP_Block $block): string
	{
		$lesson_id = self::postId($block);
		$context = CourseStructure::lessonContext($lesson_id);
		if ($context['course_id'] === 0) {
			return '';
		}

		// LMS › Course › Module (same trail as the BreadcrumbList schema).
		$crumbs = Breadcrumbs::rootLink() . sprintf(
			'<a class="lms4wp-lesson-context__course" href="%s">%s</a>',
			esc_url((string) get_permalink($context['course_id'])),
			esc_html(get_the_title($context['course_id']))
		);
		if ($context['module'] && $context['module']['title'] !== '') {
			$label = Breadcrumbs::moduleLabel($context['module']);
			$module_id = (int) $context['module']['id'];
			$crumbs .= Icons::svg('chevron-right', 14, 'lms4wp-lesson-context__sep')
				. ($module_id > 0 && get_post_status($module_id) === 'publish'
					? '<a class="lms4wp-lesson-context__module" href="' . esc_url((string) get_permalink($module_id)) . '">' . esc_html($label) . '</a>'
					: '<span class="lms4wp-lesson-context__module">' . esc_html($label) . '</span>');
		}

		// Position + quick previous / next.
		$pager = '';
		if ($context['index'] > 0) {
			$arrow = static function (?\WP_Post $target, string $icon, string $label): string {
				return $target
					? sprintf('<a class="lms4wp-lesson-context__arrow" href="%s" aria-label="%s" title="%s">%s</a>', esc_url((string) get_permalink($target)), esc_attr($label), esc_attr(get_the_title($target)), Icons::svg($icon, 18))
					: '<span class="lms4wp-lesson-context__arrow is-disabled" aria-hidden="true">' . Icons::svg($icon, 18) . '</span>';
			};
			$percent = $context['total'] > 0 ? (int) round($context['index'] * 100 / $context['total']) : 0;
			$pager = '<div class="lms4wp-lesson-context__pager">'
				. $arrow($context['prev'], 'chevron-left', __('Previous lesson', 'lms4wp'))
				/* translators: 1: lesson position, 2: total lessons */
				. '<span class="lms4wp-lesson-context__progress">' . esc_html(sprintf(__('Lesson %1$d of %2$d', 'lms4wp'), $context['index'], $context['total'])) . '</span>'
				. $arrow($context['next'], 'chevron-right', __('Next lesson', 'lms4wp'))
				. '</div>'
				. '<div class="lms4wp-lesson-context__track" aria-hidden="true"><span style="width:' . esc_attr((string) $percent) . '%"></span></div>';
		}

		return self::wrapper('lms4wp-lesson-context', '<div class="lms4wp-lesson-context__crumbs">' . $crumbs . '</div>' . $pager, 'nav');
	}

	/**
	 * Lesson sidebar media: the lesson's featured image (zoom → lightbox, caption) and the
	 * video of its module (YouTube or file, same frame as on the module page).
	 */
	public static function renderLessonMedia(array $attributes, string $content, \WP_Block $block): string
	{
		$lesson_id = self::postId($block);
		if ($lesson_id === 0) {
			return '';
		}
		// Sidebar image: not the LCP of a lesson — lazy, sized for the sidebar.
		$html = PromoVideo::renderImage($lesson_id, 'lms4wp-lesson-media__img', 'medium_large', true, [
			'loading'       => 'lazy',
			'fetchpriority' => 'low',
			'sizes'         => '(max-width: 781px) 100vw, 400px',
		]);

		$context = CourseStructure::lessonContext($lesson_id);
		$module_id = $context['module'] ? (int) $context['module']['id'] : 0;
		if ($module_id > 0 && PromoVideo::hasVideo($module_id)) {
			$html .= '<div class="lms4wp-lesson-media__video">'
				. '<p class="lms4wp-lesson-media__label">' . esc_html(sprintf(
					/* translators: %s: module label, e.g. "Module 2. Daily Workflow" */
					__('Video · %s', 'lms4wp'),
					Breadcrumbs::moduleLabel($context['module'])
				)) . '</p>'
				. '<div ' . PromoVideo::frameAttributes($module_id) . '>' . PromoVideo::renderMedia($module_id) . '</div>'
				. PromoVideo::renderYoutube($module_id)
				. '</div>';
		}

		return $html !== '' ? self::wrapper('lms4wp-lesson-media', $html, 'aside') : '';
	}

	public static function renderLessonTerminal(array $attributes, string $content, \WP_Block $block): string
	{
		$html = AdvancedCodeTerminal::render(self::postId($block), (string) ($attributes['welcome'] ?? ''));
		return $html !== '' ? self::wrapper('lms4wp-lesson-terminal', $html) : '';
	}

	public static function renderLessonIde(array $attributes, string $content, \WP_Block $block): string
	{
		$html = AdvancedCodeIde::render(self::postId($block));
		// forwp-ac-wide-host: 4wp-advanced-code adds the wide / compact toggle.
		return $html !== '' ? self::wrapper('lms4wp-lesson-ide forwp-ac-wide-host', $html) : '';
	}

	public static function renderLessonNav(array $attributes, string $content, \WP_Block $block): string
	{
		$context = CourseStructure::lessonContext(self::postId($block));
		if ($context['course_id'] === 0) {
			return '';
		}

		$link = static function (string $class, string $label, string $title, string $url): string {
			return sprintf(
				'<a class="lms4wp-lesson-nav__link %s" href="%s"><span class="lms4wp-lesson-nav__label">%s</span><span class="lms4wp-lesson-nav__title">%s</span></a>',
				esc_attr($class),
				esc_url($url),
				$class === 'is-prev'
					? Icons::svg('arrow-left', 14) . esc_html($label)
					: esc_html($label) . Icons::svg('arrow-right', 14),
				esc_html($title)
			);
		};

		$html = $context['prev']
			? $link('is-prev', __('Previous lesson', 'lms4wp'), get_the_title($context['prev']), (string) get_permalink($context['prev']))
			: '<span class="lms4wp-lesson-nav__link is-empty"></span>';

		$html .= $context['next']
			? $link('is-next', __('Next lesson', 'lms4wp'), get_the_title($context['next']), (string) get_permalink($context['next']))
			: $link('is-next', __('Course complete', 'lms4wp'), __('Back to course', 'lms4wp'), (string) get_permalink($context['course_id']));

		return self::wrapper('lms4wp-lesson-nav', $html, 'nav');
	}

	public static function renderModuleContext(array $attributes, string $content, \WP_Block $block): string
	{
		$context = self::moduleContext($block);
		if ($context['module'] === null) {
			return '';
		}
		$html = Breadcrumbs::rootLink() . sprintf(
			'<a class="lms4wp-lesson-context__course" href="%s">%s</a>',
			esc_url((string) get_permalink($context['course_id'])),
			esc_html(get_the_title($context['course_id']))
		)
			. Icons::svg('chevron-right', 14, 'lms4wp-lesson-context__sep')
			. '<span class="lms4wp-lesson-context__module">' . esc_html(sprintf(
				/* translators: 1: module position, 2: total modules */
				__('Module %1$d of %2$d', 'lms4wp'),
				$context['index'] + 1,
				count($context['modules'])
			)) . '</span>';
		return self::wrapper('lms4wp-lesson-context lms4wp-module-context', '<div class="lms4wp-lesson-context__crumbs">' . $html . '</div>', 'nav');
	}

	public static function renderModuleLessons(array $attributes, string $content, \WP_Block $block): string
	{
		$context = self::moduleContext($block);
		if ($context['module'] === null || !$context['module']['lessons']) {
			return '';
		}
		$lessons = $context['module']['lessons'];
		$completed_ids = Progress::completedLessonIds(get_current_user_id(), $context['course_id']);
		$heading = (string) ($attributes['heading'] ?? '');
		if ($heading === '') {
			$heading = __('Lessons in this module', 'lms4wp');
		}

		$html = '<h2 class="lms4wp-module-lessons__heading">' . esc_html($heading)
			/* translators: %d: number of lessons */
			. ' <span class="lms4wp-module-lessons__count">' . esc_html(sprintf(_n('%d lesson', '%d lessons', count($lessons), 'lms4wp'), count($lessons))) . '</span></h2>'
			. '<ol class="lms4wp-module-lessons__list">';
		foreach ($lessons as $i => $lesson) {
			$locked = !LessonAccess::canView($lesson->ID);
			$completed = in_array($lesson->ID, $completed_ids, true);
			$excerpt = $locked ? '' : wp_trim_words(get_the_excerpt($lesson), 22);
			$html .= sprintf(
				'<li class="lms4wp-module-lessons__item%s%s"><a href="%s"><span class="lms4wp-module-lessons__number">%d</span><span class="lms4wp-module-lessons__text"><span class="lms4wp-module-lessons__title">%s</span>%s</span>%s</a></li>',
				$locked ? ' is-locked' : '',
				$completed ? ' is-completed' : '',
				esc_url((string) get_permalink($lesson)),
				$i + 1,
				esc_html(get_the_title($lesson)),
				$excerpt !== '' ? '<span class="lms4wp-module-lessons__excerpt">' . esc_html($excerpt) . '</span>' : '',
				$locked
					? Icons::svg('lock', 16, 'lms4wp-lesson-item__lock') . '<span class="screen-reader-text">' . esc_html__('(locked)', 'lms4wp') . '</span>'
					: ($completed ? Icons::svg('circle-check', 18, 'lms4wp-lesson-item__done') . '<span class="screen-reader-text">' . esc_html__('(completed)', 'lms4wp') . '</span>' : Icons::svg('chevron-right', 18, 'lms4wp-module-lessons__arrow'))
			);
		}
		$html .= '</ol>';
		return self::wrapper('lms4wp-module-lessons', $html, 'section');
	}

	public static function renderModuleMedia(array $attributes, string $content, \WP_Block $block): string
	{
		$context = self::moduleContext($block);
		$module = $context['module'];
		if ($module === null) {
			return '';
		}
		$module_id = $module['id'];

		// Short video (poster = featured image) → featured image → placeholder.
		$media = PromoVideo::renderMedia($module_id, 'lms4wp-course-media__image');
		if ($media === '') {
			$media = '<div class="lms4wp-course-media__placeholder">' . Icons::svg('layers', 56) . '<span>' . esc_html($module['title']) . '</span></div>';
		}
		$html = '<div ' . PromoVideo::frameAttributes($module_id) . '>' . $media . '</div>'
			. '<div class="lms4wp-course-media__body">'
			. PromoVideo::renderYoutube($module_id)
			. self::moduleContinue($context)
			. self::moduleList($context)
			. '</div>';

		return self::wrapper('lms4wp-course-media lms4wp-module-media', $html, 'aside');
	}

	/**
	 * Continue button of a module: first not completed lesson; module done → next module.
	 */
	private static function moduleContinue(array $context): string
	{
		$lessons = $context['module']['lessons'];
		if (!$lessons) {
			return '';
		}
		$user_id = get_current_user_id();
		$completed_ids = $user_id > 0 ? Progress::completedLessonIds($user_id, $context['course_id']) : [];
		$ids = wp_list_pluck($lessons, 'ID');
		$done = count(array_intersect($ids, $completed_ids));

		$next = null;
		foreach ($lessons as $lesson) {
			if (!in_array($lesson->ID, $completed_ids, true)) {
				$next = $lesson;
				break;
			}
		}

		$html = '';
		if ($user_id > 0 && $done > 0) {
			$html .= self::progressBar($done, count($lessons), (int) round($done * 100 / count($lessons)));
		}

		$next_module = $context['modules'][$context['index'] + 1] ?? null;
		if ($next !== null) {
			$label = $done > 0 ? __('Continue module', 'lms4wp') : __('Start module', 'lms4wp');
			$url = (string) get_permalink($next);
		} elseif ($next_module !== null) {
			$label = __('Next module', 'lms4wp');
			$url = (string) get_permalink($next_module['id']);
		} else {
			$label = __('Review module', 'lms4wp');
			$url = (string) get_permalink($lessons[0]);
		}

		$html .= '<div class="wp-block-buttons">' . self::button($label, $url) . '</div>';
		if ($next !== null) {
			$html .= '<p class="lms4wp-course-join__note">' . esc_html(get_the_title($next)) . '</p>';
		}
		return '<div class="lms4wp-course-media__actions lms4wp-module-media__continue">' . $html . '</div>';
	}

	/**
	 * Other modules of the course (current one highlighted).
	 */
	private static function moduleList(array $context): string
	{
		if (count($context['modules']) < 2) {
			return '';
		}
		$completed_ids = Progress::completedLessonIds(get_current_user_id(), $context['course_id']);
		$html = '<p class="lms4wp-course-media__title">' . esc_html__('Course modules', 'lms4wp') . '</p><ol class="lms4wp-module-media__modules">';
		foreach ($context['modules'] as $i => $module) {
			$current = $module['id'] === $context['module']['id'];
			$ids = wp_list_pluck($module['lessons'], 'ID');
			$is_done = $ids && !array_diff($ids, $completed_ids);
			$html .= sprintf(
				'<li class="lms4wp-module-media__module%s%s"><a href="%s"%s><span class="lms4wp-module-media__number">%d</span><span class="lms4wp-module-media__name">%s</span>%s</a></li>',
				$current ? ' is-current' : '',
				$is_done ? ' is-completed' : '',
				esc_url((string) get_permalink($module['id'])),
				$current ? ' aria-current="page"' : '',
				$i + 1,
				esc_html($module['title']),
				$is_done ? Icons::svg('circle-check', 16, 'lms4wp-lesson-item__done') : ''
			);
		}
		return $html . '</ol>';
	}
}
