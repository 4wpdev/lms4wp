<?php
/**
 * Front-matter parser for hub Markdown files.
 *
 * @package ForWP\LMS\Import
 */

namespace ForWP\LMS\Import;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Splits a Markdown file into flat front-matter (key: value) and body.
 */
final class FrontMatter
{
	/**
	 * Parse Markdown text.
	 *
	 * @param string $text Raw file contents.
	 * @return array{meta: array<string, string>, body: string}
	 */
	public static function parse(string $text): array
	{
		$text = str_replace(["\r\n", "\r"], "\n", $text);
		$text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

		if (!preg_match('/\A---\n(.*?)\n---[ \t]*(?:\n|\z)/s', $text, $m)) {
			return ['meta' => [], 'body' => $text];
		}

		$meta = [];
		foreach (explode("\n", $m[1]) as $line) {
			if (!preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*)$/', $line, $kv)) {
				continue;
			}
			$value = trim($kv[2]);
			if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
				$value = substr($value, 1, -1);
			}
			$meta[strtolower($kv[1])] = $value;
		}

		return ['meta' => $meta, 'body' => substr($text, strlen($m[0]))];
	}

	/**
	 * Inline list value: ["a", "b"] or a, b.
	 *
	 * @return string[]
	 */
	public static function listValue(string $raw): array
	{
		$raw = trim($raw);
		if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
			$raw = substr($raw, 1, -1);
		}
		$items = [];
		foreach (explode(',', $raw) as $item) {
			$item = trim(trim($item), '"\'');
			if ($item !== '') {
				$items[] = $item;
			}
		}
		return array_values(array_unique($items));
	}
}
