<?php
/**
 * Plugin Name: LMS4WP
 * Plugin URI: https://github.com/4wpdev/lms4wp
 * Description: LMS platform for learning your favorite programming language. WordPress plugin for educational courses and skill development.
 * Tags: lms, learning, courses, education, woocommerce, mcp, ai
 * Version: 1.0.0
 * Author: 4wp.dev
 * Author URI: https://4wp.dev
 * Text Domain: lms4wp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Network: false
 *
 * @package ForWP\LMS
 */

namespace ForWP\LMS;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('LMS4WP_VERSION', '1.0.0');
define('LMS4WP_PATH', plugin_dir_path(__FILE__));
define('LMS4WP_URL', plugin_dir_url(__FILE__));
define('LMS4WP_BASENAME', plugin_basename(__FILE__));
define('LMS4WP_FILE', __FILE__);

// Load Composer autoloader
if (file_exists(LMS4WP_PATH . 'vendor/autoload.php')) {
	require_once LMS4WP_PATH . 'vendor/autoload.php';
}

/**
 * Main plugin class
 */
class Plugin
{
	/**
	 * Plugin instance
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return self
	 */
	public static function getInstance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct()
	{
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init(): void
	{
		// Load dependencies
		Core\Loader::loadDependencies();

		// Register activation/deactivation hooks
		register_activation_hook(__FILE__, [Core\Activator::class, 'activate']);
		register_deactivation_hook(__FILE__, [Core\Deactivator::class, 'deactivate']);

		// Initialize plugin
		add_action('plugins_loaded', [$this, 'loadPlugin'], 10);
	}

	/**
	 * Load plugin functionality
	 */
	public function loadPlugin(): void
	{
		// Check PHP and WordPress requirements
		if (!$this->checkBasicRequirements()) {
			return;
		}

		// Load text domain
		load_plugin_textdomain(
			'lms4wp',
			false,
			dirname(LMS4WP_BASENAME) . '/languages'
		);

		// Initialize components (Post Types should work even without WooCommerce)
		$this->initComponents();

		// Check WooCommerce requirement (show notice but don't block)
		$this->checkWooCommerceRequirement();
	}

	/**
	 * Check basic requirements (PHP, WordPress)
	 *
	 * @return bool
	 */
	private function checkBasicRequirements(): bool
	{
		// Check PHP version
		if (version_compare(PHP_VERSION, '8.0', '<')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: %s: PHP version */
					esc_html__('LMS4WP requires PHP 8.0 or higher. You are running PHP %s.', 'lms4wp'),
					esc_html(PHP_VERSION)
				);
				echo '</p></div>';
			});
			return false;
		}

		// Check WordPress version
		if (version_compare(get_bloginfo('version'), '6.0', '<')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: %s: WordPress version */
					esc_html__('LMS4WP requires WordPress 6.0 or higher. You are running WordPress %s.', 'lms4wp'),
					esc_html(get_bloginfo('version'))
				);
				echo '</p></div>';
			});
			return false;
		}

		return true;
	}

	/**
	 * Check WooCommerce requirement (show notice but don't block)
	 */
	private function checkWooCommerceRequirement(): void
	{
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', [$this, 'showWooCommerceNotice']);
		}
	}

	/**
	 * Show WooCommerce requirement notice with install button
	 */
	public function showWooCommerceNotice(): void
	{
		// Check if user can install plugins
		if (!current_user_can('install_plugins')) {
			return;
		}

		// Check if WooCommerce is installed but not activated
		$woocommerce_installed = file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
		
		if ($woocommerce_installed) {
			// WooCommerce installed but not activated
			$action_url = wp_nonce_url(
				admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php'),
				'activate-plugin_woocommerce/woocommerce.php'
			);
			$button_text = __('Activate WooCommerce', 'lms4wp');
		} else {
			// WooCommerce not installed - install from WordPress.org repository
			$action_url = wp_nonce_url(
				self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
				'install-plugin_woocommerce'
			);
			$button_text = __('Install WooCommerce', 'lms4wp');
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e('LMS4WP requires WooCommerce', 'lms4wp'); ?></strong>
			</p>
			<p>
				<?php esc_html_e('WooCommerce is required for user authentication, registration, personal account, and course enrollment functionality.', 'lms4wp'); ?>
			</p>
			<p>
				<a href="<?php echo esc_url($action_url); ?>" class="button button-primary button-large">
					<?php echo esc_html($button_text); ?>
				</a>
				<a href="https://wordpress.org/plugins/woocommerce/" target="_blank" class="button button-large">
					<?php esc_html_e('Learn More', 'lms4wp'); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Initialize plugin components
	 */
	private function initComponents(): void
	{
		// Initialize user roles
		if (class_exists('ForWP\LMS\Users\Roles')) {
			Users\Roles::init();
		}
		if (class_exists('ForWP\LMS\Users\EnrollmentService')) {
			Users\EnrollmentService::init();
		}

		// Initialize post types (always available)
		PostTypes\Course::init();
		PostTypes\Lesson::init();
		PostTypes\Quiz::init();

		// Initialize WooCommerce integration (if class exists)
		if (class_exists('ForWP\LMS\WooCommerce\WooBootstrap')) {
			WooCommerce\WooBootstrap::init();
		}
		if (class_exists('ForWP\LMS\WooCommerce\MyAccount')) {
			WooCommerce\MyAccount::init();
		}

		// Initialize MCP integration (if class exists)
		if (class_exists('ForWP\LMS\MCP\MCPManager')) {
			MCP\MCPManager::init();
		}

		// Initialize admin (if classes exist)
		if (is_admin()) {
			if (class_exists('ForWP\LMS\Admin\Menu')) {
				Admin\Menu::init();
			}
			if (class_exists('ForWP\LMS\Admin\Settings')) {
				Admin\Settings::init();
			}
		}

		// Initialize frontend (if classes exist)
		if (!is_admin()) {
			if (class_exists('ForWP\LMS\Frontend\Templates')) {
				Frontend\Templates::init();
			}
			if (class_exists('ForWP\LMS\Frontend\Shortcodes')) {
				Frontend\Shortcodes::init();
			}
			if (class_exists('ForWP\LMS\Frontend\AccessControl')) {
				Frontend\AccessControl::init();
			}
			if (class_exists('ForWP\LMS\Frontend\CourseEnrollment')) {
				Frontend\CourseEnrollment::init();
			}
		}

		// Initialize REST API (if class exists)
		if (class_exists('ForWP\LMS\REST\MCPBridgeController')) {
			REST\MCPBridgeController::init();
		}
	}
}

// Initialize plugin
Plugin::getInstance();

