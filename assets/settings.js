/**
 * Settings → Share images: colour pickers with the theme's palette, image
 * choosers from the media library, and a preview drawn from the form as it
 * stands, again a moment after anything in it changes. The template
 * thumbnails are drawn the same way, each with its own template.
 */
( function ( $ ) {
	'use strict';

	const config = window.eightamOgImageSettings;
	const form = document.getElementById( 'eightam-og-form' );
	const preview = document.getElementById( 'eightam-og-preview' );
	const sample = document.getElementById( 'eightam-og-sample' );
	if ( ! config || ! form || ! preview ) {
		return;
	}

	// The form's settings as the REST route takes them.
	function current() {
		const prefix = config.option + '[';
		const out = { post_types: [] };
		for ( const [ key, value ] of new FormData( form ) ) {
			if ( ! key.startsWith( prefix ) ) {
				continue;
			}
			const name = key.slice( prefix.length ).replace( /\]\[?\]?$/, '' );
			if ( key.endsWith( '[]' ) ) {
				out[ name ].push( value );
			} else {
				out[ name ] = value;
			}
		}
		out.first_image = '1' === out.first_image;
		out.google_fonts = '1' === out.google_fonts;
		return out;
	}

	function url( settings, template, width ) {
		const params = new URLSearchParams( {
			post: sample ? sample.value : '0',
			settings: JSON.stringify( settings ),
			width: String( width ),
			_wpnonce: config.nonce,
		} );
		if ( template ) {
			params.set( 'template', template );
		}
		return config.sample + ( config.sample.includes( '?' ) ? '&' : '?' ) + params.toString();
	}

	let timer = 0;
	let thumbs = '';
	function refresh() {
		clearTimeout( timer );
		timer = setTimeout( function () {
			const settings = current();
			preview.classList.add( 'is-loading' );
			preview.src = url( settings, '', 1200 );

			// The thumbnails show every template with everything else as it
			// is in the form; drawn again only when that changes.
			const others = Object.assign( {}, settings, { template: '' } );
			const key = JSON.stringify( others ) + ( sample ? sample.value : '' );
			if ( key !== thumbs ) {
				thumbs = key;
				document.querySelectorAll( '.eightam-og__template img' ).forEach( function ( img ) {
					img.src = url( settings, img.dataset.template, 600 );
				} );
			}
		}, 350 );
	}
	preview.addEventListener( 'load', () => preview.classList.remove( 'is-loading' ) );
	preview.addEventListener( 'error', () => preview.classList.remove( 'is-loading' ) );

	form.addEventListener( 'input', refresh );
	form.addEventListener( 'change', refresh );
	if ( sample ) {
		sample.addEventListener( 'change', refresh );
	}

	// Colours: WordPress's picker, with the theme's palette to pick from.
	$( '.eightam-og__color' ).wpColorPicker( {
		palettes: config.palette.length ? config.palette : true,
		change: refresh,
		clear: refresh,
	} );

	// Images: the site's, none, or one from the media library.
	document.querySelectorAll( '.eightam-og__image' ).forEach( function ( row ) {
		const field = row.querySelector( 'input[type=hidden]' );
		const mode = row.querySelector( '.eightam-og__image-mode' );
		const thumb = row.querySelector( '.eightam-og__thumb' );
		let frame = null;

		function show( src ) {
			thumb.innerHTML = '';
			if ( src ) {
				const img = document.createElement( 'img' );
				img.src = src;
				img.alt = '';
				thumb.appendChild( img );
			}
		}

		function choose() {
			if ( ! frame ) {
				frame = wp.media( {
					title: config.choose,
					button: { text: config.use },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					const image = frame.state().get( 'selection' ).first().toJSON();
					field.value = String( image.id );
					show( image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url );
					refresh();
				} );
			}
			frame.open();
		}

		mode.addEventListener( 'change', function () {
			if ( 'site' === mode.value ) {
				field.value = '0';
				show( row.dataset.siteUrl );
			} else if ( 'none' === mode.value ) {
				field.value = '-1';
				show( '' );
			} else {
				choose();
			}
			refresh();
		} );
		thumb.addEventListener( 'click', function () {
			if ( 'custom' === mode.value ) {
				choose();
			}
		} );
	} );

	refresh();
} )( jQuery );
