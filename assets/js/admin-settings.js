/**
 * Settings → Email Signature: pick the logo from the media library.
 *
 * The chosen attachment's id goes into the hidden field; "Use the bundled
 * logo" sets it back to 0. Saving is the form's own job.
 */
( function () {
	'use strict';

	var preview = document.querySelector( '.esg-logo-preview' );
	var input = document.querySelector( '.esg-logo-id' );
	var choose = document.querySelector( '.esg-logo-choose' );
	var reset = document.querySelector( '.esg-logo-reset' );
	if ( ! preview || ! input || ! choose || ! reset || ! window.wp || ! window.wp.media ) {
		return;
	}

	var frame;

	choose.addEventListener( 'click', function () {
		if ( ! frame ) {
			frame = window.wp.media( {
				title: window.esgAdmin.choose,
				button: { text: window.esgAdmin.use },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var image = frame.state().get( 'selection' ).first().toJSON();
				var size = image.sizes && image.sizes.medium ? image.sizes.medium : image;
				input.value = String( image.id );
				preview.src = size.url;
			} );
		}
		frame.open();
	} );

	reset.addEventListener( 'click', function () {
		input.value = '0';
		preview.src = window.esgAdmin.bundledLogo;
	} );
}() );
