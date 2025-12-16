<?php
/**
 * Activator class
 *
 * Handles plugin activation
 *
 * @package ForWP\LMS\Core
 */

namespace ForWP\LMS\Core;

use ForWP\LMS\Database\Schema;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Activator class
 */
class Activator
{
	/**
	 * Activate plugin
	 */
	public static function activate(): void
	{
		// Create database tables
		Schema::createTables();

		// Set default options
		self::setDefaultOptions();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options
	 */
	private static function setDefaultOptions(): void
	{
		$defaults = [
			'lms4wp_version' => LMS4WP_VERSION,
			'lms4wp_db_version' => '1.0.0',
		];

		foreach ($defaults as $key => $value) {
			if (get_option($key) === false) {
				add_option($key, $value);
			}
		}
	}
}

