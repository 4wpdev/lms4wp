<?php
/**
 * Imports and verifies a course package from the content hub
 * (docs/COURSE-FORMAT.md, ADR 0005).
 *
 * @package ForWP\LMS\Import
 */

namespace ForWP\LMS\Import;

use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Lesson;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseImporter
{
	const META_SOURCE        = '_lms4wp_source';
	const META_SOURCE_HASH   = '_lms4wp_source_hash';
	const META_SOURCE_COMMIT = '_lms4wp_source_commit';
	const META_MODULES       = '_lms4wp_modules';
	const META_TERMINALS     = '_lms4wp_terminals';
	const META_MODULE        = '_lms4wp_module';
	const META_COURSE_ID     = '_lms4wp_course_id';
	const META_ORDER         = '_lms4wp_order';

	const TAXONOMY_LEVEL = 'lms_level';
	const TAXONOMY_TAG   = 'lms_tag';
	const FAQ_SECTIONS   = ["Питання з інтерв'ю", 'FAQ'];

	const COURSE_FILE = 'course-structure.md';
	const MODULE_FILE = 'requirements.md';
	const TERMINAL_FILE = 'terminal.json';
	const STATUSES    = ['draft', 'pending', 'private', 'publish'];

	/** @var callable(string): void */
	private $log;

	private string $status;
	private bool $force;
	private bool $dry_run;
	private bool $replace;
	private string $commit;
	private int $author;

	/** @var array{created: int, updated: int, skipped: int, deleted: int} */
	private array $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];

	/**
	 * @param array{status?: string, force?: bool, dry_run?: bool, replace?: bool, commit?: string, author?: int} $options
	 *        status  — override front-matter status ('' = from file);
	 *        force   — update unchanged files too;
	 *        dry_run — report only, write nothing;
	 *        replace — delete the course and its lessons on the site, then import;
	 *        commit  — hub git commit the package comes from;
	 *        author  — post_author for created / updated posts (0 = leave as is).
	 * @param callable|null $log Line logger.
	 */
	public function __construct(array $options = [], ?callable $log = null)
	{
		$this->status = (string) ($options['status'] ?? '');
		$this->force = !empty($options['force']);
		$this->dry_run = !empty($options['dry_run']);
		$this->replace = !empty($options['replace']);
		$this->commit = (string) ($options['commit'] ?? '');
		$this->author = (int) ($options['author'] ?? 0);
		$this->log = $log ?? static function (string $line): void {
		};
	}

	/**
	 * Import a course package.
	 *
	 * @return array{course_id: int, modules: int, lessons: int, created: int, updated: int, skipped: int, deleted: int}|\WP_Error
	 */
	public function import(string $dir)
	{
		if ($this->status !== '' && !in_array($this->status, self::STATUSES, true)) {
			return new \WP_Error('lms4wp_import_status', sprintf('Unknown status "%s".', $this->status));
		}

		$package = $this->readPackage($dir);
		if (is_wp_error($package)) {
			return $package;
		}

		if ($this->replace) {
			$this->deleteCourse($package['slug']);
		}

		// Course.
		$course = $package['course'];
		$course_id = $this->upsert(
			Course::POST_TYPE,
			$course,
			[
				'post_title'   => $course['title'],
				'post_name'    => $package['slug'],
				'post_content' => MarkdownBlocks::convert($course['doc']['body']),
				'post_status'  => $this->statusFor($course['doc']['meta']),
			],
			// Guest access (ADR 0003): front-matter "guest_lessons" → course setting.
			isset($course['doc']['meta']['guest_lessons'])
				? ['_lms4wp_guest_lessons' => absint($course['doc']['meta']['guest_lessons'])]
				: [],
			$this->termsFor($course['doc']['meta'])
		);
		if (is_wp_error($course_id)) {
			return $course_id;
		}

		if (!$this->dry_run && $course_id > 0) {
			update_post_meta($course_id, self::META_MODULES, $package['modules']);
			update_post_meta($course_id, self::META_TERMINALS, $package['terminals']);
			update_post_meta($course_id, self::META_SOURCE_COMMIT, $this->commit);
		}

		// Lessons.
		$modules = array_column($package['modules'], null, 'key');
		$faq_attrs = [];

		foreach ($package['lessons'] as $lesson) {
			// FAQ category only for lessons that have Q&A (### under a FAQ section).
			$key = $lesson['module'];
			if (!isset($faq_attrs[$key]) && isset($modules[$key]) && preg_match('/^###\s+\S/m', $lesson['doc']['body'])) {
				$faq_attrs[$key] = $this->faqAttrs($package['slug'], $course['title'], $modules[$key]);
			}

			$result = $this->upsert(
				Lesson::POST_TYPE,
				$lesson,
				[
					'post_title'   => $lesson['title'],
					'post_name'    => $package['slug'] . '-' . $lesson['slug'],
					'post_content' => MarkdownBlocks::convert($lesson['doc']['body'], true, [
						'faq_sections' => self::FAQ_SECTIONS,
						'faq_attrs'    => $faq_attrs[$lesson['module']] ?? [],
					]),
					'post_status'  => $this->statusFor($lesson['doc']['meta']),
					'menu_order'   => $lesson['order'],
				],
				[
					self::META_COURSE_ID => $course_id,
					self::META_MODULE    => $lesson['module'],
					self::META_ORDER     => $lesson['order'],
				],
				$this->termsFor($lesson['doc']['meta'])
			);
			if (is_wp_error($result)) {
				return $result;
			}
		}

		return [
			'course_id' => $course_id,
			'modules'   => count($package['modules']),
			'lessons'   => count($package['lessons']),
		] + $this->stats;
	}

	/**
	 * Compare the site with a course package.
	 *
	 * @return array{course_id: int, modules: int, lessons: int, commit: string, issues: string[]}|\WP_Error
	 */
	public function verify(string $dir)
	{
		$package = $this->readPackage($dir);
		if (is_wp_error($package)) {
			return $package;
		}

		$site = [];
		foreach ($this->findSourcePosts($package['slug']) as $post_id) {
			$site[(string) get_post_meta($post_id, self::META_SOURCE, true)] = $post_id;
		}

		$issues = [];
		$course_id = $site[$package['course']['source']] ?? 0;
		$expected = array_merge([$package['course']], $package['lessons']);

		foreach ($expected as $item) {
			$post_id = $site[$item['source']] ?? 0;
			if ($post_id === 0) {
				$issues[] = 'missing   ' . $item['source'];
				continue;
			}
			if (get_post_meta($post_id, self::META_SOURCE_HASH, true) !== $item['doc']['hash']) {
				$issues[] = sprintf('changed   %s (#%d)', $item['source'], $post_id);
			}
			if (isset($item['module'])) {
				if ((int) get_post_meta($post_id, self::META_COURSE_ID, true) !== $course_id) {
					$issues[] = sprintf('course    %s (#%d): wrong course', $item['source'], $post_id);
				}
				if (get_post_meta($post_id, self::META_MODULE, true) !== $item['module']) {
					$issues[] = sprintf('module    %s (#%d): wrong module', $item['source'], $post_id);
				}
			}
			unset($site[$item['source']]);
		}

		foreach ($site as $source => $post_id) {
			$issues[] = sprintf('orphan    %s (#%d): not in package', $source, $post_id);
		}

		$site_commit = $course_id > 0 ? (string) get_post_meta($course_id, self::META_SOURCE_COMMIT, true) : '';
		if ($course_id > 0) {
			if (get_post_meta($course_id, self::META_MODULES, true) !== $package['modules']) {
				$issues[] = 'modules   course module list differs';
			}
			$site_terminals = get_post_meta($course_id, self::META_TERMINALS, true);
			if (($site_terminals === '' ? [] : $site_terminals) !== $package['terminals']) {
				$issues[] = 'terminals chapter terminal.json differs';
			}
			if ($this->commit !== '' && $site_commit !== $this->commit) {
				$issues[] = sprintf('commit    site %s, hub %s', $site_commit !== '' ? $site_commit : '—', $this->commit);
			}
		}

		return [
			'course_id' => $course_id,
			'modules'   => count($package['modules']),
			'lessons'   => count($package['lessons']),
			'commit'    => $site_commit,
			'issues'    => $issues,
		];
	}

	/**
	 * Front-matter level / tags → taxonomy terms.
	 *
	 * @param array<string, string> $meta
	 * @return array<string, string[]>
	 */
	private function termsFor(array $meta): array
	{
		$level = trim($meta['level'] ?? '');
		return [
			self::TAXONOMY_LEVEL => $level !== '' ? [$level] : [],
			self::TAXONOMY_TAG   => FrontMatter::listValue($meta['tags'] ?? ''),
		];
	}

	/**
	 * forwp/faq wrapper attributes: FAQ category "course › module" (4wp-faq).
	 * Creates the terms when 4wp-faq is active; otherwise falls back to a category name.
	 *
	 * @param array{key: string, label: string} $module
	 * @return array<string, mixed>
	 */
	private function faqAttrs(string $course_slug, string $course_title, array $module): array
	{
		$fallback = ['categoryMode' => 'new', 'categoryName' => $module['label']];

		$taxonomy = class_exists('\\ForWP\\FAQ\\Settings') ? \ForWP\FAQ\Settings::get_taxonomy() : 'faq-category';
		if ($this->dry_run || !taxonomy_exists($taxonomy)) {
			return $fallback;
		}

		$parent = $this->ensureTerm($taxonomy, $course_title, $course_slug, 0);
		$child = $parent > 0 ? $this->ensureTerm($taxonomy, $module['label'], $course_slug . '-' . $module['key'], $parent) : 0;
		if ($child === 0) {
			return $fallback;
		}

		return ['categoryMode' => 'existing', 'categoryTermIds' => [$child], 'categoryTermId' => $child];
	}

	private function ensureTerm(string $taxonomy, string $name, string $slug, int $parent): int
	{
		$term = get_term_by('slug', $slug, $taxonomy);
		if ($term instanceof \WP_Term) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term($name, $taxonomy, ['slug' => $slug, 'parent' => $parent]);
		if (is_wp_error($created)) {
			($this->log)(sprintf('warning FAQ term "%s": %s', $name, $created->get_error_message()));
			return 0;
		}
		($this->log)(sprintf('term    %s: %s', $taxonomy, $name));
		return (int) $created['term_id'];
	}

	/**
	 * Read a course package from disk.
	 *
	 * @return array{slug: string, course: array, modules: array, lessons: array, terminals: array<string, array>}|\WP_Error
	 */
	private function readPackage(string $dir)
	{
		$dir = realpath($dir);
		if ($dir === false || !is_dir($dir) || !is_readable($dir . '/' . self::COURSE_FILE)) {
			return new \WP_Error('lms4wp_import_dir', sprintf('Not a course directory (no %s).', self::COURSE_FILE));
		}

		$slug = sanitize_title(basename($dir));
		$doc = $this->readFile($dir . '/' . self::COURSE_FILE);
		$title = trim(explode(' — ', $doc['meta']['title'] ?? '')[0]);

		$course = [
			'source' => $slug . '/' . self::COURSE_FILE,
			'title'  => $title !== '' ? $title : $slug,
			'doc'    => $doc,
		];

		$modules = [];
		$lessons = [];
		$terminals = [];
		foreach ($this->moduleDirs($dir) as $number => $path) {
			$key = basename($path);
			$module_title = $key;
			$label = '';
			$goal = '';
			if (is_readable($path . '/' . self::MODULE_FILE)) {
				$module_doc = $this->readFile($path . '/' . self::MODULE_FILE);
				$raw = $module_doc['meta']['title'] ?? '';
				$raw = trim(preg_replace('/\s+—\s+вимоги$/u', '', $raw) ?? $raw);
				$label = $raw;
				$raw = preg_replace('/^Модуль\s+\d+\.\s*/u', '', $raw) ?? $raw;
				$raw = trim(preg_replace('/\s+—\s+вимоги$/u', '', $raw) ?? $raw);
				$module_title = $raw !== '' ? $raw : $key;
				if (preg_match('/^##\s+Мета модуля\s*\n+(.+?)(?:\n\s*\n|\n#|\z)/msu', $module_doc['body'], $g)) {
					$goal = trim(preg_replace('/\s+/u', ' ', $g[1]) ?? $g[1]);
				}
			}

			// Chapter terminal (ADR 0006).
			if (is_readable($path . '/' . self::TERMINAL_FILE)) {
				$terminal = json_decode((string) file_get_contents($path . '/' . self::TERMINAL_FILE), true);
				if (!is_array($terminal) || empty($terminal['commands']) || !is_array($terminal['commands'])) {
					return new \WP_Error('lms4wp_import_terminal', sprintf('%s/%s: invalid JSON or no "commands".', $key, self::TERMINAL_FILE));
				}
				$terminals[$key] = $terminal;
			}

			$modules[] = [
				'key'    => $key,
				'number' => $number,
				'title'  => $module_title,
				'label'  => $label !== '' ? $label : $module_title,
				'goal'   => $goal,
			];

			foreach ($this->lessonFiles($path) as $lesson_number => $file) {
				$lesson_doc = $this->readFile($file);
				$lesson_slug = sanitize_title($lesson_doc['meta']['slug'] ?? pathinfo($file, PATHINFO_FILENAME));
				$lessons[] = [
					'source' => $slug . '/' . $key . '/' . basename($file),
					'title'  => $lesson_doc['meta']['title'] ?? $lesson_slug,
					'slug'   => $lesson_slug,
					'module' => $key,
					'order'  => $number * 100 + $lesson_number,
					'doc'    => $lesson_doc,
				];
			}
		}

		return [
			'slug'    => $slug,
			'course'  => $course,
			'modules'   => $modules,
			'lessons'   => $lessons,
			'terminals' => $terminals,
		];
	}

	/**
	 * @return array<int, string> Module number => path, ordered.
	 */
	private function moduleDirs(string $dir): array
	{
		$dirs = [];
		foreach (glob($dir . '/module-*', GLOB_ONLYDIR) ?: [] as $path) {
			if (preg_match('/^module-(\d+)-/', basename($path), $m)) {
				$dirs[(int) $m[1]] = $path;
			}
		}
		ksort($dirs);
		return $dirs;
	}

	/**
	 * @return array<int, string> Lesson number => file, ordered.
	 */
	private function lessonFiles(string $path): array
	{
		$files = [];
		foreach (glob($path . '/lesson-*.md') ?: [] as $file) {
			if (preg_match('/^lesson-\d+-(\d+)-/', basename($file), $m)) {
				$files[(int) $m[1]] = $file;
			}
		}
		ksort($files);
		return $files;
	}

	/**
	 * @return array{meta: array<string, string>, body: string, hash: string}
	 */
	private function readFile(string $path): array
	{
		$raw = (string) file_get_contents($path);
		return FrontMatter::parse($raw) + ['hash' => sha1($raw)];
	}

	/**
	 * @param array<string, string> $meta
	 */
	private function statusFor(array $meta): string
	{
		if ($this->status !== '') {
			return $this->status;
		}
		$status = $meta['status'] ?? 'draft';
		return in_array($status, self::STATUSES, true) ? $status : 'draft';
	}

	/**
	 * Posts (course + lessons) imported from a course package.
	 *
	 * @return int[]
	 */
	private function findSourcePosts(string $slug): array
	{
		return array_map('intval', get_posts([
			'post_type'        => [Course::POST_TYPE, Lesson::POST_TYPE],
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [
				[
					'key'     => self::META_SOURCE,
					'value'   => '^' . $slug . '/',
					'compare' => 'REGEXP',
				],
			],
		]));
	}

	/**
	 * Delete the course and its lessons (replace mode).
	 */
	private function deleteCourse(string $slug): void
	{
		foreach ($this->findSourcePosts($slug) as $post_id) {
			$this->stats['deleted']++;
			($this->log)(sprintf('delete  %s (#%d)', get_post_meta($post_id, self::META_SOURCE, true), $post_id));
			if (!$this->dry_run) {
				wp_delete_post($post_id, true);
			}
		}
	}

	/**
	 * Create or update a post identified by its hub source path.
	 *
	 * @param array{source: string, doc: array{hash: string}} $item
	 * @param array<string, mixed> $postarr
	 * @param array<string, mixed> $meta
	 * @param array<string, string[]> $terms Taxonomy => term names (replaced).
	 * @return int|\WP_Error Post ID (0 on dry-run create).
	 */
	private function upsert(string $post_type, array $item, array $postarr, array $meta, array $terms = [])
	{
		$source = $item['source'];
		$hash = $item['doc']['hash'];

		$post_id = 0;
		if (!$this->replace) {
			$existing = get_posts([
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'meta_key'         => self::META_SOURCE,
				'meta_value'       => $source,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]);
			$post_id = (int) ($existing[0] ?? 0);
		}

		if ($post_id > 0 && !$this->force
			&& get_post_meta($post_id, self::META_SOURCE_HASH, true) === $hash
			&& get_post_status($post_id) === $postarr['post_status']) {
			$this->stats['skipped']++;
			($this->log)(sprintf('skip    %s (#%d)', $source, $post_id));
			return $post_id;
		}

		$action = $post_id > 0 ? 'update' : 'create';
		$this->stats[$action === 'create' ? 'created' : 'updated']++;
		($this->log)(sprintf('%-7s %s%s', $action, $source, $post_id > 0 ? " (#{$post_id})" : ''));

		if ($this->dry_run) {
			return $post_id;
		}

		$postarr['post_type'] = $post_type;
		if ($this->author > 0) {
			$postarr['post_author'] = $this->author;
		}
		if ($post_id > 0) {
			$postarr['ID'] = $post_id;
		}
		$result = $post_id > 0
			? wp_update_post(wp_slash($postarr), true)
			: wp_insert_post(wp_slash($postarr), true);
		if (is_wp_error($result)) {
			return $result;
		}

		$meta[self::META_SOURCE] = $source;
		$meta[self::META_SOURCE_HASH] = $hash;
		foreach ($meta as $key => $value) {
			update_post_meta((int) $result, $key, $value);
		}
		foreach ($terms as $taxonomy => $names) {
			if (taxonomy_exists($taxonomy)) {
				wp_set_object_terms((int) $result, $names, $taxonomy);
			}
		}

		return (int) $result;
	}
}
