<?php
/**
 * Loader class
 *
 * Responsible for loading plugin dependencies
 *
 * @package ForWP\LMS\Core
 */

namespace ForWP\LMS\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Loader class
 */
class Loader
{
	/**
	 * Load plugin dependencies
	 */
	public static function loadDependencies(): void
	{
		$includes_dir = LMS4WP_PATH . 'includes/';

		// Core classes (always required)
		require_once $includes_dir . 'Core/Activator.php';
		require_once $includes_dir . 'Core/Deactivator.php';

		// Database (always required)
		require_once $includes_dir . 'Database/Schema.php';

		// Post Types (always required)
		require_once $includes_dir . 'PostTypes/Course.php';
		require_once $includes_dir . 'PostTypes/Lesson.php';
		require_once $includes_dir . 'PostTypes/Quiz.php';

		// Users
		if (file_exists($includes_dir . 'Users/Roles.php')) {
			require_once $includes_dir . 'Users/Roles.php';
		}
		if (file_exists($includes_dir . 'Users/EnrollmentService.php')) {
			require_once $includes_dir . 'Users/EnrollmentService.php';
		}

		// Helpers (load if exists)
		if (file_exists($includes_dir . 'Helpers/Utils.php')) {
			require_once $includes_dir . 'Helpers/Utils.php';
		}
		if (file_exists($includes_dir . 'Helpers/Security.php')) {
			require_once $includes_dir . 'Helpers/Security.php';
		}

		// Services (load if exists)
		$services = [
			'Services/CourseService.php',
			'Services/LessonService.php',
			'Services/QuizService.php',
			'Services/EnrollmentService.php',
			'Services/ProgressService.php',
			'Services/AccessService.php',
		];
		foreach ($services as $service) {
			if (file_exists($includes_dir . $service)) {
				require_once $includes_dir . $service;
			}
		}

		// Database (load if exists)
		if (file_exists($includes_dir . 'Database/Migrations.php')) {
			require_once $includes_dir . 'Database/Migrations.php';
		}
		$repositories = [
			'Database/Repositories/CourseRepository.php',
			'Database/Repositories/EnrollmentRepository.php',
			'Database/Repositories/ProgressRepository.php',
			'Database/Repositories/AccessRepository.php',
		];
		foreach ($repositories as $repository) {
			if (file_exists($includes_dir . $repository)) {
				require_once $includes_dir . $repository;
			}
		}

		// MCP (load if exists)
		if (file_exists($includes_dir . 'MCP/MCPManager.php')) {
			require_once $includes_dir . 'MCP/MCPManager.php';
		}
		if (file_exists($includes_dir . 'MCP/register.php')) {
			require_once $includes_dir . 'MCP/register.php';
		}

		// AI (load if exists)
		if (file_exists($includes_dir . 'AI/AIManager.php')) {
			require_once $includes_dir . 'AI/AIManager.php';
		}

		// WooCommerce (load if exists)
		if (file_exists($includes_dir . 'WooCommerce/WooBootstrap.php')) {
			require_once $includes_dir . 'WooCommerce/WooBootstrap.php';
		}
		if (file_exists($includes_dir . 'WooCommerce/MyAccount.php')) {
			require_once $includes_dir . 'WooCommerce/MyAccount.php';
		}

		// REST (load if exists)
		if (file_exists($includes_dir . 'REST/MCPBridgeController.php')) {
			require_once $includes_dir . 'REST/MCPBridgeController.php';
		}

		// Admin (load if exists and in admin)
		if (is_admin()) {
			// Menu is always required
			require_once $includes_dir . 'Admin/Menu.php';
			
			// Other admin files (load if exists)
			$admin_files = [
				'Admin/Settings.php',
				'Admin/CourseProductUI.php',
			];
			foreach ($admin_files as $admin_file) {
				if (file_exists($includes_dir . $admin_file)) {
					require_once $includes_dir . $admin_file;
				}
			}
		}

		// Frontend (load if exists and not in admin)
		if (!is_admin()) {
			$frontend_files = [
				'Frontend/Templates.php',
				'Frontend/Shortcodes.php',
				'Frontend/AccessControl.php',
				'Frontend/CourseEnrollment.php',
			];
			foreach ($frontend_files as $frontend_file) {
				if (file_exists($includes_dir . $frontend_file)) {
					require_once $includes_dir . $frontend_file;
				}
			}
		}
	}
}

