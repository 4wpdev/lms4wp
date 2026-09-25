<?php
/**
 * Renders practice case template components from section meta.
 *
 * @package ForWP\LMS\Frontend
 */

namespace ForWP\LMS\Frontend;

use ForWP\LMS\Content\PracticeCaseContent;
use ForWP\LMS\Content\PracticeCaseTemplate;
use ForWP\LMS\PostTypes\PracticeCase;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Composes the practice case layout from stored section data.
 */
class PracticeCaseRenderer
{
	/**
	 * @param int $post_id Practice case post ID.
	 */
	public static function render(int $post_id): string
	{
		$sections = self::resolveSections($post_id);

		if (null === $sections) {
			return self::renderLegacyContent($post_id);
		}

		return self::renderSections($sections);
	}

	/**
	 * Render one template slot (used by lms4wp/practice-case-{section} blocks).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $section Section key: terminal, goal, steps, troubleshooting, real-cases, faq.
	 */
	public static function renderSection(int $post_id, string $section): string
	{
		$sections = self::resolveSections($post_id);
		if (null === $sections) {
			return '';
		}

		switch ($section) {
			case 'terminal':
				$terminal = $sections['terminal'] ?? [];
				return is_array($terminal) ? self::renderTerminal($terminal) : '';

			case 'goal':
				$goal = isset($sections['goal']) && is_string($sections['goal']) ? $sections['goal'] : '';
				return $goal !== '' ? PracticeCaseTemplate::renderGoalHtml($goal) : '';

			case 'steps':
				$steps = $sections['steps'] ?? [];
				return is_array($steps) && $steps !== [] ? PracticeCaseTemplate::renderStepsHtml($steps) : '';

			case 'troubleshooting':
				$items = $sections['troubleshooting'] ?? [];
				return is_array($items) && $items !== []
					? PracticeCaseTemplate::renderTroubleshootingHtml($items)
					: '';

			case 'real-cases':
				$items = $sections['real_cases'] ?? [];
				return is_array($items) && $items !== []
					? PracticeCaseTemplate::renderRealCasesHtml($items)
					: '';

			case 'faq':
				$items = $sections['faq'] ?? [];
				return is_array($items) && $items !== []
					? PracticeCaseTemplate::renderFaqHtml($items)
					: '';

			default:
				return '';
		}
	}

	/**
	 * Load section meta with lazy migration from terminal profile or definition class.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public static function resolveSections(int $post_id): ?array
	{
		$sections = PracticeCaseContent::get($post_id);

		if (null === $sections) {
			$sections = self::buildSectionsFromTerminalMeta($post_id);
			if (null !== $sections) {
				PracticeCaseContent::save($post_id, $sections);
			}
		}

		if (null === $sections) {
			$sections = self::buildSectionsFromDefinition($post_id);
			if (null !== $sections) {
				PracticeCaseContent::save($post_id, $sections);
			}
		}

		return $sections;
	}

	/**
	 * @param array<string, mixed> $sections Normalized section payload.
	 */
	public static function renderSections(array $sections): string
	{
		$parts = [];

		$terminal = $sections['terminal'] ?? [];
		if (is_array($terminal)) {
			$parts[] = self::renderTerminal($terminal);
		}

		$goal = isset($sections['goal']) && is_string($sections['goal']) ? $sections['goal'] : '';
		if ($goal !== '') {
			$parts[] = PracticeCaseTemplate::renderGoalHtml($goal);
		}

		$steps = $sections['steps'] ?? [];
		if (is_array($steps) && $steps !== []) {
			$parts[] = PracticeCaseTemplate::renderStepsHtml($steps);
		}

		$troubleshooting = $sections['troubleshooting'] ?? [];
		if (is_array($troubleshooting) && $troubleshooting !== []) {
			$parts[] = PracticeCaseTemplate::renderTroubleshootingHtml($troubleshooting);
		}

		$real_cases = $sections['real_cases'] ?? [];
		if (is_array($real_cases) && $real_cases !== []) {
			$parts[] = PracticeCaseTemplate::renderRealCasesHtml($real_cases);
		}

		$faq = $sections['faq'] ?? [];
		if (is_array($faq) && $faq !== []) {
			$parts[] = PracticeCaseTemplate::renderFaqHtml($faq);
		}

		$parts = array_filter($parts);

		if ($parts === []) {
			return '';
		}

		return '<div class="forwp-practice-case__parts">' . implode('', $parts) . '</div>';
	}

	/**
	 * @param array<string, mixed> $terminal Terminal component config.
	 */
	public static function renderTerminal(array $terminal): string
	{
		$welcome = isset($terminal['welcomeMessage']) && is_string($terminal['welcomeMessage'])
			? $terminal['welcomeMessage']
			: '';

		$attrs = wp_json_encode(
			[
				'profile'        => 'embedded',
				'welcomeMessage' => $welcome,
				'className'      => 'forwp-practice-case__terminal',
			],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$markup = '<!-- wp:forwp-advanced-code/terminal ' . $attrs . ' /-->';

		if (function_exists('do_blocks')) {
			return (string) do_blocks($markup);
		}

		return $markup;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private static function buildSectionsFromTerminalMeta(int $post_id): ?array
	{
		if (!class_exists('\ForWP\AdvancedCode\Terminal_Post_Meta')) {
			return null;
		}

		$data = \ForWP\AdvancedCode\Terminal_Post_Meta::get_data($post_id);
		if (!is_array($data) || $data === []) {
			return null;
		}

		return PracticeCaseTemplate::buildSectionData($data);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private static function buildSectionsFromDefinition(int $post_id): ?array
	{
		$case_key = get_post_meta($post_id, PracticeCase::META_CASE_KEY, true);
		if (!is_string($case_key) || $case_key === '') {
			return null;
		}

		$definition = PracticeCaseTemplate::resolveDefinition($case_key);
		if ($definition === null || !method_exists($definition, 'getSectionData')) {
			return null;
		}

		return $definition::getSectionData();
	}

	/**
	 * Fallback for posts imported before section meta existed.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function renderLegacyContent(int $post_id): string
	{
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post || trim($post->post_content) === '') {
			return '';
		}

		return '<div class="forwp-practice-case__parts forwp-practice-case__parts--legacy">' .
			apply_filters('the_content', $post->post_content) .
			'</div>';
	}
}
