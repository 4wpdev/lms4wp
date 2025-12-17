<?php
/**
 * Activator class
 *
 * Handles plugin activation
 *
 * @package ForWP\LMS\Core
 */

namespace ForWP\LMS\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Load required classes
require_once LMS4WP_PATH . 'includes/Database/Schema.php';
require_once LMS4WP_PATH . 'includes/Users/Roles.php';

use ForWP\LMS\Database\Schema;
use ForWP\LMS\Users\Roles;

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

		// Register user roles (must be done immediately, not via init hook)
		$roles = new Roles();
		$roles->registerRoles();
		$roles->registerCapabilities();

		// Set default options
		self::setDefaultOptions();

		// Register WooCommerce My Account endpoints before flushing
		if (class_exists('WooCommerce')) {
			require_once LMS4WP_PATH . 'includes/WooCommerce/MyAccount.php';
			$myaccount = new \ForWP\LMS\WooCommerce\MyAccount();
			$myaccount->addEndpoints();
		}

		// Flush rewrite rules (includes WooCommerce My Account endpoints)
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

