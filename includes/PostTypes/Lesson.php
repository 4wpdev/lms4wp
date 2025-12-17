<?php
/**
 * Lesson Post Type
 *
 * Handles lesson post type registration and functionality
 *
 * @package ForWP\LMS\PostTypes
 */

namespace ForWP\LMS\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Lesson post type class
 */
class Lesson
{
	/**
	 * Post type slug
	 */
	const POST_TYPE = 'lms_lesson';

	/**
	 * Initialize lesson post type
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'registerPostType'], 10);
		add_action('add_meta_boxes', [$self, 'addMetaBoxes']);
		add_action('save_post_' . self::POST_TYPE, [$self, 'saveMetaBoxes'], 10, 2);
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$self, 'addColumns']);
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$self, 'renderColumns'], 10, 2);
	}

	/**
	 * Register lesson post type
	 */
	public function registerPostType(): void
	{
		$labels = [
			'name'                  => _x('Lessons', 'Post type general name', 'lms4wp'),
			'singular_name'         => _x('Lesson', 'Post type singular name', 'lms4wp'),
			'menu_name'             => _x('Lessons', 'Admin Menu text', 'lms4wp'),
			'name_admin_bar'        => _x('Lesson', 'Add New on Toolbar', 'lms4wp'),
			'add_new'               => __('Add New', 'lms4wp'),
			'add_new_item'          => __('Add New Lesson', 'lms4wp'),
			'new_item'              => __('New Lesson', 'lms4wp'),
			'edit_item'             => __('Edit Lesson', 'lms4wp'),
			'view_item'             => __('View Lesson', 'lms4wp'),
			'all_items'             => __('All Lessons', 'lms4wp'),
			'search_items'          => __('Search Lessons', 'lms4wp'),
			'not_found'             => __('No lessons found.', 'lms4wp'),
			'not_found_in_trash'    => __('No lessons found in Trash.', 'lms4wp'),
			'featured_image'        => _x('Lesson Cover Image', 'Overrides the "Featured Image" phrase', 'lms4wp'),
			'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'lms4wp'),
			'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'lms4wp'),
			'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'lms4wp'),
			'archives'              => _x('Lesson archives', 'The post type archive label used in nav menus', 'lms4wp'),
			'insert_into_item'      => _x('Insert into lesson', 'Overrides the "Insert into post"/"Insert into page" phrase', 'lms4wp'),
			'uploaded_to_this_item' => _x('Uploaded to this lesson', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'lms4wp'),
			'filter_items_list'     => _x('Filter lessons list', 'Screen reader text for the filter links', 'lms4wp'),
			'items_list_navigation' => _x('Lessons list navigation', 'Screen reader text for the pagination', 'lms4wp'),
			'items_list'            => _x('Lessons list', 'Screen reader text for the items list', 'lms4wp'),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true, // Show as separate menu
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => ['slug' => 'lessons'],
			'capability_type'    => 'lms_lesson',
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 28, // After Courses (27)
			'menu_icon'          => 'dashicons-book-alt',
			'supports'           => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'page-attributes', // For menu_order (lesson order)
				'comments',
				'revisions',
			],
		];

		register_post_type(self::POST_TYPE, $args);
	}

	/**
	 * Add meta boxes for lesson
	 */
	public function addMetaBoxes(): void
	{
		add_meta_box(
			'lms4wp_lesson_settings',
			__('Lesson Settings', 'lms4wp'),
			[$this, 'renderMetaBox'],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render lesson meta box
	 *
	 * @param \WP_Post $post Current post object
	 */
	public function renderMetaBox(\WP_Post $post): void
	{
		wp_nonce_field('lms4wp_lesson_meta', 'lms4wp_lesson_meta_nonce');

		$course_id = get_post_meta($post->ID, '_lms4wp_course_id', true);
		$order = get_post_meta($post->ID, '_lms4wp_order', true);
		$duration = get_post_meta($post->ID, '_lms4wp_duration', true);
		$video_url = get_post_meta($post->ID, '_lms4wp_video_url', true);
		$is_free = get_post_meta($post->ID, '_lms4wp_is_free', true);

		// Get all courses for dropdown
		$all_courses = get_posts([
			'post_type'      => Course::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="lms4wp_course_id">
						<?php esc_html_e('Course', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<select name="lms4wp_course_id" id="lms4wp_course_id" style="width: 100%;">
						<option value=""><?php esc_html_e('— Free Lesson (No Course) —', 'lms4wp'); ?></option>
						<?php foreach ($all_courses as $course) : ?>
							<option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_id, $course->ID); ?>>
								<?php echo esc_html($course->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e('Assign this lesson to a course. Leave empty for free/standalone lessons.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_order">
						<?php esc_html_e('Order', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input type="number" name="lms4wp_order" id="lms4wp_order" value="<?php echo esc_attr($order); ?>" min="0" step="1" />
					<p class="description">
						<?php esc_html_e('Lesson order within the course. Lower numbers appear first.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_duration">
						<?php esc_html_e('Duration (minutes)', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input type="number" name="lms4wp_duration" id="lms4wp_duration" value="<?php echo esc_attr($duration); ?>" min="0" step="1" />
					<p class="description">
						<?php esc_html_e('Estimated lesson duration in minutes.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_video_url">
						<?php esc_html_e('Video URL', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input type="url" name="lms4wp_video_url" id="lms4wp_video_url" value="<?php echo esc_url($video_url); ?>" class="regular-text" />
					<p class="description">
						<?php esc_html_e('Video URL for this lesson (YouTube, Vimeo, etc.).', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_is_free">
						<?php esc_html_e('Free Lesson', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="lms4wp_is_free" id="lms4wp_is_free" value="1" <?php checked($is_free, '1'); ?> />
						<?php esc_html_e('This lesson is free and accessible without enrollment.', 'lms4wp'); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save lesson meta boxes
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 */
	public function saveMetaBoxes(int $post_id, \WP_Post $post): void
	{
		// Verify nonce
		if (!isset($_POST['lms4wp_lesson_meta_nonce']) || !wp_verify_nonce($_POST['lms4wp_lesson_meta_nonce'], 'lms4wp_lesson_meta')) {
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

		// Save Course ID
		if (isset($_POST['lms4wp_course_id'])) {
			$course_id = absint($_POST['lms4wp_course_id']);
			if ($course_id > 0) {
				update_post_meta($post_id, '_lms4wp_course_id', $course_id);
			} else {
				delete_post_meta($post_id, '_lms4wp_course_id');
			}
		}

		// Save Order
		if (isset($_POST['lms4wp_order'])) {
			$order = absint($_POST['lms4wp_order']);
			update_post_meta($post_id, '_lms4wp_order', $order);
			// Also update menu_order for sorting
			wp_update_post([
				'ID'         => $post_id,
				'menu_order' => $order,
			]);
		}

		// Save Duration
		if (isset($_POST['lms4wp_duration'])) {
			$duration = absint($_POST['lms4wp_duration']);
			if ($duration > 0) {
				update_post_meta($post_id, '_lms4wp_duration', $duration);
			} else {
				delete_post_meta($post_id, '_lms4wp_duration');
			}
		}

		// Save Video URL
		if (isset($_POST['lms4wp_video_url'])) {
			$video_url = esc_url_raw($_POST['lms4wp_video_url']);
			if (!empty($video_url)) {
				update_post_meta($post_id, '_lms4wp_video_url', $video_url);
			} else {
				delete_post_meta($post_id, '_lms4wp_video_url');
			}
		}

		// Save Free Lesson flag
		$is_free = isset($_POST['lms4wp_is_free']) ? '1' : '';
		update_post_meta($post_id, '_lms4wp_is_free', $is_free);
	}

	/**
	 * Add custom columns to lessons list
	 *
	 * @param array $columns Existing columns
	 * @return array
	 */
	public function addColumns(array $columns): array
	{
		$new_columns = [];
		foreach ($columns as $key => $value) {
			$new_columns[$key] = $value;
			if ($key === 'title') {
				$new_columns['course'] = __('Course', 'lms4wp');
				$new_columns['order'] = __('Order', 'lms4wp');
				$new_columns['duration'] = __('Duration', 'lms4wp');
			}
		}
		return $new_columns;
	}

	/**
	 * Render custom column content
	 *
	 * @param string $column  Column name
	 * @param int    $post_id Post ID
	 */
	public function renderColumns(string $column, int $post_id): void
	{
		switch ($column) {
			case 'course':
				$course_id = get_post_meta($post_id, '_lms4wp_course_id', true);
				if ($course_id) {
					$course = get_post($course_id);
					if ($course) {
						printf(
							'<a href="%s">%s</a>',
							esc_url(get_edit_post_link($course_id)),
							esc_html($course->post_title)
						);
					}
				} else {
					echo '<span style="color: #999;">' . esc_html__('Free Lesson', 'lms4wp') . '</span>';
				}
				break;

			case 'order':
				$order = get_post_meta($post_id, '_lms4wp_order', true);
				echo esc_html($order ?: '—');
				break;

			case 'duration':
				$duration = get_post_meta($post_id, '_lms4wp_duration', true);
				if ($duration) {
					printf(
						/* translators: %d: minutes */
						esc_html__('%d min', 'lms4wp'),
						esc_html($duration)
					);
				} else {
					echo '—';
				}
				break;
		}
	}
}


