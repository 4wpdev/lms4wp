<?php
/**
 * Enrollment Service
 *
 * Handles course enrollment functionality
 *
 * @package ForWP\LMS\Users
 */

namespace ForWP\LMS\Users;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * EnrollmentService class
 */
class EnrollmentService
{
	/**
	 * Initialize enrollment service
	 */
	public static function init(): void
	{
		$self = new self();
		
		// Handle enrollment via AJAX
		add_action('wp_ajax_lms4wp_enroll', [$self, 'handleEnrollment']);
		add_action('wp_ajax_lms4wp_unenroll', [$self, 'handleUnenrollment']);
		
		// Handle enrollment via form submission (non-AJAX)
		add_action('template_redirect', [$self, 'handleEnrollmentForm']);
	}

	/**
	 * Enroll user in course
	 *
	 * @param int $user_id User ID
	 * @param int $course_id Course ID
	 * @return bool|int Enrollment ID on success, false on failure
	 */
	public static function enroll(int $user_id, int $course_id): bool|int
	{
		global $wpdb;
		$table = $wpdb->prefix . 'lms4wp_enrollments';

		// Check if already enrolled
		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		));

		if ($existing) {
			// Update status to active if was cancelled
			$wpdb->update(
				$table,
				['status' => 'active'],
				['id' => $existing],
				['%s'],
				['%d']
			);
			return $existing;
		}

		// Create new enrollment
		$result = $wpdb->insert(
			$table,
			[
				'user_id' => $user_id,
				'course_id' => $course_id,
				'status' => 'active',
				'enrolled_at' => current_time('mysql'),
			],
			['%d', '%d', '%s', '%s']
		);

		if ($result) {
			do_action('lms4wp_user_enrolled', $user_id, $course_id);
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Unenroll user from course
	 *
	 * @param int $user_id User ID
	 * @param int $course_id Course ID
	 * @return bool
	 */
	public static function unenroll(int $user_id, int $course_id): bool
	{
		global $wpdb;
		$table = $wpdb->prefix . 'lms4wp_enrollments';

		$result = $wpdb->update(
			$table,
			['status' => 'cancelled'],
			[
				'user_id' => $user_id,
				'course_id' => $course_id,
			],
			['%s'],
			['%d', '%d']
		);

		if ($result !== false) {
			do_action('lms4wp_user_unenrolled', $user_id, $course_id);
			return true;
		}

		return false;
	}

	/**
	 * Check if user is enrolled in course
	 *
	 * @param int $user_id User ID
	 * @param int $course_id Course ID
	 * @return bool
	 */
	public static function isEnrolled(int $user_id, int $course_id): bool
	{
		global $wpdb;
		$table = $wpdb->prefix . 'lms4wp_enrollments';

		$enrollment = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'active'",
			$user_id,
			$course_id
		));

		return !empty($enrollment);
	}

	/**
	 * Handle enrollment via AJAX
	 */
	public function handleEnrollment(): void
	{
		check_ajax_referer('lms4wp_enroll', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => __('You must be logged in to enroll.', 'lms4wp')]);
		}

		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		if (!$course_id) {
			wp_send_json_error(['message' => __('Invalid course ID.', 'lms4wp')]);
		}

		$user_id = get_current_user_id();
		$result = self::enroll($user_id, $course_id);

		if ($result) {
			wp_send_json_success([
				'message' => __('Successfully enrolled in course.', 'lms4wp'),
				'enrollment_id' => $result,
			]);
		} else {
			wp_send_json_error(['message' => __('Failed to enroll in course.', 'lms4wp')]);
		}
	}

	/**
	 * Handle unenrollment via AJAX
	 */
	public function handleUnenrollment(): void
	{
		check_ajax_referer('lms4wp_enroll', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => __('You must be logged in.', 'lms4wp')]);
		}

		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		if (!$course_id) {
			wp_send_json_error(['message' => __('Invalid course ID.', 'lms4wp')]);
		}

		$user_id = get_current_user_id();
		$result = self::unenroll($user_id, $course_id);

		if ($result) {
			wp_send_json_success(['message' => __('Successfully unenrolled from course.', 'lms4wp')]);
		} else {
			wp_send_json_error(['message' => __('Failed to unenroll from course.', 'lms4wp')]);
		}
	}

	/**
	 * Handle enrollment via form submission (non-AJAX)
	 */
	public function handleEnrollmentForm(): void
	{
		if (!isset($_POST['lms4wp_enroll']) || !isset($_POST['lms4wp_enroll_nonce'])) {
			return;
		}

		if (!wp_verify_nonce($_POST['lms4wp_enroll_nonce'], 'lms4wp_enroll')) {
			wp_die(__('Security check failed.', 'lms4wp'));
		}

		if (!is_user_logged_in()) {
			wp_die(__('You must be logged in to enroll.', 'lms4wp'));
		}

		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		if (!$course_id) {
			wp_die(__('Invalid course ID.', 'lms4wp'));
		}

		$user_id = get_current_user_id();
		$result = self::enroll($user_id, $course_id);

		if ($result) {
			wp_safe_redirect(add_query_arg('enrolled', '1', get_permalink($course_id)));
		} else {
			wp_safe_redirect(add_query_arg('enrolled', '0', get_permalink($course_id)));
		}
		exit;
	}
}







