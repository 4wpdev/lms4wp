<?php
/**
 * Normalize practice case block markup for dynamic TechArticle blocks.
 *
 * Dynamic blocks (save => null) must not wrap inner blocks in extra
 * section/div elements inside block comments — PHP render callbacks add wrappers.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Block markup helpers for practice case content.
 */
class PracticeCaseBlockMarkup
{
	/**
	 * Strip redundant wrappers from TechArticle dynamic block markup.
	 */
	public static function normalize(string $content): string
	{
		$replacements = [
			"<!-- wp:forwp-seo/techarticle-goal -->\n<section class=\"forwp-seo-techarticle-goal\">" => '<!-- wp:forwp-seo/techarticle-goal -->',
			"</section>\n<!-- /wp:forwp-seo/techarticle-goal -->" => '<!-- /wp:forwp-seo/techarticle-goal -->',
			"<!-- wp:forwp-seo/techarticle-context -->\n<section class=\"forwp-seo-techarticle-context\">" => '<!-- wp:forwp-seo/techarticle-context -->',
			"</section>\n<!-- /wp:forwp-seo/techarticle-context -->" => '<!-- /wp:forwp-seo/techarticle-context -->',
			"<!-- wp:forwp-seo/techarticle-issues -->\n<section class=\"forwp-seo-techarticle-issues\">" => '<!-- wp:forwp-seo/techarticle-issues -->',
			"</section>\n<!-- /wp:forwp-seo/techarticle-issues -->" => '<!-- /wp:forwp-seo/techarticle-issues -->',
			"<!-- wp:forwp-seo/techarticle-steps -->\n<div class=\"forwp-seo-techarticle-steps\">" => '<!-- wp:forwp-seo/techarticle-steps -->',
			"</div>\n<!-- /wp:forwp-seo/techarticle-steps -->" => '<!-- /wp:forwp-seo/techarticle-steps -->',
			"<!-- wp:forwp-seo/techarticle-step -->\n<div class=\"forwp-seo-techarticle-step\">" => '<!-- wp:forwp-seo/techarticle-step -->',
			"</div>\n<!-- /wp:forwp-seo/techarticle-step -->" => '<!-- /wp:forwp-seo/techarticle-step -->',
		];

		return str_replace(array_keys($replacements), array_values($replacements), $content);
	}

	/**
	 * Replace terminal block attrs for post-meta runtime (embedded challenge data).
	 *
	 * @param string               $content Block content.
	 * @param array<string, mixed> $data    Challenge profile JSON.
	 */
	public static function embedTerminalBlock(string $content, array $data): string
	{
		$welcome = '';
		if (!empty($data['instructions']) && is_string($data['instructions'])) {
			$welcome = $data['instructions'];
		} elseif (!empty($data['description']) && is_string($data['description'])) {
			$welcome = $data['description'];
		}

		$terminal_attrs = wp_json_encode(
			[
				'profile'        => 'embedded',
				'welcomeMessage' => $welcome,
				'className'      => 'forwp-practice-case__terminal',
			],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$replacement = sprintf('<!-- wp:forwp-advanced-code/terminal %s /-->', $terminal_attrs);

		if (preg_match('/<!-- wp:forwp-advanced-code\/terminal\b/', $content)) {
			return (string) preg_replace(
				'/<!-- wp:forwp-advanced-code\/terminal\s+\{.*?\}\s+\/-->/s',
				$replacement,
				$content,
				1
			);
		}

		return $content . "\n\n" . $replacement;
	}

	/**
	 * Move terminal block to the top of post content (layout + mobile fallback).
	 */
	public static function orderForLayout(string $content): string
	{
		if (!preg_match('/<!-- wp:forwp-advanced-code\/terminal\b[^>]*\/-->/s', $content, $match)) {
			return $content;
		}

		$terminal = $match[0];
		$content  = preg_replace('/\n?\s*<!-- wp:forwp-advanced-code\/terminal\b[^>]*\/-->\s*/s', "\n\n", $content, 1) ?? $content;

		return trim($terminal) . "\n\n" . trim($content);
	}
}
