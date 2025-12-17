<?php
/**
 * Deactivator class
 *
 * Handles plugin deactivation
 *
 * @package ForWP\LMS\Core
 */

namespace ForWP\LMS\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Load Roles class
require_once LMS4WP_PATH . 'includes/Users/Roles.php';

use ForWP\LMS\Users\Roles;

/**
 * Deactivator class
 */
class Deactivator
{
	/**
	 * Deactivate plugin
	 */
	public static function deactivate(): void
	{
		// Note: We don't remove roles on deactivation to preserve user assignments
		// Roles will be removed only on uninstall if needed

		// Flush rewrite rules
		flush_rewrite_rules();

		// Clear scheduled events if any
		wp_clear_scheduled_hook('lms4wp_daily_cleanup');
	}
}


