/**
 * Promo video meta box: pick a video from the Media Library into the URL field.
 * No build step (vanilla, wp.media).
 */
( function () {
	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.lms4wp-video-pick' );
		if ( ! button || ! window.wp || ! window.wp.media ) {
			return;
		}
		event.preventDefault();

		const input = document.getElementById( button.dataset.target );
		const frame = window.wp.media( {
			title: button.textContent,
			library: { type: 'video' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			if ( input && attachment.url ) {
				input.value = attachment.url;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
		frame.open();
	} );
} )();
