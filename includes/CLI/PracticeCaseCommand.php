<?php
/**
 * WP-CLI commands for practice cases.
 *
 * @package ForWP\LMS\CLI
 */

namespace ForWP\LMS\CLI;

use ForWP\LMS\Content\PracticeCaseSeeder;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WP-CLI: wp lms4wp practice-case <command>
 */
class PracticeCaseCommand
{
	/**
	 * Seed practice case content from PHP definition files.
	 *
	 * ## OPTIONS
	 *
	 * [<case>]
	 * : Case key to seed (e.g. deploy-new-site). Seeds all when omitted.
	 *
	 * [--force]
	 * : Overwrite content on existing posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lms4wp practice-case seed deploy-new-site
	 *     wp lms4wp practice-case seed --force
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function seed(array $args, array $assoc_args): void
	{
		$force = isset($assoc_args['force']);
		$case = $args[0] ?? '';

		if ($case !== '') {
			$result = PracticeCaseSeeder::seed($case, $force);
			if (is_wp_error($result)) {
				\WP_CLI::error($result->get_error_message());
			}
			\WP_CLI::success(sprintf('Practice case seeded (post ID %d).', $result));
			return;
		}

		$results = PracticeCaseSeeder::seedAll($force);
		foreach ($results as $key => $result) {
			if (is_wp_error($result)) {
				\WP_CLI::warning(sprintf('%s: %s', $key, $result->get_error_message()));
				continue;
			}
			\WP_CLI::log(sprintf('%s → post ID %d', $key, $result));
		}
		\WP_CLI::success('Practice cases seeded.');
	}

	/**
	 * List registered practice case definitions and linked posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lms4wp practice-case list
	 *
	 * @subcommand list
	 */
	public function list_cases(): void
	{
		$rows = PracticeCaseSeeder::listCases();
		if ($rows === []) {
			\WP_CLI::warning('No practice case definitions registered.');
			return;
		}

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			['key', 'post_id', 'status', 'slug', 'class']
		);
	}

	/**
	 * Delete (trash) a seeded practice case post.
	 *
	 * ## OPTIONS
	 *
	 * <case>
	 * : Case key (e.g. deploy-new-site).
	 *
	 * [--hard]
	 * : Permanently delete instead of moving to trash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lms4wp practice-case delete deploy-new-site
	 *     wp lms4wp practice-case delete deploy-new-site --hard
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function delete(array $args, array $assoc_args): void
	{
		$case = $args[0] ?? '';
		if ($case === '') {
			\WP_CLI::error('Please provide a case key.');
		}

		$hard = isset($assoc_args['hard']);
		$result = PracticeCaseSeeder::delete($case, $hard);
		if (is_wp_error($result)) {
			\WP_CLI::error($result->get_error_message());
		}

		\WP_CLI::success(
			$hard
				? sprintf('Practice case permanently deleted: %s', $case)
				: sprintf('Practice case moved to trash: %s', $case)
		);
	}
}
