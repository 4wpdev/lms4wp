/**
 * Editor side of LMS4WP course blocks: server-side rendered preview.
 * No build step (vanilla, WP globals).
 */
( function ( wp ) {
	const { registerBlockType, getBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	const names = [
		'lms4wp/course-meta',
		'lms4wp/course-media',
		'lms4wp/course-join',
		'lms4wp/course-outline',
		'lms4wp/lesson-context',
		'lms4wp/lesson-nav',
		'lms4wp/lesson-terminal',
		'lms4wp/lesson-ide',
		'lms4wp/lesson-complete',
	];

	names.forEach( function ( name ) {
		const settings = getBlockType( name ) || {};

		registerBlockType( name, {
			title: settings.title || name,
			category: 'widgets',
			icon: 'welcome-learn-more',
			apiVersion: 3,
			usesContext: [ 'postId', 'postType' ],
			edit: function ( props ) {
				const blockProps = useBlockProps();
				const controls =
					name === 'lms4wp/course-outline'
						? el(
								InspectorControls,
								null,
								el(
									PanelBody,
									{ title: 'Outline' },
									el( TextControl, {
										label: 'Heading',
										value: props.attributes.heading || '',
										onChange: function ( value ) {
											props.setAttributes( { heading: value } );
										},
									} ),
									el( ToggleControl, {
										label: 'Compact (sidebar)',
										checked: !! props.attributes.compact,
										onChange: function ( value ) {
											props.setAttributes( { compact: value } );
										},
									} )
								)
						  )
						: null;

				return el(
					Fragment,
					null,
					controls,
					el(
						'div',
						blockProps,
						el( ServerSideRender, {
							block: name,
							attributes: props.attributes,
							urlQueryArgs: props.context.postId ? { post_id: props.context.postId } : {},
						} )
					)
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp );
