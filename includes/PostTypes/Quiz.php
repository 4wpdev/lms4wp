<?php
/**
 * Quiz Post Type
 *
 * Handles quiz post type registration and functionality
 *
 * @package ForWP\LMS\PostTypes
 */

namespace ForWP\LMS\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Quiz post type class
 */
class Quiz
{
	/**
	 * Post type slug
	 */
	const POST_TYPE = 'lms_quiz';

	/**
	 * Initialize quiz post type
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'registerPostType'], 10);
		add_action('add_meta_boxes', [$self, 'addMetaBoxes']);
		add_action('save_post_' . self::POST_TYPE, [$self, 'saveMetaBoxes'], 10, 2);
	}

	/**
	 * Register quiz post type
	 */
	public function registerPostType(): void
	{
		$labels = [
			'name'                  => _x('Quizzes', 'Post type general name', 'lms4wp'),
			'singular_name'         => _x('Quiz', 'Post type singular name', 'lms4wp'),
			'menu_name'             => _x('Quizzes', 'Admin Menu text', 'lms4wp'),
			'name_admin_bar'        => _x('Quiz', 'Add New on Toolbar', 'lms4wp'),
			'add_new'               => __('Add New', 'lms4wp'),
			'add_new_item'          => __('Add New Quiz', 'lms4wp'),
			'new_item'              => __('New Quiz', 'lms4wp'),
			'edit_item'             => __('Edit Quiz', 'lms4wp'),
			'view_item'             => __('View Quiz', 'lms4wp'),
			'all_items'             => __('All Quizzes', 'lms4wp'),
			'search_items'          => __('Search Quizzes', 'lms4wp'),
			'not_found'             => __('No quizzes found.', 'lms4wp'),
			'not_found_in_trash'    => __('No quizzes found in Trash.', 'lms4wp'),
			'featured_image'        => _x('Quiz Cover Image', 'Overrides the "Featured Image" phrase', 'lms4wp'),
			'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'lms4wp'),
			'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'lms4wp'),
			'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'lms4wp'),
			'archives'              => _x('Quiz archives', 'The post type archive label used in nav menus', 'lms4wp'),
			'insert_into_item'      => _x('Insert into quiz', 'Overrides the "Insert into post"/"Insert into page" phrase', 'lms4wp'),
			'uploaded_to_this_item' => _x('Uploaded to this quiz', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'lms4wp'),
			'filter_items_list'     => _x('Filter quizzes list', 'Screen reader text for the filter links', 'lms4wp'),
			'items_list_navigation' => _x('Quizzes list navigation', 'Screen reader text for the pagination', 'lms4wp'),
			'items_list'            => _x('Quizzes list', 'Screen reader text for the items list', 'lms4wp'),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true, // Show as separate menu
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => ['slug' => 'quizzes'],
			'capability_type'    => 'lms_quiz',
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 29, // After Lessons (28)
			'menu_icon'          => 'dashicons-clipboard',
			'supports'           => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
			],
		];

		register_post_type(self::POST_TYPE, $args);
	}

	/**
	 * Add meta boxes for quiz
	 */
	public function addMetaBoxes(): void
	{
		add_meta_box(
			'lms4wp_quiz_settings',
			__('Quiz Settings', 'lms4wp'),
			[$this, 'renderMetaBox'],
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'lms4wp_quiz_questions',
			__('Quiz Questions', 'lms4wp'),
			[$this, 'renderQuestionsMetaBox'],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render quiz settings meta box
	 *
	 * @param \WP_Post $post Current post object
	 */
	public function renderMetaBox(\WP_Post $post): void
	{
		wp_nonce_field('lms4wp_quiz_meta', 'lms4wp_quiz_meta_nonce');

		$course_id = get_post_meta($post->ID, '_lms4wp_course_id', true);
		$lesson_id = get_post_meta($post->ID, '_lms4wp_lesson_id', true);
		$time_limit = get_post_meta($post->ID, '_lms4wp_time_limit', true);
		$passing_score = get_post_meta($post->ID, '_lms4wp_passing_score', true);
		$attempts_allowed = get_post_meta($post->ID, '_lms4wp_attempts_allowed', true);

		// Get all courses for dropdown
		$all_courses = get_posts([
			'post_type'      => Course::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);

		// Get lessons for selected course
		$lessons = [];
		if ($course_id) {
			$lessons = get_posts([
				'post_type'      => Lesson::POST_TYPE,
				'posts_per_page' => -1,
				'meta_key'       => '_lms4wp_course_id',
				'meta_value'     => $course_id,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [
					[
						'key'     => '_lms4wp_order',
						'compare' => 'EXISTS',
					],
				],
			]);
		}
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
						<option value=""><?php esc_html_e('— Select Course —', 'lms4wp'); ?></option>
						<?php foreach ($all_courses as $course) : ?>
							<option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_id, $course->ID); ?>>
								<?php echo esc_html($course->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_lesson_id">
						<?php esc_html_e('Lesson', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<select name="lms4wp_lesson_id" id="lms4wp_lesson_id" style="width: 100%;">
						<option value=""><?php esc_html_e('— Select Lesson —', 'lms4wp'); ?></option>
						<?php foreach ($lessons as $lesson) : ?>
							<option value="<?php echo esc_attr($lesson->ID); ?>" <?php selected($lesson_id, $lesson->ID); ?>>
								<?php echo esc_html($lesson->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e('Optional: Associate this quiz with a specific lesson.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_time_limit">
						<?php esc_html_e('Time Limit (minutes)', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input type="number" name="lms4wp_time_limit" id="lms4wp_time_limit" value="<?php echo esc_attr($time_limit); ?>" min="0" step="1" />
					<p class="description">
						<?php esc_html_e('Time limit for completing the quiz. Leave empty for no time limit.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_passing_score">
						<?php esc_html_e('Passing Score (%)', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input type="number" name="lms4wp_passing_score" id="lms4wp_passing_score" value="<?php echo esc_attr($passing_score); ?>" min="0" max="100" step="1" />
					<p class="description">
						<?php esc_html_e('Minimum score required to pass the quiz (0-100).', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_attempts_allowed">
						<?php esc_html_e('Attempts Allowed', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input type="number" name="lms4wp_attempts_allowed" id="lms4wp_attempts_allowed" value="<?php echo esc_attr($attempts_allowed); ?>" min="0" step="1" />
					<p class="description">
						<?php esc_html_e('Maximum number of attempts allowed. Leave empty or set to 0 for unlimited attempts.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render quiz questions meta box
	 *
	 * @param \WP_Post $post Current post object
	 */
	public function renderQuestionsMetaBox(\WP_Post $post): void
	{
		$questions = get_post_meta($post->ID, '_lms4wp_questions', true);
		$questions = is_array($questions) ? $questions : [];

		// For MVP: Simple JSON editor
		// Later: Rich UI for question management
		?>
		<div class="lms4wp-quiz-questions">
			<p class="description">
				<?php esc_html_e('Quiz questions are stored as JSON. For MVP, questions are managed via API. Full UI coming in future versions.', 'lms4wp'); ?>
			</p>
			<textarea name="lms4wp_questions_json" id="lms4wp_questions_json" rows="10" style="width: 100%; font-family: monospace;"><?php echo esc_textarea(wp_json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
			<p class="description">
				<?php esc_html_e('Question structure will be optimized for API interaction. Format: Array of question objects with type, text, options, correct_answer, etc.', 'lms4wp'); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save quiz meta boxes
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 */
	public function saveMetaBoxes(int $post_id, \WP_Post $post): void
	{
		// Verify nonce
		if (!isset($_POST['lms4wp_quiz_meta_nonce']) || !wp_verify_nonce($_POST['lms4wp_quiz_meta_nonce'], 'lms4wp_quiz_meta')) {
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

		// Save Lesson ID
		if (isset($_POST['lms4wp_lesson_id'])) {
			$lesson_id = absint($_POST['lms4wp_lesson_id']);
			if ($lesson_id > 0) {
				update_post_meta($post_id, '_lms4wp_lesson_id', $lesson_id);
			} else {
				delete_post_meta($post_id, '_lms4wp_lesson_id');
			}
		}

		// Save Time Limit
		if (isset($_POST['lms4wp_time_limit'])) {
			$time_limit = absint($_POST['lms4wp_time_limit']);
			if ($time_limit > 0) {
				update_post_meta($post_id, '_lms4wp_time_limit', $time_limit);
			} else {
				delete_post_meta($post_id, '_lms4wp_time_limit');
			}
		}

		// Save Passing Score
		if (isset($_POST['lms4wp_passing_score'])) {
			$passing_score = absint($_POST['lms4wp_passing_score']);
			if ($passing_score >= 0 && $passing_score <= 100) {
				update_post_meta($post_id, '_lms4wp_passing_score', $passing_score);
			} else {
				delete_post_meta($post_id, '_lms4wp_passing_score');
			}
		}

		// Save Attempts Allowed
		if (isset($_POST['lms4wp_attempts_allowed'])) {
			$attempts_allowed = absint($_POST['lms4wp_attempts_allowed']);
			update_post_meta($post_id, '_lms4wp_attempts_allowed', $attempts_allowed);
		}

		// Save Questions (JSON)
		if (isset($_POST['lms4wp_questions_json'])) {
			$questions_json = wp_unslash($_POST['lms4wp_questions_json']);
			$questions = json_decode($questions_json, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($questions)) {
				update_post_meta($post_id, '_lms4wp_questions', $questions);
			}
		}
	}
}


