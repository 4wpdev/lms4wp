<?php
/**
 * User Roles class
 *
 * Handles registration of custom user roles and capabilities
 *
 * @package ForWP\LMS\Users
 */

namespace ForWP\LMS\Users;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Roles class
 */
class Roles
{
	/**
	 * Role slugs
	 */
	const ROLE_MENTOR = 'lms_mentor';
	const ROLE_STUDENT = 'lms_student';

	/**
	 * Initialize roles
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'registerRoles'], 10);
		add_action('init', [$self, 'registerCapabilities'], 10);
	}

	/**
	 * Register custom user roles
	 */
	public function registerRoles(): void
	{
		// Mentor role
		add_role(
			self::ROLE_MENTOR,
			__('Mentor', 'lms4wp'),
			[
				'read' => true,
			]
		);

		// Student role (inherits WooCommerce customer capabilities)
		add_role(
			self::ROLE_STUDENT,
			__('Student', 'lms4wp'),
			[
				'read' => true,
			]
		);
	}

	/**
	 * Register capabilities for roles
	 */
	public function registerCapabilities(): void
	{
		$mentor = get_role(self::ROLE_MENTOR);
		$student = get_role(self::ROLE_STUDENT);
		$administrator = get_role('administrator');

		if (!$mentor || !$student) {
			return;
		}

		// Mentor capabilities
		$mentor_caps = [
			// Course management
			'edit_lms_courses' => true,
			'edit_others_lms_courses' => false,
			'publish_lms_courses' => true,
			'delete_lms_courses' => true,
			'delete_others_lms_courses' => false,
			'read_private_lms_courses' => true,

			// Lesson management
			'edit_lms_lessons' => true,
			'edit_others_lms_lessons' => false,
			'publish_lms_lessons' => true,
			'delete_lms_lessons' => true,
			'delete_others_lms_lessons' => false,
			'read_private_lms_lessons' => true,

			// Quiz management
			'edit_lms_quizzes' => true,
			'edit_others_lms_quizzes' => false,
			'publish_lms_quizzes' => true,
			'delete_lms_quizzes' => true,
			'delete_others_lms_quizzes' => false,
			'read_private_lms_quizzes' => true,

			// View enrollments and progress (own courses only)
			'view_lms_enrollments' => true,
			'view_lms_progress' => true,
		];

		foreach ($mentor_caps as $cap => $grant) {
			$mentor->add_cap($cap, $grant);
		}

		// Student capabilities (basic read access)
		$student_caps = [
			'read_lms_courses' => true,
			'read_lms_lessons' => true,
			'read_lms_quizzes' => true,
			'enroll_in_courses' => true,
			'view_own_progress' => true,
		];

		foreach ($student_caps as $cap => $grant) {
			$student->add_cap($cap, $grant);
		}

		// Administrator gets all capabilities
		if ($administrator) {
			$admin_caps = array_merge($mentor_caps, $student_caps, [
				'edit_others_lms_courses' => true,
				'delete_others_lms_courses' => true,
				'edit_others_lms_lessons' => true,
				'delete_others_lms_lessons' => true,
				'edit_others_lms_quizzes' => true,
				'delete_others_lms_quizzes' => true,
				'manage_lms_settings' => true,
			]);

			foreach ($admin_caps as $cap => $grant) {
				$administrator->add_cap($cap, $grant);
			}
		}

		// Integrate with WooCommerce: Student = Customer
		$this->integrateWooCommerceRoles();
	}

	/**
	 * Integrate Student role with WooCommerce Customer role
	 */
	private function integrateWooCommerceRoles(): void
	{
		if (!class_exists('WooCommerce')) {
			return;
		}

		$student = get_role(self::ROLE_STUDENT);
		if (!$student) {
			return;
		}

		// Get WooCommerce customer capabilities
		$customer = get_role('customer');
		if ($customer) {
			// Copy customer capabilities to student
			foreach ($customer->capabilities as $cap => $grant) {
				$student->add_cap($cap, $grant);
			}
		}
	}

	/**
	 * Remove roles (for deactivation/uninstall)
	 */
	public static function removeRoles(): void
	{
		remove_role(self::ROLE_MENTOR);
		remove_role(self::ROLE_STUDENT);
	}
}

