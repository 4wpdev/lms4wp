<?php
/**
 * Course package read through 4wp-drive filters (GitHub source).
 * The package is bound to the post Drive imported its "original" into (the course).
 *
 * @package ForWP\LMS\Import
 */

namespace ForWP\LMS\Import;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class DrivePackageReader implements PackageReader
{
	private int $post_id;
	private string $slug;

	/** @var array<string, string> */
	private array $cache = [];

	public function __construct(int $post_id, string $slug)
	{
		$this->post_id = $post_id;
		$this->slug = sanitize_title($slug);
	}

	public function postId(): int
	{
		return $this->post_id;
	}

	public function slug(): string
	{
		return $this->slug;
	}

	public function files()
	{
		$files = apply_filters('forwp_drive_package_files', null, $this->post_id, true);
		if ($files === null) {
			return new \WP_Error('lms4wp_package_drive', '4wp-drive package is not available for this course.');
		}
		if (is_wp_error($files)) {
			return $files;
		}
		$list = array_map('strval', array_keys((array) $files));
		sort($list);
		return $list;
	}

	public function read(string $relative)
	{
		if (isset($this->cache[$relative])) {
			return $this->cache[$relative];
		}
		$text = apply_filters('forwp_drive_package_file', null, $this->post_id, $relative);
		if ($text === null) {
			return new \WP_Error('lms4wp_package_drive', sprintf('4wp-drive cannot read %s', $relative));
		}
		if (is_wp_error($text)) {
			return $text;
		}
		return $this->cache[$relative] = (string) $text;
	}

	public function toArgs(): array
	{
		return ['type' => 'drive', 'post_id' => $this->post_id, 'slug' => $this->slug];
	}

	/**
	 * Reader from queue args.
	 *
	 * @param array<string, mixed> $args
	 */
	public static function fromArgs(array $args): ?PackageReader
	{
		if (($args['type'] ?? '') === 'drive') {
			return new self((int) ($args['post_id'] ?? 0), (string) ($args['slug'] ?? ''));
		}
		if (($args['type'] ?? '') === 'dir') {
			return new FilesystemPackageReader((string) ($args['path'] ?? ''));
		}
		return null;
	}
}
