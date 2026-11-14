<?php
/**
 * 4wp-advanced-code terminal fed by course chapter data (ADR 0006).
 *
 * Chapter terminal.json (hub) → course meta _lms4wp_terminals[module] (import)
 * → profile "lms4wp-lesson-<ID>" served through the forwp_ac_terminal_profile_data filter.
 *
 * @package ForWP\LMS\Integrations
 */

namespace ForWP\LMS\Integrations;

use ForWP\LMS\Content\CourseStructure;
use ForWP\LMS\Import\CourseImporter;
use ForWP\LMS\PostTypes\Lesson;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class AdvancedCodeTerminal
{
	const PROFILE_PREFIX = 'lms4wp-lesson-';
	const TERMINAL_BLOCK = 'forwp-advanced-code/terminal';

	public static function init(): void
	{
		add_filter('forwp_ac_terminal_profile_data', [self::class, 'provideProfile'], 10, 2);
		add_filter('forwp_ac_terminal_title', [self::class, 'title'], 10, 2);
	}

	/**
	 * forwp_ac_terminal_title: chapter title instead of the "lms4wp-lesson-<ID>" slug.
	 */
	public static function title(string $title, string $profile): string
	{
		if (!preg_match('/^' . preg_quote(self::PROFILE_PREFIX, '/') . '(\d+)$/', $profile, $m)) {
			return $title;
		}
		$context = self::forLesson((int) $m[1]);
		return $context && !empty($context['terminal']['title']) ? (string) $context['terminal']['title'] : $title;
	}

	public static function profileSlug(int $lesson_id): string
	{
		return self::PROFILE_PREFIX . $lesson_id;
	}

	/**
	 * Chapter terminal data for a lesson.
	 *
	 * @return array{terminal: array<string, mixed>, lesson: array<string, mixed>}|null
	 */
	public static function forLesson(int $lesson_id): ?array
	{
		if (get_post_type($lesson_id) !== Lesson::POST_TYPE) {
			return null;
		}
		$course_id = CourseStructure::courseIdForLesson($lesson_id);
		if ($course_id === 0) {
			return null;
		}

		$terminals = get_post_meta($course_id, CourseImporter::META_TERMINALS, true);
		$module = (string) get_post_meta($lesson_id, CourseImporter::META_MODULE, true);
		$terminal = is_array($terminals) ? ($terminals[$module] ?? null) : null;
		if (!is_array($terminal) || empty($terminal['commands']) || !is_array($terminal['commands'])) {
			return null;
		}

		$lesson_key = pathinfo((string) get_post_meta($lesson_id, CourseImporter::META_SOURCE, true), PATHINFO_FILENAME);
		$lesson = $terminal['lessons'][$lesson_key] ?? [];

		return ['terminal' => $terminal, 'lesson' => is_array($lesson) ? $lesson : []];
	}

	/**
	 * forwp_ac_terminal_profile_data: build "lms4wp-lesson-<ID>" from chapter data.
	 *
	 * @param array<string, mixed>|null $data
	 * @return array<string, mixed>|null
	 */
	public static function provideProfile($data, string $profile)
	{
		if (is_array($data) || !preg_match('/^' . preg_quote(self::PROFILE_PREFIX, '/') . '(\d+)$/', $profile, $m)) {
			return $data;
		}

		$lesson_id = (int) $m[1];
		// Public REST route: only lessons the visitor may read.
		if (get_post_status($lesson_id) !== 'publish' && !current_user_can('read_post', $lesson_id)) {
			return $data;
		}

		$context = self::forLesson($lesson_id);
		return $context ? self::buildProfile($profile, $context['terminal'], $context['lesson']) : $data;
	}

	/**
	 * Profile in 4wp-advanced-code format. All chapter commands work;
	 * the sidebar shows the lesson's commands first.
	 *
	 * @param array<string, mixed> $terminal
	 * @param array<string, mixed> $lesson
	 * @return array<string, mixed>
	 */
	public static function buildProfile(string $profile, array $terminal, array $lesson): array
	{
		$commands = array_values(array_filter(
			$terminal['commands'],
			static fn($c): bool => is_array($c)
				&& (!empty($c['command']) || !empty($c['pattern']))
				&& !empty($c['output']) && is_string($c['output'])
		));

		$by_slug = [];
		foreach ($commands as $command) {
			if (!empty($command['slug'])) {
				$by_slug[(string) $command['slug']] ??= $command;
			}
		}

		$item = static fn(array $c): array => [
			'cmd'  => (string) ($c['command'] ?? $c['pattern']),
			'desc' => (string) ($c['tip'] ?? ''),
		];

		$focus = [];
		foreach ((array) ($lesson['commands'] ?? []) as $slug) {
			if (isset($by_slug[$slug])) {
				$focus[$slug] = $item($by_slug[$slug]);
			}
		}
		$rest = [];
		foreach ($by_slug as $slug => $command) {
			if (!isset($focus[$slug]) && $slug !== 'help') {
				$rest[] = $item($command);
			}
		}

		$categories = [];
		if ($focus) {
			$categories[] = ['id' => 'lesson', 'name' => __('This lesson', 'lms4wp'), 'color' => '#4ec9b0', 'commands' => array_values($focus)];
		}
		if ($rest) {
			$categories[] = ['id' => 'chapter', 'name' => __('Chapter', 'lms4wp'), 'color' => '#569cd6', 'commands' => $rest];
		}

		return [
			'version'    => 1,
			'profile'    => $profile,
			'title'      => (string) ($terminal['title'] ?? 'bash'),
			'prompt'     => (string) ($terminal['prompt'] ?? '$'),
			'categories' => $categories,
			'commands'   => $commands,
		];
	}

	/**
	 * Render the 4wp-advanced-code terminal for a lesson ('' without chapter data or plugin).
	 */
	public static function render(int $lesson_id, string $welcome = ''): string
	{
		if (!\WP_Block_Type_Registry::get_instance()->is_registered(self::TERMINAL_BLOCK)) {
			return '';
		}
		$context = self::forLesson($lesson_id);
		if ($context === null) {
			return '';
		}

		if ($welcome === '') {
			$welcome = (string) ($context['lesson']['welcome'] ?? $context['terminal']['welcome'] ?? '');
		}
		$attrs = ['profile' => self::profileSlug($lesson_id)];
		if ($welcome !== '') {
			$attrs['welcomeMessage'] = $welcome;
		}

		return render_block([
			'blockName'    => self::TERMINAL_BLOCK,
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		]);
	}
}
