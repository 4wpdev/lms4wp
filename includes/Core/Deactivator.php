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
		// Flush rewrite rules
		flush_rewrite_rules();

		// Clear scheduled events if any
		wp_clear_scheduled_hook('lms4wp_daily_cleanup');
	}
}

