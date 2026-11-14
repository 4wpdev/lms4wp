<?php
/**
 * Course package from a local folder (hub clone; CLI).
 *
 * @package ForWP\LMS\Import
 */

namespace ForWP\LMS\Import;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class FilesystemPackageReader implements PackageReader
{
	private string $dir;

	public function __construct(string $dir)
	{
		$real = realpath($dir);
		$this->dir = $real !== false ? $real : $dir;
	}

	public function dir(): string
	{
		return $this->dir;
	}

	public function slug(): string
	{
		return sanitize_title(basename($this->dir));
	}

	public function files()
	{
		if (!is_dir($this->dir)) {
			return new \WP_Error('lms4wp_package_dir', sprintf('Not a folder: %s', $this->dir));
		}

		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($this->dir))), '/');
			if ($relative === '' || preg_match('#(^|/)\.#', $relative)) {
				continue;
			}
			$files[] = $relative;
		}
		sort($files);
		return $files;
	}

	public function read(string $relative)
	{
		$path = $this->dir . '/' . ltrim($relative, '/');
		if (str_contains($relative, '..') || !is_readable($path)) {
			return new \WP_Error('lms4wp_package_read', sprintf('Cannot read %s', $relative));
		}
		return (string) file_get_contents($path);
	}

	public function toArgs(): array
	{
		return ['type' => 'dir', 'path' => $this->dir];
	}
}
