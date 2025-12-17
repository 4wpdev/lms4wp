<?php
/**
 * Admin Menu class
 *
 * Handles admin menu registration (similar to WooCommerce structure)
 *
 * @package ForWP\LMS\Admin
 */

namespace ForWP\LMS\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Menu class
 */
class Menu
{
	/**
	 * Initialize admin menu
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('admin_menu', [$self, 'addMenu'], 9);
		add_action('admin_head', [$self, 'highlightMenuItems']);
	}

	/**
	 * Add main menu and submenus
	 */
	public function addMenu(): void
	{
		// Main LMS4WP menu (only Dashboard, Enrollments, Settings)
		// Position 26: after Comments (25) and before WooCommerce (55.5)
		// Use dashicons-schedule icon
		add_menu_page(
			__('LMS4WP', 'lms4wp'),
			__('LMS4WP', 'lms4wp'),
			'manage_lms_settings',
			'lms4wp',
			[$this, 'renderDashboard'],
			'dashicons-schedule',
			26
		);

		// Dashboard (home page, same as main menu)
		add_submenu_page(
			'lms4wp',
			__('Dashboard', 'lms4wp'),
			__('Dashboard', 'lms4wp'),
			'manage_lms_settings',
			'lms4wp',
			[$this, 'renderDashboard']
		);

		// Enrollments
		add_submenu_page(
			'lms4wp',
			__('Enrollments', 'lms4wp'),
			__('Enrollments', 'lms4wp'),
			'view_lms_enrollments',
			'lms4wp-enrollments',
			[$this, 'renderEnrollments']
		);

		// Settings
		add_submenu_page(
			'lms4wp',
			__('Settings', 'lms4wp'),
			__('Settings', 'lms4wp'),
			'manage_lms_settings',
			'lms4wp-settings',
			[$this, 'renderSettings']
		);

		// Note: Courses, Lessons, Quizzes are separate menus
		// They are registered via Post Types with show_in_menu => true
		// WordPress will create separate menus for each automatically
	}

	/**
	 * Render dashboard page
	 */
	public function renderDashboard(): void
	{
		?>
		<div class="wrap">
			<h1><?php esc_html_e('LMS4WP Dashboard', 'lms4wp'); ?></h1>
			<div class="lms4wp-dashboard">
				<p><?php esc_html_e('Welcome to LMS4WP Dashboard. This page will show statistics and overview.', 'lms4wp'); ?></p>
				<!-- Dashboard content will be added later -->
			</div>
		</div>
		<?php
	}

	/**
	 * Render enrollments page
	 */
	public function renderEnrollments(): void
	{
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Enrollments', 'lms4wp'); ?></h1>
			<div class="lms4wp-enrollments">
				<p><?php esc_html_e('Enrollments list will be displayed here.', 'lms4wp'); ?></p>
				<!-- Enrollments table will be added later -->
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function renderSettings(): void
	{
		?>
		<div class="wrap">
			<h1><?php esc_html_e('LMS4WP Settings', 'lms4wp'); ?></h1>
			<div class="lms4wp-settings">
				<p><?php esc_html_e('Settings page will be displayed here.', 'lms4wp'); ?></p>
				<!-- Settings form will be added later -->
			</div>
		</div>
		<?php
	}

	/**
	 * Highlight menu items when on post type pages
	 */
	public function highlightMenuItems(): void
	{
		// WordPress automatically handles menu highlighting for Post Types
		// No custom highlighting needed since they are separate menus
	}
}

