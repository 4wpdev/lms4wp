<?php
/**
 * Uninstall file
 *
 * Fired when the plugin is uninstalled
 *
 * @package ForWP\LMS
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

// Delete plugin options
delete_option('lms4wp_version');
delete_option('lms4wp_db_version');

// Delete all plugin data if requested
$delete_data = get_option('lms4wp_delete_data_on_uninstall', false);

if ($delete_data) {
	// Drop custom tables
	$tables = [
		$wpdb->prefix . 'lms4wp_enrollments',
		$wpdb->prefix . 'lms4wp_progress',
		$wpdb->prefix . 'lms4wp_access',
		$wpdb->prefix . 'lms4wp_quiz_results',
	];

	foreach ($tables as $table) {
		$wpdb->query("DROP TABLE IF EXISTS {$table}");
	}

	// Delete all plugin meta
	$wpdb->delete(
		$wpdb->postmeta,
		['meta_key' => '_lms4wp_course_id'],
		['%s']
	);

	$wpdb->delete(
		$wpdb->postmeta,
		['meta_key' => '_lms4wp_lesson_id'],
		['%s']
	);
}




