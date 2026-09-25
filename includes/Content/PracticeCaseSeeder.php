<?php
/**
 * Seed practice case posts from content definitions.
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
 * Creates or updates practice case posts.
 */
class PracticeCaseSeeder
{
	/**
	 * Registered case definitions.
	 *
	 * @return array<string, class-string>
	 */
	public static function getDefinitions(): array
	{
		return PracticeCaseTemplate::getDefinitions();
	}

	/**
	 * Seed a practice case by case key.
	 *
	 * @param string $key   Case key (e.g. deploy-new-site).
	 * @param bool   $force Update existing post content when true.
	 * @return int|\WP_Error Post ID or error.
	 */
	public static function seed(string $key, bool $force = false)
	{
		$definitions = self::getDefinitions();
		if (!isset($definitions[$key])) {
			return new \WP_Error(
				'lms4wp_unknown_practice_case',
				sprintf('Unknown practice case: %s', $key)
			);
		}

		/** @var class-string $class */
		$class = $definitions[$key];
		$post_data = $class::getPostData();
		$meta = $class::getMeta();

		$existing = self::findExistingPost($key, (string) ($meta[PracticeCase::META_CASE_SLUG] ?? ''), (string) ($post_data['post_title'] ?? ''));
		if ($existing instanceof \WP_Post && !$force) {
			return (int) $existing->ID;
		}

		if ($existing instanceof \WP_Post) {
			$post_data['ID'] = $existing->ID;
			unset($post_data['post_status']);
		}

		$post_id = wp_insert_post(wp_slash($post_data), true);
		if (is_wp_error($post_id)) {
			return $post_id;
		}

		update_post_meta($post_id, PracticeCase::META_CASE_KEY, $key);

		foreach ($meta as $meta_key => $meta_value) {
			update_post_meta($post_id, $meta_key, $meta_value);
		}

		foreach ($class::getTaxonomies() as $taxonomy => $term_slug) {
			wp_set_object_terms($post_id, [$term_slug], $taxonomy, false);
		}

		if (method_exists($class, 'getSectionData')) {
			PracticeCaseContent::save($post_id, $class::getSectionData());
		}

		PracticeCaseTemplate::ensureEditorShell($post_id);

		clean_post_cache($post_id);
		$post = get_post($post_id);
		if ($post instanceof \WP_Post) {
			$practice_case = new PracticeCase();
			$practice_case->maybeUpdatePostSlug($post_id, $post);
		}

		return (int) $post_id;
	}

	/**
	 * Seed all registered practice cases.
	 *
	 * @param bool $force Update existing posts when true.
	 * @return array<string, int|\WP_Error>
	 */
	public static function seedAll(bool $force = false): array
	{
		$results = [];
		foreach (array_keys(self::getDefinitions()) as $key) {
			$results[$key] = self::seed($key, $force);
		}
		return $results;
	}

	/**
	 * Move a seeded practice case to trash (or delete permanently).
	 *
	 * @param string $key        Case key.
	 * @param bool   $hard_delete Permanently delete when true.
	 * @return true|\WP_Error
	 */
	public static function delete(string $key, bool $hard_delete = false)
	{
		$post = self::findExistingPost($key);
		if (!$post instanceof \WP_Post) {
			return new \WP_Error(
				'lms4wp_practice_case_not_found',
				sprintf('No practice case found for key: %s', $key)
			);
		}

		if ($hard_delete) {
			$result = wp_delete_post($post->ID, true);
			return $result !== false && $result !== null ? true : new \WP_Error(
				'lms4wp_practice_case_delete_failed',
				sprintf('Could not delete practice case: %s', $key)
			);
		}

		$result = wp_trash_post($post->ID);
		return $result instanceof \WP_Post ? true : new \WP_Error(
			'lms4wp_practice_case_trash_failed',
			sprintf('Could not trash practice case: %s', $key)
		);
	}

	/**
	 * List registered definitions and matching posts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function listCases(): array
	{
		$rows = [];

		foreach (self::getDefinitions() as $key => $class) {
			$post = self::findExistingPost($key);
			$rows[] = [
				'key'    => $key,
				'class'  => $class,
				'post_id' => $post instanceof \WP_Post ? (int) $post->ID : 0,
				'status' => $post instanceof \WP_Post ? $post->post_status : '',
				'slug'   => $post instanceof \WP_Post ? $post->post_name : '',
			];
		}

		return $rows;
	}

	/**
	 * Find an existing practice case by case key, case slug meta, or title.
	 *
	 * @param string $case_key   Definition key stored in _lms4wp_case_key.
	 * @param string $case_slug  Optional short case slug meta value.
	 * @param string $post_title Optional post title fallback.
	 * @return \WP_Post|null
	 */
	public static function findExistingPost(string $case_key = '', string $case_slug = '', string $post_title = ''): ?\WP_Post
	{
		if ($case_key !== '') {
			$posts = get_posts(
				[
					'post_type'      => PracticeCase::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => PracticeCase::META_CASE_KEY,
					'meta_value'     => $case_key,
					'fields'         => 'ids',
				]
			);

			if (!empty($posts)) {
				$post = get_post((int) $posts[0]);
				return $post instanceof \WP_Post ? $post : null;
			}
		}

		if ($case_slug !== '') {
			$posts = get_posts(
				[
					'post_type'      => PracticeCase::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => PracticeCase::META_CASE_SLUG,
					'meta_value'     => $case_slug,
					'fields'         => 'ids',
				]
			);

			if (!empty($posts)) {
				$post = get_post((int) $posts[0]);
				return $post instanceof \WP_Post ? $post : null;
			}
		}

		if ($post_title === '') {
			return null;
		}

		$post = get_page_by_title($post_title, OBJECT, PracticeCase::POST_TYPE);
		return $post instanceof \WP_Post ? $post : null;
	}
}
