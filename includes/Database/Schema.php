<?php
/**
 * Schema class
 *
 * Handles database table creation and management
 *
 * @package ForWP\LMS\Database
 */

namespace ForWP\LMS\Database;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Schema class
 */
class Schema
{
	/**
	 * Create all database tables
	 */
	public static function createTables(): void
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Enrollments table
		$table_enrollments = $wpdb->prefix . 'lms4wp_enrollments';
		$sql_enrollments = "CREATE TABLE IF NOT EXISTS {$table_enrollments} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'active',
			enrollment_method varchar(20) DEFAULT 'manual',
			completed_at datetime NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status),
			UNIQUE KEY user_course (user_id, course_id)
		) {$charset_collate};";

		dbDelta($sql_enrollments);

		// Progress table
		$table_progress = $wpdb->prefix . 'lms4wp_progress';
		$sql_progress = "CREATE TABLE IF NOT EXISTS {$table_progress} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			lesson_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) DEFAULT 'in_progress',
			completed_at datetime NULL,
			progress_percentage int(3) DEFAULT 0,
			time_spent int(11) DEFAULT 0 COMMENT 'Time in seconds',
			last_accessed datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY lesson_id (lesson_id),
			KEY status (status),
			UNIQUE KEY user_course_lesson (user_id, course_id, lesson_id)
		) {$charset_collate};";

		dbDelta($sql_progress);

		// Access table (for future use - time-limited access, etc.)
		$table_access = $wpdb->prefix . 'lms4wp_access';
		$sql_access = "CREATE TABLE IF NOT EXISTS {$table_access} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			access_type varchar(20) DEFAULT 'enrollment',
			granted_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NULL,
			revoked_at datetime NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY access_type (access_type),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		dbDelta($sql_access);

		// Quiz results table
		$table_quiz_results = $wpdb->prefix . 'lms4wp_quiz_results';
		$sql_quiz_results = "CREATE TABLE IF NOT EXISTS {$table_quiz_results} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			quiz_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NULL,
			lesson_id bigint(20) UNSIGNED NULL,
			score int(11) DEFAULT 0,
			percentage int(3) DEFAULT 0,
			passed tinyint(1) DEFAULT 0,
			answers longtext COMMENT 'JSON array of answers',
			started_at datetime DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime NULL,
			time_spent int(11) DEFAULT 0 COMMENT 'Time in seconds',
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY quiz_id (quiz_id),
			KEY course_id (course_id),
			KEY lesson_id (lesson_id),
			KEY completed_at (completed_at)
		) {$charset_collate};";

		dbDelta($sql_quiz_results);
	}

	/**
	 * Drop all database tables
	 */
	public static function dropTables(): void
	{
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'lms4wp_enrollments',
			$wpdb->prefix . 'lms4wp_progress',
			$wpdb->prefix . 'lms4wp_access',
			$wpdb->prefix . 'lms4wp_quiz_results',
		];

		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS {$table}");
		}
	}
}





