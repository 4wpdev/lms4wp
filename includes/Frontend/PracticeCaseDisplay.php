<?php
/**
 * Practice Case frontend display helpers (breadcrumb, etc.).
 *
 * @package ForWP\LMS\Frontend
 */

namespace ForWP\LMS\Frontend;

use ForWP\LMS\PostTypes\PracticeCase;
use ForWP\LMS\Taxonomies\PracticeCaseTaxonomies;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Practice case frontend presentation.
 */
class PracticeCaseDisplay
{
	/**
	 * Initialize hooks.
	 */
	public static function init(): void
	{
		$self = new self();
		add_filter('render_block', [$self, 'prependBreadcrumbToTitle'], 10, 2);
		add_action('wp_enqueue_scripts', [self::class, 'enqueueFrontendAssets']);
	}

	/**
	 * Step highlight sync with terminal challenge progress.
	 */
	public static function enqueueFrontendAssets(): void
	{
		if (!is_singular(PracticeCase::POST_TYPE)) {
			return;
		}

		$path = LMS4WP_PATH . 'assets/js/practice-case-steps-sync.js';
		$url  = LMS4WP_URL . 'assets/js/practice-case-steps-sync.js';

		if (!is_readable($path)) {
			return;
		}

		wp_enqueue_script(
			'lms4wp-practice-case-steps-sync',
			$url,
			[],
			(string) filemtime($path),
			true
		);
	}

	/**
	 * Prepend documentation breadcrumb before the post title on practice cases.
	 *
	 * @param string               $block_content Block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string
	 */
	public function prependBreadcrumbToTitle(string $block_content, array $block): string
	{
		if (!is_singular(PracticeCase::POST_TYPE) || ($block['blockName'] ?? '') !== 'core/post-title') {
			return $block_content;
		}

		$post_id = get_queried_object_id();
		if ($post_id <= 0) {
			return $block_content;
		}

		$breadcrumb = self::renderBreadcrumb($post_id);
		if ($breadcrumb === '') {
			return $block_content;
		}

		return $breadcrumb . $block_content;
	}

	/**
	 * Build breadcrumb HTML from taxonomy-linked documentation pages.
	 *
	 * @param int $post_id Practice case post ID.
	 * @return string
	 */
	public static function renderBreadcrumb(int $post_id): string
	{
		$items = PracticeCaseTaxonomies::getBreadcrumbItems($post_id);
		if ($items === []) {
			return '';
		}

		$links = [];
		foreach ($items as $item) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url($item['url']),
				esc_html($item['label'])
			);
		}

		return sprintf(
			'<nav class="forwp-practice-case__breadcrumb" aria-label="%s">%s</nav>',
			esc_attr__('Breadcrumb', 'lms4wp'),
			implode(' <span class="forwp-practice-case__breadcrumb-sep">/</span> ', $links)
		);
	}
}
