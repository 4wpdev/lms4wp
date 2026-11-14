<?php
/**
 * Read model of a course: modules → lessons, lesson context (ADR 0004).
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Lesson;
use ForWP\LMS\PostTypes\Module;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseStructure
{
	/** @var array<int, array> */
	private static array $cache = [];

	/**
	 * Modules (lms_module posts) with their published lessons, in order (ADR 0008).
	 * Lessons without a module go to a trailing untitled group.
	 *
	 * @return array<int, array{id: int, key: string, number: int, title: string, goal: string, lessons: \WP_Post[]}>
	 */
	public static function modules(int $course_id): array
	{
		if (isset(self::$cache[$course_id])) {
			return self::$cache[$course_id];
		}

		$modules = [];
		foreach (Module::forCourse($course_id) as $position => $post) {
			$goal = trim((string) $post->post_excerpt);
			$modules[$post->ID] = [
				'id'      => (int) $post->ID,
				'key'     => (string) (get_post_meta($post->ID, Module::META_KEY, true) ?: $post->post_name),
				'number'  => $post->menu_order > 0 ? (int) $post->menu_order : $position + 1,
				'title'   => (string) $post->post_title,
				'goal'    => $goal,
				'lessons' => [],
			];
		}

		$lessons = get_posts([
			'post_type'              => Lesson::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => ['menu_order' => 'ASC', 'ID' => 'ASC'],
			'meta_key'               => '_lms4wp_course_id',
			'meta_value'             => $course_id,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
		]);

		foreach ($lessons as $lesson) {
			$module_id = Module::forLesson($lesson->ID);
			if (!isset($modules[$module_id])) {
				$module_id = 0;
				$modules[0] ??= ['id' => 0, 'key' => '', 'number' => 0, 'title' => '', 'goal' => '', 'lessons' => []];
			}
			$modules[$module_id]['lessons'][] = $lesson;
		}

		// Untitled group last.
		if (isset($modules[0])) {
			$rest = $modules[0];
			unset($modules[0]);
			$modules[0] = $rest;
		}

		return self::$cache[$course_id] = array_values($modules);
	}

	/**
	 * Module of a lesson within its course structure (null — none).
	 *
	 * @return array{id: int, key: string, number: int, title: string, goal: string, lessons: \WP_Post[]}|null
	 */
	public static function moduleForLesson(int $lesson_id): ?array
	{
		$course_id = self::courseIdForLesson($lesson_id);
		foreach (self::modules($course_id) as $module) {
			foreach ($module['lessons'] as $lesson) {
				if ($lesson->ID === $lesson_id) {
					return $module['id'] > 0 ? $module : null;
				}
			}
		}
		return null;
	}

	/**
	 * Reset the request cache (after import).
	 */
	public static function flush(): void
	{
		self::$cache = [];
	}

	/**
	 * @return \WP_Post[] All published lessons in course order.
	 */
	public static function lessons(int $course_id): array
	{
		$lessons = [];
		foreach (self::modules($course_id) as $module) {
			array_push($lessons, ...$module['lessons']);
		}
		return $lessons;
	}

	public static function courseIdForLesson(int $lesson_id): int
	{
		$course_id = (int) get_post_meta($lesson_id, '_lms4wp_course_id', true);
		return $course_id > 0 && get_post_type($course_id) === Course::POST_TYPE ? $course_id : 0;
	}

	/**
	 * Where a lesson sits in its course.
	 *
	 * @return array{course_id: int, module: ?array, index: int, total: int, prev: ?\WP_Post, next: ?\WP_Post}
	 */
	public static function lessonContext(int $lesson_id): array
	{
		$context = ['course_id' => 0, 'module' => null, 'index' => 0, 'total' => 0, 'prev' => null, 'next' => null];

		$course_id = self::courseIdForLesson($lesson_id);
		if ($course_id === 0) {
			return $context;
		}
		$context['course_id'] = $course_id;

		$lessons = self::lessons($course_id);
		$context['total'] = count($lessons);
		foreach ($lessons as $i => $lesson) {
			if ($lesson->ID !== $lesson_id) {
				continue;
			}
			$context['index'] = $i + 1;
			$context['prev'] = $lessons[$i - 1] ?? null;
			$context['next'] = $lessons[$i + 1] ?? null;
			break;
		}

		foreach (self::modules($course_id) as $module) {
			foreach ($module['lessons'] as $lesson) {
				if ($lesson->ID === $lesson_id) {
					$context['module'] = $module;
					break 2;
				}
			}
		}

		return $context;
	}
}
