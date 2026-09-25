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

require_once LMS4WP_PATH . 'includes/Database/Schema.php';
require_once LMS4WP_PATH . 'includes/Users/Roles.php';

use ForWP\LMS\Database\Schema;
use ForWP\LMS\Content\PracticeCaseTemplateSync;
use ForWP\LMS\Content\PracticeCaseEditorShell;
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
		Schema::createTables();

		$roles = new Roles();
		$roles->registerRoles();
		$roles->registerCapabilities();

		self::setDefaultOptions();

		if (class_exists('WooCommerce')) {
			require_once LMS4WP_PATH . 'includes/WooCommerce/MyAccount.php';
			$myaccount = new \ForWP\LMS\WooCommerce\MyAccount();
			$myaccount->addEndpoints();
		}

		flush_rewrite_rules();
	}

	/**
	 * Run lightweight upgrades without requiring manual reactivation.
	 */
	public static function maybeUpgrade(): void
	{
		$stored = get_option('lms4wp_version', '');
		$rewrite_version = get_option('lms4wp_rewrite_version', '');

		if ($stored === LMS4WP_VERSION && $rewrite_version === \ForWP\LMS\PostTypes\PracticeCase::REWRITE_VERSION) {
			PracticeCaseTemplateSync::maybeSync();
			PracticeCaseEditorShell::maybeBackfill();

			return;
		}

		$roles = new Roles();
		$roles->registerRoles();
		$roles->registerCapabilities();

		update_option('lms4wp_version', LMS4WP_VERSION, false);
		update_option('lms4wp_rewrite_version', \ForWP\LMS\PostTypes\PracticeCase::REWRITE_VERSION, false);
		PracticeCaseTemplateSync::maybeSync();
		PracticeCaseEditorShell::maybeBackfill();
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options
	 */
	private static function setDefaultOptions(): void
	{
		if (get_option('lms4wp_db_version') === false) {
			add_option('lms4wp_db_version', '1.0.0');
		}

		update_option('lms4wp_version', LMS4WP_VERSION, false);
	}
}
