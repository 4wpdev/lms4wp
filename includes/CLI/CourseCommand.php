<?php
/**
 * WP-CLI commands for courses.
 *
 * @package ForWP\LMS\CLI
 */

namespace ForWP\LMS\CLI;

use ForWP\LMS\Import\CourseImporter;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WP-CLI: wp lms4wp course <command>
 */
class CourseCommand
{
	/**
	 * Import a course package from the content hub (docs/COURSE-FORMAT.md).
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Course directory, e.g. ~/4wp-publish-hub/LMS4WP/courses/git-basics
	 *
	 * [--status=<status>]
	 * : Override front-matter status.
	 * ---
	 * options:
	 *   - draft
	 *   - pending
	 *   - private
	 *   - publish
	 * ---
	 *
	 * [--author=<user>]
	 * : Author (ID, login or email). Default: first administrator.
	 *
	 * [--replace]
	 * : Delete the course and its lessons on the site, then import from scratch.
	 *
	 * [--force]
	 * : Update unchanged files too.
	 *
	 * [--dry-run]
	 * : Show what would happen, write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lms4wp course import ~/4wp-publish-hub/LMS4WP/courses/git-basics --replace --status=publish
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function import(array $args, array $assoc_args): void
	{
		$dry_run = isset($assoc_args['dry-run']);
		$commit = $this->hubCommit($args[0]);
		$author = $this->author((string) ($assoc_args['author'] ?? ''));

		$importer = new CourseImporter(
			[
				'status'  => (string) ($assoc_args['status'] ?? ''),
				'force'   => isset($assoc_args['force']),
				'dry_run' => $dry_run,
				'replace' => isset($assoc_args['replace']),
				'commit'  => $commit,
				'author'  => $author,
			],
			static function (string $line): void {
				\WP_CLI::log($line);
			}
		);

		$result = $importer->import($args[0]);
		if (is_wp_error($result)) {
			\WP_CLI::error($result->get_error_message());
		}

		\WP_CLI::success(sprintf(
			'%sCourse #%d from %s: %d modules, %d lessons. Deleted %d, created %d, updated %d, skipped %d.',
			$dry_run ? '[dry-run] ' : '',
			$result['course_id'],
			$commit !== '' ? $commit : 'no commit',
			$result['modules'],
			$result['lessons'],
			$result['deleted'],
			$result['created'],
			$result['updated'],
			$result['skipped']
		));
	}

	/**
	 * Compare the site with a course package. Writes nothing.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Course directory, e.g. ~/4wp-publish-hub/LMS4WP/courses/git-basics
	 *
	 * ## EXAMPLES
	 *
	 *     wp lms4wp course verify ~/4wp-publish-hub/LMS4WP/courses/git-basics
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function verify(array $args, array $assoc_args): void
	{
		$commit = $this->hubCommit($args[0]);
		$result = (new CourseImporter(['commit' => $commit]))->verify($args[0]);
		if (is_wp_error($result)) {
			\WP_CLI::error($result->get_error_message());
		}

		\WP_CLI::log(sprintf(
			'Package: %d modules, %d lessons, commit %s. Site: course #%d, commit %s.',
			$result['modules'],
			$result['lessons'],
			$commit !== '' ? $commit : '—',
			$result['course_id'],
			$result['commit'] !== '' ? $result['commit'] : '—'
		));

		foreach ($result['issues'] as $issue) {
			\WP_CLI::log($issue);
		}

		if ($result['issues']) {
			\WP_CLI::error(sprintf('%d differences.', count($result['issues'])));
		}
		\WP_CLI::success('Site matches the package.');
	}

	/**
	 * Resolve author: ID, login or email; default — first administrator.
	 */
	private function author(string $value): int
	{
		if ($value !== '') {
			$user = is_numeric($value)
				? get_user_by('id', (int) $value)
				: (get_user_by('login', $value) ?: get_user_by('email', $value));
			if (!$user) {
				\WP_CLI::error(sprintf('Unknown author "%s".', $value));
			}
			return (int) $user->ID;
		}

		$admins = get_users(['role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
		return (int) ($admins[0] ?? 0);
	}

	/**
	 * Last hub commit touching the course directory; "-dirty" when it has uncommitted changes.
	 * Empty when the directory is not under git. Warns when it is not committed.
	 */
	private function hubCommit(string $dir): string
	{
		if (!function_exists('exec') || !is_dir($dir)) {
			return '';
		}

		$git = 'git -C ' . escapeshellarg($dir);
		$hash = [];
		$code = 0;
		exec($git . ' log -1 --format=%h -- . 2>/dev/null', $hash, $code);
		$hash = $code === 0 ? trim($hash[0] ?? '') : '';

		$dirty = [];
		exec($git . ' status --porcelain -- . 2>/dev/null', $dirty);

		if ($hash === '' || $dirty) {
			\WP_CLI::warning('Course package has uncommitted changes in the hub — git is the source of truth.');
		}

		if ($hash === '') {
			return $dirty ? 'uncommitted' : '';
		}

		return $dirty ? $hash . '-dirty' : $hash;
	}
}
