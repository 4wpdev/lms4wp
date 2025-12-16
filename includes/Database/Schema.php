<?php
/**
 * Database Schema class
 *
 * Handles database table creation and structure
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

		// Table: Course enrollments
		$table_enrollments = $wpdb->prefix . 'lms4wp_enrollments';
		$sql_enrollments = "CREATE TABLE IF NOT EXISTS {$table_enrollments} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'active',
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status),
			UNIQUE KEY user_course (user_id, course_id)
		) {$charset_collate};";

		// Table: Learning progress (separate table)
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

		// Table: Course access (for WooCommerce integration)
		$table_access = $wpdb->prefix . 'lms4wp_access';
		$sql_access = "CREATE TABLE IF NOT EXISTS {$table_access} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			product_id bigint(20) UNSIGNED NULL,
			order_id bigint(20) UNSIGNED NULL,
			granted_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NULL,
			access_type varchar(20) DEFAULT 'purchase',
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY product_id (product_id),
			KEY order_id (order_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta($sql_enrollments);
		dbDelta($sql_progress);
		dbDelta($sql_access);
	}

	/**
	 * Get database version
	 *
	 * @return string
	 */
	public static function getDbVersion(): string
	{
		return get_option('lms4wp_db_version', '1.0.0');
	}

	/**
	 * Update database version
	 *
	 * @param string $version Version string
	 */
	public static function updateDbVersion(string $version): void
	{
		update_option('lms4wp_db_version', $version);
	}
}

