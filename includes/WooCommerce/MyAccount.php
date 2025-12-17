<?php
/**
 * WooCommerce My Account integration
 *
 * Adds LMS tabs to WooCommerce My Account page
 *
 * @package ForWP\LMS\WooCommerce
 */

namespace ForWP\LMS\WooCommerce;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * MyAccount class
 */
class MyAccount
{
	/**
	 * Initialize My Account integration
	 */
	public static function init(): void
	{
		$self = new self();
		
		// Add custom endpoints
		add_action('init', [$self, 'addEndpoints']);
		
		// Add query vars
		add_filter('query_vars', [$self, 'addQueryVars'], 0);
		
		// Add menu items
		add_filter('woocommerce_account_menu_items', [$self, 'addMenuItems']);
		
		// Register endpoint content
		add_action('woocommerce_account_my-courses_endpoint', [$self, 'renderMyCourses']);
		add_action('woocommerce_account_my-progress_endpoint', [$self, 'renderMyProgress']);
		add_action('woocommerce_account_certificates_endpoint', [$self, 'renderCertificates']);
		
		// Enqueue compiled CSS
		add_action('wp_enqueue_scripts', [$self, 'enqueueStyles']);
	}
	
	/**
	 * Enqueue compiled CSS for My Account pages
	 */
	public function enqueueStyles(): void
	{
		if (is_account_page()) {
			wp_enqueue_style(
				'lms4wp-my-account',
				LMS4WP_URL . 'assets/css/my-account.css',
				['woocommerce-general'],
				LMS4WP_VERSION
			);
		}
	}

	/**
	 * Add custom endpoints
	 */
	public function addEndpoints(): void
	{
		// Get WooCommerce My Account page ID
		$myaccount_page_id = get_option('woocommerce_myaccount_page_id');
		if (!$myaccount_page_id) {
			return;
		}

		// Use EP_PAGES to add endpoints to pages (like WooCommerce does)
		add_rewrite_endpoint('my-courses', EP_PAGES);
		add_rewrite_endpoint('my-progress', EP_PAGES);
		add_rewrite_endpoint('certificates', EP_PAGES);
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars Query vars
	 * @return array
	 */
	public function addQueryVars(array $vars): array
	{
		$vars[] = 'my-courses';
		$vars[] = 'my-progress';
		$vars[] = 'certificates';
		return $vars;
	}

	/**
	 * Add menu items to My Account
	 *
	 * @param array $items Menu items
	 * @return array
	 */
	public function addMenuItems(array $items): array
	{
		// Check if WooCommerce is active
		if (!class_exists('WooCommerce')) {
			return $items;
		}

		// Show to all logged in users (we can restrict later based on roles)
		if (!is_user_logged_in()) {
			return $items;
		}

		$user_id = get_current_user_id();

		// Hide Downloads if user has no downloadable products
		if (isset($items['downloads'])) {
			$downloads = wc_get_customer_available_downloads($user_id);
			if (empty($downloads)) {
				unset($items['downloads']);
			}
		}

		// Hide Addresses if user has no orders with addresses
		if (isset($items['edit-address'])) {
			$orders = wc_get_orders([
				'customer_id' => $user_id,
				'limit' => 1,
				'return' => 'ids',
			]);
			if (empty($orders)) {
				unset($items['edit-address']);
			}
		}

		// Remove logout, orders, and account details from end
		$logout = $items['customer-logout'] ?? __('Log out', 'woocommerce');
		$orders = $items['orders'] ?? null;
		$account_details = $items['edit-account'] ?? null;
		unset($items['customer-logout']);
		unset($items['orders']);
		unset($items['edit-account']);

		// Add LMS items
		$items['my-courses'] = __('Courses', 'lms4wp');
		$items['my-progress'] = __('Progress', 'lms4wp');
		$items['certificates'] = __('Certificates', 'lms4wp');

		// Add Account details and Orders back at the end (before logout)
		if ($account_details !== null) {
			$items['edit-account'] = $account_details;
		}
		if ($orders !== null) {
			$items['orders'] = $orders;
		}

		// Add logout back at the very end
		$items['customer-logout'] = $logout;

		return $items;
	}

	/**
	 * Render My Courses endpoint content
	 */
	public function renderMyCourses(): void
	{
		$user_id = get_current_user_id();
		
		// Get user enrollments
		global $wpdb;
		$enrollments_table = $wpdb->prefix . 'lms4wp_enrollments';
		
		$enrollments = $wpdb->get_results($wpdb->prepare(
			"SELECT course_id, enrolled_at, status 
			FROM {$enrollments_table} 
			WHERE user_id = %d AND status = 'active'
			ORDER BY enrolled_at DESC",
			$user_id
		));

		?>
		<div class="lms4wp-my-courses">
			<h2><?php esc_html_e('Courses', 'lms4wp'); ?></h2>
			
			<?php if (empty($enrollments)): ?>
				<p><?php esc_html_e('You are not enrolled in any courses yet.', 'lms4wp'); ?></p>
			<?php else: ?>
				<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
					<thead>
						<tr>
							<th class="woocommerce-orders-table__header"><?php esc_html_e('Course', 'lms4wp'); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e('Enrolled', 'lms4wp'); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e('Progress', 'lms4wp'); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e('Actions', 'lms4wp'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($enrollments as $enrollment): 
							$course = get_post($enrollment->course_id);
							if (!$course) continue;
							
							// Get course progress
							$progress_table = $wpdb->prefix . 'lms4wp_progress';
							$progress = $wpdb->get_var($wpdb->prepare(
								"SELECT AVG(progress_percentage) 
								FROM {$progress_table} 
								WHERE user_id = %d AND course_id = %d",
								$user_id,
								$enrollment->course_id
							));
							$progress = $progress ? round($progress) : 0;
							?>
							<tr class="woocommerce-orders-table__row">
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e('Course', 'lms4wp'); ?>">
									<a href="<?php echo esc_url(get_permalink($course->ID)); ?>">
										<?php echo esc_html($course->post_title); ?>
									</a>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e('Enrolled', 'lms4wp'); ?>">
									<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment->enrolled_at))); ?>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e('Progress', 'lms4wp'); ?>">
									<div class="lms4wp-progress-bar">
										<div class="lms4wp-progress-bar-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
										<span class="lms4wp-progress-bar-text"><?php echo esc_html($progress); ?>%</span>
									</div>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e('Actions', 'lms4wp'); ?>">
									<a href="<?php echo esc_url(get_permalink($course->ID)); ?>" class="woocommerce-button button">
										<?php esc_html_e('Continue', 'lms4wp'); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render My Progress endpoint content
	 */
	public function renderMyProgress(): void
	{
		$user_id = get_current_user_id();
		
		global $wpdb;
		$progress_table = $wpdb->prefix . 'lms4wp_progress';
		$enrollments_table = $wpdb->prefix . 'lms4wp_enrollments';
		
		// Get all progress records
		$progress_records = $wpdb->get_results($wpdb->prepare(
			"SELECT p.*, c.post_title as course_title, l.post_title as lesson_title
			FROM {$progress_table} p
			INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
			INNER JOIN {$wpdb->posts} l ON p.lesson_id = l.ID
			WHERE p.user_id = %d
			ORDER BY p.last_accessed DESC
			LIMIT 20",
			$user_id
		));

		?>
		<div class="lms4wp-my-progress">
			<h2><?php esc_html_e('Progress', 'lms4wp'); ?></h2>
			
			<?php if (empty($progress_records)): ?>
				<p><?php esc_html_e('No progress recorded yet.', 'lms4wp'); ?></p>
			<?php else: ?>
				<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive">
					<thead>
						<tr>
							<th><?php esc_html_e('Course', 'lms4wp'); ?></th>
							<th><?php esc_html_e('Lesson', 'lms4wp'); ?></th>
							<th><?php esc_html_e('Progress', 'lms4wp'); ?></th>
							<th><?php esc_html_e('Status', 'lms4wp'); ?></th>
							<th><?php esc_html_e('Last Accessed', 'lms4wp'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($progress_records as $record): ?>
							<tr>
								<td><?php echo esc_html($record->course_title); ?></td>
								<td>
									<a href="<?php echo esc_url(get_permalink($record->lesson_id)); ?>">
										<?php echo esc_html($record->lesson_title); ?>
									</a>
								</td>
								<td>
									<div class="lms4wp-progress-bar">
										<div class="lms4wp-progress-bar-fill" style="width: <?php echo esc_attr($record->progress_percentage); ?>%"></div>
										<span class="lms4wp-progress-bar-text"><?php echo esc_html($record->progress_percentage); ?>%</span>
									</div>
								</td>
								<td>
									<?php 
									if ($record->status === 'completed') {
										echo '<span class="lms4wp-status lms4wp-status-completed">' . esc_html__('Completed', 'lms4wp') . '</span>';
									} else {
										echo '<span class="lms4wp-status lms4wp-status-in-progress">' . esc_html__('In Progress', 'lms4wp') . '</span>';
									}
									?>
								</td>
								<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($record->last_accessed))); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Certificates endpoint content
	 */
	public function renderCertificates(): void
	{
		?>
		<div class="lms4wp-certificates">
			<h2><?php esc_html_e('Certificates', 'lms4wp'); ?></h2>
			<p><?php esc_html_e('Certificates will be displayed here once you complete courses.', 'lms4wp'); ?></p>
			<!-- Certificates functionality will be added later -->
		</div>
		<?php
	}

	/**
	 * Flush rewrite rules
	 */
	public function flushRewriteRules(): void
	{
		add_rewrite_endpoint('my-courses', EP_ROOT | EP_PAGES);
		add_rewrite_endpoint('my-progress', EP_ROOT | EP_PAGES);
		add_rewrite_endpoint('certificates', EP_ROOT | EP_PAGES);
		flush_rewrite_rules();
	}
}

