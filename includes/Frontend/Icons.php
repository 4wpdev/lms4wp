<?php
/**
 * Lucide icons as inline SVG (same approach as the 4wpdev theme).
 * Stroke uses currentColor — colour comes from CSS.
 *
 * @package ForWP\LMS\Frontend
 */

namespace ForWP\LMS\Frontend;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class Icons
{
	/** Lucide icon bodies (lucide.dev, ISC). */
	private const PATHS = [
		'lock'          => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
		'chevron-down'  => '<path d="m6 9 6 6 6-6"></path>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"></path>',
		'arrow-left'    => '<path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>',
		'arrow-right'   => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
		'check'         => '<path d="M20 6 9 17l-5-5"></path>',
		'circle-check'  => '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>',
	];

	public static function svg(string $name, int $size = 16, string $class = ''): string
	{
		if (!isset(self::PATHS[$name])) {
			return '';
		}
		return sprintf(
			'<svg class="lms4wp-icon lms4wp-icon--%1$s%2$s" xmlns="http://www.w3.org/2000/svg" width="%3$d" height="%3$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%4$s</svg>',
			esc_attr($name),
			$class !== '' ? ' ' . esc_attr($class) : '',
			$size,
			self::PATHS[$name]
		);
	}
}
