<?php
/**
 * Backfill post editor shells for imported practice cases.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\PracticeCase;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Writes section block placeholders into post_content so the block editor shows previews.
 */
class PracticeCaseEditorShell
{
	public const VERSION = '5';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void
	{
		add_action('init', [self::class, 'maybeBackfill'], 20);
		add_action('load-post.php', [self::class, 'ensureShellOnEditScreen']);
	}

	/**
	 * Populate editor blocks when opening an imported practice case.
	 */
	public static function ensureShellOnEditScreen(): void
	{
		if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'edit') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ($post_id <= 0 || get_post_type($post_id) !== PracticeCase::POST_TYPE) {
			return;
		}

		if (!self::hasImportedData($post_id)) {
			return;
		}

		PracticeCaseTemplate::ensureEditorShell($post_id);
	}

	/**
	 * One-shot backfill for posts imported before editor shells existed.
	 */
	public static function maybeBackfill(): void
	{
		$stored = get_option('lms4wp_practice_case_editor_shell_version', '');

		if ($stored === self::VERSION) {
			return;
		}

		self::backfillAll();
		update_option('lms4wp_practice_case_editor_shell_version', self::VERSION, false);
	}

	/**
	 * Populate editor shells for all practice cases that have imported data.
	 */
	public static function backfillAll(): void
	{
		$posts = get_posts(
			[
				'post_type'      => PracticeCase::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		foreach ($posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			if (!self::hasImportedData($post_id)) {
				continue;
			}

			PracticeCaseTemplate::ensureEditorShell($post_id);
		}
	}

	private static function hasImportedData(int $post_id): bool
	{
		if (PracticeCaseContent::has($post_id)) {
			return true;
		}

		if (class_exists('\ForWP\AdvancedCode\Terminal_Post_Meta')
			&& \ForWP\AdvancedCode\Terminal_Post_Meta::has_data($post_id)) {
			return true;
		}

		$case_key = get_post_meta($post_id, PracticeCase::META_CASE_KEY, true);

		return is_string($case_key) && $case_key !== '';
	}
}
