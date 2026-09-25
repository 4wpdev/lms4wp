<?php
/**
 * Practice Case Post Type
 *
 * Technical articles with terminal challenge workflows.
 *
 * @package ForWP\LMS\PostTypes
 */

namespace ForWP\LMS\PostTypes;

use ForWP\LMS\Content\PracticeCaseContent;
use ForWP\LMS\Taxonomies\PracticeCaseTaxonomies;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Practice Case post type class
 */
class PracticeCase
{
	/**
	 * Post type slug
	 */
	const POST_TYPE = 'practice_case';

	/**
	 * Prefix for generated post slugs and URLs
	 */
	const SLUG_PREFIX = 'practice-case';

	/**
	 * Bump when rewrite rules change (triggers flush on upgrade).
	 */
	const REWRITE_VERSION = '2';

	/**
	 * Meta keys
	 */
	const META_CASE_SLUG = '_lms4wp_practice_case_slug';
	const META_CASE_KEY = '_lms4wp_case_key';
	const META_GOAL = '_lms4wp_practice_goal';
	const META_TERMINAL_PROFILE = '_lms4wp_practice_terminal_profile';
	const META_ESTIMATED_TIME  = '_lms4wp_practice_estimated_time';
	const META_RELATED_DOC_URL = '_lms4wp_practice_related_doc_url';
	const META_AUTHOR_URL      = '_lms4wp_practice_author_url';
	const META_IMAGE           = '_lms4wp_practice_image';

	/**
	 * Shared LMS level taxonomy (same terms as courses).
	 */
	const LEVEL_TAXONOMY = 'lms_level';

	/**
	 * Option flag: default lms_level terms were seeded for practice cases.
	 */
	const OPTION_LEVEL_TERMS_SEEDED = 'lms4wp_practice_level_terms_seeded';

	/**
	 * @var bool Guard against recursive slug updates
	 */
	private static bool $updatingSlug = false;

	/**
	 * Initialize practice case post type
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'registerPostType'], 10);
		add_action('init', [$self, 'registerLevelTaxonomy'], 11);
		add_action('init', [$self, 'registerRewriteRules'], 11);
		add_action('add_meta_boxes', [$self, 'addMetaBoxes']);
		add_action('save_post_' . self::POST_TYPE, [$self, 'saveMetaBoxes'], 10, 2);
		add_action('save_post_' . self::POST_TYPE, [$self, 'maybeUpdatePostSlug'], 20, 2);
		add_filter('post_type_link', [$self, 'filterPermalink'], 10, 2);
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$self, 'addColumns']);
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$self, 'renderColumns'], 10, 2);
		add_action('admin_menu', [$self, 'registerAdminSubmenus'], 11);
		add_action('post_submitbox_misc_actions', [$self, 'renderSeedNotice']);
	}

	/**
	 * Admin menu slug when shown as a top-level item.
	 */
	public static function adminMenuSlug(): string
	{
		return 'edit.php?post_type=' . self::POST_TYPE;
	}

	/**
	 * Register practice case post type
	 */
	public function registerPostType(): void
	{
		$labels = [
			'name'                  => _x('Practice Cases', 'Post type general name', 'lms4wp'),
			'singular_name'         => _x('Practice Case', 'Post type singular name', 'lms4wp'),
			'menu_name'             => _x('Practices', 'Admin Menu text', 'lms4wp'),
			'name_admin_bar'        => _x('Practice Case', 'Add New on Toolbar', 'lms4wp'),
			'add_new'               => __('Add New', 'lms4wp'),
			'add_new_item'          => __('Add New Practice Case', 'lms4wp'),
			'new_item'              => __('New Practice Case', 'lms4wp'),
			'edit_item'             => __('Edit Practice Case', 'lms4wp'),
			'view_item'             => __('View Practice Case', 'lms4wp'),
			'all_items'             => __('All Practices', 'lms4wp'),
			'search_items'          => __('Search Practice Cases', 'lms4wp'),
			'not_found'             => __('No practice cases found.', 'lms4wp'),
			'not_found_in_trash'    => __('No practice cases found in Trash.', 'lms4wp'),
			'featured_image'        => _x('Cover Image', 'Overrides the "Featured Image" phrase', 'lms4wp'),
			'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'lms4wp'),
			'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'lms4wp'),
			'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'lms4wp'),
			'archives'              => _x('Practice Case archives', 'The post type archive label used in nav menus', 'lms4wp'),
			'insert_into_item'      => _x('Insert into practice case', 'Overrides the "Insert into post"/"Insert into page" phrase', 'lms4wp'),
			'uploaded_to_this_item' => _x('Uploaded to this practice case', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'lms4wp'),
			'filter_items_list'     => _x('Filter practice cases list', 'Screen reader text for the filter links', 'lms4wp'),
			'items_list_navigation' => _x('Practice cases list navigation', 'Screen reader text for the pagination', 'lms4wp'),
			'items_list'            => _x('Practice cases list', 'Screen reader text for the items list', 'lms4wp'),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			// Flat URLs are handled by registerRewriteRules(); CPT rewrite is disabled intentionally.
			'rewrite'            => false,
			'capability_type'    => 'practice_case',
			'map_meta_cap'       => true,
			'has_archive'        => 'practice-cases',
			'hierarchical'       => false,
			'menu_position'      => 27,
			'menu_icon'          => 'dashicons-editor-code',
			'supports'           => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
			],
			'taxonomies'         => [
				PracticeCaseTaxonomies::TAX_TOOL,
				PracticeCaseTaxonomies::TAX_CATEGORY,
				self::LEVEL_TAXONOMY,
				'post_tag',
			],
		];

		register_post_type(self::POST_TYPE, $args);

		$this->registerPostMeta();
	}

	/**
	 * Attach shared lms_level taxonomy and seed beginner/intermediate/advanced terms.
	 */
	public function registerLevelTaxonomy(): void
	{
		register_taxonomy_for_object_type(self::LEVEL_TAXONOMY, self::POST_TYPE);
		$this->maybeSeedLevelTerms();
	}

	/**
	 * Seed default lms_level terms once (beginner, intermediate, advanced).
	 */
	public function maybeSeedLevelTerms(): void
	{
		if (get_option(self::OPTION_LEVEL_TERMS_SEEDED)) {
			return;
		}

		self::seedLevelTerms();
		update_option(self::OPTION_LEVEL_TERMS_SEEDED, '1', false);
	}

	/**
	 * Root-level permalinks: /practice-case-wp-cli-deploy-new-site/
	 *
	 * WordPress CPT rewrite slug "." is unreliable; explicit rules are required.
	 */
	public function registerRewriteRules(): void
	{
		$prefix = preg_quote(self::SLUG_PREFIX, '/');

		add_rewrite_rule(
			'^(' . $prefix . '-[^/]+)/?$',
			'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^practice-cases/?$',
			'index.php?post_type=' . self::POST_TYPE,
			'top'
		);
	}

	/**
	 * Register post meta for REST/block editor use
	 */
	private function registerPostMeta(): void
	{
		$string_meta = [
			self::META_CASE_KEY,
			self::META_CASE_SLUG,
			self::META_GOAL,
			self::META_TERMINAL_PROFILE,
			self::META_ESTIMATED_TIME,
			self::META_RELATED_DOC_URL,
		];

		foreach ($string_meta as $meta_key) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => static function (bool $allowed, string $meta_key, int $post_id): bool {
						return current_user_can('edit_post', $post_id);
					},
					'sanitize_callback' => 'sanitize_text_field',
				]
			);
		}

		register_post_meta(
			self::POST_TYPE,
			PracticeCaseContent::META_SECTIONS,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function (bool $allowed, string $meta_key, int $post_id): bool {
					return current_user_can('edit_post', $post_id);
				},
			]
		);
	}

	/**
	 * Add meta boxes
	 */
	public function addMetaBoxes(): void
	{
		add_meta_box(
			'lms4wp_practice_case_settings',
			__('Practice Case Settings', 'lms4wp'),
			[$this, 'renderMetaBox'],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render practice case meta box
	 *
	 * @param \WP_Post $post Current post object
	 */
	public function renderMetaBox(\WP_Post $post): void
	{
		wp_nonce_field('lms4wp_practice_case_meta', 'lms4wp_practice_case_meta_nonce');

		$case_slug = get_post_meta($post->ID, self::META_CASE_SLUG, true);
		$goal = get_post_meta($post->ID, self::META_GOAL, true);
		$terminal_profile = get_post_meta($post->ID, self::META_TERMINAL_PROFILE, true);
		$estimated_time   = get_post_meta($post->ID, self::META_ESTIMATED_TIME, true);
		$related_doc_url  = get_post_meta($post->ID, self::META_RELATED_DOC_URL, true);

		$preview_slug = $this->buildPostSlug($post->ID, (string) $case_slug);
		$preview_url = $preview_slug ? home_url('/' . $preview_slug . '/') : '';
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="lms4wp_practice_case_slug">
						<?php esc_html_e('Case slug', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						name="lms4wp_practice_case_slug"
						id="lms4wp_practice_case_slug"
						value="<?php echo esc_attr($case_slug); ?>"
						class="regular-text"
						placeholder="export-database"
					/>
					<p class="description">
						<?php esc_html_e('Short identifier for this case. Combined with tool and category taxonomies to build the post URL.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e('Expected URL', 'lms4wp'); ?>
				</th>
				<td>
					<?php if ($preview_url) : ?>
						<code><?php echo esc_html($preview_url); ?></code>
					<?php else : ?>
						<span class="description">
							<?php esc_html_e('Set case slug and assign Tool + Category taxonomies to preview the URL.', 'lms4wp'); ?>
						</span>
					<?php endif; ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: slug pattern example */
							esc_html__('Pattern: %s', 'lms4wp'),
							esc_html(self::SLUG_PREFIX . '-{tool}-{category}-{case-slug}')
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_practice_goal">
						<?php esc_html_e('Goal', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<textarea
						name="lms4wp_practice_goal"
						id="lms4wp_practice_goal"
						rows="3"
						class="large-text"
					><?php echo esc_textarea($goal); ?></textarea>
					<p class="description">
						<?php esc_html_e('What the learner should accomplish in this practice case.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_practice_terminal_profile">
						<?php esc_html_e('Terminal profile', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						name="lms4wp_practice_terminal_profile"
						id="lms4wp_practice_terminal_profile"
						value="<?php echo esc_attr($terminal_profile); ?>"
						class="regular-text"
						placeholder="challenge-wp-cli-deploy-new-site"
					/>
					<p class="description">
						<?php esc_html_e('Slug of the challenge JSON profile in 4WP Advanced Code terminal.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_practice_estimated_time">
						<?php esc_html_e('Estimated time', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						name="lms4wp_practice_estimated_time"
						id="lms4wp_practice_estimated_time"
						value="<?php echo esc_attr($estimated_time); ?>"
						class="regular-text"
						placeholder="15 min"
					/>
					<p class="description">
						<?php esc_html_e('Per-case duration label (e.g. "10 min"). Level is set via the Levels taxonomy in the sidebar.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lms4wp_practice_related_doc_url">
						<?php esc_html_e('Related documentation URL', 'lms4wp'); ?>
					</label>
				</th>
				<td>
					<input
						type="url"
						name="lms4wp_practice_related_doc_url"
						id="lms4wp_practice_related_doc_url"
						value="<?php echo esc_url($related_doc_url); ?>"
						class="large-text"
						placeholder="<?php echo esc_attr(home_url('/wp-cli/database/')); ?>"
					/>
					<p class="description">
						<?php esc_html_e('Internal group page or official handbook link related to this case.', 'lms4wp'); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save practice case meta boxes
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 */
	public function saveMetaBoxes(int $post_id, \WP_Post $post): void
	{
		if (!isset($_POST['lms4wp_practice_case_meta_nonce']) || !wp_verify_nonce($_POST['lms4wp_practice_case_meta_nonce'], 'lms4wp_practice_case_meta')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		if (isset($_POST['lms4wp_practice_case_slug'])) {
			$case_slug = sanitize_title(wp_unslash($_POST['lms4wp_practice_case_slug']));
			if ($case_slug !== '') {
				update_post_meta($post_id, self::META_CASE_SLUG, $case_slug);
			} else {
				delete_post_meta($post_id, self::META_CASE_SLUG);
			}
		}

		if (isset($_POST['lms4wp_practice_goal'])) {
			$goal = sanitize_textarea_field(wp_unslash($_POST['lms4wp_practice_goal']));
			if ($goal !== '') {
				update_post_meta($post_id, self::META_GOAL, $goal);
			} else {
				delete_post_meta($post_id, self::META_GOAL);
			}
		}

		if (isset($_POST['lms4wp_practice_terminal_profile'])) {
			$profile = sanitize_title(wp_unslash($_POST['lms4wp_practice_terminal_profile']));
			if ($profile !== '') {
				update_post_meta($post_id, self::META_TERMINAL_PROFILE, $profile);
			} else {
				delete_post_meta($post_id, self::META_TERMINAL_PROFILE);
			}
		}

		if (isset($_POST['lms4wp_practice_estimated_time'])) {
			$estimated_time = sanitize_text_field(wp_unslash($_POST['lms4wp_practice_estimated_time']));
			if ($estimated_time !== '') {
				update_post_meta($post_id, self::META_ESTIMATED_TIME, $estimated_time);
			} else {
				delete_post_meta($post_id, self::META_ESTIMATED_TIME);
			}
		}

		if (isset($_POST['lms4wp_practice_related_doc_url'])) {
			$url = esc_url_raw(wp_unslash($_POST['lms4wp_practice_related_doc_url']));
			if ($url !== '') {
				update_post_meta($post_id, self::META_RELATED_DOC_URL, $url);
			} else {
				delete_post_meta($post_id, self::META_RELATED_DOC_URL);
			}
		}
	}

	/**
	 * Build and persist post slug from taxonomies + case slug meta
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 */
	public function maybeUpdatePostSlug(int $post_id, \WP_Post $post): void
	{
		if (self::$updatingSlug || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		$case_slug = (string) get_post_meta($post_id, self::META_CASE_SLUG, true);
		$new_slug = $this->buildPostSlug($post_id, $case_slug);

		if ($new_slug === '' || $post->post_name === $new_slug) {
			return;
		}

		self::$updatingSlug = true;

		wp_update_post(
			[
				'ID'        => $post_id,
				'post_name' => $new_slug,
			]
		);

		self::$updatingSlug = false;
	}

	/**
	 * Use flat root permalink without CPT prefix
	 *
	 * @param string   $post_link Generated permalink
	 * @param \WP_Post $post      Post object
	 * @return string
	 */
	public function filterPermalink(string $post_link, \WP_Post $post): string
	{
		if ($post->post_type !== self::POST_TYPE || $post->post_status === 'draft') {
			return $post_link;
		}

		if ($post->post_name === '') {
			return $post_link;
		}

		return home_url('/' . $post->post_name . '/');
	}

	/**
	 * Add admin list columns
	 *
	 * @param array<string, string> $columns Existing columns
	 * @return array<string, string>
	 */
	public function addColumns(array $columns): array
	{
		$new_columns = [];

		foreach ($columns as $key => $label) {
			$new_columns[$key] = $label;

			if ($key === 'title') {
				$new_columns['lms4wp_practice_source'] = __('Source', 'lms4wp');
				$new_columns['lms4wp_practice_slug'] = __('URL slug', 'lms4wp');
				$new_columns['lms4wp_practice_terminal'] = __('Terminal profile', 'lms4wp');
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom admin list columns
	 *
	 * @param string $column  Column key
	 * @param int    $post_id Post ID
	 */
	public function renderColumns(string $column, int $post_id): void
	{
		if ($column === 'lms4wp_practice_source') {
			$case_key = get_post_meta($post_id, self::META_CASE_KEY, true);
			if ($case_key !== '') {
				echo '<code>' . esc_html((string) $case_key) . '</code>';
			} else {
				echo esc_html__('Manual', 'lms4wp');
			}
			return;
		}

		if ($column === 'lms4wp_practice_slug') {
			$post = get_post($post_id);
			if ($post instanceof \WP_Post && $post->post_name !== '') {
				echo '<code>' . esc_html($post->post_name) . '</code>';
			}
			return;
		}

		if ($column === 'lms4wp_practice_terminal') {
			$profile = get_post_meta($post_id, self::META_TERMINAL_PROFILE, true);
			if ($profile !== '') {
				echo '<code>' . esc_html((string) $profile) . '</code>';
			} else {
				echo '&mdash;';
			}
		}
	}

	/**
	 * Taxonomy screens under the Practices top-level menu.
	 */
	public function registerAdminSubmenus(): void
	{
		add_submenu_page(
			self::adminMenuSlug(),
			__('Tools', 'lms4wp'),
			__('Tools', 'lms4wp'),
			'edit_practice_cases',
			'edit-tags.php?taxonomy=' . PracticeCaseTaxonomies::TAX_TOOL . '&post_type=' . self::POST_TYPE
		);

		add_submenu_page(
			self::adminMenuSlug(),
			__('Case Categories', 'lms4wp'),
			__('Categories', 'lms4wp'),
			'edit_practice_cases',
			'edit-tags.php?taxonomy=' . PracticeCaseTaxonomies::TAX_CATEGORY . '&post_type=' . self::POST_TYPE
		);

		add_submenu_page(
			self::adminMenuSlug(),
			__('Levels', 'lms4wp'),
			__('Levels', 'lms4wp'),
			'edit_practice_cases',
			'edit-tags.php?taxonomy=' . self::LEVEL_TAXONOMY . '&post_type=' . self::POST_TYPE
		);
	}

	/**
	 * Show resync hint for posts seeded from content files.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function renderSeedNotice(\WP_Post $post): void
	{
		if ($post->post_type !== self::POST_TYPE) {
			return;
		}

		$case_key = get_post_meta($post->ID, self::META_CASE_KEY, true);
		if ($case_key === '') {
			return;
		}

		echo '<div class="misc-pub-section">';
		printf(
			'<strong>%s</strong><br><code>%s</code><br><span class="description">%s</span>',
			esc_html__('Seeded from file', 'lms4wp'),
			esc_html((string) $case_key),
			esc_html(sprintf(
				/* translators: %s: WP-CLI command example */
				__('Resync: wp lms4wp practice-case seed %s --force', 'lms4wp'),
				(string) $case_key
			))
		);
		echo '</div>';
	}

	/**
	 * Build full post slug from taxonomies and case slug
	 *
	 * @param int    $post_id   Post ID
	 * @param string $case_slug Short case slug
	 * @return string
	 */
	public function buildPostSlug(int $post_id, string $case_slug): string
	{
		$case_slug = sanitize_title($case_slug);
		if ($case_slug === '') {
			return '';
		}

		$tool_slug = PracticeCaseTaxonomies::getPrimaryTermSlug($post_id, PracticeCaseTaxonomies::TAX_TOOL);
		$category_slug = PracticeCaseTaxonomies::getPrimaryTermSlug($post_id, PracticeCaseTaxonomies::TAX_CATEGORY);

		if ($tool_slug === '' || $category_slug === '') {
			return '';
		}

		return sanitize_title(
			self::SLUG_PREFIX . '-' . $tool_slug . '-' . $category_slug . '-' . $case_slug
		);
	}

	/**
	 * Practice case level slugs (lms_level taxonomy).
	 *
	 * @return array<string, string> slug => label
	 */
	public static function getLevelOptions(): array
	{
		return [
			'beginner'     => __('Beginner', 'lms4wp'),
			'intermediate' => __('Intermediate', 'lms4wp'),
			'advanced'     => __('Advanced', 'lms4wp'),
		];
	}

	/**
	 * Create default lms_level terms for practice cases.
	 */
	public static function seedLevelTerms(): void
	{
		foreach (self::getLevelOptions() as $slug => $name) {
			self::ensureLevelTerm($slug, $name);
		}
	}

	/**
	 * Ensure an lms_level term exists.
	 *
	 * @param string      $slug Term slug.
	 * @param string|null $name Term name; defaults to label from getLevelOptions().
	 * @return int Term ID or 0.
	 */
	public static function ensureLevelTerm(string $slug, ?string $name = null): int
	{
		$slug = sanitize_key($slug);
		if ($slug === '') {
			return 0;
		}

		$options = self::getLevelOptions();
		if ($name === null) {
			$name = $options[$slug] ?? $slug;
		}

		$existing = get_term_by('slug', $slug, self::LEVEL_TAXONOMY);
		if ($existing instanceof \WP_Term) {
			return (int) $existing->term_id;
		}

		$result = wp_insert_term(
			$name,
			self::LEVEL_TAXONOMY,
			[
				'slug' => $slug,
			]
		);

		if (is_wp_error($result)) {
			return 0;
		}

		return (int) ($result['term_id'] ?? 0);
	}

	/**
	 * Assign lms_level from JSON difficulty slug.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $slug    Level slug (beginner, intermediate, advanced).
	 */
	public static function assignLevelFromSlug(int $post_id, string $slug): void
	{
		$slug = sanitize_key($slug);
		$options = self::getLevelOptions();

		if (!isset($options[$slug])) {
			return;
		}

		self::ensureLevelTerm($slug, $options[$slug]);
		wp_set_object_terms($post_id, $slug, self::LEVEL_TAXONOMY, false);
	}
}
