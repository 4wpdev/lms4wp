<?php
/**
 * Course completion page (/courses/{slug}/completed/), retake and "what next".
 *
 * Content comes from the package file completion.md (stored on the course, META);
 * without it a default thank-you text is shown. A learner who finished the course sees
 * the completion date and a retake button; everybody else sees the page as a normal page.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\Users\EnrollmentService;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseCompletion
{
	const META          = '_lms4wp_completion';
	const ENDPOINT      = 'completed';
	const QUERY_VAR     = 'lms4wp_completed';
	const ACTION_RETAKE = 'lms4wp_course_retake';
	const TEMPLATE      = 'single-lms_course-completed';

	/** How many related courses to show. */
	const RELATED = 3;

	public static function init(): void
	{
		add_action('init', [self::class, 'registerEndpoint']);
		add_filter('single_template_hierarchy', [self::class, 'templateHierarchy']);
		add_action('template_redirect', [self::class, 'handleRetake']);
		add_filter('document_title_parts', [self::class, 'documentTitle']);
		add_filter('wpseo_title', [self::class, 'yoastTitle']);
		add_filter('wp_robots', [self::class, 'robots']);
		add_filter('wpseo_robots', [self::class, 'yoastRobots']);
	}

	public static function registerEndpoint(): void
	{
		add_rewrite_endpoint(self::ENDPOINT, EP_PERMALINK, self::QUERY_VAR);
	}

	/**
	 * The current request is a course completion page.
	 */
	public static function isView(): bool
	{
		global $wp_query;
		return is_singular(Course::POST_TYPE) && isset($wp_query->query_vars[self::QUERY_VAR]);
	}

	public static function url(int $course_id): string
	{
		return trailingslashit((string) get_permalink($course_id)) . self::ENDPOINT . '/';
	}

	/**
	 * Completion content: title, excerpt, blocks, next course slugs.
	 *
	 * @return array{title: string, excerpt: string, content: string, next: string[]}
	 */
	public static function data(int $course_id): array
	{
		$data = get_post_meta($course_id, self::META, true);
		$data = is_array($data) ? $data : [];
		$title = trim((string) ($data['title'] ?? ''));
		return [
			'title'   => $title !== '' ? $title : sprintf(
				/* translators: %s: course title */
				__('You finished %s', 'lms4wp'),
				get_the_title($course_id)
			),
			'excerpt' => (string) ($data['excerpt'] ?? ''),
			'content' => (string) ($data['content'] ?? ''),
			'next'    => array_values(array_filter(array_map('strval', (array) ($data['next'] ?? [])))),
		];
	}

	/**
	 * Date the learner finished the course ('' — not finished).
	 */
	public static function completedAt(int $user_id, int $course_id): string
	{
		if ($user_id <= 0) {
			return '';
		}
		$summary = Progress::summary($user_id, $course_id);
		if ($summary['total'] === 0 || $summary['completed'] < $summary['total']) {
			return '';
		}
		global $wpdb;
		$date = (string) $wpdb->get_var($wpdb->prepare(
			"SELECT MAX(completed_at) FROM {$wpdb->prefix}lms4wp_progress WHERE user_id = %d AND course_id = %d AND status = 'completed'",
			$user_id,
			$course_id
		));
		return $date !== '' ? $date : current_time('mysql');
	}

	/**
	 * @param string[] $templates
	 * @return string[]
	 */
	public static function templateHierarchy(array $templates): array
	{
		if (self::isView()) {
			array_unshift($templates, self::TEMPLATE . '.php');
		}
		return $templates;
	}

	/**
	 * Retake: clear the learner's progress of the course and start from the first lesson.
	 */
	public static function handleRetake(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST[self::ACTION_RETAKE]) || !is_singular(Course::POST_TYPE)) {
			return;
		}
		$course_id = (int) get_queried_object_id();
		$nonce = isset($_POST['lms4wp_retake_nonce']) ? sanitize_text_field(wp_unslash($_POST['lms4wp_retake_nonce'])) : '';
		if (!wp_verify_nonce($nonce, self::ACTION_RETAKE . '_' . $course_id)) {
			wp_die(esc_html__('Security check failed.', 'lms4wp'), '', ['response' => 403]);
		}
		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			wp_die(esc_html__('Sign in to retake the course.', 'lms4wp'), '', ['response' => 403]);
		}
		Progress::reset($user_id, $course_id);
		$lessons = CourseStructure::lessons($course_id);
		wp_safe_redirect($lessons ? (string) get_permalink($lessons[0]) : (string) get_permalink($course_id));
		exit;
	}

	/**
	 * Retake button with an in-page confirmation (no browser dialog).
	 */
	public static function retakeForm(int $course_id): string
	{
		return '<details class="lms4wp-retake">'
			. '<summary class="wp-block-button is-style-outline"><span class="wp-block-button__link wp-element-button">' . esc_html__('Retake course', 'lms4wp') . '</span></summary>'
			. '<form method="post" action="' . esc_url((string) get_permalink($course_id)) . '" class="lms4wp-retake__confirm">'
			. wp_nonce_field(self::ACTION_RETAKE . '_' . $course_id, 'lms4wp_retake_nonce', true, false)
			. '<p>' . esc_html__('Your progress in this course will be cleared and you will start again from the first lesson.', 'lms4wp') . '</p>'
			. '<div class="wp-block-button"><button type="submit" name="' . esc_attr(self::ACTION_RETAKE) . '" value="1" class="wp-block-button__link wp-element-button">'
			. esc_html__('Yes, start over', 'lms4wp') . '</button></div>'
			. '</form></details>';
	}

	/**
	 * Courses to recommend: "next" slugs from completion.md, else courses sharing tags.
	 *
	 * @return \WP_Post[]
	 */
	public static function related(int $course_id): array
	{
		$next = self::data($course_id)['next'];
		if ($next) {
			$posts = get_posts([
				'post_type'      => Course::POST_TYPE,
				'post_status'    => 'publish',
				'post_name__in'  => $next,
				'orderby'        => 'post_name__in',
				'posts_per_page' => self::RELATED,
				'post__not_in'   => [$course_id],
			]);
			if ($posts) {
				return $posts;
			}
		}
		$tags = wp_get_post_terms($course_id, 'lms_tag', ['fields' => 'ids']);
		$args = [
			'post_type'      => Course::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::RELATED,
			'post__not_in'   => [$course_id],
			'post_parent'    => 0,
		];
		if (!is_wp_error($tags) && $tags) {
			$args['tax_query'] = [['taxonomy' => 'lms_tag', 'field' => 'term_id', 'terms' => $tags]];
		}
		/**
		 * Query for the courses recommended after a completed course.
		 *
		 * @param array<string, mixed> $args      get_posts() args.
		 * @param int                  $course_id Finished course.
		 */
		return get_posts(apply_filters('lms4wp_related_courses_args', $args, $course_id));
	}

	/**
	 * @param array<string, string> $parts
	 * @return array<string, string>
	 */
	public static function documentTitle(array $parts): array
	{
		if (self::isView()) {
			$parts['title'] = self::data((int) get_queried_object_id())['title'];
		}
		return $parts;
	}

	public static function yoastTitle(string $title): string
	{
		return self::isView() ? self::data((int) get_queried_object_id())['title'] . ' - ' . get_bloginfo('name') : $title;
	}

	/**
	 * Completion page is personal: keep it out of search.
	 *
	 * @param array<string, bool|string> $robots
	 * @return array<string, bool|string>
	 */
	public static function robots(array $robots): array
	{
		if (self::isView()) {
			unset($robots['index'], $robots['max-image-preview']);
			$robots['noindex'] = true;
			$robots['follow'] = true;
		}
		return $robots;
	}

	/**
	 * @param string $robots
	 */
	public static function yoastRobots($robots)
	{
		return self::isView() ? 'noindex, follow' : $robots;
	}

	/**
	 * Learner is enrolled (retake keeps the enrollment).
	 */
	public static function isEnrolled(int $user_id, int $course_id): bool
	{
		return class_exists(EnrollmentService::class) && EnrollmentService::isEnrolled($user_id, $course_id);
	}
}
