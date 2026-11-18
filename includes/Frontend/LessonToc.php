<?php
/**
 * Lesson page helpers: contents by H2 of the lesson body (left column) and reading time.
 *
 * Ids are added to the H2 blocks at render time (own anchor kept), so the contents links
 * work on any theme; the scroll-spy script marks the section in view.
 *
 * @package LMS4WP
 */

namespace ForWP\LMS\Frontend;

use ForWP\LMS\Content\LessonAccess;
use ForWP\LMS\PostTypes\Lesson;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class LessonToc
{
	const SCRIPT = 'lms4wp-lesson-toc';

	/** Words per minute for the reading time. */
	const WPM = 200;

	/** @var array<int, array<int, array{text: string, id: string}>> */
	private static array $cache = [];

	/** @var array<string, int> Ids already given out while rendering the current lesson. */
	private static array $given = [];

	public static function init(): void
	{
		add_filter('render_block_core/heading', [self::class, 'addId'], 10, 2);
		add_action('wp_enqueue_scripts', [self::class, 'register']);
	}

	public static function register(): void
	{
		$file = 'assets/js/lesson-toc.js';
		wp_register_script(
			self::SCRIPT,
			LMS4WP_URL . $file,
			[],
			file_exists(LMS4WP_PATH . $file) ? (string) filemtime(LMS4WP_PATH . $file) : LMS4WP_VERSION,
			['strategy' => 'defer', 'in_footer' => true]
		);
		// Course / module / lesson: the script also measures the sticky site header (--forwp-header-h).
		if (is_singular([Lesson::POST_TYPE, \ForWP\LMS\PostTypes\Course::POST_TYPE, \ForWP\LMS\PostTypes\Module::POST_TYPE])) {
			wp_enqueue_script(self::SCRIPT);
		}
	}

	/**
	 * H2 of the lesson body in order, each with a unique id (own anchor kept).
	 *
	 * @return array<int, array{text: string, id: string}>
	 */
	public static function headings(int $post_id): array
	{
		if (isset(self::$cache[$post_id])) {
			return self::$cache[$post_id];
		}
		$out = [];
		$used = [];
		$walk = static function (array $blocks) use (&$walk, &$out, &$used): void {
			foreach ($blocks as $block) {
				if (($block['blockName'] ?? '') === 'core/heading' && (int) ($block['attrs']['level'] ?? 2) === 2) {
					$text = trim(wp_strip_all_tags((string) $block['innerHTML']));
					if ($text !== '') {
						$id = (string) ($block['attrs']['anchor'] ?? '');
						if ($id === '') {
							$base = sanitize_title($text) ?: 'section';
							$id = $base;
							for ($n = 2; isset($used[$id]); $n++) {
								$id = $base . '-' . $n;
							}
						}
						$used[$id] = true;
						$out[] = ['text' => $text, 'id' => $id];
					}
				}
				if (!empty($block['innerBlocks'])) {
					$walk($block['innerBlocks']);
				}
			}
		};
		$walk(parse_blocks((string) get_post_field('post_content', $post_id)));
		return self::$cache[$post_id] = $out;
	}

	/**
	 * render_block_core/heading: id on the lesson's H2 (matched by text, in order).
	 *
	 * @param array<string, mixed> $block
	 */
	public static function addId(string $html, array $block): string
	{
		if (!is_singular(Lesson::POST_TYPE) || (int) ($block['attrs']['level'] ?? 2) !== 2 || preg_match('/<h2\b[^>]*\bid=/i', $html)) {
			return $html;
		}
		$text = trim(wp_strip_all_tags($html));
		$post_id = (int) get_queried_object_id();
		foreach (self::headings($post_id) as $i => $heading) {
			if ($heading['text'] === $text && !isset(self::$given[$post_id . ':' . $i])) {
				self::$given[$post_id . ':' . $i] = 1;
				return (string) preg_replace('/<h2\b/i', '<h2 id="' . esc_attr($heading['id']) . '"', $html, 1);
			}
		}
		return $html;
	}

	/**
	 * Reading time in minutes: lesson text without code, terminal and diagrams.
	 */
	public static function minutes(int $post_id): int
	{
		$minutes = (int) get_post_meta($post_id, '_yoast_wpseo_estimated-reading-time-minutes', true);
		if ($minutes > 0) {
			return $minutes;
		}
		$content = (string) get_post_field('post_content', $post_id);
		$content = (string) preg_replace('#<pre\b.*?</pre>#is', ' ', $content);
		$content = (string) preg_replace('#<!-- wp:(lms4wp|forwp/diagram)[^>]*/-->#', ' ', $content);
		$words = str_word_count(wp_strip_all_tags($content));
		return max(1, (int) ceil($words / self::WPM));
	}

	/**
	 * Contents block markup ('' when the lesson is locked or has fewer than 2 H2).
	 */
	public static function render(int $post_id): string
	{
		if (get_post_type($post_id) !== Lesson::POST_TYPE || !LessonAccess::canView($post_id)) {
			return '';
		}
		$headings = self::headings($post_id);
		if (count($headings) < 2) {
			return '';
		}
		wp_enqueue_script(self::SCRIPT);

		$items = '';
		foreach ($headings as $heading) {
			$items .= '<li><a href="#' . esc_attr($heading['id']) . '" data-lms4wp-toc-link>' . esc_html($heading['text']) . '</a></li>';
		}
		return '<p class="lms4wp-lesson-toc__title">' . esc_html__('In this lesson', 'lms4wp') . '</p>'
			. '<ol class="lms4wp-lesson-toc__list">' . $items . '</ol>';
	}
}
