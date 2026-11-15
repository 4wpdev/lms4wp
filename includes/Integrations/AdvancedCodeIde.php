<?php
/**
 * 4wp-advanced-code IDE practice fed by course chapter data (ADR 0010).
 *
 * Chapter ide.json (hub) → module post meta _lms4wp_ide (import)
 * → practice profile "lms4wp-lesson-<ID>" served through the forwp_ac_ide_practice_data filter.
 *
 * @package ForWP\LMS\Integrations
 */

namespace ForWP\LMS\Integrations;

use ForWP\LMS\Content\CourseStructure;
use ForWP\LMS\Import\CourseImporter;
use ForWP\LMS\PostTypes\Lesson;
use ForWP\LMS\PostTypes\Module;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class AdvancedCodeIde
{
	const PROFILE_PREFIX = 'lms4wp-lesson-';
	const IDE_BLOCK = 'forwp-advanced-code/ide';

	public static function init(): void
	{
		add_filter('forwp_ac_ide_practice_data', [self::class, 'providePractice'], 10, 2);
	}

	public static function profileSlug(int $lesson_id): string
	{
		return self::PROFILE_PREFIX . $lesson_id;
	}

	/**
	 * Practice of one lesson from the chapter ide.json: { title, tasks }.
	 *
	 * @return array{title: string, tasks: array<int, array<string, mixed>>}|null
	 */
	public static function forLesson(int $lesson_id): ?array
	{
		if (get_post_type($lesson_id) !== Lesson::POST_TYPE || CourseStructure::courseIdForLesson($lesson_id) === 0) {
			return null;
		}

		$module_id = Module::forLesson($lesson_id);
		$ide = $module_id > 0 ? get_post_meta($module_id, CourseImporter::META_IDE, true) : null;
		if (!is_array($ide) || empty($ide['lessons']) || !is_array($ide['lessons'])) {
			return null;
		}

		$lesson_key = pathinfo((string) get_post_meta($lesson_id, CourseImporter::META_SOURCE, true), PATHINFO_FILENAME);
		$lesson = $ide['lessons'][$lesson_key] ?? null;
		if (!is_array($lesson) || empty($lesson['tasks']) || !is_array($lesson['tasks'])) {
			return null;
		}

		return [
			'title' => (string) ($lesson['title'] ?? $ide['title'] ?? ''),
			'tasks' => array_values($lesson['tasks']),
		];
	}

	/**
	 * forwp_ac_ide_practice_data: practice for "lms4wp-lesson-<ID>".
	 *
	 * @param array<string, mixed>|null $data
	 * @return array<string, mixed>|null
	 */
	public static function providePractice($data, string $profile)
	{
		if (is_array($data) || !preg_match('/^' . preg_quote(self::PROFILE_PREFIX, '/') . '(\d+)$/', $profile, $m)) {
			return $data;
		}

		$lesson_id = (int) $m[1];
		// Public REST route: only lessons the visitor may read.
		if (get_post_status($lesson_id) !== 'publish' && !current_user_can('read_post', $lesson_id)) {
			return $data;
		}

		return self::forLesson($lesson_id) ?? $data;
	}

	/**
	 * Render the 4wp-advanced-code IDE for a lesson ('' without chapter data or plugin).
	 */
	public static function render(int $lesson_id): string
	{
		if (!\WP_Block_Type_Registry::get_instance()->is_registered(self::IDE_BLOCK) || self::forLesson($lesson_id) === null) {
			return '';
		}

		return render_block([
			'blockName'    => self::IDE_BLOCK,
			'attrs'        => ['practice' => self::profileSlug($lesson_id)],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		]);
	}
}
