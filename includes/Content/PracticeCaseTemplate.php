<?php
/**
 * Practice case import — structured section meta + empty post body.
 *
 * Layout order (renderer): Terminal → Goal → Steps → Troubleshooting → Real cases → FAQ
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\PracticeCase;
use ForWP\LMS\Taxonomies\PracticeCaseTaxonomies;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Builds practice case block content and meta from challenge JSON or registered definitions.
 */
class PracticeCaseTemplate
{
	/**
	 * Registered rich content definitions (case key → class).
	 *
	 * @return array<string, class-string>
	 */
	public static function getDefinitions(): array
	{
		/**
		 * Filter registered practice case content definitions.
		 *
		 * @param array<string, class-string> $definitions Case key => definition class.
		 */
		return apply_filters( 'forwp_lms_practice_case_definitions', [] );
	}

	/**
	 * Resolve definition class for a case key.
	 *
	 * @param string $case_key Case key from challenge JSON slug field.
	 * @return class-string|null
	 */
	public static function resolveDefinition(string $case_key): ?string
	{
		$case_key = sanitize_title($case_key);
		if ($case_key === '') {
			return null;
		}

		$class = self::getDefinitions()[$case_key] ?? null;

		return is_string($class) && class_exists($class) ? $class : null;
	}

	/**
	 * Build editor post_content from challenge JSON (TechArticle wrappers via 4wp-seo).
	 *
	 * @param array<string, mixed> $data Challenge profile JSON.
	 */
	public static function buildPostShellFromImport(array $data): string
	{
		return self::buildPostShell(self::buildSectionData($data), $data);
	}

	/**
	 * Post editor shell — forwp-seo TechArticle wrappers + terminal block.
	 *
	 * @param array<string, mixed> $sections Normalized section payload.
	 * @param array<string, mixed> $data     Optional challenge JSON for terminal attrs.
	 */
	public static function buildPostShell(array $sections = [], array $data = []): string
	{
		if (class_exists('\Forwp\SeoHelper\Content\TechArticleMarkup')) {
			return \Forwp\SeoHelper\Content\TechArticleMarkup::build_post_content($sections, $data);
		}

		return '';
	}

	/**
	 * @deprecated Unused — use buildPostShellFromImport().
	 */
	public static function buildPostShellLegacy(): string
	{
		return '';
	}

	/**
	 * Ensure the block editor shows imported section previews (not used on the front end).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function ensureEditorShell(int $post_id): void
	{
		if (!class_exists('\Forwp\SeoHelper\Content\TechArticleMarkup')) {
			return;
		}

		$post = get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== PracticeCase::POST_TYPE) {
			return;
		}

		$current = trim($post->post_content);
		if (\Forwp\SeoHelper\Content\TechArticleMarkup::is_valid_post_shell($current)) {
			return;
		}

		$sections = PracticeCaseContent::get($post_id);
		if (null === $sections && class_exists('\ForWP\LMS\Frontend\PracticeCaseRenderer')) {
			$sections = \ForWP\LMS\Frontend\PracticeCaseRenderer::resolveSections($post_id);
		}

		if (!is_array($sections) || $sections === []) {
			return;
		}

		$shell = self::buildPostShell($sections, []);
		if ($shell === '') {
			return;
		}

		wp_update_post(
			wp_slash(
				[
					'ID'           => $post_id,
					'post_content' => $shell,
				]
			)
		);
	}

	/**
	 * @deprecated Use buildPostShell() + saveSections(). Kept for backward compatibility.
	 *
	 * @param array<string, mixed> $data Challenge profile JSON.
	 */
	public static function buildContent(array $data): string
	{
		return self::buildPostShellFromImport($data);
	}

	/**
	 * Persist structured sections from challenge JSON or registered definition.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Challenge profile JSON.
	 */
	public static function saveSections(int $post_id, array $data): void
	{
		$sections = self::buildSectionData($data);
		PracticeCaseContent::save($post_id, $sections);
		self::syncTerminalData($post_id, $data);

		$shell = self::buildPostShell($sections, $data);
		if ($shell !== '') {
			wp_update_post(
				wp_slash(
					[
						'ID'           => $post_id,
						'post_content' => $shell,
					]
				)
			);
		}
	}

	/**
	 * Build normalized section payload for PracticeCaseRenderer.
	 *
	 * @param array<string, mixed> $data Challenge profile JSON.
	 * @return array<string, mixed>
	 */
	public static function buildSectionData(array $data): array
	{
		$case_key   = self::resolveCaseKey($data);
		$definition = self::resolveDefinition($case_key);

		if ($definition !== null && method_exists($definition, 'getSectionData')) {
			$sections = $definition::getSectionData();
		} else {
			$sections = self::buildSectionDataFromChallengeJson($data);
		}

		$sections['terminal'] = self::buildTerminalComponent($data);

		/**
		 * Filter section data before it is saved to post meta.
		 *
		 * @param array<string, mixed> $sections Section payload.
		 * @param array<string, mixed> $data     Source challenge JSON.
		 * @param string               $case_key Case key.
		 */
		return apply_filters('forwp_lms_practice_case_sections', $sections, $data, $case_key);
	}

	/**
	 * @param array<string, mixed> $data Challenge profile JSON.
	 * @return array<string, mixed>
	 */
	private static function buildTerminalComponent(array $data): array
	{
		$welcome = '';
		if (!empty($data['instructions']) && is_string($data['instructions'])) {
			$welcome = $data['instructions'];
		} elseif (!empty($data['description']) && is_string($data['description'])) {
			$welcome = $data['description'];
		}

		return [
			'welcomeMessage' => $welcome,
		];
	}

	/**
	 * Post excerpt for import.
	 *
	 * @param array<string, mixed> $data Challenge profile JSON.
	 */
	public static function buildExcerpt(array $data): string
	{
		$definition = self::resolveDefinition(self::resolveCaseKey($data));

		if ($definition !== null && method_exists($definition, 'getPostData')) {
			$post = $definition::getPostData();
			if (!empty($post['post_excerpt']) && is_string($post['post_excerpt'])) {
				return $post['post_excerpt'];
			}
		}

		return isset($data['description']) && is_string($data['description'])
			? $data['description']
			: '';
	}

	/**
	 * Apply practice case post meta after import (terminal runtime meta is separate).
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Challenge profile JSON.
	 */
	public static function applyMeta(int $post_id, array $data): void
	{
		if (!class_exists(PracticeCase::class)) {
			return;
		}

		$case_key   = self::resolveCaseKey($data);
		$definition = self::resolveDefinition($case_key);

		if ($case_key !== '') {
			update_post_meta($post_id, PracticeCase::META_CASE_KEY, $case_key);
		}

		if ($definition !== null && method_exists($definition, 'getMeta')) {
			foreach ($definition::getMeta() as $meta_key => $meta_value) {
				if (PracticeCase::META_TERMINAL_PROFILE === $meta_key) {
					continue;
				}
				update_post_meta($post_id, $meta_key, $meta_value);
			}
		} else {
			self::applyMetaFromJson($post_id, $data, $case_key);
		}

		delete_post_meta($post_id, PracticeCase::META_TERMINAL_PROFILE);

		if (class_exists('\Forwp\SeoHelper\Schema\TechArticle') || metadata_exists('post', $post_id, '_forwp_seo_techarticle_enabled')) {
			update_post_meta($post_id, '_forwp_seo_techarticle_enabled', '1');
		}

		self::applySeoFromJson($post_id, $data);
	}

	/**
	 * Assign taxonomy terms for an imported practice case.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Challenge profile JSON.
	 * @return array<string, string> Applied taxonomy => term slug map.
	 */
	public static function resolveTaxonomies(array $data): array
	{
		if (!class_exists(PracticeCaseTaxonomies::class)) {
			return [];
		}

		$definition = self::resolveDefinition(self::resolveCaseKey($data));

		if ($definition !== null && method_exists($definition, 'getTaxonomies')) {
			return $definition::getTaxonomies();
		}

		$content = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];

		if (!empty($content['taxonomies']) && is_array($content['taxonomies'])) {
			$resolved = [];
			foreach ($content['taxonomies'] as $taxonomy => $term_slug) {
				if (is_string($taxonomy) && is_string($term_slug) && taxonomy_exists($taxonomy)) {
					$resolved[$taxonomy] = sanitize_title($term_slug);
				}
			}

			if ($resolved !== []) {
				return $resolved;
			}
		}

		return self::defaultTaxonomyMap(self::resolveCaseKey($data));
	}

	/**
	 * @param array<string, mixed> $data Challenge profile JSON.
	 */
	private static function resolveCaseKey(array $data): string
	{
		return isset($data['slug']) && is_string($data['slug']) ? sanitize_title($data['slug']) : '';
	}

	/**
	 * @param array<string, mixed> $data Challenge profile JSON.
	 * @return array<string, mixed>
	 */
	private static function buildSectionDataFromChallengeJson(array $data): array
	{
		$content = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];

		$goal = '';
		if (!empty($content['goal']) && is_string($content['goal'])) {
			$goal = $content['goal'];
		} elseif (!empty($data['description']) && is_string($data['description'])) {
			$goal = $data['description'];
		}

		$steps = $content['steps'] ?? null;
		if (!is_array($steps) || $steps === []) {
			$steps = self::articleStepsFromChallengeSteps(
				isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : []
			);
		}

		$sections = [
			'goal'  => $goal,
			'steps' => is_array($steps) ? $steps : [],
		];

		if (!empty($content['troubleshooting']) && is_array($content['troubleshooting'])) {
			$sections['troubleshooting'] = $content['troubleshooting'];
		}

		if (!empty($content['real_cases']) && is_array($content['real_cases'])) {
			$sections['real_cases'] = $content['real_cases'];
		}

		if (!empty($content['faq']) && is_array($content['faq'])) {
			$sections['faq'] = $content['faq'];
		}

		if (!empty($data['completion']) && is_array($data['completion'])) {
			$completion = self::normalizeCompletion($data['completion']);
			if ($completion !== []) {
				$sections['completion'] = $completion;
			}
		}

		return $sections;
	}

	/**
	 * Persist full challenge JSON for terminal runtime (steps + completion screen).
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Challenge profile JSON.
	 */
	private static function syncTerminalData(int $post_id, array $data): void
	{
		if (!class_exists('\ForWP\AdvancedCode\Terminal_Post_Meta')) {
			return;
		}

		\ForWP\AdvancedCode\Terminal_Post_Meta::set_data($post_id, $data);
	}

	/**
	 * @param array<string, mixed> $completion Raw completion block from JSON.
	 * @return array<string, array<int, string>>
	 */
	private static function normalizeCompletion(array $completion): array
	{
		$normalized = [];

		foreach (['perfect', 'optimize', 'next'] as $key) {
			if (empty($completion[$key]) || !is_array($completion[$key])) {
				continue;
			}

			$items = [];
			foreach ($completion[$key] as $item) {
				if (!is_string($item)) {
					continue;
				}

				$item = trim($item);
				if ($item !== '') {
					$items[] = $item;
				}
			}

			if ($items !== []) {
				$normalized[$key] = $items;
			}
		}

		return $normalized;
	}

	/**
	 * Render Goal component HTML.
	 */
	public static function renderGoalHtml(string $text): string
	{
		return '<div class="forwp-seo-techarticle-goal">' .
			'<h2 class="wp-block-heading">' . esc_html__('Goal', 'lms4wp') . '</h2>' .
			'<p>' . wp_kses_post($text) . '</p>' .
			'</div>';
	}

	/**
	 * @param array<int, array<string, mixed>> $steps Article steps.
	 */
	public static function renderStepsHtml(array $steps): string
	{
		$body = '<h2 class="wp-block-heading">' . esc_html__('Steps', 'lms4wp') . '</h2>';

		foreach ($steps as $step) {
			if (!is_array($step)) {
				continue;
			}

			$title = isset($step['title']) && is_string($step['title']) ? $step['title'] : '';
			if ($title === '') {
				continue;
			}

			$body .= '<div class="forwp-seo-techarticle-step">';
			$body .= '<h3 class="wp-block-heading">' . esc_html($title) . '</h3>';

			$paragraphs = $step['paragraphs'] ?? [];
			if (is_string($paragraphs)) {
				$paragraphs = [$paragraphs];
			}
			if (is_array($paragraphs)) {
				foreach ($paragraphs as $paragraph) {
					if (is_string($paragraph) && trim($paragraph) !== '') {
						$body .= '<p>' . wp_kses_post($paragraph) . '</p>';
					}
				}
			}

			$command = isset($step['command']) && is_string($step['command']) ? trim($step['command']) : '';
			if ($command !== '') {
				$body .= '<pre class="wp-block-code"><code class="language-bash" lang="bash">' .
					esc_html($command) . '</code></pre>';
			}

			$body .= '</div>';
		}

		return '<div class="forwp-seo-techarticle-steps">' . $body . '</div>';
	}

	/**
	 * @param array<int, string> $items Troubleshooting lines.
	 */
	public static function renderTroubleshootingHtml(array $items): string
	{
		$list = '';
		foreach ($items as $item) {
			if (!is_string($item) || trim($item) === '') {
				continue;
			}
			$list .= '<li>' . wp_kses_post($item) . '</li>';
		}

		if ($list === '') {
			return '';
		}

		return '<div class="forwp-seo-techarticle-issues">' .
			'<h2 class="wp-block-heading">' . esc_html__('Troubleshooting', 'lms4wp') . '</h2>' .
			'<ul class="wp-block-list">' . $list . '</ul>' .
			'</div>';
	}

	/**
	 * @param array<int, array<string, mixed>> $items Link rows.
	 */
	public static function renderRealCasesHtml(array $items): string
	{
		$list = '';
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$label = isset($item['label']) && is_string($item['label']) ? $item['label'] : '';
			$url   = isset($item['url']) && is_string($item['url']) ? self::resolveUrl($item['url']) : '';
			if ($label === '' || $url === '') {
				continue;
			}
			$list .= '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
		}

		if ($list === '') {
			return '';
		}

		return '<div class="wp-block-group forwp-practice-case__real-cases">' .
			'<h2 class="wp-block-heading">' . esc_html__('Real cases', 'lms4wp') . '</h2>' .
			'<ul class="wp-block-list">' . $list . '</ul>' .
			'</div>';
	}

	/**
	 * @param array<int, array<string, mixed>> $items FAQ rows.
	 */
	public static function renderFaqHtml(array $items): string
	{
		$details = '';
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$question = isset($item['question']) && is_string($item['question']) ? $item['question'] : '';
			$answer   = isset($item['answer']) && is_string($item['answer']) ? $item['answer'] : '';
			if ($question === '' || $answer === '') {
				continue;
			}
			$details .= '<details class="wp-block-details"><summary>' . esc_html($question) .
				'</summary><p>' . wp_kses_post($answer) . '</p></details>';
		}

		if ($details === '') {
			return '';
		}

		return '<h2 class="wp-block-heading">' . esc_html__('FAQ', 'lms4wp') . '</h2>' .
			'<div class="wp-block-4wp-faq">' . $details . '</div>';
	}

	/**
	 * @param int                  $post_id  Post ID.
	 * @param array<string, mixed> $data     Challenge profile JSON.
	 * @param string               $case_key Case key.
	 */
	private static function applyMetaFromJson(int $post_id, array $data, string $case_key): void
	{
		$content = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];

		if ($case_key !== '') {
			update_post_meta($post_id, PracticeCase::META_CASE_SLUG, self::deriveCaseSlug($case_key));
		}

		$goal = '';
		if (!empty($content['goal']) && is_string($content['goal'])) {
			$goal = $content['goal'];
		} elseif (!empty($data['description']) && is_string($data['description'])) {
			$goal = $data['description'];
		}

		if ($goal !== '') {
			update_post_meta($post_id, PracticeCase::META_GOAL, $goal);
		}

		if (!empty($data['difficulty']) && is_string($data['difficulty'])) {
			PracticeCase::assignLevelFromSlug($post_id, $data['difficulty']);
		}

		if (!empty($data['estimated_time']) && is_string($data['estimated_time'])) {
			update_post_meta($post_id, PracticeCase::META_ESTIMATED_TIME, sanitize_text_field($data['estimated_time']));
		}

		if (!empty($data['related_page']) && is_string($data['related_page'])) {
			update_post_meta($post_id, PracticeCase::META_RELATED_DOC_URL, self::resolveUrl($data['related_page']));
		}

		if (!empty($data['author_url']) && is_string($data['author_url'])) {
			update_post_meta($post_id, PracticeCase::META_AUTHOR_URL, self::resolveUrl($data['author_url']));
		}

		if (!empty($data['image']) && is_string($data['image'])) {
			update_post_meta($post_id, PracticeCase::META_IMAGE, self::resolveUrl($data['image']));
		}
	}

	/**
	 * @param string $case_key Case key from JSON slug field.
	 */
	public static function deriveCaseSlug(string $case_key): string
	{
		$case_key = sanitize_title($case_key);

		$map = [
			'deploy-new-site' => 'new-site',
			'maintenance'     => 'maintenance',
		];

		return $map[$case_key] ?? $case_key;
	}

	/**
	 * @param string $case_key Case key from JSON slug field.
	 * @return array<string, string>
	 */
	private static function defaultTaxonomyMap(string $case_key): array
	{
		if (!class_exists(PracticeCaseTaxonomies::class)) {
			return [];
		}

		$tax = PracticeCaseTaxonomies::class;

		$map = [
			'deploy-new-site' => [
				$tax::TAX_TOOL     => 'wp-cli',
				$tax::TAX_CATEGORY => 'deploy',
			],
			'maintenance'     => [
				$tax::TAX_TOOL     => 'wp-cli',
				$tax::TAX_CATEGORY => 'maintenance',
			],
		];

		return $map[$case_key] ?? [];
	}

	/**
	 * @param array<int, array<string, mixed>> $steps Challenge terminal steps.
	 * @return array<int, array<string, mixed>>
	 */
	private static function articleStepsFromChallengeSteps(array $steps): array
	{
		$article_steps = [];

		foreach ($steps as $index => $step) {
			if (!is_array($step)) {
				continue;
			}

			$num     = isset($step['id']) ? (int) $step['id'] : ($index + 1);
			$hint    = isset($step['hint']) && is_string($step['hint']) ? $step['hint'] : '';
			$title   = isset($step['title']) && is_string($step['title']) ? $step['title'] : $hint;
			$command = isset($step['command_hint']) && is_string($step['command_hint'])
				? $step['command_hint']
				: (is_array($step['accepted'] ?? null) && !empty($step['accepted'][0])
					? (string) $step['accepted'][0]
					: '');

			$paragraphs = [];
			if ($hint !== '' && $hint !== $title) {
				$paragraphs[] = $hint;
			} elseif ($hint !== '') {
				$paragraphs[] = $hint;
			}

			$article_steps[] = [
				'title'      => sprintf('%d. %s', $num, $title),
				'paragraphs' => $paragraphs,
				'command'    => $command,
			];
		}

		return $article_steps;
	}

	/**
	 * Write Yoast SEO meta and keywords-as-tags from the JSON seo block.
	 * Called after both definition-based and JSON-based meta paths.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Challenge profile JSON.
	 */
	private static function applySeoFromJson(int $post_id, array $data): void
	{
		$seo = isset($data['seo']) && is_array($data['seo']) ? $data['seo'] : [];

		if (!empty($seo['title']) && is_string($seo['title'])) {
			$existing = get_post_meta($post_id, '_yoast_wpseo_title', true);
			if ($existing === '') {
				update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo['title']));
			}
		}

		if (!empty($seo['description']) && is_string($seo['description'])) {
			$existing = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
			if ($existing === '') {
				update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($seo['description']));
			}
		}

		if (!empty($seo['keywords']) && is_array($seo['keywords'])) {
			$keywords = array_values(array_filter(array_map('sanitize_text_field', $seo['keywords'])));

			if (!empty($keywords)) {
				$focus = array_slice($keywords, 0, 5);

				if (class_exists('\Forwp\SeoHelper\SeoMeta\FocusKeyphrases')) {
					\Forwp\SeoHelper\SeoMeta\FocusKeyphrases::save($post_id, $focus);
				}

				if (get_post_meta($post_id, '_yoast_wpseo_focuskw', true) === '') {
					update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus[0]);
				}

				if (taxonomy_exists('post_tag')) {
					wp_set_object_terms($post_id, $keywords, 'post_tag', false);
				}
			}
		}
	}

	/**
	 * @param string $path_or_url Relative path or absolute URL.
	 */
	private static function resolveUrl(string $path_or_url): string
	{
		$path_or_url = trim($path_or_url);

		if ($path_or_url === '') {
			return '';
		}

		if (preg_match('#^https?://#i', $path_or_url)) {
			return esc_url_raw($path_or_url);
		}

		return esc_url_raw(home_url('/' . ltrim($path_or_url, '/')));
	}
}
