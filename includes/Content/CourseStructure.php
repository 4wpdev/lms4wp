<?php
/**
 * Read model of a course: modules → lessons, lesson context (ADR 0004).
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Lesson;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseStructure
{
	/** @var array<int, array> */
	private static array $cache = [];

	/**
	 * Modules with their published lessons, in order.
	 * Lessons without a known module go to a trailing untitled group.
	 *
	 * @return array<int, array{key: string, number: int, title: string, goal: string, lessons: \WP_Post[]}>
	 */
	public static function modules(int $course_id): array
	{
		if (isset(self::$cache[$course_id])) {
			return self::$cache[$course_id];
		}

		$meta = get_post_meta($course_id, '_lms4wp_modules', true);
		$modules = [];
		foreach (is_array($meta) ? $meta : [] as $module) {
			if (empty($module['key'])) {
				continue;
			}
			$modules[(string) $module['key']] = [
				'key'     => (string) $module['key'],
				'number'  => (int) ($module['number'] ?? 0),
				'title'   => (string) ($module['title'] ?? ''),
				'goal'    => (string) ($module['goal'] ?? ''),
				'lessons' => [],
			];
		}

		$lessons = get_posts([
			'post_type'      => Lesson::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => ['menu_order' => 'ASC', 'ID' => 'ASC'],
			'meta_key'       => '_lms4wp_course_id',
			'meta_value'     => $course_id,
			'no_found_rows'  => true,
		]);

		foreach ($lessons as $lesson) {
			$key = (string) get_post_meta($lesson->ID, '_lms4wp_module', true);
			if (!isset($modules[$key])) {
				$key = '';
				$modules[''] ??= ['key' => '', 'number' => 0, 'title' => '', 'goal' => '', 'lessons' => []];
			}
			$modules[$key]['lessons'][] = $lesson;
		}

		// Untitled group last.
		if (isset($modules[''])) {
			$rest = $modules[''];
			unset($modules['']);
			$modules[''] = $rest;
		}

		return self::$cache[$course_id] = array_values($modules);
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
