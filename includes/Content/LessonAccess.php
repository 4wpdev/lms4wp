<?php
/**
 * Guest access to lessons (ADR 0003, SPEC F3–F4).
 *
 * - Guest: the first N lessons of the course (course setting) and lessons marked free.
 * - Signed in: the same + all lessons of courses they are enrolled in (F5).
 * - Editors: always.
 * Locked lesson: content replaced by a sign-in / join panel; hidden from REST; noindex.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\PostTypes\Lesson;
use ForWP\LMS\Users\EnrollmentService;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class LessonAccess
{
	const META_GUEST_LESSONS    = '_lms4wp_guest_lessons';
	const DEFAULT_GUEST_LESSONS = 1;

	public static function init(): void
	{
		add_filter('the_content', [self::class, 'filterContent'], 1);
		add_filter('get_the_excerpt', [self::class, 'filterExcerpt'], 1, 2);
		add_filter('rest_prepare_' . Lesson::POST_TYPE, [self::class, 'filterRest'], 10, 2);
		add_filter('wp_robots', [self::class, 'filterRobots']);
	}

	/**
	 * Lessons open to guests for a course (course setting; default 1).
	 */
	public static function guestLessons(int $course_id): int
	{
		$value = get_post_meta($course_id, self::META_GUEST_LESSONS, true);
		return $value === '' ? self::DEFAULT_GUEST_LESSONS : max(0, (int) $value);
	}

	/**
	 * Open without sign-in: within the first N lessons of the course, or marked free.
	 */
	public static function isGuestOpen(int $lesson_id): bool
	{
		if (get_post_meta($lesson_id, '_lms4wp_is_free', true) === '1') {
			return true;
		}
		$context = CourseStructure::lessonContext($lesson_id);
		if ($context['course_id'] === 0) {
			return true; // Standalone lesson.
		}
		return $context['index'] > 0 && $context['index'] <= self::guestLessons($context['course_id']);
	}

	public static function canView(int $lesson_id, ?int $user_id = null): bool
	{
		if (get_post_type($lesson_id) !== Lesson::POST_TYPE) {
			return true;
		}
		$user_id ??= get_current_user_id();

		if ($user_id > 0 && user_can($user_id, 'edit_post', $lesson_id)) {
			return true;
		}

		$can = self::isGuestOpen($lesson_id);
		if (!$can && $user_id > 0) {
			$course_id = CourseStructure::courseIdForLesson($lesson_id);
			$can = $course_id > 0 && EnrollmentService::isEnrolled($user_id, $course_id);
		}

		/**
		 * Whether a user may view a lesson's content.
		 *
		 * @param bool $can       Default decision.
		 * @param int  $lesson_id Lesson ID.
		 * @param int  $user_id   User ID (0 — guest).
		 */
		return (bool) apply_filters('lms4wp_can_view_lesson', $can, $lesson_id, $user_id);
	}

	public static function filterContent(string $content): string
	{
		$post_id = (int) get_the_ID();
		if ($post_id <= 0 || get_post_type($post_id) !== Lesson::POST_TYPE || self::canView($post_id)) {
			return $content;
		}
		return self::lockedPanel($post_id);
	}

	/**
	 * @param string        $excerpt
	 * @param \WP_Post|null $post
	 */
	public static function filterExcerpt($excerpt, $post = null)
	{
		$post_id = $post instanceof \WP_Post ? $post->ID : (int) get_the_ID();
		return $post_id > 0 && !self::canView($post_id) ? '' : $excerpt;
	}

	/**
	 * @param \WP_REST_Response $response
	 * @param \WP_Post          $post
	 */
	public static function filterRest($response, $post)
	{
		if (!$post instanceof \WP_Post || self::canView($post->ID)) {
			return $response;
		}
		$data = $response->get_data();
		foreach (['content', 'excerpt'] as $field) {
			if (isset($data[$field]) && is_array($data[$field])) {
				$data[$field]['rendered'] = '';
				$data[$field]['protected'] = true;
				unset($data[$field]['raw']);
			}
		}
		$response->set_data($data);
		return $response;
	}

	/**
	 * @param array<string, bool|string> $robots
	 * @return array<string, bool|string>
	 */
	public static function filterRobots(array $robots): array
	{
		if (is_singular(Lesson::POST_TYPE) && !self::canView((int) get_queried_object_id())) {
			$robots['noindex'] = true;
			$robots['follow'] = true;
		}
		return $robots;
	}

	public static function loginUrl(int $lesson_id): string
	{
		$back = (string) get_permalink($lesson_id);
		// 4wp-account sign-in page (returns to the lesson); core login otherwise.
		$url = class_exists('\\ForWP\\Account\\Account\\AccountMenu')
			? (string) \ForWP\Account\Account\AccountMenu::get_login_url($back)
			: '';

		/**
		 * Sign-in URL for locked lessons.
		 *
		 * @param string $url       4wp-account or wp_login_url().
		 * @param int    $lesson_id Lesson ID.
		 */
		return (string) apply_filters('lms4wp_login_url', $url !== '' ? $url : wp_login_url($back), $lesson_id);
	}

	/**
	 * Sign-up URL: 4wp-account page in register mode when password sign-up is on,
	 * otherwise the same account page (accounts are created on first provider sign-in).
	 */
	public static function registerUrl(int $lesson_id): string
	{
		$url = self::loginUrl($lesson_id);
		$can_register = class_exists('\\ForWP\\Account\\Auth\\PasswordAuth')
			? \ForWP\Account\Auth\PasswordAuth::can_register()
			: (bool) get_option('users_can_register');
		$url = $can_register ? add_query_arg('form', 'register', $url) : $url;

		/**
		 * Sign-up URL for lessons.
		 *
		 * @param string $url       Account page URL.
		 * @param int    $lesson_id Lesson ID.
		 */
		return (string) apply_filters('lms4wp_register_url', $url, $lesson_id);
	}

	/**
	 * "Sign in" + "Create account" buttons (both return to the lesson).
	 */
	public static function authButtons(int $lesson_id): string
	{
		$icon = static fn(string $name): string => \ForWP\LMS\Frontend\Icons::svg($name, 18);
		return '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url(self::loginUrl($lesson_id)) . '">'
			. $icon('log-in') . '<span>' . esc_html__('Sign in', 'lms4wp') . '</span></a></div>'
			. '<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url(self::registerUrl($lesson_id)) . '">'
			. $icon('user-plus') . '<span>' . esc_html__('Create account', 'lms4wp') . '</span></a></div>';
	}

	/**
	 * WooCommerce product that sells the course (ADR 0002), 0 — free course.
	 */
	public static function productId(int $course_id): int
	{
		$product_id = (int) get_post_meta($course_id, '_lms4wp_woocommerce_product_id', true);
		return $product_id > 0 && class_exists('WooCommerce') && get_post_status($product_id) === 'publish' ? $product_id : 0;
	}

	/**
	 * Join form for a free course (EnrollmentService::handleEnrollmentForm), back to $redirect.
	 */
	public static function joinForm(int $course_id, string $redirect, string $label): string
	{
		return '<form method="post" class="lms4wp-join-form">'
			. wp_nonce_field('lms4wp_enroll', 'lms4wp_enroll_nonce', true, false)
			. '<input type="hidden" name="course_id" value="' . esc_attr((string) $course_id) . '">'
			. '<input type="hidden" name="redirect_to" value="' . esc_url($redirect) . '">'
			. '<div class="wp-block-button"><button type="submit" name="lms4wp_enroll" value="1" class="wp-block-button__link wp-element-button">'
			. esc_html($label) . '</button></div>'
			. '</form>';
	}

	private static function lockedPanel(int $lesson_id): string
	{
		$context = CourseStructure::lessonContext($lesson_id);
		$course_id = $context['course_id'];
		$free = $course_id ? self::guestLessons($course_id) : 0;
		$back = $course_id
			? '<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url((string) get_permalink($course_id)) . '">' . esc_html__('Back to course', 'lms4wp') . '</a></div>'
			: '';

		$html = '<div class="lms4wp-locked">';
		$html .= '<span class="lms4wp-locked__icon">' . \ForWP\LMS\Frontend\Icons::svg('lock', 22) . '</span>';

		if (!is_user_logged_in()) {
			$html .= '<p class="lms4wp-locked__title">' . esc_html__('This lesson is available after sign-in', 'lms4wp') . '</p>';
			if ($free > 0) {
				$html .= '<p class="lms4wp-locked__text">' . esc_html(sprintf(
					/* translators: %d: number of lessons open without sign-in */
					_n('The first %d lesson is open without an account. Sign in to continue the course.', 'The first %d lessons are open without an account. Sign in to continue the course.', $free, 'lms4wp'),
					$free
				)) . '</p>';
			}
			$html .= '<div class="wp-block-buttons">' . self::authButtons($lesson_id) . '</div>';
			$html .= '<p class="lms4wp-locked__back"><a href="' . esc_url((string) get_permalink($course_id)) . '">' . esc_html__('Back to course', 'lms4wp') . '</a></p>';
		} elseif ($course_id && ($product_id = self::productId($course_id))) {
			$html .= '<p class="lms4wp-locked__title">' . esc_html__('This lesson is part of a paid course', 'lms4wp') . '</p>';
			$html .= '<div class="wp-block-buttons">'
				. '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url((string) get_permalink($product_id)) . '">' . esc_html__('Get the course', 'lms4wp') . '</a></div>'
				. $back . '</div>';
		} else {
			$html .= '<p class="lms4wp-locked__title">' . esc_html__('Join the course to continue', 'lms4wp') . '</p>';
			$html .= '<p class="lms4wp-locked__text">' . esc_html__('Joining is free. Your progress will be saved.', 'lms4wp') . '</p>';
			$html .= '<div class="wp-block-buttons">'
				. ($course_id ? self::joinForm($course_id, (string) get_permalink($lesson_id), __('Join course', 'lms4wp')) : '')
				. $back . '</div>';
		}

		return $html . '</div>';
	}
}
