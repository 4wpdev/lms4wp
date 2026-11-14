<?php
/**
 * Source of a course package: local folder (CLI) or 4wp-drive (ADR 0007).
 *
 * @package ForWP\LMS\Import
 */

namespace ForWP\LMS\Import;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

interface PackageReader
{
	/**
	 * Course slug (folder name / package id).
	 */
	public function slug(): string;

	/**
	 * All files of the package, relative paths ("module-1-basics/lesson-1-1-….md").
	 *
	 * @return string[]|\WP_Error
	 */
	public function files();

	/**
	 * Contents of one file.
	 *
	 * @return string|\WP_Error
	 */
	public function read(string $relative);

	/**
	 * Serializable description for queue jobs.
	 *
	 * @return array<string, mixed>
	 */
	public function toArgs(): array;
}
