<?php
/**
 * Markdown → core block markup converter for hub content.
 *
 * Supports the subset described in docs/COURSE-FORMAT.md.
 *
 * @package ForWP\LMS\Import
 */

namespace ForWP\LMS\Import;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class MarkdownBlocks
{
	/**
	 * Convert Markdown body to serialized blocks.
	 *
	 * Options:
	 * - faq_sections: H2 titles whose "### question + answer" pairs become a forwp/faq accordion;
	 * - faq_attrs:    attributes of the forwp/faq wrapper (category).
	 *
	 * Fence ```terminal: empty → lms4wp/lesson-terminal (chapter terminal.json); with "profile: …" → forwp-advanced-code/terminal.
	 *
	 * @param string $markdown Markdown without front-matter.
	 * @param bool   $skip_h1  Drop the first H1 (duplicates post title).
	 * @param array{faq_sections?: string[], faq_attrs?: array<string, mixed>} $options
	 */
	public static function convert(string $markdown, bool $skip_h1 = true, array $options = []): string
	{
		$lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
		$count = count($lines);
		$blocks = [];
		$para = [];
		$list = [];
		$list_ordered = false;
		$quote = [];
		$h1_skipped = !$skip_h1;

		$faq_sections = array_map([self::class, 'normalizeHeading'], $options['faq_sections'] ?? []);
		$faq_attrs = $options['faq_attrs'] ?? [];
		$in_faq = false;
		$faq = [];

		// Inside a FAQ item, blocks go to its answer.
		$push = static function (string $block) use (&$blocks, &$faq, &$in_faq): void {
			if ($in_faq && $faq) {
				$faq[count($faq) - 1]['answer'][] = $block;
				return;
			}
			$blocks[] = $block;
		};

		$flush = static function () use ($push, &$para, &$list, &$list_ordered, &$quote): void {
			if ($para) {
				$push(self::paragraph(implode(' ', $para)));
				$para = [];
			}
			if ($list) {
				$push(self::listBlock($list, $list_ordered));
				$list = [];
			}
			if ($quote) {
				$push(self::quote(implode(' ', $quote)));
				$quote = [];
			}
		};

		$close_faq = static function () use (&$blocks, &$faq, &$in_faq, $faq_attrs): void {
			if ($faq) {
				$blocks[] = self::faq($faq, $faq_attrs);
			}
			$faq = [];
			$in_faq = false;
		};

		for ($i = 0; $i < $count; $i++) {
			$line = rtrim($lines[$i]);

			// Fenced code.
			if (preg_match('/^\s*```\s*([\w+-]*)\s*$/', $line, $m)) {
				$flush();
				$code = [];
				for ($i++; $i < $count && !preg_match('/^\s*```\s*$/', $lines[$i]); $i++) {
					$code[] = rtrim($lines[$i], "\r");
				}
				$lang = strtolower($m[1]);
				if ($lang === 'terminal') {
					$push(self::terminal($code));
				} elseif ($lang === 'mermaid') {
					$push(self::diagram(implode("\n", $code)));
				} else {
					$push(self::code(implode("\n", $code), $m[1]));
				}
				continue;
			}

			if (trim($line) === '') {
				$flush();
				continue;
			}

			if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $line, $m)) {
				$flush();
				$level = strlen($m[1]);
				if ($level === 1 && !$h1_skipped) {
					$h1_skipped = true;
					continue;
				}
				if ($level <= 2) {
					$close_faq();
					$blocks[] = self::heading($m[2], $level);
					$in_faq = $level === 2 && in_array(self::normalizeHeading($m[2]), $faq_sections, true);
					continue;
				}
				if ($level === 3 && $in_faq) {
					$faq[] = ['question' => $m[2], 'answer' => []];
					continue;
				}
				$push(self::heading($m[2], $level));
				continue;
			}

			if (preg_match('/^\s*(-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
				$flush();
				$push("<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->");
				continue;
			}

			if (self::isTableRow($line) && isset($lines[$i + 1]) && self::isTableSeparator($lines[$i + 1])) {
				$flush();
				$rows = [$line];
				for ($i += 2; $i < $count && self::isTableRow(rtrim($lines[$i])); $i++) {
					$rows[] = rtrim($lines[$i]);
				}
				$i--;
				$push(self::table($rows));
				continue;
			}

			if (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
				if ($para || $list) {
					$flush();
				}
				$quote[] = $m[1];
				continue;
			}

			if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m) || preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $m2)) {
				$ordered = !isset($m[1]);
				$item = $ordered ? $m2[1] : $m[1];
				if ($para || $quote || ($list && $list_ordered !== $ordered)) {
					$flush();
				}
				$list_ordered = $ordered;
				$list[] = $item;
				continue;
			}

			// Indented continuation of a list item.
			if ($list && preg_match('/^\s+\S/', $line)) {
				$list[count($list) - 1] .= ' ' . trim($line);
				continue;
			}

			if ($list || $quote) {
				$flush();
			}
			$para[] = trim($line);
		}
		$flush();
		$close_faq();

		return implode("\n\n", $blocks);
	}

	private static function normalizeHeading(string $text): string
	{
		$text = str_replace(['’', 'ʼ', '`'], "'", trim($text));
		return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
	}

	/**
	 * @param array<string, mixed> $attrs
	 */
	private static function attrs(array $attrs): string
	{
		if (!$attrs) {
			return '';
		}
		$json = function_exists('serialize_block_attributes')
			? serialize_block_attributes($attrs)
			: (string) json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return ' ' . $json;
	}

	/**
	 * Mermaid fence → 4wp-advanced-code diagram block (same markup as its Drive mapping).
	 */
	private static function diagram(string $code): string
	{
		$code = trim($code);
		return $code === '' ? '' : '<!-- wp:forwp/diagram' . self::attrs(['code' => $code]) . ' /-->';
	}

	/**
	 * 4wp-advanced-code terminal from "key: value" lines (profile, welcome).
	 *
	 * @param string[] $lines
	 */
	private static function terminal(array $lines): string
	{
		$attrs = [];
		foreach ($lines as $line) {
			if (!preg_match('/^\s*([a-z_-]+)\s*:\s*(.+)$/i', $line, $m)) {
				continue;
			}
			$key = strtolower($m[1]);
			$value = trim($m[2]);
			if ($key === 'profile') {
				$attrs['profile'] = preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
			} elseif ($key === 'welcome') {
				$attrs['welcomeMessage'] = $value;
			}
		}

		// No profile: chapter terminal from course data (terminal.json, ADR 0006).
		if (empty($attrs['profile'])) {
			$lesson_attrs = isset($attrs['welcomeMessage']) ? ['welcome' => $attrs['welcomeMessage']] : [];
			return '<!-- wp:lms4wp/lesson-terminal' . self::attrs($lesson_attrs) . ' /-->';
		}

		return '<!-- wp:forwp-advanced-code/terminal' . self::attrs($attrs) . ' /-->';
	}

	/**
	 * 4wp-faq wrapper around a core accordion (same markup as the editor saves).
	 *
	 * @param array<int, array{question: string, answer: string[]}> $items
	 * @param array<string, mixed> $attrs
	 */
	private static function faq(array $items, array $attrs): string
	{
		$html = [];
		foreach ($items as $item) {
			$html[] = "<!-- wp:accordion-item -->\n<div class=\"wp-block-accordion-item\"><!-- wp:accordion-heading -->\n"
				. '<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle">'
				. '<span class="wp-block-accordion-heading__toggle-title">' . self::inline($item['question']) . '</span>'
				. '<span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>'
				. "\n<!-- /wp:accordion-heading -->\n\n<!-- wp:accordion-panel -->\n<div role=\"region\" class=\"wp-block-accordion-panel\">"
				. implode("\n\n", $item['answer'])
				. "</div>\n<!-- /wp:accordion-panel --></div>\n<!-- /wp:accordion-item -->";
		}

		return '<!-- wp:forwp/faq' . self::attrs($attrs) . " -->\n<!-- wp:accordion -->\n<div role=\"group\" class=\"wp-block-accordion\">"
			. implode("\n\n", $html)
			. "</div>\n<!-- /wp:accordion -->\n<!-- /wp:forwp/faq -->";
	}

	private static function paragraph(string $text): string
	{
		return "<!-- wp:paragraph -->\n<p>" . self::inline($text) . "</p>\n<!-- /wp:paragraph -->";
	}

	private static function heading(string $text, int $level): string
	{
		$attrs = $level === 2 ? '' : ' {"level":' . $level . '}';
		return "<!-- wp:heading{$attrs} -->\n<h{$level} class=\"wp-block-heading\">" . self::inline($text) . "</h{$level}>\n<!-- /wp:heading -->";
	}

	/**
	 * @param string[] $items
	 */
	private static function listBlock(array $items, bool $ordered): string
	{
		$tag = $ordered ? 'ol' : 'ul';
		$attrs = $ordered ? ' {"ordered":true}' : '';
		$html = '';
		foreach ($items as $item) {
			$html .= "<!-- wp:list-item -->\n<li>" . self::inline($item) . "</li>\n<!-- /wp:list-item -->\n";
		}
		return "<!-- wp:list{$attrs} -->\n<{$tag} class=\"wp-block-list\">" . $html . "</{$tag}>\n<!-- /wp:list -->";
	}

	private static function quote(string $text): string
	{
		return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">" . self::paragraph($text) . "</blockquote>\n<!-- /wp:quote -->";
	}

	/**
	 * Explicit language for 4wp-advanced-code (core/code "language" attribute):
	 * never "auto" — auto-detect mislabels lesson snippets (e.g. git commands as SQL).
	 */
	private static function codeLanguage(string $lang): string
	{
		$lang = strtolower(preg_replace('/[^\w+-]/', '', $lang) ?? '');
		$aliases = [
			'sh' => 'bash', 'shell' => 'bash', 'zsh' => 'bash', 'console' => 'bash', 'terminal' => 'bash',
			'js' => 'javascript', 'ts' => 'typescript', 'yml' => 'yaml', 'md' => 'markdown',
			'text' => 'plaintext', 'txt' => 'plaintext', 'ps1' => 'powershell', 'htm' => 'html', 'gql' => 'graphql',
		];
		$lang = $aliases[$lang] ?? $lang;
		$supported = [
			'php', 'javascript', 'typescript', 'html', 'css', 'scss', 'python', 'json', 'sql',
			'bash', 'powershell', 'yaml', 'xml', 'markdown', 'plaintext', 'mermaid', 'graphql', 'liquid',
		];
		return in_array($lang, $supported, true) ? $lang : 'plaintext';
	}

	private static function code(string $code, string $lang): string
	{
		$language = self::codeLanguage($lang);
		$attrs = ['language' => $language];
		$class = '';
		if ($language !== 'plaintext') {
			$attrs['className'] = 'language-' . $language;
			$class = ' language-' . $language;
		}
		return '<!-- wp:code' . self::attrs($attrs) . " -->\n<pre class=\"wp-block-code{$class}\"><code>" . esc_html($code) . "</code></pre>\n<!-- /wp:code -->";
	}

	/**
	 * @param string[] $rows Header row followed by body rows (separator removed).
	 */
	private static function table(array $rows): string
	{
		$head = self::cells(array_shift($rows));
		$html = '<thead><tr>';
		foreach ($head as $cell) {
			$html .= '<th>' . self::inline($cell) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ($rows as $row) {
			$html .= '<tr>';
			foreach (self::cells($row) as $cell) {
				$html .= '<td>' . self::inline($cell) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';
		return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">" . $html . "</table></figure>\n<!-- /wp:table -->";
	}

	/**
	 * @return string[]
	 */
	private static function cells(string $row): array
	{
		$row = trim($row);
		if (str_starts_with($row, '|')) {
			$row = substr($row, 1);
		}
		if (str_ends_with($row, '|') && !str_ends_with($row, '\\|')) {
			$row = substr($row, 0, -1);
		}

		// Split on pipes outside inline code; "\|" is a literal pipe.
		$cells = [];
		$current = '';
		$in_code = false;
		$length = strlen($row);
		for ($i = 0; $i < $length; $i++) {
			$char = $row[$i];
			if ($char === '\\' && ($row[$i + 1] ?? '') === '|') {
				$current .= '|';
				$i++;
				continue;
			}
			if ($char === '`') {
				$in_code = !$in_code;
			}
			if ($char === '|' && !$in_code) {
				$cells[] = trim($current);
				$current = '';
				continue;
			}
			$current .= $char;
		}
		$cells[] = trim($current);

		return $cells;
	}

	private static function isTableRow(string $line): bool
	{
		return (bool) preg_match('/^\s*\|.*\|\s*$/', $line);
	}

	private static function isTableSeparator(string $line): bool
	{
		return (bool) preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', rtrim($line));
	}

	/**
	 * Inline Markdown → safe HTML.
	 */
	private static function inline(string $text): string
	{
		$codes = [];
		$text = preg_replace_callback(
			'/`([^`]+)`/',
			static function (array $m) use (&$codes): string {
				$codes[] = '<code>' . esc_html($m[1]) . '</code>';
				return '@@LMS4WPCODE' . (count($codes) - 1) . '@@';
			},
			$text
		) ?? $text;

		$text = esc_html($text);

		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			static fn(array $m): string => '<a href="' . esc_url(html_entity_decode($m[2], ENT_QUOTES)) . '">' . $m[1] . '</a>',
			$text
		) ?? $text;
		$text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
		$text = preg_replace('/(?<![*\w])\*(?![\s*])(.+?)(?<!\s)\*(?![*\w])/u', '<em>$1</em>', $text) ?? $text;

		return preg_replace_callback(
			'/@@LMS4WPCODE(\d+)@@/',
			static fn(array $m): string => $codes[(int) $m[1]] ?? '',
			$text
		) ?? $text;
	}
}
