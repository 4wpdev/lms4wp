<?php
/**
 * Sync single-practice_case FSE template from plugin/theme defaults.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Pushes post-content layout into the Site Editor template.
 */
class PracticeCaseTemplateSync
{
	public const TEMPLATE_VERSION = '6';
	public const TEMPLATE_SLUG    = 'single-practice_case';

	public static function init(): void
	{
		add_action('init', [self::class, 'maybeSync'], 20);
	}

	/**
	 * Run once when template version changes.
	 */
	public static function maybeSync(): void
	{
		$stored = get_option('lms4wp_practice_case_template_version', '');

		if ($stored === self::TEMPLATE_VERSION) {
			return;
		}

		self::syncFromDefaults();
		update_option('lms4wp_practice_case_template_version', self::TEMPLATE_VERSION, false);
	}

	/**
	 * Replace customized DB template with post-title + post-content shell.
	 */
	public static function syncFromDefaults(): bool
	{
		$shell = self::wrapShell(
			'<!-- wp:post-title {"level":1,"style":{"spacing":{"margin":{"bottom":"0","top":"0"}}},"fontSize":"xxx-large"} /-->' . "\n\n"
			. '<!-- wp:post-content {"layout":{"type":"default"}} /-->'
		);
		$theme    = get_stylesheet();
		$existing = get_block_template($theme . '//' . self::TEMPLATE_SLUG, 'wp_template');

		if ($existing instanceof \WP_Block_Template && !empty($existing->wp_id)) {
			$result = wp_update_post(
				wp_slash(
					[
						'ID'           => (int) $existing->wp_id,
						'post_content' => $shell,
					]
				),
				true
			);

			return !is_wp_error($result);
		}

		$result = wp_insert_post(
			wp_slash(
				[
					'post_type'    => 'wp_template',
					'post_name'    => self::TEMPLATE_SLUG,
					'post_title'   => __('Practice Case', 'lms4wp'),
					'post_content' => $shell,
					'post_status'  => 'publish',
				]
			),
			true
		);

		if (is_wp_error($result)) {
			return false;
		}

		wp_set_object_terms((int) $result, $theme, 'wp_theme');

		return true;
	}

	/**
	 * @param string $inner Block markup inside the practice case shell group.
	 */
	private static function wrapShell(string $inner): string
	{
		return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n\n"
			. '<!-- wp:group {"tagName":"main","align":"full","className":"forwp-practice-case","layout":{"type":"default"}} -->' . "\n"
			. '<main class="wp-block-group alignfull forwp-practice-case">' . "\n"
			. '<!-- wp:group {"align":"wide","className":"forwp-practice-case__shell","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|70"}}},"layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group alignwide forwp-practice-case__shell" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70)">' . "\n"
			. trim($inner) . "\n"
			. '</div>' . "\n"
			. '<!-- /wp:group -->' . "\n"
			. '</main>' . "\n"
			. '<!-- /wp:group -->' . "\n\n"
			. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
	}
}
