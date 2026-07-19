/**
 * Accessibility Statement Generator - sector notice toggle.
 *
 * Reads the per-sector notice texts from the localized `livqaceaStatement` object.
 */
(function () {
	'use strict';

	var select = document.getElementById( 'livqacea_sector' );
	var box    = document.getElementById( 'livqacea_sector_notice' );

	if ( ! select || ! box || typeof livqaceaStatement === 'undefined' ) {
		return;
	}

	function updateNotice( val ) {
		var n = livqaceaStatement.notices[ val ];
		if ( n ) {
			box.style.display = 'block';
			box.style.background = n.bg;
			box.style.borderColor = n.border;
			box.textContent = n.text;
		} else {
			box.style.display = 'none';
		}
	}

	select.addEventListener( 'change', function () { updateNotice( this.value ); } );
	updateNotice( select.value );
})();
