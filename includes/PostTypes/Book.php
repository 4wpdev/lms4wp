<?php
/**
 * Book Post Type
 *
 * Public book pages used by the Books catalog at /books/.
 *
 * @package ForWP\LMS\PostTypes
 */

namespace ForWP\LMS\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Book post type class
 */
class Book
{
	/**
	 * Post type slug. Matches theme templates (single-book, page-books query).
	 */
	const POST_TYPE = 'book';

	/**
	 * Hierarchical book category. Matches post-terms blocks in the theme.
	 */
	const TAX_CATEGORY = 'forwp_book_category';

	/**
	 * Bump when rewrite rules change (triggers flush on upgrade).
	 */
	const REWRITE_VERSION = '1';

	/**
	 * Initialize book post type
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('init', [$self, 'registerTaxonomy'], 9);
		add_action('init', [$self, 'registerPostType'], 10);
	}

	/**
	 * Register the book post type.
	 *
	 * Singles live at /book/{name}/. The catalog stays on the /books/ page.
	 */
	public function registerPostType(): void
	{
		$labels = [
			'name'                  => _x('Books', 'Post type general name', 'lms4wp'),
			'singular_name'         => _x('Book', 'Post type singular name', 'lms4wp'),
			'menu_name'             => _x('Books', 'Admin Menu text', 'lms4wp'),
			'name_admin_bar'        => _x('Book', 'Add New on Toolbar', 'lms4wp'),
			'add_new'               => __('Add New', 'lms4wp'),
			'add_new_item'          => __('Add New Book', 'lms4wp'),
			'new_item'              => __('New Book', 'lms4wp'),
			'edit_item'             => __('Edit Book', 'lms4wp'),
			'view_item'             => __('View Book', 'lms4wp'),
			'all_items'             => __('All Books', 'lms4wp'),
			'search_items'          => __('Search Books', 'lms4wp'),
			'not_found'             => __('No books found.', 'lms4wp'),
			'not_found_in_trash'    => __('No books found in Trash.', 'lms4wp'),
			'featured_image'        => _x('Cover Image', 'Overrides the "Featured Image" phrase', 'lms4wp'),
			'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'lms4wp'),
			'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'lms4wp'),
			'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'lms4wp'),
			'archives'              => _x('Book archives', 'The post type archive label used in nav menus', 'lms4wp'),
			'insert_into_item'      => _x('Insert into book', 'Overrides the "Insert into post"/"Insert into page" phrase', 'lms4wp'),
			'uploaded_to_this_item' => _x('Uploaded to this book', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'lms4wp'),
			'filter_items_list'     => _x('Filter books list', 'Screen reader text for the filter links', 'lms4wp'),
			'items_list_navigation' => _x('Books list navigation', 'Screen reader text for the pagination', 'lms4wp'),
			'items_list'            => _x('Books list', 'Screen reader text for the items list', 'lms4wp'),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => [
				'slug'       => 'book',
				'with_front' => false,
			],
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 28,
			'menu_icon'          => 'dashicons-book-alt',
			'supports'           => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
			],
			'taxonomies'         => [
				self::TAX_CATEGORY,
			],
		];

		register_post_type(self::POST_TYPE, $args);
	}

	/**
	 * Register the hierarchical book category.
	 */
	public function registerTaxonomy(): void
	{
		$labels = [
			'name'              => _x('Categories', 'Taxonomy general name', 'lms4wp'),
			'singular_name'     => _x('Category', 'Taxonomy singular name', 'lms4wp'),
			'search_items'      => __('Search Categories', 'lms4wp'),
			'all_items'         => __('All Categories', 'lms4wp'),
			'parent_item'       => __('Parent Category', 'lms4wp'),
			'parent_item_colon' => __('Parent Category:', 'lms4wp'),
			'edit_item'         => __('Edit Category', 'lms4wp'),
			'view_item'         => __('View Category', 'lms4wp'),
			'update_item'       => __('Update Category', 'lms4wp'),
			'add_new_item'      => __('Add New Category', 'lms4wp'),
			'new_item_name'     => __('New Category Name', 'lms4wp'),
			'menu_name'         => __('Categories', 'lms4wp'),
			'not_found'         => __('No categories found.', 'lms4wp'),
			'back_to_items'     => __('&larr; Back to Categories', 'lms4wp'),
		];

		register_taxonomy(
			self::TAX_CATEGORY,
			[self::POST_TYPE],
			[
				'hierarchical'      => true,
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => [
					'slug'         => 'book-category',
					'with_front'   => false,
					'hierarchical' => true,
				],
			]
		);
	}
}
