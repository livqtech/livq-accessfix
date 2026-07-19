/**
 * Contrast Checker - WCAG relative luminance formula, client-side only.
 *
 * Reads translated strings from the localized `livqaceaContrast` object.
 */
(function () {
	'use strict';

	// ── WCAG relative luminance formula ─────────────────────────────────────

	function linearize( c ) {
		c /= 255;
		return c <= 0.04045 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	}

	function luminance( hex ) {
		hex = hex.replace( '#', '' );
		if ( hex.length === 3 ) {
			hex = hex.split( '' ).map( function ( c ) { return c + c; } ).join( '' );
		}
		var r = parseInt( hex.substr( 0, 2 ), 16 );
		var g = parseInt( hex.substr( 2, 2 ), 16 );
		var b = parseInt( hex.substr( 4, 2 ), 16 );
		return 0.2126 * linearize( r ) + 0.7152 * linearize( g ) + 0.0722 * linearize( b );
	}

	function contrastRatio( h1, h2 ) {
		var l1 = luminance( h1 ), l2 = luminance( h2 );
		var lighter = Math.max( l1, l2 ), darker = Math.min( l1, l2 );
		return ( lighter + 0.05 ) / ( darker + 0.05 );
	}

	function isValidHex( h ) {
		return /^#[0-9a-fA-F]{6}$/.test( h ) || /^#[0-9a-fA-F]{3}$/.test( h );
	}

	function normalizeHex( h ) {
		h = h.trim();
		if ( ! h.startsWith( '#' ) ) h = '#' + h;
		if ( h.length === 4 ) h = '#' + h[1] + h[1] + h[2] + h[2] + h[3] + h[3];
		return h;
	}

	function badge( pass ) {
		return pass
			? '<span class="livqacea-pass">✓ PASS</span>'
			: '<span class="livqacea-fail">✗ FAIL</span>';
	}

	// ── Update UI ────────────────────────────────────────────────────────────

	function update() {
		var fg = normalizeHex( document.getElementById( 'livqacea-ct-fg-hex' ).value );
		var bg = normalizeHex( document.getElementById( 'livqacea-ct-bg-hex' ).value );
		if ( ! isValidHex( fg ) || ! isValidHex( bg ) ) return;

		var ratio    = contrastRatio( fg, bg );
		var ratioStr = Math.round( ratio * 100 ) / 100;

		document.getElementById( 'livqacea-ct-ratio-num' ).textContent = ratioStr;
		document.getElementById( 'livqacea-ct-preview' ).style.background = bg;
		document.getElementById( 'livqacea-ct-preview-text' ).style.color  = fg;

		if ( fg.length === 7 ) document.getElementById( 'livqacea-ct-fg-picker' ).value = fg;
		if ( bg.length === 7 ) document.getElementById( 'livqacea-ct-bg-picker' ).value = bg;

		document.getElementById( 'res-aa-normal' ).innerHTML  = badge( ratio >= 4.5 );
		document.getElementById( 'res-aa-large' ).innerHTML   = badge( ratio >= 3 );
		document.getElementById( 'res-aa-ui' ).innerHTML      = badge( ratio >= 3 );
		document.getElementById( 'res-aaa-normal' ).innerHTML = badge( ratio >= 7 );
		document.getElementById( 'res-aaa-large' ).innerHTML  = badge( ratio >= 4.5 );

		var tip     = document.getElementById( 'livqacea-ct-tip' );
		var tipText = document.getElementById( 'livqacea-ct-tip-text' );
		if ( ratio < 4.5 ) {
			tip.style.display = 'block';
			tipText.textContent = ratio < 3
				? livqaceaContrast.strings.belowThree
				: livqaceaContrast.strings.aaLargeOnly;
		} else {
			tip.style.display = 'none';
		}
	}

	// ── Wire inputs ──────────────────────────────────────────────────────────

	document.getElementById( 'livqacea-ct-fg-hex' ).addEventListener( 'input', update );
	document.getElementById( 'livqacea-ct-bg-hex' ).addEventListener( 'input', update );
	document.getElementById( 'livqacea-ct-fg-picker' ).addEventListener( 'input', function () {
		document.getElementById( 'livqacea-ct-fg-hex' ).value = this.value;
		update();
	} );
	document.getElementById( 'livqacea-ct-bg-picker' ).addEventListener( 'input', function () {
		document.getElementById( 'livqacea-ct-bg-hex' ).value = this.value;
		update();
	} );

	// ── Quick palette ──────────────────────────────────────────────────────────

	var palette = [
		{ fg: '#000000', bg: '#ffffff', label: 'Black / White' },
		{ fg: '#ffffff', bg: '#000000', label: 'White / Black' },
		{ fg: '#1d2327', bg: '#ffffff', label: 'WP Dark / White' },
		{ fg: '#0056b3', bg: '#ffffff', label: 'LivQ Blue / White' },
		{ fg: '#ffffff', bg: '#0056b3', label: 'White / LivQ Blue' },
		{ fg: '#767676', bg: '#ffffff', label: 'Grey #767 / White ⚠️' },
		{ fg: '#000000', bg: '#fdde10', label: 'Black / Yellow' },
		{ fg: '#fdde10', bg: '#ffffff', label: 'Yellow / White ✗' },
		{ fg: '#dc3232', bg: '#ffffff', label: 'WP Red / White' },
		{ fg: '#ffffff', bg: '#46b450', label: 'White / WP Green' }
	];
	var container = document.getElementById( 'livqacea-ct-palette' );
	palette.forEach( function ( p ) {
		var btn = document.createElement( 'button' );
		btn.className   = 'livqacea-palette-chip';
		btn.textContent = p.label;
		btn.type        = 'button';
		btn.style.background = p.bg;
		btn.style.color      = p.fg;
		btn.addEventListener( 'click', function () {
			document.getElementById( 'livqacea-ct-fg-hex' ).value = p.fg;
			document.getElementById( 'livqacea-ct-bg-hex' ).value = p.bg;
			update();
		} );
		container.appendChild( btn );
	} );

	// Init on page load.
	update();
})();
