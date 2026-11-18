<?php
/**
 * Background import of a course package (Action Scheduler, ADR 0007).
 *
 * Fan-out / fan-in:
 *   course   → course post, modules, terminals; schedules one job per module + finalize
 *   module:X → lessons of one module (idempotent: upsert by _lms4wp_source)
 *   finalize → waits for module jobs, runs verify, fires lms4wp_import_finished
 *
 * Retries: a failed job is rescheduled up to MAX_ATTEMPTS; the last failure stays
 * in Action Scheduler as "failed" (dead letter). State per course: option lms4wp_import_state_<slug>.
 * Without Action Scheduler the steps run inline.
 *
 * @package ForWP\LMS\Queue
 */

namespace ForWP\LMS\Queue;

use ForWP\LMS\Import\CourseImporter;
use ForWP\LMS\Import\DrivePackageReader;
use ForWP\LMS\Import\PackageReader;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class ImportQueue
{
	const HOOK            = 'lms4wp_import_step';
	const GROUP           = 'lms4wp-import';
	const MAX_ATTEMPTS    = 3;
	const RETRY_DELAY     = 60;
	const FINALIZE_DELAY  = 20;
	const FINALIZE_WAITS  = 90;
	const STATE_PREFIX    = 'lms4wp_import_state_';

	public static function init(): void
	{
		add_action(self::HOOK, [self::class, 'run'], 10, 1);
	}

	public static function available(): bool
	{
		return function_exists('as_enqueue_async_action') && function_exists('as_schedule_single_action');
	}

	/**
	 * Queue a course package.
	 *
	 * @param array{status?: string, author?: int, commit?: string, replace?: bool} $options
	 */
	public static function enqueue(PackageReader $reader, array $options = []): void
	{
		self::setState($reader->slug(), [
			'state'      => 'queued',
			'source'     => $reader->toArgs(),
			'total'      => 0,
			'done'       => 0,
			'failed'     => 0,
			'errors'     => [],
			'issues'     => [],
			'started_at' => gmdate('c'),
		], true);

		self::schedule([
			'source'  => $reader->toArgs(),
			'step'    => 'course',
			'options' => $options,
			'attempt' => 1,
		]);
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function schedule(array $job, int $delay = 0): void
	{
		if (!self::available()) {
			self::run($job);
			return;
		}
		if ($delay > 0) {
			as_schedule_single_action(time() + $delay, self::HOOK, [$job], self::GROUP);
		} else {
			as_enqueue_async_action(self::HOOK, [$job], self::GROUP);
		}
	}

	/**
	 * Action Scheduler callback: one step.
	 *
	 * @param array<string, mixed> $job
	 */
	public static function run($job): void
	{
		if (!is_array($job)) {
			return;
		}
		$reader = DrivePackageReader::fromArgs((array) ($job['source'] ?? []));
		if ($reader === null) {
			return;
		}

		$step = (string) ($job['step'] ?? '');
		$options = (array) ($job['options'] ?? []);
		$slug = $reader->slug();

		$importer = new CourseImporter([
			'status'  => (string) ($options['status'] ?? ''),
			'author'  => (int) ($options['author'] ?? 0),
			'commit'  => (string) ($options['commit'] ?? ''),
			// Replace only on the course step: module jobs must stay idempotent on retry.
			'replace' => $step === 'course' && !empty($options['replace']),
		]);

		if ($step === 'finalize') {
			self::finalize($job, $reader, $importer);
			return;
		}

		$result = $importer->import($reader, $step === 'course' ? 'course' : $step);
		if (is_wp_error($result)) {
			self::fail($job, $result);
			return;
		}

		if ($step === 'course') {
			$keys = CourseImporter::moduleKeys($reader);
			if (is_wp_error($keys)) {
				self::fail($job, $keys);
				return;
			}
			self::setState($slug, ['state' => 'in_progress', 'total' => count($keys), 'course_id' => (int) $result['course_id']]);
			foreach ($keys as $key) {
				self::schedule(['step' => 'module:' . $key, 'attempt' => 1] + $job);
			}
			self::schedule(['step' => 'finalize', 'attempt' => 1, 'waits' => 0] + $job, self::FINALIZE_DELAY);
			return;
		}

		$state = self::state($slug);
		self::setState($slug, ['done' => (int) ($state['done'] ?? 0) + 1]);
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function finalize(array $job, PackageReader $reader, CourseImporter $importer): void
	{
		$slug = $reader->slug();
		$waits = (int) ($job['waits'] ?? 0);

		// Fan-in: wait until no module job of this course is pending / running / retrying.
		$pending = self::pendingModuleJobs($slug);
		if ($pending > 0 && $waits < self::FINALIZE_WAITS) {
			self::schedule(['waits' => $waits + 1] + $job, self::FINALIZE_DELAY);
			return;
		}

		$state = self::state($slug);
		// Module jobs run in parallel: link every lesson to its module once they are all done.
		$importer->repairLessonModules((int) ($state['course_id'] ?? CourseImporter::findCourseId($slug)));
		$verify = $importer->verify($reader);
		$issues = is_wp_error($verify) ? [$verify->get_error_message()] : $verify['issues'];
		$final = $pending > 0 || (int) ($state['failed'] ?? 0) > 0 || $issues ? 'failed' : 'done';

		$state = self::setState($slug, [
			'state'       => $final,
			'issues'      => $issues,
			'finished_at' => gmdate('c'),
		]);

		/**
		 * Course package import finished (done / failed).
		 *
		 * @param string               $slug    Course slug.
		 * @param array<string, mixed> $state   Import state.
		 * @param PackageReader        $reader  Package source.
		 * @param array<string, mixed> $options Import options.
		 */
		do_action('lms4wp_import_finished', $slug, $state, $reader, (array) ($job['options'] ?? []));
	}

	/**
	 * Module jobs of a course still waiting or running in Action Scheduler.
	 */
	private static function pendingModuleJobs(string $slug): int
	{
		if (!self::available() || !function_exists('as_get_scheduled_actions') || !class_exists('ActionScheduler_Store')) {
			return 0;
		}
		$count = 0;
		foreach ([\ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING] as $status) {
			$actions = as_get_scheduled_actions([
				'hook'     => self::HOOK,
				'group'    => self::GROUP,
				'status'   => $status,
				'per_page' => -1,
			]);
			foreach ($actions as $action) {
				$args = is_object($action) && method_exists($action, 'get_args') ? $action->get_args() : [];
				$job = $args[0] ?? [];
				$reader = is_array($job) ? DrivePackageReader::fromArgs((array) ($job['source'] ?? [])) : null;
				if ($reader && $reader->slug() === $slug && str_starts_with((string) ($job['step'] ?? ''), 'module:')) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Retry, or record the failure (and let Action Scheduler mark the job failed).
	 *
	 * @param array<string, mixed> $job
	 */
	private static function fail(array $job, \WP_Error $error): void
	{
		$reader = DrivePackageReader::fromArgs((array) ($job['source'] ?? []));
		$slug = $reader ? $reader->slug() : 'unknown';
		$attempt = (int) ($job['attempt'] ?? 1);
		$message = sprintf('%s (attempt %d): %s', (string) ($job['step'] ?? ''), $attempt, $error->get_error_message());

		$state = self::state($slug);
		$errors = (array) ($state['errors'] ?? []);
		$errors[] = $message;

		if ($attempt < self::MAX_ATTEMPTS) {
			self::setState($slug, ['errors' => $errors]);
			self::schedule(['attempt' => $attempt + 1] + $job, self::RETRY_DELAY * $attempt);
			return;
		}

		$patch = ['errors' => $errors];
		if (str_starts_with((string) ($job['step'] ?? ''), 'module:')) {
			$patch['failed'] = (int) ($state['failed'] ?? 0) + 1;
		} else {
			$patch['state'] = 'failed';
		}
		self::setState($slug, $patch);

		if (self::available()) {
			throw new \RuntimeException($message); // Kept by Action Scheduler as a failed action.
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function state(string $slug): array
	{
		$state = get_option(self::STATE_PREFIX . $slug, []);
		return is_array($state) ? $state : [];
	}

	/**
	 * @param array<string, mixed> $patch
	 * @return array<string, mixed>
	 */
	private static function setState(string $slug, array $patch, bool $reset = false): array
	{
		$state = $reset ? [] : self::state($slug);
		$state = array_merge($state, $patch, ['slug' => $slug, 'updated_at' => gmdate('c')]);
		update_option(self::STATE_PREFIX . $slug, $state, false);
		return $state;
	}
}
