/**
 * Lesson contents: marks the section in view (scroll-spy) and keeps the URL hash in sync.
 */
( function () {
	// Real height of the sticky site header: sticky bars and asides sit right under it.
	const root = document.documentElement;
	const header = document.querySelector( 'header.wp-block-template-part, body > .wp-site-blocks > header, header' );
	const measure = () => root.style.setProperty( '--forwp-header-h', ( header ? header.offsetHeight : 80 ) + 'px' );
	measure();
	window.addEventListener( 'resize', measure );

	const links = Array.from( document.querySelectorAll( '[data-lms4wp-toc-link]' ) );
	if ( ! links.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	const byId = new Map();
	links.forEach( ( link ) => {
		const target = document.getElementById( decodeURIComponent( link.hash.slice( 1 ) ) );
		if ( target ) {
			byId.set( target.id, link );
		}
	} );

	const setActive = ( id ) => {
		links.forEach( ( link ) => {
			const active = byId.get( id ) === link;
			link.classList.toggle( 'is-active', active );
			if ( active ) {
				link.setAttribute( 'aria-current', 'location' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	};

	const visible = new Set();
	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					visible.add( entry.target );
				} else {
					visible.delete( entry.target );
				}
			} );
			// Topmost visible heading, else the last one above the viewport.
			const headings = Array.from( byId.keys() ).map( ( id ) => document.getElementById( id ) );
			const inView = headings.filter( ( h ) => visible.has( h ) );
			if ( inView.length ) {
				setActive( inView[ 0 ].id );
				return;
			}
			const above = headings.filter( ( h ) => h.getBoundingClientRect().top < 0 );
			if ( above.length ) {
				setActive( above[ above.length - 1 ].id );
			}
		},
		{ rootMargin: '-120px 0px -55% 0px' }
	);
	byId.forEach( ( link, id ) => observer.observe( document.getElementById( id ) ) );

	links.forEach( ( link ) =>
		link.addEventListener( 'click', () => setActive( decodeURIComponent( link.hash.slice( 1 ) ) ) )
	);
} )();
