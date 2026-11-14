<?php
/**
 * Module (course section) — CPT with a shadow taxonomy (ADR 0008).
 *
 * - lms_module: post_parent = course, menu_order = position, title, content (description), excerpt (goal).
 * - lms_module_tax: one term per module post (kept in sync automatically), assigned to lessons.
 *   Terms are not created by hand; the lesson editor only picks a module.
 *
 * @package ForWP\LMS\PostTypes
 */

namespace ForWP\LMS\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class Module
{
	const POST_TYPE   = 'lms_module';
	const TAXONOMY    = 'lms_module_tax';
	const META_KEY    = '_lms4wp_module_key';   // Hub folder key (module-1-basics).
	const TERM_META   = 'lms4wp_module_id';     // Term → module post.
	const POST_META_TERM = '_lms4wp_module_term'; // Module post → term.

	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'register'], 10);
		add_action('save_post_' . self::POST_TYPE, [self::class, 'syncTerm'], 20, 2);
		add_action('before_delete_post', [self::class, 'deleteTerm']);
		add_action('add_meta_boxes', [$self, 'addMetaBoxes']);
		add_action('save_post_' . self::POST_TYPE, [$self, 'saveMetaBox'], 10, 2);
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$self, 'columns']);
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$self, 'renderColumn'], 10, 2);
	}

	public function register(): void
	{
		register_post_type(self::POST_TYPE, [
			'labels'             => [
				'name'          => _x('Modules', 'Post type general name', 'lms4wp'),
				'singular_name' => _x('Module', 'Post type singular name', 'lms4wp'),
				'menu_name'     => _x('Modules', 'Admin Menu text', 'lms4wp'),
				'add_new_item'  => __('Add New Module', 'lms4wp'),
				'edit_item'     => __('Edit Module', 'lms4wp'),
				'all_items'     => __('Modules', 'lms4wp'),
				'search_items'  => __('Search Modules', 'lms4wp'),
				'not_found'     => __('No modules found.', 'lms4wp'),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=' . Course::POST_TYPE,
			'show_in_rest'       => true,
			'rewrite'            => false,
			'capability_type'    => 'lms_course',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'supports'           => ['title', 'editor', 'excerpt', 'page-attributes'],
		]);

		register_taxonomy(self::TAXONOMY, [Lesson::POST_TYPE], [
			'labels'            => [
				'name'          => _x('Modules', 'Taxonomy general name', 'lms4wp'),
				'singular_name' => _x('Module', 'Taxonomy singular name', 'lms4wp'),
				'menu_name'     => __('Modules', 'lms4wp'),
			],
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
			'query_var'         => self::TAXONOMY, // Admin lesson list filter (?lms_module_tax=…).
			// Terms mirror module posts: pick only, never create / edit by hand.
			'capabilities'      => [
				'manage_terms' => 'do_not_allow',
				'edit_terms'   => 'do_not_allow',
				'delete_terms' => 'do_not_allow',
				'assign_terms' => 'edit_lms_lessons',
			],
		]);
	}

	/**
	 * Term of a module post (0 — none yet).
	 */
	public static function termId(int $module_id): int
	{
		return (int) get_post_meta($module_id, self::POST_META_TERM, true);
	}

	/**
	 * Module post of a term (0 — none).
	 */
	public static function moduleIdForTerm(int $term_id): int
	{
		return (int) get_term_meta($term_id, self::TERM_META, true);
	}

	/**
	 * Module posts of a course, ordered.
	 *
	 * @return \WP_Post[]
	 */
	public static function forCourse(int $course_id, string $status = 'publish'): array
	{
		if ($course_id <= 0) {
			return [];
		}
		return get_posts([
			'post_type'        => self::POST_TYPE,
			'post_status'      => $status,
			'post_parent'      => $course_id,
			'posts_per_page'   => -1,
			'orderby'          => ['menu_order' => 'ASC', 'ID' => 'ASC'],
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);
	}

	/**
	 * Module post a lesson belongs to (0 — none).
	 */
	public static function forLesson(int $lesson_id): int
	{
		$terms = get_the_terms($lesson_id, self::TAXONOMY);
		if (!is_array($terms) || !$terms) {
			return 0;
		}
		return self::moduleIdForTerm((int) $terms[0]->term_id);
	}

	/**
	 * Put a lesson into a module (replaces its module).
	 */
	public static function assignLesson(int $lesson_id, int $module_id): void
	{
		$term_id = $module_id > 0 ? self::termId($module_id) : 0;
		wp_set_object_terms($lesson_id, $term_id > 0 ? [$term_id] : [], self::TAXONOMY);
	}

	/**
	 * Create / rename the shadow term of a module post.
	 */
	public static function syncTerm(int $post_id, \WP_Post $post): void
	{
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status === 'auto-draft') {
			return;
		}

		$course = $post->post_parent ? get_the_title($post->post_parent) : '';
		$name = $course !== '' ? sprintf('%s — %s', $course, $post->post_title) : $post->post_title;
		$slug = 'module-' . $post_id;
		$term_id = self::termId($post_id);

		if ($term_id > 0 && term_exists($term_id, self::TAXONOMY)) {
			wp_update_term($term_id, self::TAXONOMY, ['name' => $name, 'slug' => $slug]);
			return;
		}

		$created = wp_insert_term($name !== '' ? $name : $slug, self::TAXONOMY, ['slug' => $slug]);
		if (is_wp_error($created)) {
			$existing = get_term_by('slug', $slug, self::TAXONOMY);
			if (!$existing instanceof \WP_Term) {
				return;
			}
			$term_id = (int) $existing->term_id;
		} else {
			$term_id = (int) $created['term_id'];
		}
		update_term_meta($term_id, self::TERM_META, $post_id);
		update_post_meta($post_id, self::POST_META_TERM, $term_id);
	}

	/**
	 * Remove the shadow term with its module.
	 */
	public static function deleteTerm(int $post_id): void
	{
		if (get_post_type($post_id) !== self::POST_TYPE) {
			return;
		}
		$term_id = self::termId($post_id);
		if ($term_id > 0) {
			wp_delete_term($term_id, self::TAXONOMY);
		}
	}

	public function addMetaBoxes(): void
	{
		add_meta_box('lms4wp_module_course', __('Course', 'lms4wp'), [$this, 'renderMetaBox'], self::POST_TYPE, 'side', 'high');
	}

	public function renderMetaBox(\WP_Post $post): void
	{
		wp_nonce_field('lms4wp_module_meta', 'lms4wp_module_meta_nonce');
		$courses = get_posts([
			'post_type'      => Course::POST_TYPE,
			'post_status'    => ['publish', 'draft', 'private', 'pending'],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);
		echo '<select name="lms4wp_module_course" style="width:100%">';
		echo '<option value="0">' . esc_html__('— Select course —', 'lms4wp') . '</option>';
		foreach ($courses as $course) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $course->ID,
				selected($post->post_parent, $course->ID, false),
				esc_html($course->post_title)
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('Order: "Page Attributes → Order". Goal: excerpt.', 'lms4wp') . '</p>';
	}

	public function saveMetaBox(int $post_id, \WP_Post $post): void
	{
		if (!isset($_POST['lms4wp_module_meta_nonce'])
			|| !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lms4wp_module_meta_nonce'])), 'lms4wp_module_meta')
			|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			|| !current_user_can('edit_post', $post_id)
			|| !isset($_POST['lms4wp_module_course'])) {
			return;
		}
		$course_id = absint($_POST['lms4wp_module_course']);
		if ($course_id !== (int) $post->post_parent) {
			remove_action('save_post_' . self::POST_TYPE, [$this, 'saveMetaBox'], 10);
			wp_update_post(['ID' => $post_id, 'post_parent' => $course_id]);
			add_action('save_post_' . self::POST_TYPE, [$this, 'saveMetaBox'], 10, 2);
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function columns(array $columns): array
	{
		$out = [];
		foreach ($columns as $key => $label) {
			$out[$key] = $label;
			if ($key === 'title') {
				$out['lms4wp_course'] = __('Course', 'lms4wp');
				$out['lms4wp_order'] = __('Order', 'lms4wp');
				$out['lms4wp_lessons'] = __('Lessons', 'lms4wp');
			}
		}
		return $out;
	}

	public function renderColumn(string $column, int $post_id): void
	{
		$post = get_post($post_id);
		if (!$post) {
			return;
		}
		if ($column === 'lms4wp_course' && $post->post_parent) {
			printf('<a href="%s">%s</a>', esc_url((string) get_edit_post_link($post->post_parent)), esc_html(get_the_title($post->post_parent)));
		} elseif ($column === 'lms4wp_order') {
			echo esc_html((string) $post->menu_order);
		} elseif ($column === 'lms4wp_lessons') {
			$term_id = self::termId($post_id);
			$term = $term_id ? get_term($term_id, self::TAXONOMY) : null;
			$count = $term instanceof \WP_Term ? (int) $term->count : 0;
			printf(
				'<a href="%s">%d</a>',
				esc_url(add_query_arg([self::TAXONOMY => $term instanceof \WP_Term ? $term->slug : '', 'post_type' => Lesson::POST_TYPE], admin_url('edit.php'))),
				$count
			);
		}
	}
}
