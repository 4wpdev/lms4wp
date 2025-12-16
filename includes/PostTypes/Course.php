<?php
/**
 * Course Post Type
 *
 * Handles course post type registration and functionality
 *
 * @package ForWP\LMS\PostTypes
 */

namespace ForWP\LMS\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Course post type class
 */
class Course
{
	/**
	 * Post type slug
	 */
	const POST_TYPE = 'lms_course';

	/**
	 * Initialize course post type
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'registerPostType'], 10);
		add_action('init', [$self, 'registerTaxonomies'], 10);
		add_action('add_meta_boxes', [$self, 'addMetaBoxes']);
		add_action('save_post_' . self::POST_TYPE, [$self, 'saveMetaBoxes'], 10, 2);
	}

	/**
	 * Register course post type
	 */
	public function registerPostType(): void
	{
		$labels = [
			'name'                  => _x('Courses', 'Post type general name', 'lms4wp'),
			'singular_name'         => _x('Course', 'Post type singular name', 'lms4wp'),
			'menu_name'             => _x('Courses', 'Admin Menu text', 'lms4wp'),
			'name_admin_bar'        => _x('Course', 'Add New on Toolbar', 'lms4wp'),
			'add_new'               => __('Add New', 'lms4wp'),
			'add_new_item'          => __('Add New Course', 'lms4wp'),
			'new_item'              => __('New Course', 'lms4wp'),
			'edit_item'             => __('Edit Course', 'lms4wp'),
			'view_item'             => __('View Course', 'lms4wp'),
			'all_items'             => __('All Courses', 'lms4wp'),
			'search_items'          => __('Search Courses', 'lms4wp'),
			'parent_item_colon'     => __('Parent Courses:', 'lms4wp'),
			'not_found'             => __('No courses found.', 'lms4wp'),
			'not_found_in_trash'    => __('No courses found in Trash.', 'lms4wp'),
			'featured_image'        => _x('Course Cover Image', 'Overrides the "Featured Image" phrase', 'lms4wp'),
			'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'lms4wp'),
			'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'lms4wp'),
			'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'lms4wp'),
			'archives'              => _x('Course archives', 'The post type archive label used in nav menus', 'lms4wp'),
			'insert_into_item'      => _x('Insert into course', 'Overrides the "Insert into post"/"Insert into page" phrase', 'lms4wp'),
			'uploaded_to_this_item' => _x('Uploaded to this course', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'lms4wp'),
			'filter_items_list'     => _x('Filter courses list', 'Screen reader text for the filter links', 'lms4wp'),
			'items_list_navigation' => _x('Courses list navigation', 'Screen reader text for the pagination', 'lms4wp'),
			'items_list'            => _x('Courses list', 'Screen reader text for the items list', 'lms4wp'),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => ['slug' => 'courses'],
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => true, // Allow parent courses (sub-courses)
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'supports'           => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'page-attributes', // For parent/child relationship
				'comments',
				'revisions',
			],
			'taxonomies'         => [
				'lms_direction', // Course direction/category
				'lms_level',     // Course level (junior/middle/senior)
			],
		];

		register_post_type(self::POST_TYPE, $args);
	}

	/**
	 * Register taxonomies for courses
	 */
	public function registerTaxonomies(): void
	{
		// Course Direction (напрямки)
		$direction_labels = [
			'name'              => _x('Directions', 'Taxonomy general name', 'lms4wp'),
			'singular_name'     => _x('Direction', 'Taxonomy singular name', 'lms4wp'),
			'search_items'      => __('Search Directions', 'lms4wp'),
			'all_items'         => __('All Directions', 'lms4wp'),
			'parent_item'       => __('Parent Direction', 'lms4wp'),
			'parent_item_colon' => __('Parent Direction:', 'lms4wp'),
			'edit_item'         => __('Edit Direction', 'lms4wp'),
			'update_item'       => __('Update Direction', 'lms4wp'),
			'add_new_item'      => __('Add New Direction', 'lms4wp'),
			'new_item_name'     => __('New Direction Name', 'lms4wp'),
			'menu_name'         => __('Directions', 'lms4wp'),
		];

		register_taxonomy(
			'lms_direction',
			[self::POST_TYPE],
			[
				'hierarchical'      => true,
				'labels'            => $direction_labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => ['slug' => 'course-direction'],
			]
		);

		// Course Level (рівні: junior/middle/senior)
		$level_labels = [
			'name'              => _x('Levels', 'Taxonomy general name', 'lms4wp'),
			'singular_name'     => _x('Level', 'Taxonomy singular name', 'lms4wp'),
			'search_items'      => __('Search Levels', 'lms4wp'),
			'all_items'         => __('All Levels', 'lms4wp'),
			'parent_item'       => __('Parent Level', 'lms4wp'),
			'parent_item_colon' => __('Parent Level:', 'lms4wp'),
			'edit_item'         => __('Edit Level', 'lms4wp'),
			'update_item'       => __('Update Level', 'lms4wp'),
			'add_new_item'      => __('Add New Level', 'lms4wp'),
			'new_item_name'     => __('New Level Name', 'lms4wp'),
			'menu_name'         => __('Levels', 'lms4wp'),
		];

		register_taxonomy(
			'lms_level',
			[self::POST_TYPE],
			[
				'hierarchical'      => true,
				'labels'            => $level_labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => ['slug' => 'course-level'],
			]
		);
	}

	/**
	 * Add meta boxes for course
	 */
	public function addMetaBoxes(): void
	{
		add_meta_box(
			'lms4wp_course_settings',
			__('Course Settings', 'lms4wp'),
			[$this, 'renderMetaBox'],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render course meta box
	 *
	 * @param \WP_Post $post Current post object
	 */
	public function renderMetaBox(\WP_Post $post): void
	{
		wp_nonce_field('lms4wp_course_meta', 'lms4wp_course_meta_nonce');

		$woocommerce_product_id = get_post_meta($post->ID, '_lms4wp_woocommerce_product_id', true);
		$prerequisites = get_post_meta($post->ID, '_lms4wp_prerequisites', true);
		$prerequisites = is_array($prerequisites) ? $prerequisites : [];

		// Get all courses for prerequisites dropdown
		$all_courses = get_posts([
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post__not_in'    => [$post->ID],
			'orderby'         => 'title',
			'order'           => 'ASC',
		]);
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="lms4wp_woocommerce_product_id">
						<?php esc_html_e('WooCommerce Product', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<?php if (class_exists('WooCommerce')) : ?>
						<select name="lms4wp_woocommerce_product_id" id="lms4wp_woocommerce_product_id" style="width: 100%;">
							<option value=""><?php esc_html_e('— Select Product —', 'lms4wp'); ?></option>
							<?php
							$products = wc_get_products(['limit' => -1]);
							foreach ($products as $product) {
								$selected = selected($woocommerce_product_id, $product->get_id(), false);
								printf(
									'<option value="%d"%s>%s</option>',
									esc_attr($product->get_id()),
									$selected,
									esc_html($product->get_name())
								);
							}
							?>
						</select>
						<p class="description">
							<?php esc_html_e('Associate this course with a WooCommerce product for selling.', 'lms4wp'); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e('WooCommerce is not installed. Install WooCommerce to sell courses.', 'lms4wp'); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_prerequisites">
						<?php esc_html_e('Prerequisites', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<select name="lms4wp_prerequisites[]" id="lms4wp_prerequisites" multiple="multiple" style="width: 100%; min-height: 100px;">
						<?php foreach ($all_courses as $course) : ?>
							<option value="<?php echo esc_attr($course->ID); ?>" <?php selected(in_array($course->ID, $prerequisites, true)); ?>>
								<?php echo esc_html($course->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e('Select courses that must be completed before accessing this course. Hold Ctrl/Cmd to select multiple.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save course meta boxes
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 */
	public function saveMetaBoxes(int $post_id, \WP_Post $post): void
	{
		// Verify nonce
		if (!isset($_POST['lms4wp_course_meta_nonce']) || !wp_verify_nonce($_POST['lms4wp_course_meta_nonce'], 'lms4wp_course_meta')) {
			return;
		}

		// Check autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Check permissions
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		// Save WooCommerce Product ID
		if (isset($_POST['lms4wp_woocommerce_product_id'])) {
			$product_id = absint($_POST['lms4wp_woocommerce_product_id']);
			if ($product_id > 0) {
				update_post_meta($post_id, '_lms4wp_woocommerce_product_id', $product_id);
			} else {
				delete_post_meta($post_id, '_lms4wp_woocommerce_product_id');
			}
		}

		// Save Prerequisites
		if (isset($_POST['lms4wp_prerequisites']) && is_array($_POST['lms4wp_prerequisites'])) {
			$prerequisites = array_map('absint', $_POST['lms4wp_prerequisites']);
			$prerequisites = array_filter($prerequisites);
			update_post_meta($post_id, '_lms4wp_prerequisites', $prerequisites);
		} else {
			delete_post_meta($post_id, '_lms4wp_prerequisites');
		}
	}
}

