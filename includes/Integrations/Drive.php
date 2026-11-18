<?php
/**
 * 4wp-drive integration: course package import (ADR 0007).
 *
 * Package = course folder in the hub with meta.json ("package.type": "lms-course",
 * "original.file": "course-structure.md"). Drive imports the original into the course
 * post; LMS4WP queues the rest (modules, lessons, FAQ, terminals) and writes the result
 * back to meta.json under "lms". Drive knows nothing about LMS — only its public filters are used.
 *
 * @package ForWP\LMS\Integrations
 */

namespace ForWP\LMS\Integrations;

use ForWP\LMS\Import\CourseImporter;
use ForWP\LMS\Import\DrivePackageReader;
use ForWP\LMS\Import\PackageReader;
use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\Queue\ImportQueue;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class Drive
{
	const PACKAGE_TYPE = 'lms-course';
	const PLUGIN_SLUG  = 'lms4wp';

	public static function init(): void
	{
		add_filter('forwp_drive_family_plugins', [self::class, 'registerFamily']);
		add_filter('forwp_drive_import_postarr', [self::class, 'reuseCourse'], 10, 2);
		add_action('forwp_drive_document_imported', [self::class, 'onImported'], 20, 2);
		add_action('lms4wp_import_finished', [self::class, 'writeBack'], 10, 4);
	}

	/**
	 * @param array<string, array<string, mixed>> $plugins
	 * @return array<string, array<string, mixed>>
	 */
	public static function registerFamily($plugins)
	{
		$plugins = is_array($plugins) ? $plugins : [];
		$plugins[self::PLUGIN_SLUG] = [
			'slug'        => self::PLUGIN_SLUG,
			'label'       => 'LMS4WP',
			'file'        => 'lms4wp/lms4wp.php',
			'uri'         => 'https://4wp.dev/',
			'wporg'       => '',
			'description' => __('Course packages (meta.json, "lms-course") → course, modules, lessons, FAQ and terminals, imported in the background.', 'lms4wp'),
		];
		return $plugins;
	}

	/**
	 * meta.json of a post imported by Drive, when it is a course package.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function coursePackage(int $post_id): ?array
	{
		$package = apply_filters('forwp_drive_package', null, $post_id, true);
		if (!is_array($package) || ($package['package']['type'] ?? '') !== self::PACKAGE_TYPE) {
			return null;
		}
		return $package;
	}

	/**
	 * Drive creates a new course post: reuse the course already imported from the same package
	 * (CLI or an earlier import), so there is one course per package.
	 *
	 * @param array<string, mixed> $postarr
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	public static function reuseCourse($postarr, $metadata)
	{
		if (!is_array($postarr) || ($postarr['post_type'] ?? '') !== Course::POST_TYPE || !empty($postarr['ID'])) {
			return $postarr;
		}
		$slug = sanitize_title((string) (is_array($metadata) ? ($metadata['slug'] ?? '') : ''));
		$existing = $slug !== '' ? CourseImporter::findCourseId($slug) : 0;
		if ($existing > 0) {
			$postarr['ID'] = $existing;
			$postarr['post_name'] = (string) get_post_field('post_name', $existing);
			$postarr['post_status'] = (string) get_post_status($existing);
		}
		return $postarr;
	}

	/**
	 * Drive imported the package original into a course post: queue the course.
	 */
	public static function onImported($post_id, $document_id = 0): void
	{
		$post_id = (int) $post_id;
		if (get_post_type($post_id) !== Course::POST_TYPE) {
			return;
		}
		$package = self::coursePackage($post_id);
		if ($package === null) {
			return;
		}

		$slug = sanitize_title((string) ($package['package']['id'] ?? get_post_field('post_name', $post_id)));
		$existing = CourseImporter::findCourseId($slug);
		if ($existing > 0 && $existing !== $post_id) {
			// Second course for one package: keep the first one, report.
			apply_filters('forwp_drive_update_package', null, $post_id, ['lms' => [
				'state' => 'failed',
				'error' => sprintf('Course "%s" already exists as post #%d.', $slug, $existing),
			]]);
			return;
		}
		update_post_meta($post_id, CourseImporter::META_SOURCE, $slug . '/' . CourseImporter::COURSE_FILE);
		// Drive just wrote its own conversion of the original: let the course step rebuild it.
		delete_post_meta($post_id, CourseImporter::META_SOURCE_HASH);

		$reader = new DrivePackageReader($post_id, $slug);
		ImportQueue::enqueue($reader, [
			'status' => (string) get_post_status($post_id),
			'author' => (int) get_post_field('post_author', $post_id),
			'commit' => self::hubCommit(),
		]);

		apply_filters('forwp_drive_update_package', null, $post_id, ['lms' => [
			'state'     => 'queued',
			'queued_at' => gmdate('c'),
		]]);
	}

	/**
	 * Import finished: course, module and lesson IDs, verify and commit → meta.json "lms".
	 *
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $options
	 */
	public static function writeBack($slug, $state, $reader, $options): void
	{
		if (!$reader instanceof DrivePackageReader) {
			return;
		}
		$course_id = CourseImporter::findCourseId((string) $slug);

		// Module and lesson IDs by their source file (modules: module.md or requirements.md).
		$modules = self::idsBySource('lms_module', (string) $slug);
		$lessons = self::idsBySource('lms_lesson', (string) $slug);

		$issues = (array) ($state['issues'] ?? []);
		apply_filters('forwp_drive_update_package', null, $reader->postId(), ['lms' => [
			'state'       => (string) ($state['state'] ?? 'done'),
			'course_id'   => $course_id,
			'modules'     => $modules,
			'lessons'     => $lessons,
			'commit'      => (string) ($options['commit'] ?? ''),
			'verify'      => $issues ? count($issues) . ' differences' : 'ok',
			'errors'      => array_values((array) ($state['errors'] ?? [])),
			'finished_at' => gmdate('c'),
		]]);
	}

	/**
	 * Imported posts of a package: "module-1-x/file.md" => post ID.
	 *
	 * @return array<string, int>
	 */
	private static function idsBySource(string $post_type, string $slug): array
	{
		$out = [];
		$sources = get_posts([
			'post_type'        => $post_type,
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [[
				'key'     => CourseImporter::META_SOURCE,
				'value'   => '^' . $slug . '/',
				'compare' => 'REGEXP',
			]],
		]);
		foreach ($sources as $post_id) {
			$source = (string) get_post_meta((int) $post_id, CourseImporter::META_SOURCE, true);
			$out[substr($source, strlen($slug) + 1)] = (int) $post_id;
		}
		ksort($out);
		return $out;
	}

	/**
	 * Hub HEAD (short) from Drive's GitHub client, '' when unavailable.
	 */
	private static function hubCommit(): string
	{
		if (!class_exists('\\ForWP\\Drive\\Api\\GitHub_Client')) {
			return '';
		}
		$sha = (new \ForWP\Drive\Api\GitHub_Client())->head_sha();
		return is_string($sha) ? substr($sha, 0, 7) : '';
	}
}
