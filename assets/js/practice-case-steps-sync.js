/**
 * Sync TechArticle steps panel with terminal challenge progress.
 *
 * Listens to forwp-ac-terminal-challenge-active-step (from challenge-app.js).
 * Highlights the active step and scrolls it into view inside the steps column.
 */
( function () {
	function parseStepNumber( value ) {
		const step = parseInt( String( value || '' ), 10 );
		return Number.isFinite( step ) && step > 0 ? step : 0;
	}

	function findStepsSection( root ) {
		return root.querySelector(
			'.forwp-seo-techarticle-steps, .wp-block-forwp-seo-techarticle-steps'
		);
	}

	function getStepGroups( stepsSection ) {
		const tagged = Array.from(
			stepsSection.querySelectorAll( '.forwp-seo-techarticle-step[data-step]' )
		);

		if ( tagged.length ) {
			return tagged.map( ( element ) => ( {
				step: parseStepNumber( element.dataset.step ),
				nodes: [ element ],
				scrollTarget: element,
			} ) );
		}

		const headings = Array.from(
			stepsSection.querySelectorAll( ':scope > h3.wp-block-heading' )
		);

		return headings.map( ( heading, index ) => {
			const nodes = [ heading ];
			let node = heading.nextElementSibling;

			while ( node && ! node.matches( 'h3.wp-block-heading' ) ) {
				nodes.push( node );
				node = node.nextElementSibling;
			}

			return {
				step: index + 1,
				nodes,
				scrollTarget: heading,
			};
		} );
	}

	function setActiveStep( stepsSection, currentStep, options = {} ) {
		const groups = getStepGroups( stepsSection );
		if ( ! groups.length ) {
			return;
		}

		let scrollTarget = null;

		groups.forEach( ( group ) => {
			const isActive = group.step === currentStep;
			const isComplete = group.step < currentStep;

			group.nodes.forEach( ( node ) => {
				node.classList.toggle( 'is-active', isActive );
				node.classList.toggle( 'is-complete', isComplete );
				node.classList.toggle( 'is-error', isActive && !! options.error );
			} );

			if ( isActive ) {
				scrollTarget = group.scrollTarget;
			}
		} );

		if ( ! scrollTarget || typeof scrollTarget.scrollIntoView !== 'function' ) {
			return;
		}

		scrollTarget.scrollIntoView( {
			behavior: options.smooth === false ? 'auto' : 'smooth',
			block: 'nearest',
		} );
	}

	function bindPracticeCase( root ) {
		const stepsSection = findStepsSection( root );
		const terminal = root.querySelector( '.forwp-ac-terminal' );

		if ( ! stepsSection || ! terminal ) {
			return;
		}

		const applyFromTerminal = ( options = {} ) => {
			const currentStep = parseStepNumber( terminal.dataset.challengeStep ) || 1;
			setActiveStep( stepsSection, currentStep, options );
		};

		applyFromTerminal( { smooth: false } );

		root.addEventListener( 'forwp-ac-terminal-challenge-active-step', ( event ) => {
			const currentStep =
				parseStepNumber( event.detail?.currentStep ) ||
				parseStepNumber( terminal.dataset.challengeStep ) ||
				1;

			setActiveStep( stepsSection, currentStep, {
				error: !! event.detail?.error,
			} );
		} );
	}

	function init() {
		document
			.querySelectorAll( '.forwp-practice-case' )
			.forEach( bindPracticeCase );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
