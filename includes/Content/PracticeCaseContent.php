<?php
/**
 * Structured practice case sections (meta) — not post_content HTML.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Persists and loads section data for the practice case template block.
 */
class PracticeCaseContent
{
	public const META_SECTIONS = '_lms4wp_practice_case_sections';
	public const DATA_VERSION  = 1;

	/**
	 * @param int                  $post_id  Post ID.
	 * @param array<string, mixed> $sections Normalized section payload.
	 */
	public static function save(int $post_id, array $sections): void
	{
		$sections['version'] = self::DATA_VERSION;

		update_post_meta(
			$post_id,
			self::META_SECTIONS,
			wp_json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public static function get(int $post_id): ?array
	{
		$raw = get_post_meta($post_id, self::META_SECTIONS, true);

		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$data = json_decode($raw, true);

		return is_array($data) ? $data : null;
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public static function has(int $post_id): bool
	{
		return null !== self::get($post_id);
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public static function clear(int $post_id): void
	{
		delete_post_meta($post_id, self::META_SECTIONS);
	}
}
