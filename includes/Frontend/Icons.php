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
		'chevron-left'  => '<path d="m15 18-6-6 6-6"></path>',
		'layers'        => '<path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path>',
		'book-open'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
		'signal'        => '<path d="M18 20V10"></path><path d="M12 20V4"></path><path d="M6 20v-6"></path>',
		'lock-open'     => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path>',
		'graduation-cap' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"></path><path d="M22 10v6"></path><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"></path>',
		'terminal'      => '<polyline points="4 17 10 11 4 5"></polyline><line x1="12" x2="20" y1="19" y2="19"></line>',
		'circle-help'   => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path>',
		'log-in'        => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" x2="3" y1="12" y2="12"></line>',
		'user-plus'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line>',
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
