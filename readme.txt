=== LMS4WP ===
Contributors: 4wpdev
Tags: lms, learning, courses, education, woocommerce, mcp, ai
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

LMS platform for learning your favorite programming language. WordPress plugin for educational courses and skill development.

== Description ==

LMS4WP is a comprehensive Learning Management System plugin for WordPress that enables you to create, manage, and sell online courses. Built with modern PHP practices and integrated with WooCommerce for seamless e-commerce functionality.

= Key Features =

* Course Management - Create and manage courses with lessons and quizzes
* Progress Tracking - Detailed learning progress tracking with separate database table
* WooCommerce Integration - Sell courses as products with automatic access granting
* MCP Integration - WordPress Model Context Protocol support for AI integrations
* AI Support - Multiple AI provider support (OpenAI, Anthropic, Local LLM)
* Modern Architecture - PSR-4 autoloading, Repository pattern, Service layer
* TypeScript Blocks - Gutenberg blocks built with TypeScript

= Technical Details =

* PHP 8.0+ required
* WordPress 6.0+ required
* Namespace: ForWP\LMS
* MIT License

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/lms4wp` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Run `composer install` to install dependencies.
4. Run `npm install` to install block dependencies.
5. Configure the plugin settings through 'LMS4WP' menu in WordPress admin.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

WooCommerce is optional but recommended for selling courses. The plugin will work without WooCommerce for free courses.

= What is MCP integration? =

MCP (Model Context Protocol) allows AI assistants to interact with your LMS data through standardized contexts, resources, and tools.

= Can I use this with my existing theme? =

Yes, LMS4WP works with any WordPress theme. Custom templates are provided but can be overridden by your theme.

== Changelog ==

= 1.0.0 =
* Initial MVP release
* Core plugin architecture
* Database schema with custom tables
* Post types (Course, Lesson, Quiz)
* Service layer implementation
* Repository pattern
* WooCommerce integration
* MCP protocol support
* AI provider abstraction
* Admin interface
* Frontend templates and shortcodes
* REST API for MCP bridge

== Upgrade Notice ==

= 1.0.0 =
Initial release. Make sure you have PHP 8.0+ and WordPress 6.0+ before installing.


