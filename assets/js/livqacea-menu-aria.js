/**
 * Menu accessibility helper - toggles aria-expanded on sub-menu triggers.
 *
 * WCAG 4.1.2. Fully static, no dynamic PHP data.
 */
(function () {
	'use strict';

	function closeOthers( except ) {
		document.querySelectorAll( '[aria-haspopup="true"][aria-expanded="true"]' )
			.forEach( function ( el ) {
				if ( el !== except ) {
					el.setAttribute( 'aria-expanded', 'false' );
				}
			} );
	}

	function toggle( trigger ) {
		var expanded = trigger.getAttribute( 'aria-expanded' ) === 'true';
		closeOthers( expanded ? null : trigger );
		trigger.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {

		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '[aria-haspopup="true"]' );
			if ( trigger ) {
				toggle( trigger );
			} else {
				closeOthers( null );
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			var trigger = e.target.closest( '[aria-haspopup="true"]' );

			if ( trigger && ( e.key === 'Enter' || e.key === ' ' ) ) {
				e.preventDefault();
				toggle( trigger );
				return;
			}

			if ( e.key === 'Escape' ) {
				var open = document.querySelector( '[aria-haspopup="true"][aria-expanded="true"]' );
				if ( open ) {
					open.setAttribute( 'aria-expanded', 'false' );
					open.focus();
				}
			}
		} );
	} );
})();
