<?php
/**
 * Course Enrollment UI
 *
 * Adds enrollment button to course pages
 *
 * @package ForWP\LMS\Frontend
 */

namespace ForWP\LMS\Frontend;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * CourseEnrollment class
 */
class CourseEnrollment
{
	/**
	 * Initialize enrollment UI
	 */
	public static function init(): void
	{
		$self = new self();
		
		// Add enrollment button to single course page
		add_action('woocommerce_single_product_summary', [$self, 'renderEnrollmentButton'], 25);
		add_action('the_content', [$self, 'addEnrollmentButtonToContent'], 10);
	}

	/**
	 * Add enrollment button to course content
	 *
	 * @param string $content Post content
	 * @return string
	 */
	public function addEnrollmentButtonToContent(string $content): string
	{
		// Only on single course pages
		if (!is_singular('lms_course')) {
			return $content;
		}

		// Only for logged in users
		if (!is_user_logged_in()) {
			return $content;
		}

		global $post;
		$course_id = $post->ID;
		$user_id = get_current_user_id();

		// Check if user is enrolled
		$is_enrolled = \ForWP\LMS\Users\EnrollmentService::isEnrolled($user_id, $course_id);

		// Add enrollment button after content
		$button_html = $this->getEnrollmentButton($course_id, $is_enrolled);
		
		return $content . $button_html;
	}

	/**
	 * Render enrollment button (for WooCommerce integration)
	 */
	public function renderEnrollmentButton(): void
	{
		if (!is_singular('lms_course')) {
			return;
		}

		if (!is_user_logged_in()) {
			return;
		}

		global $post;
		$course_id = $post->ID;
		$user_id = get_current_user_id();
		$is_enrolled = \ForWP\LMS\Users\EnrollmentService::isEnrolled($user_id, $course_id);

		echo $this->getEnrollmentButton($course_id, $is_enrolled);
	}

	/**
	 * Get enrollment button HTML
	 *
	 * @param int $course_id Course ID
	 * @param bool $is_enrolled Whether user is enrolled
	 * @return string
	 */
	private function getEnrollmentButton(int $course_id, bool $is_enrolled): string
	{
		ob_start();
		?>
		<div class="lms4wp-enrollment" style="margin: 20px 0;">
			<?php if ($is_enrolled): ?>
				<p>
					<strong><?php esc_html_e('You are enrolled in this course.', 'lms4wp'); ?></strong>
					<a href="<?php echo esc_url(wc_get_account_endpoint_url('my-courses')); ?>" class="button">
						<?php esc_html_e('Go to My Courses', 'lms4wp'); ?>
					</a>
				</p>
			<?php else: ?>
				<form method="post" action="">
					<?php wp_nonce_field('lms4wp_enroll', 'lms4wp_enroll_nonce'); ?>
					<input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>">
					<button type="submit" name="lms4wp_enroll" class="button button-primary button-large">
						<?php esc_html_e('Enroll in Course', 'lms4wp'); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

