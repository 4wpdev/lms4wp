<?php
/**
 * Lesson progress (SPEC F6): "lesson complete", course summary, next lesson.
 * Table {prefix}lms4wp_progress (Database/Schema.php).
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\Lesson;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class Progress
{
	const ACTION_COMPLETE   = 'lms4wp_lesson_complete';
	const ACTION_UNCOMPLETE = 'lms4wp_lesson_uncomplete';

	/** @var array<string, int[]> */
	private static array $cache = [];

	public static function init(): void
	{
		add_action('template_redirect', [self::class, 'handleForm']);
	}

	private static function table(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'lms4wp_progress';
	}

	/**
	 * @return int[] Completed lesson IDs of a course.
	 */
	public static function completedLessonIds(int $user_id, int $course_id): array
	{
		if ($user_id <= 0 || $course_id <= 0) {
			return [];
		}
		$key = $user_id . ':' . $course_id;
		if (!isset(self::$cache[$key])) {
			global $wpdb;
			$table = self::table();
			self::$cache[$key] = array_map('intval', $wpdb->get_col($wpdb->prepare(
				"SELECT lesson_id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'completed'",
				$user_id,
				$course_id
			)));
		}
		return self::$cache[$key];
	}

	public static function isCompleted(int $user_id, int $lesson_id): bool
	{
		$course_id = CourseStructure::courseIdForLesson($lesson_id);
		return in_array($lesson_id, self::completedLessonIds($user_id, $course_id), true);
	}

	public static function complete(int $user_id, int $lesson_id): bool
	{
		$course_id = CourseStructure::courseIdForLesson($lesson_id);
		if ($user_id <= 0 || $course_id === 0) {
			return false;
		}

		global $wpdb;
		$table = self::table();
		$now = current_time('mysql');
		$result = $wpdb->query($wpdb->prepare(
			"INSERT INTO {$table} (user_id, course_id, lesson_id, status, completed_at, progress_percentage, last_accessed)
			VALUES (%d, %d, %d, 'completed', %s, 100, %s)
			ON DUPLICATE KEY UPDATE status = 'completed', completed_at = VALUES(completed_at), progress_percentage = 100, last_accessed = VALUES(last_accessed)",
			$user_id,
			$course_id,
			$lesson_id,
			$now,
			$now
		));
		unset(self::$cache[$user_id . ':' . $course_id]);

		if ($result === false) {
			return false;
		}

		do_action('lms4wp_lesson_completed', $user_id, $lesson_id, $course_id);

		$summary = self::summary($user_id, $course_id);
		if ($summary['total'] > 0 && $summary['completed'] === $summary['total']) {
			$wpdb->update(
				$wpdb->prefix . 'lms4wp_enrollments',
				['completed_at' => $now],
				['user_id' => $user_id, 'course_id' => $course_id],
				['%s'],
				['%d', '%d']
			);
			do_action('lms4wp_course_completed', $user_id, $course_id);
		}

		return true;
	}

	public static function uncomplete(int $user_id, int $lesson_id): bool
	{
		$course_id = CourseStructure::courseIdForLesson($lesson_id);
		if ($user_id <= 0 || $course_id === 0) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->update(
			self::table(),
			['status' => 'in_progress', 'completed_at' => null, 'progress_percentage' => 0],
			['user_id' => $user_id, 'course_id' => $course_id, 'lesson_id' => $lesson_id],
			['%s', '%s', '%d'],
			['%d', '%d', '%d']
		);
		$wpdb->update(
			$wpdb->prefix . 'lms4wp_enrollments',
			['completed_at' => null],
			['user_id' => $user_id, 'course_id' => $course_id],
			['%s'],
			['%d', '%d']
		);
		unset(self::$cache[$user_id . ':' . $course_id]);

		return $result !== false;
	}

	/**
	 * Retake: clear the learner's progress in a course (enrollment stays).
	 */
	public static function reset(int $user_id, int $course_id): bool
	{
		if ($user_id <= 0 || $course_id <= 0) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->delete(self::table(), ['user_id' => $user_id, 'course_id' => $course_id], ['%d', '%d']);
		$wpdb->update(
			$wpdb->prefix . 'lms4wp_enrollments',
			['completed_at' => null],
			['user_id' => $user_id, 'course_id' => $course_id],
			['%s'],
			['%d', '%d']
		);
		unset(self::$cache[$user_id . ':' . $course_id]);
		do_action('lms4wp_course_retake', $user_id, $course_id);
		return $result !== false;
	}

	/**
	 * @return array{completed: int, total: int, percent: int, next: ?\WP_Post}
	 */
	public static function summary(int $user_id, int $course_id): array
	{
		$lessons = CourseStructure::lessons($course_id);
		$done = self::completedLessonIds($user_id, $course_id);

		$completed = 0;
		$next = null;
		foreach ($lessons as $lesson) {
			if (in_array($lesson->ID, $done, true)) {
				$completed++;
			} elseif ($next === null) {
				$next = $lesson;
			}
		}
		$total = count($lessons);

		return [
			'completed' => $completed,
			'total'     => $total,
			'percent'   => $total > 0 ? (int) floor($completed * 100 / $total) : 0,
			'next'      => $next,
		];
	}

	/**
	 * POST from lms4wp/lesson-complete on a lesson page.
	 */
	public static function handleForm(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_singular(Lesson::POST_TYPE)) {
			return;
		}
		$complete = isset($_POST[self::ACTION_COMPLETE]);
		$uncomplete = isset($_POST[self::ACTION_UNCOMPLETE]);
		if (!$complete && !$uncomplete) {
			return;
		}

		$lesson_id = (int) get_queried_object_id();
		$nonce = isset($_POST['lms4wp_progress_nonce']) ? sanitize_text_field(wp_unslash($_POST['lms4wp_progress_nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'lms4wp_progress_' . $lesson_id)) {
			wp_die(esc_html__('Security check failed.', 'lms4wp'), '', ['response' => 403]);
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0 || !LessonAccess::canView($lesson_id, $user_id)) {
			wp_die(esc_html__('You cannot track progress for this lesson.', 'lms4wp'), '', ['response' => 403]);
		}

		if ($uncomplete) {
			self::uncomplete($user_id, $lesson_id);
			wp_safe_redirect((string) get_permalink($lesson_id));
			exit;
		}

		self::complete($user_id, $lesson_id);
		$context = CourseStructure::lessonContext($lesson_id);
		$summary = self::summary($user_id, $context['course_id']);
		if ($summary['total'] > 0 && $summary['next'] === null) {
			// Whole course done → completion page.
			$target = CourseCompletion::url($context['course_id']);
		} else {
			$target = $context['next']
				? (string) get_permalink($context['next'])
				: (string) get_permalink($summary['next'] ?? $context['course_id']);
		}
		wp_safe_redirect($target);
		exit;
	}
}
