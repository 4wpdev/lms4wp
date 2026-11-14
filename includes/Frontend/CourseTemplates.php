<?php
/**
 * Plugin block templates for course and lesson (WP 6.7+ register_block_template).
 * A theme template with the same slug overrides these.
 *
 * @package ForWP\LMS\Frontend
 */

namespace ForWP\LMS\Frontend;

use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Lesson;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class CourseTemplates
{
	public static function init(): void
	{
		add_action('init', [self::class, 'register'], 20);
	}

	public static function register(): void
	{
		if (!function_exists('register_block_template')) {
			return;
		}

		$templates = [
			'single-' . Course::POST_TYPE => [
				__('Single Course', 'lms4wp'),
				__('Course landing page: hero, description, program, join button.', 'lms4wp'),
				Course::POST_TYPE,
			],
			'single-' . Lesson::POST_TYPE => [
				__('Single Lesson', 'lms4wp'),
				__('Lesson page: course context, content, previous / next, course outline.', 'lms4wp'),
				Lesson::POST_TYPE,
			],
		];

		foreach ($templates as $slug => [$title, $description, $post_type]) {
			$file = LMS4WP_PATH . 'templates/' . $slug . '.html';
			if (!is_readable($file)) {
				continue;
			}
			register_block_template('lms4wp//' . $slug, [
				'title'       => $title,
				'description' => $description,
				'content'     => (string) file_get_contents($file),
				'post_types'  => [$post_type],
			]);
		}
	}
}
