<?php
/**
 * Block patterns for Practice Case posts.
 *
 * @package ForWP\LMS\Frontend
 */

namespace ForWP\LMS\Frontend;

use ForWP\LMS\PostTypes\PracticeCase;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers block patterns for practice cases.
 */
class BlockPatterns
{
	/**
	 * Pattern category slug.
	 */
	const CATEGORY = 'forwp-seo-techarticle';

	/**
	 * Initialize block patterns.
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'register'], 20);
		add_filter('forwp_seo_supported_post_types', [$self, 'addSeoPostType']);
	}

	/**
	 * Register pattern category and patterns.
	 */
	public function register(): void
	{
		if (!function_exists('register_block_pattern')) {
			return;
		}

		if (function_exists('register_block_pattern_category')) {
			register_block_pattern_category(
				self::CATEGORY,
				[
					'label' => __('TechArticle', 'lms4wp'),
				]
			);
		}

		register_block_pattern(
			'forwp-seo/techarticle-practice-layout',
			[
				'title'       => __('TechArticle — practice layout', 'lms4wp'),
				'description' => __('Terminal, goal, steps, and troubleshooting wrappers (4wp-seo). Add any blocks inside each section.', 'lms4wp'),
				'categories'  => [self::CATEGORY],
				'postTypes'   => [PracticeCase::POST_TYPE],
				'content'     => $this->getPatternMarkup(),
			]
		);
	}

	/**
	 * Empty TechArticle wrapper shells for manual editing.
	 */
	private function getPatternMarkup(): string
	{
		return <<<'HTML'
<!-- wp:forwp-advanced-code/terminal {"profile":"embedded"} /-->

<!-- wp:forwp-seo/techarticle-goal -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Goal</h2>
<!-- /wp:heading -->
<!-- /wp:forwp-seo/techarticle-goal -->

<!-- wp:forwp-seo/techarticle-steps -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Steps</h2>
<!-- /wp:heading -->
<!-- /wp:forwp-seo/techarticle-steps -->

<!-- wp:forwp-seo/techarticle-issues -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Common mistakes</h2>
<!-- /wp:heading -->
<!-- /wp:forwp-seo/techarticle-issues -->
HTML;
	}

	/**
	 * Enable 4wp SEO TechArticle meta box on practice cases.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 * @return array<int, string>
	 */
	public function addSeoPostType(array $post_types): array
	{
		if (!in_array(PracticeCase::POST_TYPE, $post_types, true)) {
			$post_types[] = PracticeCase::POST_TYPE;
		}

		return $post_types;
	}
}
