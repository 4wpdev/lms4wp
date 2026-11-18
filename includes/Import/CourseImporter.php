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
use ForWP\LMS\PostTypes\Module;
use ForWP\LMS\Content\CourseCompletion;
use ForWP\LMS\Content\CourseStructure;
use ForWP\LMS\Content\PromoVideo;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseImporter
{
	const META_SOURCE        = '_lms4wp_source';
	const META_SOURCE_HASH   = '_lms4wp_source_hash';
	const META_SOURCE_COMMIT = '_lms4wp_source_commit';
	const META_TERMINAL      = '_lms4wp_terminal'; // On the module post (ADR 0006, 0008).
	const META_IDE           = '_lms4wp_ide'; // On the module post (ADR 0010).
	const META_COURSE_ID     = '_lms4wp_course_id';
	const META_ORDER         = '_lms4wp_order';
	const META_IMAGE_SOURCE  = '_lms4wp_image_source'; // On the attachment: package path of the image.
	const META_IMAGE_HASH    = '_lms4wp_image_hash';   // On the attachment: sha1 of the image file.

	const TAXONOMY_LEVEL = 'lms_level';
	const TAXONOMY_TAG   = 'lms_tag';
	const FAQ_SECTIONS   = ["Питання з інтерв'ю", 'Interview Questions', 'FAQ'];

	const COURSE_FILE = 'course-structure.md';
	const COMPLETION_FILE = 'completion.md'; // Course completion page (optional).
	const MODULE_FILE = 'requirements.md';
	const MODULE_PAGE_FILE = 'module.md'; // Public module page (landing); requirements.md stays internal.
	const TERMINAL_FILE = 'terminal.json';
	const IDE_FILE      = 'ide.json';
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
	 * Import a course package (or one step of it, for the queue).
	 *
	 * @param PackageReader|string $source Reader or local course folder.
	 * @param string               $scope  "all" | "course" (course post, modules, terminals) | "module:<key>" (lessons of one module).
	 * @return array{course_id: int, modules: int, lessons: int, created: int, updated: int, skipped: int, deleted: int}|\WP_Error
	 */
	public function import($source, string $scope = 'all')
	{
		if ($this->status !== '' && !in_array($this->status, self::STATUSES, true)) {
			return new \WP_Error('lms4wp_import_status', sprintf('Unknown status "%s".', $this->status));
		}

		$reader = $source instanceof PackageReader ? $source : new FilesystemPackageReader((string) $source);
		$only_module = str_starts_with($scope, 'module:') ? substr($scope, 7) : null;
		$with_course = $scope === 'all' || $scope === 'course';
		$with_lessons = $scope === 'all' || $only_module !== null;

		$package = $this->readPackage($reader, [
			'lessons'   => $with_lessons,
			'module'    => $only_module,
			'terminals' => $with_course,
		]);
		if (is_wp_error($package)) {
			return $package;
		}

		$course = $package['course'];

		if ($with_course) {
			if ($this->replace) {
				$this->deleteCourse($package['slug']);
			}

			// Course.
			$course_id = $this->upsert(
				Course::POST_TYPE,
				$course,
				[
					'post_title'   => $course['title'],
					'post_name'    => $package['slug'],
					'post_content' => MarkdownBlocks::convert($course['doc']['body']),
					'post_excerpt' => (string) ($course['doc']['meta']['excerpt'] ?? ''),
					'post_status'  => $this->statusFor($course['doc']['meta']),
				],
				// Guest access (ADR 0003): front-matter "guest_lessons" → course setting.
				(isset($course['doc']['meta']['guest_lessons'])
					? ['_lms4wp_guest_lessons' => absint($course['doc']['meta']['guest_lessons'])]
					: []) + $this->seoMetaFor($course['doc']['meta']) + PromoVideo::metaFrom($course['doc']['meta']),
				$this->termsFor($course['doc']['meta'])
			);
			if (is_wp_error($course_id)) {
				return $course_id;
			}

			if (!$this->dry_run && $course_id > 0) {
				$this->applyCompletion($course_id, $course['completion']);
				update_post_meta($course_id, self::META_SOURCE_COMMIT, $this->commit);
				$this->applyImage($reader, $course_id, '', $course['doc']['meta'], $course['title']);
			}

			// Modules: lms_module posts under the course (ADR 0008); terminal.json on the module.
			foreach ($package['modules'] as $module) {
				$module_id = $this->upsert(
					Module::POST_TYPE,
					$module,
					[
						'post_title'   => $module['title'],
						'post_name'    => $package['slug'] . '-' . $module['key'],
						'post_content' => MarkdownBlocks::convert($module['page']['body']),
						'post_excerpt' => $module['goal'],
						'post_status'  => 'publish',
						'post_parent'  => $course_id,
						'menu_order'   => $module['number'],
					],
					[
						Module::META_KEY    => $module['key'],
						self::META_TERMINAL => $module['terminal'] ?? '',
						self::META_IDE      => $module['ide'] ?? '',
					] + PromoVideo::metaFrom($module['page']['meta']) + $this->seoMetaFor($module['page']['meta'])
				);
				if (is_wp_error($module_id)) {
					return $module_id;
				}
				if (!$this->dry_run && $module_id > 0) {
					$this->applyImage($reader, $module_id, $module['key'] . '/', $module['page']['meta'], $module['title']);
				}
				// Keep the module under the current course even when its file did not change.
				if (!$this->dry_run && $module_id > 0 && (int) wp_get_post_parent_id($module_id) !== $course_id) {
					wp_update_post(['ID' => $module_id, 'post_parent' => $course_id]);
				}
			}
		} else {
			$course_id = self::findCourseId($package['slug']);
			if ($course_id === 0 && !$this->dry_run) {
				return new \WP_Error('lms4wp_import_course_missing', sprintf('Course "%s" is not imported yet — run the course step first.', $package['slug']));
			}
		}

		if (!$with_lessons) {
			return [
				'course_id' => $course_id,
				'modules'   => count($package['modules']),
				'lessons'   => 0,
			] + $this->stats;
		}

		// Lessons.
		$modules = array_column($package['modules'], null, 'key');
		$faq_attrs = [];
		$module_posts = [];
		foreach ($modules as $key => $module) {
			$module_posts[$key] = $this->findModulePost($module, $course_id);
		}

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
					'post_excerpt' => (string) ($lesson['doc']['meta']['excerpt'] ?? ''),
					'post_status'  => $this->statusFor($lesson['doc']['meta']),
					'menu_order'   => $lesson['order'],
				],
				[
					self::META_COURSE_ID => $course_id,
					self::META_ORDER     => $lesson['order'],
				] + $this->seoMetaFor($lesson['doc']['meta']),
				$this->termsFor($lesson['doc']['meta'])
			);
			// Module via the shadow taxonomy (also when the file itself did not change).
			if (!$this->dry_run && !is_wp_error($result) && $result > 0) {
				$this->applyImage($reader, (int) $result, $lesson['module'] . '/', $lesson['doc']['meta'], $lesson['title']);
				$module_id = $module_posts[$lesson['module']] ?? 0;
				// Never take a lesson out of its module when the module post was not found.
				if ($module_id > 0 && Module::forLesson((int) $result) !== $module_id) {
					Module::assignLesson((int) $result, $module_id);
				}
			}
			if (is_wp_error($result)) {
				return $result;
			}
		}

		// Full import (CLI): re-link lessons to modules in one pass at the end.
		if ($only_module === null) {
			$this->repairLessonModules($course_id);
		}
		CourseStructure::flush();

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
	public function verify($source)
	{
		$reader = $source instanceof PackageReader ? $source : new FilesystemPackageReader((string) $source);
		$package = $this->readPackage($reader);
		if (is_wp_error($package)) {
			return $package;
		}

		$site = [];
		foreach ($this->findSourcePosts($package['slug']) as $post_id) {
			$site[(string) get_post_meta($post_id, self::META_SOURCE, true)] = $post_id;
		}

		$issues = [];
		$course_id = $site[$package['course']['source']] ?? 0;
		$expected = array_merge([$package['course']], $package['modules'], $package['lessons']);
		$module_posts = [];
		foreach ($package['modules'] as $module) {
			$module_posts[$module['key']] = $site[$module['source']] ?? $this->findModuleByKey($module['key'], $course_id);
		}

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
				if (Module::forLesson($post_id) !== ($module_posts[$item['module']] ?? 0)) {
					$issues[] = sprintf('module    %s (#%d): wrong module', $item['source'], $post_id);
				}
			} elseif (isset($item['key'])) {
				if ((int) wp_get_post_parent_id($post_id) !== $course_id) {
					$issues[] = sprintf('module    %s (#%d): not under the course', $item['source'], $post_id);
				}
				$site_terminal = get_post_meta($post_id, self::META_TERMINAL, true);
				if (($site_terminal === '' ? null : $site_terminal) !== ($item['terminal'] ?? null)) {
					$issues[] = sprintf('terminal  %s (#%d): terminal.json differs', $item['source'], $post_id);
				}
				$site_ide = get_post_meta($post_id, self::META_IDE, true);
				if (($site_ide === '' ? null : $site_ide) !== ($item['ide'] ?? null)) {
					$issues[] = sprintf('ide       %s (#%d): ide.json differs', $item['source'], $post_id);
				}
			}
			unset($site[$item['source']]);
		}

		foreach ($site as $source => $post_id) {
			$issues[] = sprintf('orphan    %s (#%d): not in package', $source, $post_id);
		}

		$site_commit = $course_id > 0 ? (string) get_post_meta($course_id, self::META_SOURCE_COMMIT, true) : '';
		if ($course_id > 0) {
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
	 * SEO post meta from front-matter (same keys as 4wp-drive Seo_Meta_Applicator).
	 *
	 * yoast_seo_title → _yoast_wpseo_title; yoast_seo_description → _yoast_wpseo_metadesc;
	 * focus_keyword → _yoast_wpseo_focuskw; focus_keyword + keywords → 4wp-seo-helper keyphrases.
	 * Only keys present in the file are written, so a re-import never wipes SEO set on the site.
	 *
	 * @param array<string, string> $meta Front-matter.
	 * @return array<string, string>
	 */
	private function seoMetaFor(array $meta): array
	{
		$out = [];
		$map = [
			'yoast_seo_title'       => '_yoast_wpseo_title',
			'yoast_seo_description' => '_yoast_wpseo_metadesc',
			'focus_keyword'         => '_yoast_wpseo_focuskw',
		];
		foreach ($map as $key => $meta_key) {
			$value = sanitize_text_field((string) ($meta[$key] ?? ''));
			if ($value !== '') {
				$out[$meta_key] = $value;
			}
		}

		$phrases = array_merge(
			isset($out['_yoast_wpseo_focuskw']) ? [$out['_yoast_wpseo_focuskw']] : [],
			array_map('sanitize_text_field', FrontMatter::listValue($meta['keywords'] ?? ''))
		);
		$phrases = array_values(array_unique(array_filter($phrases, 'strlen')));
		if ($phrases) {
			$out['_forwp_seo_focus_keyphrases'] = implode("\n", $phrases);
		}

		return $out;
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
	 * Read a course package through a reader (folder or 4wp-drive).
	 *
	 * Options: lessons (read lesson files, default true), module (only this module key),
	 * terminals (read terminal.json, default true).
	 *
	 * @param array{lessons?: bool, module?: ?string, terminals?: bool} $options
	 * @return array{slug: string, course: array, modules: array, lessons: array, terminals: array<string, array>}|\WP_Error
	 */
	private function readPackage(PackageReader $reader, array $options = [])
	{
		$with_lessons = $options['lessons'] ?? true;
		$only_module = $options['module'] ?? null;
		$with_terminals = $options['terminals'] ?? true;

		$files = $reader->files();
		if (is_wp_error($files)) {
			return $files;
		}
		if (!in_array(self::COURSE_FILE, $files, true)) {
			return new \WP_Error('lms4wp_import_dir', sprintf('Not a course package (no %s).', self::COURSE_FILE));
		}

		$slug = $reader->slug();
		$doc = $this->readDoc($reader, self::COURSE_FILE);
		if (is_wp_error($doc)) {
			return $doc;
		}
		// Completion page: part of the course item (its hash re-imports the course when it changes).
		$completion = null;
		if (in_array(self::COMPLETION_FILE, $files, true)) {
			$completion = $this->readDoc($reader, self::COMPLETION_FILE);
			if (is_wp_error($completion)) {
				return $completion;
			}
			$doc['hash'] = sha1($doc['hash'] . '|' . $completion['hash']);
		}
		$title = trim(explode(' — ', $doc['meta']['title'] ?? '')[0]);

		$course = [
			'source'     => $slug . '/' . self::COURSE_FILE,
			'title'      => $title !== '' ? $title : $slug,
			'doc'        => $doc,
			'completion' => $completion,
		];

		// Index: module-<N>-<slug>/<file>.
		$index = [];
		foreach ($files as $file) {
			if (preg_match('#^(module-(\d+)-[^/]+)/([^/]+)$#', $file, $m)) {
				$index[(int) $m[2]]['key'] = $m[1];
				$index[(int) $m[2]]['files'][] = $m[3];
			}
		}
		ksort($index);

		$modules = [];
		$lessons = [];
		$terminals = [];
		foreach ($index as $number => $entry) {
			$key = $entry['key'];
			if ($only_module !== null && $key !== $only_module) {
				continue;
			}
			$module_files = $entry['files'];

			$module_title = $key;
			$label = '';
			$goal = '';
			$module_doc = ['meta' => [], 'body' => '', 'hash' => ''];
			if (in_array(self::MODULE_FILE, $module_files, true)) {
				$module_doc = $this->readDoc($reader, $key . '/' . self::MODULE_FILE);
				if (is_wp_error($module_doc)) {
					return $module_doc;
				}
				$raw = trim($module_doc['meta']['title'] ?? '');
				if (($module_doc['meta']['type'] ?? '') !== 'module') {
					// Legacy title "Модуль N. Назва — вимоги".
					$raw = trim(preg_replace('/\s+—\s+вимоги$/u', '', $raw) ?? $raw);
					$label = $raw;
					$raw = trim(preg_replace('/^Модуль\s+\d+\.\s*/u', '', $raw) ?? $raw);
				}
				$module_title = $raw !== '' ? $raw : $key;
				$label = $label !== '' ? $label : $module_title;
				if (preg_match('/^##\s+(?:Мета модуля|Module goal)\s*\n+(.+?)(?:\n\s*\n|\n#|\z)/msu', $module_doc['body'], $g)) {
					$goal = trim(preg_replace('/\s+/u', ' ', $g[1]) ?? $g[1]);
				}
			}

			// Module page (module.md): title, goal (excerpt), video and content of the public module page.
			// Without it the page falls back to requirements.md (legacy packages).
			$page_doc = $module_doc;
			$has_page = in_array(self::MODULE_PAGE_FILE, $module_files, true);
			if ($has_page) {
				$page_doc = $this->readDoc($reader, $key . '/' . self::MODULE_PAGE_FILE);
				if (is_wp_error($page_doc)) {
					return $page_doc;
				}
				$page_title = trim((string) ($page_doc['meta']['title'] ?? ''));
				if ($page_title !== '') {
					$module_title = $page_title;
					$label = $page_title;
				}
				$excerpt = trim((string) ($page_doc['meta']['excerpt'] ?? ''));
				if ($excerpt !== '') {
					$goal = $excerpt;
				}
			}

			// Chapter terminal (ADR 0006).
			$terminal = null;
			$raw_json = '';
			if ($with_terminals && in_array(self::TERMINAL_FILE, $module_files, true)) {
				$raw_json = $reader->read($key . '/' . self::TERMINAL_FILE);
				if (is_wp_error($raw_json)) {
					return $raw_json;
				}
				$terminal = json_decode($raw_json, true);
				if (!is_array($terminal) || empty($terminal['commands']) || !is_array($terminal['commands'])) {
					return new \WP_Error('lms4wp_import_terminal', sprintf('%s/%s: invalid JSON or no "commands".', $key, self::TERMINAL_FILE));
				}
				$terminals[$key] = $terminal;
			}

			// Chapter IDE practice (ADR 0010).
			$ide = null;
			$raw_ide = '';
			if ($with_terminals && in_array(self::IDE_FILE, $module_files, true)) {
				$raw_ide = $reader->read($key . '/' . self::IDE_FILE);
				if (is_wp_error($raw_ide)) {
					return $raw_ide;
				}
				$ide = json_decode($raw_ide, true);
				if (!is_array($ide) || empty($ide['lessons']) || !is_array($ide['lessons'])) {
					return new \WP_Error('lms4wp_import_ide', sprintf('%s/%s: invalid JSON or no "lessons".', $key, self::IDE_FILE));
				}
			}

			$modules[] = [
				// Module identity = its page file; requirements.md for legacy packages (looked up as legacy_source).
				'source'   => $slug . '/' . $key . '/' . ($has_page ? self::MODULE_PAGE_FILE : self::MODULE_FILE),
				'legacy_source' => $slug . '/' . $key . '/' . self::MODULE_FILE,
				'key'      => $key,
				'number'   => $number,
				'title'    => $module_title,
				'label'    => $label !== '' ? $label : $module_title,
				'goal'     => $goal,
				'terminal' => $terminal,
				'ide'      => $ide,
				'page'     => ['meta' => $page_doc['meta'], 'body' => $page_doc['body']],
				// Hash covers requirements.md, module.md, terminal.json and ide.json; "page-v3" re-imports module pages
				// once (1.15.0 SEO meta; 1.16.1 unescaped front-matter quotes).
				'doc'      => ['meta' => $module_doc['meta'], 'body' => $module_doc['body'], 'hash' => sha1($module_doc['hash'] . '|' . sha1((string) $raw_json) . ($raw_ide !== '' ? '|' . sha1($raw_ide) : '') . ($has_page ? '|' . $page_doc['hash'] . '|page-v3' : ''))],
			];

			if (!$with_lessons) {
				continue;
			}

			$lesson_files = [];
			foreach ($module_files as $name) {
				if (preg_match('/^lesson-\d+-(\d+)-.*\.md$/', $name, $l)) {
					$lesson_files[(int) $l[1]] = $name;
				}
			}
			ksort($lesson_files);

			foreach ($lesson_files as $lesson_number => $name) {
				$lesson_doc = $this->readDoc($reader, $key . '/' . $name);
				if (is_wp_error($lesson_doc)) {
					return $lesson_doc;
				}
				$lesson_slug = sanitize_title($lesson_doc['meta']['slug'] ?? pathinfo($name, PATHINFO_FILENAME));
				$lessons[] = [
					'source' => $slug . '/' . $key . '/' . $name,
					'title'  => $lesson_doc['meta']['title'] ?? $lesson_slug,
					'slug'   => $lesson_slug,
					'module' => $key,
					'order'  => $number * 100 + $lesson_number,
					'doc'    => $lesson_doc,
				];
			}
		}

		return [
			'slug'      => $slug,
			'course'    => $course,
			'modules'   => $modules,
			'lessons'   => $lessons,
			'terminals' => $terminals,
		];
	}

	/**
	 * @return array{meta: array<string, string>, body: string, hash: string}|\WP_Error
	 */
	/**
	 * Front-matter "image" → featured image. The value is a file in the package, relative to the
	 * document's folder ("4wpdev-git-module-5-workflows.jpg"), or an attachment ID.
	 * The file is uploaded once: the attachment keeps its package path and content hash, so a
	 * re-import reuses it and only uploads again when the image itself changed.
	 *
	 * @param array<string, mixed> $meta Front-matter.
	 */
	private function applyImage(PackageReader $reader, int $post_id, string $dir, array $meta, string $title): void
	{
		$image = trim((string) ($meta['image'] ?? ''));
		if ($image === '') {
			return;
		}

		if (ctype_digit($image)) {
			if (wp_attachment_is_image((int) $image)) {
				set_post_thumbnail($post_id, (int) $image);
			}
			return;
		}

		$relative = $dir . ltrim($image, '/');
		$source = $reader->slug() . '/' . $relative;

		// Same file already attached and no forced update: nothing to download.
		$current = (int) get_post_thumbnail_id($post_id);
		if ($current > 0 && !$this->force && get_post_meta($current, self::META_IMAGE_SOURCE, true) === $source) {
			$this->applyImageText($current, $meta, $title);
			return;
		}

		$bytes = $reader->read($relative);
		if (is_wp_error($bytes) || $bytes === '') {
			($this->log)(sprintf('image   %s: %s', $source, is_wp_error($bytes) ? $bytes->get_error_message() : 'empty file'));
			return;
		}
		$hash = sha1($bytes);

		$attachment_id = $this->findImage($source, $hash);
		if ($attachment_id === 0) {
			$attachment_id = $this->uploadImage(basename($relative), $bytes, $post_id, $title);
			if ($attachment_id === 0) {
				($this->log)(sprintf('image   %s: upload failed', $source));
				return;
			}
			update_post_meta($attachment_id, self::META_IMAGE_SOURCE, $source);
			update_post_meta($attachment_id, self::META_IMAGE_HASH, $hash);
			($this->log)(sprintf('image   %s (#%d)', $source, $attachment_id));
		}

		if ($current !== $attachment_id) {
			set_post_thumbnail($post_id, $attachment_id);
		}
		$this->applyImageText($attachment_id, $meta, $title);
	}

	/**
	 * Front-matter "image_alt" / "image_caption" → alt text and caption of the featured image.
	 * Alt falls back to the post title; an empty caption leaves the current one as is.
	 *
	 * @param array<string, mixed> $meta Front-matter.
	 */
	private function applyImageText(int $attachment_id, array $meta, string $title): void
	{
		$alt = sanitize_text_field((string) ($meta['image_alt'] ?? ''));
		if ($alt === '') {
			$alt = sanitize_text_field($title);
		}
		if ($alt !== '' && get_post_meta($attachment_id, '_wp_attachment_image_alt', true) !== $alt) {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
		}

		$caption = sanitize_text_field((string) ($meta['image_caption'] ?? ''));
		if ($caption !== '' && get_post_field('post_excerpt', $attachment_id) !== $caption) {
			wp_update_post(['ID' => $attachment_id, 'post_excerpt' => $caption]);
		}
	}

	/**
	 * Attachment uploaded earlier from the same package file with the same content (0 — none).
	 */
	private function findImage(string $source, string $hash): int
	{
		$ids = get_posts([
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [
				['key' => self::META_IMAGE_SOURCE, 'value' => $source],
				['key' => self::META_IMAGE_HASH, 'value' => $hash],
			],
		]);
		return (int) ($ids[0] ?? 0);
	}

	/**
	 * Upload image bytes into the media library (0 — failed / not an image).
	 */
	private function uploadImage(string $name, string $bytes, int $parent_id, string $title): int
	{
		$type = wp_check_filetype($name);
		if (!is_string($type['type']) || !str_starts_with($type['type'], 'image/')) {
			return 0;
		}
		$upload = wp_upload_bits($name, null, $bytes);
		if (!empty($upload['error'])) {
			return 0;
		}

		$attachment_id = wp_insert_attachment([
			'post_mime_type' => $type['type'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
		], $upload['file'], $parent_id, true);
		if (is_wp_error($attachment_id)) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
		update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
		return (int) $attachment_id;
	}

	private function readDoc(PackageReader $reader, string $relative)
	{
		$raw = $reader->read($relative);
		if (is_wp_error($raw)) {
			return $raw;
		}
		return FrontMatter::parse($raw) + ['hash' => sha1($raw)];
	}

	/**
	 * Post imported from a source path (0 — none).
	 */
	/**
	 * Module post of a package module: by its page file, then by requirements.md, then by course + folder key.
	 *
	 * @param array{source: string, legacy_source?: string, key?: string} $module
	 */
	private function findModulePost(array $module, int $course_id = 0): int
	{
		$id = $this->findSourcePost(Module::POST_TYPE, $module['source']);
		if ($id === 0 && !empty($module['legacy_source'])) {
			$id = $this->findSourcePost(Module::POST_TYPE, (string) $module['legacy_source']);
		}
		// Source path differs (module.md ↔ requirements.md): the module of this course by its folder key.
		if ($id === 0 && $course_id > 0 && !empty($module['key'])) {
			$id = $this->findModuleByKey((string) $module['key'], $course_id);
		}
		if ($id === 0) {
			($this->log)(sprintf('module  %s: module post not found, lessons keep their module', $module['source']));
		}
		return $id;
	}

	/**
	 * completion.md → course meta (title, excerpt, blocks, next course slugs); no file → removed.
	 *
	 * @param array{meta: array<string, string>, body: string}|null $doc
	 */
	private function applyCompletion(int $course_id, ?array $doc): void
	{
		if ($doc === null) {
			delete_post_meta($course_id, CourseCompletion::META);
			return;
		}
		$body = (string) preg_replace('/\A\s*#\s+[^\n]*\n/', '', $doc['body']); // H1 = page title.
		update_post_meta($course_id, CourseCompletion::META, wp_slash([
			'title'   => (string) ($doc['meta']['title'] ?? ''),
			'excerpt' => (string) ($doc['meta']['excerpt'] ?? ''),
			'content' => MarkdownBlocks::convert($body),
			'next'    => FrontMatter::listValue((string) ($doc['meta']['next'] ?? '')),
		]));
	}

	/**
	 * Module post of a course by its folder key (module-3-branching); 0 — none.
	 */
	private function findModuleByKey(string $key, int $course_id): int
	{
		if ($key === '' || $course_id <= 0) {
			return 0;
		}
		$ids = get_posts([
			'post_type'        => Module::POST_TYPE,
			'post_status'      => 'any',
			'post_parent'      => $course_id,
			'meta_key'         => Module::META_KEY,
			'meta_value'       => $key,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);
		return (int) ($ids[0] ?? 0);
	}

	/**
	 * Re-link every lesson of a course to its module by the folder in its source path
	 * (course/module-N-x/lesson.md). Runs once all module jobs are done, so parallel jobs
	 * or a partial file list can never leave lessons without a module.
	 *
	 * @return int Lessons re-linked.
	 */
	public function repairLessonModules(int $course_id): int
	{
		if ($course_id <= 0 || $this->dry_run) {
			return 0;
		}
		$lessons = get_posts([
			'post_type'        => Lesson::POST_TYPE,
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'meta_key'         => self::META_COURSE_ID,
			'meta_value'       => $course_id,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);
		$modules = [];
		$fixed = 0;
		foreach ($lessons as $lesson_id) {
			$source = (string) get_post_meta((int) $lesson_id, self::META_SOURCE, true);
			if (!preg_match('#/(module-\d+-[^/]+)/[^/]+$#', $source, $m)) {
				continue;
			}
			$modules[$m[1]] ??= $this->findModuleByKey($m[1], $course_id);
			$module_id = $modules[$m[1]];
			if ($module_id > 0 && Module::forLesson((int) $lesson_id) !== $module_id) {
				Module::assignLesson((int) $lesson_id, $module_id);
				($this->log)(sprintf('module  %s (#%d) → module #%d', $source, $lesson_id, $module_id));
				$fixed++;
			}
		}
		if ($fixed > 0) {
			CourseStructure::flush();
		}
		return $fixed;
	}

	private function findSourcePost(string $post_type, string $source): int
	{
		$ids = get_posts([
			'post_type'        => $post_type,
			'post_status'      => 'any',
			'meta_key'         => self::META_SOURCE,
			'meta_value'       => $source,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);
		return (int) ($ids[0] ?? 0);
	}

	/**
	 * Course post imported from a package slug (0 — none yet).
	 */
	public static function findCourseId(string $slug): int
	{
		$ids = get_posts([
			'post_type'        => Course::POST_TYPE,
			'post_status'      => 'any',
			'meta_key'         => self::META_SOURCE,
			'meta_value'       => $slug . '/' . self::COURSE_FILE,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);
		return (int) ($ids[0] ?? 0);
	}

	/**
	 * Module keys of a package, in order (for queue fan-out).
	 *
	 * @return string[]|\WP_Error
	 */
	public static function moduleKeys(PackageReader $reader)
	{
		$files = $reader->files();
		if (is_wp_error($files)) {
			return $files;
		}
		$keys = [];
		foreach ($files as $file) {
			if (preg_match('#^(module-(\d+)-[^/]+)/#', $file, $m)) {
				$keys[(int) $m[2]] = $m[1];
			}
		}
		ksort($keys);
		return array_values($keys);
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
			'post_type'        => [Course::POST_TYPE, Module::POST_TYPE, Lesson::POST_TYPE],
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
		$order = [Lesson::POST_TYPE => 0, Module::POST_TYPE => 1, Course::POST_TYPE => 2];
		$ids = $this->findSourcePosts($slug);
		usort($ids, static fn(int $a, int $b): int => ($order[get_post_type($a)] ?? 3) <=> ($order[get_post_type($b)] ?? 3));
		foreach ($ids as $post_id) {
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
			// Module that got a module.md: keep the post imported from requirements.md.
			if ($post_id === 0 && !empty($item['legacy_source']) && $item['legacy_source'] !== $source) {
				$post_id = $this->findSourcePost($post_type, (string) $item['legacy_source']);
			}
			// Module identity = course + folder key: a partial file list (module.md not listed) must not
			// create a second module post (its new term would take the lessons away from the real one).
			if ($post_id === 0 && $post_type === Module::POST_TYPE && !empty($item['key'])) {
				$post_id = $this->findModuleByKey((string) $item['key'], (int) ($postarr['post_parent'] ?? 0));
				$current = $post_id > 0 ? (string) get_post_meta($post_id, self::META_SOURCE, true) : '';
				if ($current !== '' && str_ends_with($current, '/' . self::MODULE_PAGE_FILE) && !str_ends_with($source, '/' . self::MODULE_PAGE_FILE)) {
					($this->log)(sprintf('warn    %s: module.md not in the file list, module #%d kept as is', $source, $post_id));
					$this->stats['skipped']++;
					return $post_id;
				}
			}
		}

		// Re-import keeps the site status (publish stays publish); front-matter status applies on create,
		// --status only when given explicitly.
		if ($post_id > 0 && $this->status === '') {
			$postarr['post_status'] = (string) get_post_status($post_id);
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
			// update_post_meta() unslashes: keep backslashes in terminal output (git graph "|\\").
			update_post_meta((int) $result, $key, wp_slash($value));
		}
		foreach ($terms as $taxonomy => $names) {
			if (taxonomy_exists($taxonomy)) {
				wp_set_object_terms((int) $result, $names, $taxonomy);
			}
		}

		return (int) $result;
	}
}
