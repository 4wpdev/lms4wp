<?php
/**
 * Practice Case taxonomies: Tool and Category
 *
 * @package ForWP\LMS\Taxonomies
 */

namespace ForWP\LMS\Taxonomies;

use ForWP\LMS\PostTypes\PracticeCase;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Practice Case taxonomies class
 */
class PracticeCaseTaxonomies
{
	/**
	 * Tool taxonomy (e.g. wp-cli)
	 */
	const TAX_TOOL = 'lms_practice_tool';

	/**
	 * Category taxonomy (e.g. database, deploy)
	 */
	const TAX_CATEGORY = 'lms_practice_category';

	/**
	 * Term meta: linked documentation page ID
	 */
	const META_DOC_PAGE_ID = '_lms4wp_practice_doc_page_id';

	/**
	 * Option flag: default terms were seeded
	 */
	const OPTION_TERMS_SEEDED = 'lms4wp_practice_terms_seeded';

	/**
	 * Initialize taxonomies
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'register'], 9);
		add_action('init', [$self, 'maybeSeedDefaultTerms'], 20);

		foreach ([self::TAX_TOOL, self::TAX_CATEGORY] as $taxonomy) {
			add_action("{$taxonomy}_add_form_fields", [$self, 'renderTermAddFields']);
			add_action("{$taxonomy}_edit_form_fields", [$self, 'renderTermEditFields'], 10, 2);
			add_filter("manage_edit-{$taxonomy}_columns", [$self, 'addTermColumns']);
			add_filter("manage_{$taxonomy}_custom_column", [$self, 'renderTermColumns'], 10, 3);
		}

		add_action('created_term', [$self, 'saveTermMeta'], 10, 3);
		add_action('edited_term', [$self, 'saveTermMeta'], 10, 3);
	}

	/**
	 * Register tool and category taxonomies
	 */
	public function register(): void
	{
		$tool_labels = [
			'name'                       => _x('Tools', 'Taxonomy general name', 'lms4wp'),
			'singular_name'              => _x('Tool', 'Taxonomy singular name', 'lms4wp'),
			'search_items'               => __('Search Tools', 'lms4wp'),
			'popular_items'              => __('Popular Tools', 'lms4wp'),
			'all_items'                  => __('All Tools', 'lms4wp'),
			'parent_item'                => __('Parent Tool', 'lms4wp'),
			'parent_item_colon'          => __('Parent Tool:', 'lms4wp'),
			'edit_item'                  => __('Edit Tool', 'lms4wp'),
			'view_item'                  => __('View Tool', 'lms4wp'),
			'update_item'                => __('Update Tool', 'lms4wp'),
			'add_new_item'               => __('Add New Tool', 'lms4wp'),
			'new_item_name'              => __('New Tool Name', 'lms4wp'),
			'separate_items_with_commas' => __('Separate tools with commas', 'lms4wp'),
			'add_or_remove_items'        => __('Add or remove tools', 'lms4wp'),
			'choose_from_most_used'      => __('Choose from the most used tools', 'lms4wp'),
			'not_found'                  => __('No tools found.', 'lms4wp'),
			'no_terms'                   => __('No tools', 'lms4wp'),
			'menu_name'                  => __('Tools', 'lms4wp'),
			'items_list_navigation'      => __('Tools list navigation', 'lms4wp'),
			'items_list'                 => __('Tools list', 'lms4wp'),
			'back_to_items'              => __('&larr; Back to Tools', 'lms4wp'),
		];

		register_taxonomy(
			self::TAX_TOOL,
			[PracticeCase::POST_TYPE],
			[
				'hierarchical'      => true,
				'labels'            => $tool_labels,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => false,
				'show_in_rest'      => true,
				'query_var'         => true,
				'show_in_menu'      => false,
				'rewrite'           => [
					'slug'         => 'practice-tool',
					'with_front'   => false,
					'hierarchical' => true,
				],
				'capabilities'      => $this->getTermCapabilities(),
			]
		);

		$category_labels = [
			'name'                       => _x('Case Categories', 'Taxonomy general name', 'lms4wp'),
			'singular_name'              => _x('Case Category', 'Taxonomy singular name', 'lms4wp'),
			'search_items'               => __('Search Categories', 'lms4wp'),
			'popular_items'              => __('Popular Categories', 'lms4wp'),
			'all_items'                  => __('All Categories', 'lms4wp'),
			'parent_item'                => __('Parent Category', 'lms4wp'),
			'parent_item_colon'          => __('Parent Category:', 'lms4wp'),
			'edit_item'                  => __('Edit Category', 'lms4wp'),
			'view_item'                  => __('View Category', 'lms4wp'),
			'update_item'                => __('Update Category', 'lms4wp'),
			'add_new_item'               => __('Add New Category', 'lms4wp'),
			'new_item_name'              => __('New Category Name', 'lms4wp'),
			'separate_items_with_commas' => __('Separate categories with commas', 'lms4wp'),
			'add_or_remove_items'        => __('Add or remove categories', 'lms4wp'),
			'choose_from_most_used'      => __('Choose from the most used categories', 'lms4wp'),
			'not_found'                  => __('No categories found.', 'lms4wp'),
			'no_terms'                   => __('No categories', 'lms4wp'),
			'menu_name'                  => __('Categories', 'lms4wp'),
			'items_list_navigation'      => __('Categories list navigation', 'lms4wp'),
			'items_list'                 => __('Categories list', 'lms4wp'),
			'back_to_items'              => __('&larr; Back to Categories', 'lms4wp'),
		];

		register_taxonomy(
			self::TAX_CATEGORY,
			[PracticeCase::POST_TYPE],
			[
				'hierarchical'      => true,
				'labels'            => $category_labels,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => false,
				'show_in_rest'      => true,
				'query_var'         => true,
				'show_in_menu'      => false,
				'rewrite'           => [
					'slug'         => 'practice-category',
					'with_front'   => false,
					'hierarchical' => true,
				],
				'capabilities'      => $this->getTermCapabilities(),
			]
		);
	}

	/**
	 * Seed default tool and category terms once
	 */
	public function maybeSeedDefaultTerms(): void
	{
		if (get_option(self::OPTION_TERMS_SEEDED)) {
			return;
		}

		self::seedDefaultTerms();
		update_option(self::OPTION_TERMS_SEEDED, '1', false);
	}

	/**
	 * Create default taxonomy terms for WP-CLI practice cases
	 */
	public static function seedDefaultTerms(): void
	{
		$wp_cli = self::ensureTerm(self::TAX_TOOL, 'wp-cli', 'WP-CLI');

		$command_groups = [
			'core'     => 'Core',
			'database' => 'Database',
			'plugins'  => 'Plugins',
			'themes'   => 'Themes',
			'users'    => 'Users',
			'cache'    => 'Cache',
		];

		foreach ($command_groups as $slug => $name) {
			self::ensureTerm(self::TAX_CATEGORY, $slug, $name);
		}

		$workflow_groups = [
			'deploy'      => 'Deploy',
			'maintenance' => 'Maintenance',
			'migration'   => 'Migration',
			'security'    => 'Security',
			'automation'  => 'Automation',
		];

		foreach ($workflow_groups as $slug => $name) {
			self::ensureTerm(self::TAX_CATEGORY, $slug, $name);
		}

		if ($wp_cli > 0) {
			$wp_cli_page = get_page_by_path('wp-cli');
			if ($wp_cli_page instanceof \WP_Post) {
				update_term_meta($wp_cli, self::META_DOC_PAGE_ID, (int) $wp_cli_page->ID);
			}
		}

		foreach ($command_groups as $slug => $name) {
			$term = get_term_by('slug', $slug, self::TAX_CATEGORY);
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$page = get_page_by_path('wp-cli/' . $slug);
			if ($page instanceof \WP_Post) {
				update_term_meta((int) $term->term_id, self::META_DOC_PAGE_ID, (int) $page->ID);
			}
		}
	}

	/**
	 * Render fields on "Add term" screen
	 *
	 * @param string $taxonomy Taxonomy slug
	 */
	public function renderTermAddFields(string $taxonomy): void
	{
		if (!in_array($taxonomy, [self::TAX_TOOL, self::TAX_CATEGORY], true)) {
			return;
		}
		?>
		<div class="form-field">
			<label for="lms4wp_practice_doc_page_id">
				<?php esc_html_e('Documentation page', 'lms4wp'); ?>
			</label>
			<?php $this->renderPageSelect(0); ?>
			<p class="description">
				<?php esc_html_e('Optional link to the related documentation page (e.g. /wp-cli/database/).', 'lms4wp'); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render fields on "Edit term" screen
	 *
	 * @param \WP_Term $term     Term object
	 * @param string   $taxonomy Taxonomy slug
	 */
	public function renderTermEditFields(\WP_Term $term, string $taxonomy): void
	{
		if (!in_array($taxonomy, [self::TAX_TOOL, self::TAX_CATEGORY], true)) {
			return;
		}

		$page_id = (int) get_term_meta($term->term_id, self::META_DOC_PAGE_ID, true);
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="lms4wp_practice_doc_page_id">
					<?php esc_html_e('Documentation page', 'lms4wp'); ?>
				</label>
			</th>
			<td>
				<?php $this->renderPageSelect($page_id); ?>
				<p class="description">
					<?php esc_html_e('Optional link to the related documentation page (e.g. /wp-cli/database/).', 'lms4wp'); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta
	 *
	 * @param int    $term_id  Term ID
	 * @param int    $tt_id    Term taxonomy ID
	 * @param string $taxonomy Taxonomy slug
	 */
	public function saveTermMeta(int $term_id, int $tt_id, string $taxonomy): void
	{
		unset($tt_id);

		if (!in_array($taxonomy, [self::TAX_TOOL, self::TAX_CATEGORY], true)) {
			return;
		}

		if (!current_user_can('edit_practice_cases')) {
			return;
		}

		if (!isset($_POST['lms4wp_practice_doc_page_id'])) {
			return;
		}

		$page_id = absint(wp_unslash($_POST['lms4wp_practice_doc_page_id']));
		if ($page_id > 0) {
			update_term_meta($term_id, self::META_DOC_PAGE_ID, $page_id);
		} else {
			delete_term_meta($term_id, self::META_DOC_PAGE_ID);
		}
	}

	/**
	 * Get primary term slug for a post
	 *
	 * @param int    $post_id  Post ID
	 * @param string $taxonomy Taxonomy slug
	 * @return string
	 */
	public static function getPrimaryTermSlug(int $post_id, string $taxonomy): string
	{
		$term = self::getPrimaryTerm($post_id, $taxonomy);
		return $term instanceof \WP_Term ? (string) $term->slug : '';
	}

	/**
	 * Get primary assigned term object for a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return \WP_Term|null
	 */
	public static function getPrimaryTerm(int $post_id, string $taxonomy): ?\WP_Term
	{
		$terms = wp_get_object_terms(
			$post_id,
			$taxonomy,
			[
				'orderby' => 'term_id',
				'order'   => 'ASC',
			]
		);

		if (is_wp_error($terms) || empty($terms)) {
			return null;
		}

		$term = $terms[0];
		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Permalink of the documentation page linked to a term.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function getDocPageUrl(int $term_id): string
	{
		$page_id = (int) get_term_meta($term_id, self::META_DOC_PAGE_ID, true);
		if ($page_id <= 0) {
			return '';
		}

		$url = get_permalink($page_id);
		return is_string($url) ? $url : '';
	}

	/**
	 * Breadcrumb items from tool/category doc pages (tool first, then category when unique).
	 *
	 * @param int $post_id Practice case post ID.
	 * @return array<int, array{label: string, url: string}>
	 */
	public static function getBreadcrumbItems(int $post_id): array
	{
		$items = [];
		$seen_urls = [];

		foreach ([self::TAX_TOOL, self::TAX_CATEGORY] as $taxonomy) {
			$term = self::getPrimaryTerm($post_id, $taxonomy);
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$url = self::getDocPageUrl((int) $term->term_id);
			if ($url === '' || isset($seen_urls[$url])) {
				continue;
			}

			$items[] = [
				'label' => $term->name,
				'url'   => $url,
			];
			$seen_urls[$url] = true;
		}

		return $items;
	}

	/**
	 * Add documentation page column to taxonomy list tables.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function addTermColumns(array $columns): array
	{
		$columns['lms4wp_doc_page'] = __('Documentation page', 'lms4wp');
		return $columns;
	}

	/**
	 * Render documentation page column.
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public function renderTermColumns(string $content, string $column_name, int $term_id): string
	{
		if ($column_name !== 'lms4wp_doc_page') {
			return $content;
		}

		$page_id = (int) get_term_meta($term_id, self::META_DOC_PAGE_ID, true);
		if ($page_id <= 0) {
			return '&mdash;';
		}

		$title = get_the_title($page_id);
		$url = get_permalink($page_id);
		if (!is_string($url) || $url === '') {
			return esc_html($title);
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url($url),
			esc_html($title)
		);
	}

	/**
	 * Ensure taxonomy term exists
	 *
	 * @param string $taxonomy Taxonomy slug
	 * @param string $slug     Term slug
	 * @param string $name     Term name
	 * @return int Term ID or 0
	 */
	private static function ensureTerm(string $taxonomy, string $slug, string $name): int
	{
		$existing = get_term_by('slug', $slug, $taxonomy);
		if ($existing instanceof \WP_Term) {
			return (int) $existing->term_id;
		}

		$result = wp_insert_term(
			$name,
			$taxonomy,
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
	 * Render page select dropdown
	 *
	 * @param int $selected_page_id Selected page ID
	 */
	private function renderPageSelect(int $selected_page_id): void
	{
		wp_dropdown_pages(
			[
				'name'              => 'lms4wp_practice_doc_page_id',
				'id'                => 'lms4wp_practice_doc_page_id',
				'selected'          => $selected_page_id,
				'show_option_none'  => __('— None —', 'lms4wp'),
				'option_none_value' => '0',
			]
		);
	}

	/**
	 * Capabilities for managing taxonomy terms
	 *
	 * @return array<string, string>
	 */
	private function getTermCapabilities(): array
	{
		return [
			'manage_terms' => 'edit_practice_cases',
			'edit_terms'   => 'edit_practice_cases',
			'delete_terms' => 'edit_practice_cases',
			'assign_terms' => 'edit_practice_cases',
		];
	}
}
