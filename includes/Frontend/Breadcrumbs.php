<?php
/**
 * Breadcrumb trail of courses, modules and lessons: LMS › Course › Module › Lesson.
 *
 * One trail feeds the visible crumbs (lesson-context / module-context blocks) and the
 * Schema.org BreadcrumbList. With Yoast SEO the trail replaces the items of Yoast's own
 * BreadcrumbList piece (same "#breadcrumb" @id the WebPage points to, every item with a URL);
 * without Yoast the plugin prints its own JSON-LD.
 *
 * @package LMS4WP
 */

namespace ForWP\LMS\Frontend;

use ForWP\LMS\Content\CourseStructure;
use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Lesson;
use ForWP\LMS\PostTypes\Module;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class Breadcrumbs
{
	/** Path of the LMS start page (filter: lms4wp_breadcrumb_root_page). */
	const ROOT_PATH = 'lms';

	public static function init(): void
	{
		add_filter('wpseo_schema_breadcrumb', [self::class, 'filterYoastPiece'], 20);
		add_action('wp_head', [self::class, 'printJsonLd'], 99);
	}

	/**
	 * LMS start page (null — none published).
	 *
	 * @return array{name: string, url: string}|null
	 */
	public static function root(): ?array
	{
		$page = get_page_by_path(self::ROOT_PATH);
		/**
		 * LMS start page shown as the first crumb (WP_Post|null).
		 *
		 * @param \WP_Post|null $page Page found by the "lms" path.
		 */
		$page = apply_filters('lms4wp_breadcrumb_root_page', $page);
		if (!$page instanceof \WP_Post || $page->post_status !== 'publish') {
			return null;
		}
		return ['name' => get_the_title($page), 'url' => (string) get_permalink($page)];
	}

	/**
	 * Trail from the LMS page to the post itself (last item = the post).
	 *
	 * @return array<int, array{name: string, url: string}>
	 */
	public static function trail(int $post_id): array
	{
		$post_type = get_post_type($post_id);
		$items = [];
		$course_id = 0;
		$module = null;

		if ($post_type === Lesson::POST_TYPE) {
			$context = CourseStructure::lessonContext($post_id);
			$course_id = $context['course_id'];
			$module = $context['module'];
		} elseif ($post_type === Module::POST_TYPE) {
			$course_id = (int) wp_get_post_parent_id($post_id);
		} elseif ($post_type === Course::POST_TYPE) {
			$course_id = $post_id;
		} else {
			return [];
		}

		$root = self::root();
		if ($root) {
			$items[] = $root;
		}
		if ($course_id > 0) {
			$items[] = ['name' => get_the_title($course_id), 'url' => (string) get_permalink($course_id)];
		}
		if ($module && (int) $module['id'] > 0 && get_post_status((int) $module['id']) === 'publish') {
			$items[] = ['name' => self::moduleLabel($module), 'url' => (string) get_permalink((int) $module['id'])];
		}
		if ($post_type !== Course::POST_TYPE) {
			$items[] = ['name' => get_the_title($post_id), 'url' => (string) get_permalink($post_id)];
		}
		return $items;
	}

	/**
	 * "Module 1. Git Basics".
	 *
	 * @param array{number: int, title: string} $module
	 */
	public static function moduleLabel(array $module): string
	{
		return $module['number'] > 0
			/* translators: 1: module number, 2: module title */
			? sprintf(__('Module %1$d. %2$s', 'lms4wp'), $module['number'], $module['title'])
			: $module['title'];
	}

	/**
	 * Visible root crumb + separator ('' without an LMS page).
	 */
	public static function rootLink(): string
	{
		$root = self::root();
		if (!$root) {
			return '';
		}
		return sprintf('<a class="lms4wp-lesson-context__root" href="%s">%s</a>', esc_url($root['url']), esc_html($root['name']))
			. Icons::svg('chevron-right', 14, 'lms4wp-lesson-context__sep');
	}

	private static function currentPostId(): int
	{
		if (!is_singular([Course::POST_TYPE, Module::POST_TYPE, Lesson::POST_TYPE])) {
			return 0;
		}
		return (int) get_queried_object_id();
	}

	/**
	 * schema.org ListItem list: Home first, every item with its URL.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function listItems(int $post_id): array
	{
		$items = array_merge(
			[['name' => __('Home', 'lms4wp'), 'url' => home_url('/')]],
			self::trail($post_id)
		);
		$list = [];
		foreach ($items as $i => $item) {
			$list[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags($item['name']),
				'item'     => $item['url'],
			];
		}
		return $list;
	}

	/**
	 * Yoast BreadcrumbList piece → our trail (keeps Yoast's @id).
	 *
	 * @param array<string, mixed>|false $piece
	 * @return array<string, mixed>|false
	 */
	public static function filterYoastPiece($piece)
	{
		$post_id = self::currentPostId();
		if ($post_id === 0 || !is_array($piece)) {
			return $piece;
		}
		$piece['itemListElement'] = self::listItems($post_id);
		return $piece;
	}

	/**
	 * Own JSON-LD when Yoast SEO does not print a graph.
	 */
	public static function printJsonLd(): void
	{
		if (defined('WPSEO_VERSION')) {
			return;
		}
		$post_id = self::currentPostId();
		if ($post_id === 0) {
			return;
		}
		$data = [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink($post_id) . '#breadcrumb',
			'itemListElement' => self::listItems($post_id),
		];
		echo '<script type="application/ld+json" class="lms4wp-breadcrumb-schema">'
			. wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
			. '</script>' . "\n";
	}
}
