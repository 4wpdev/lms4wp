<?php
/**
 * Dynamic block: composes practice case from section meta.
 *
 * @package ForWP\LMS\Blocks
 */

namespace ForWP\LMS\Blocks;

use ForWP\LMS\Frontend\PracticeCaseRenderer;
use ForWP\LMS\PostTypes\PracticeCase;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers lms4wp/practice-case — layout lives in theme template + CSS.
 */
class PracticeCaseBlock
{
	public const BLOCK_NAME = 'lms4wp/practice-case';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void
	{
		add_action('init', [self::class, 'register'], 20);
	}

	/**
	 * Register dynamic block (no save — data in post meta).
	 */
	public static function register(): void
	{
		register_block_type(
			self::BLOCK_NAME,
			[
				'api_version'     => 3,
				'title'           => __('Practice Case', 'lms4wp'),
				'description'     => __('Renders imported practice case sections from post meta.', 'lms4wp'),
				'category'        => 'widgets',
				'icon'            => 'welcome-learn-more',
				'supports'        => [
					'html'       => false,
					'align'      => ['wide', 'full'],
					'visibility' => false,
				],
				'uses_context'    => ['postId'],
				'render_callback' => [self::class, 'render'],
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved content (unused).
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render(array $attributes, string $content, \WP_Block $block): string
	{
		unset($attributes, $content);

		$post_id = 0;
		if (!empty($block->context['postId'])) {
			$post_id = (int) $block->context['postId'];
		}

		if ($post_id <= 0) {
			$post_id = get_the_ID();
		}

		if ($post_id <= 0 || get_post_type($post_id) !== PracticeCase::POST_TYPE) {
			return '';
		}

		return PracticeCaseRenderer::render($post_id);
	}
}
