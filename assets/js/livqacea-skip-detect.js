/**
 * Skip link target auto-detect button - LivQ AccessFix settings page.
 *
 * Reads config from the localized `livqaceaSkipDetect` object (nonce + strings).
 */
(function () {
	'use strict';

	var btn    = document.getElementById( 'livqacea-detect-btn' );
	var input  = document.getElementById( 'livqacea_skip_link_target' );
	var status = document.getElementById( 'livqacea-detect-status' );

	if ( ! btn || typeof livqaceaSkipDetect === 'undefined' ) {
		return;
	}

	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		status.style.color = '#646970';
		status.textContent = livqaceaSkipDetect.strings.detecting;

		var fd = new FormData();
		fd.append( 'action', 'livqacea_detect_skip_target' );
		fd.append( 'nonce', livqaceaSkipDetect.nonce );

		fetch( ajaxurl, { method: 'POST', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				btn.disabled = false;
				if ( data.success && data.data ) {
					input.value = data.data;
					status.style.color = '#0a6b2d';
					status.textContent = '✓ ' + data.data;
				} else {
					status.style.color = '#b32d2e';
					status.textContent = livqaceaSkipDetect.strings.notFound;
				}
			} )
			.catch( function () {
				btn.disabled = false;
				status.style.color = '#b32d2e';
				status.textContent = livqaceaSkipDetect.strings.requestFailed;
			} );
	} );
})();
