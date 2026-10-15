/**
 * Settings → Share images: colour pickers with the theme's palette, image
 * choosers from the media library, variants to add and remove, and a preview
 * drawn from the form as it stands, again a moment after anything in it
 * changes. The template thumbnails are drawn the same way, each with its own
 * template.
 *
 * And what the server cannot do with PHP alone, done here once: an SVG logo
 * is drawn to a PNG by the browser, which draws SVG exactly, and a typeface
 * that exists only as WOFF2 is unpacked to TrueType with Google's decoder
 * (assets/vendor/woff2/). Both are sent to the server, which checks them and
 * keeps them for every card from then on.
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

	// The form's settings as the REST route takes them: names such as
	// option[variants][3][logo] become nested objects, [] lists.
	function current() {
		const prefix = config.option;
		const out = { post_types: [] };
		for ( const [ key, value ] of new FormData( form ) ) {
			if ( ! key.startsWith( prefix + '[' ) ) {
				continue;
			}
			const path = key
				.slice( prefix.length )
				.match( /\[([^\]]*)\]/g )
				.map( ( part ) => part.slice( 1, -1 ) );
			let node = out;
			path.forEach( ( part, i ) => {
				const last = i === path.length - 1;
				if ( '' === part ) {
					return;
				}
				if ( last ) {
					if ( '' === path[ i + 1 ] ) {
						return;
					}
					node[ part ] = value;
				} else if ( '' === path[ i + 1 ] ) {
					node[ part ] = node[ part ] || [];
					node[ part ].push( value );
				} else {
					node[ part ] = node[ part ] || {};
					node = node[ part ];
				}
			} );
		}
		out.first_image = '1' === out.first_image;
		out.google_fonts = '1' === out.google_fonts;
		out.variants = Object.values( out.variants || {} );
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
	function refresh( force ) {
		clearTimeout( timer );
		timer = setTimeout( function () {
			const settings = current();
			preview.classList.add( 'is-loading' );
			preview.src = url( settings, '', 1200 ) + ( true === force ? '&_=' + Date.now() : '' );

			// The thumbnails show every template with everything else as it
			// is in the form; drawn again only when that changes.
			const others = Object.assign( {}, settings, { template: '' } );
			const key = JSON.stringify( others ) + ( sample ? sample.value : '' );
			if ( key !== thumbs || true === force ) {
				thumbs = key;
				document.querySelectorAll( '.eightam-og__template img' ).forEach( function ( img ) {
					img.src = url( settings, img.dataset.template, 600 ) + ( true === force ? '&_=' + Date.now() : '' );
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
	function colors( root ) {
		$( root ).find( '.eightam-og__color' ).wpColorPicker( {
			palettes: config.palette.length ? config.palette : true,
			change: refresh,
			clear: refresh,
		} );
	}

	// Images: one of the field's values, or one from the media library.
	function images( root ) {
		root.querySelectorAll( '.eightam-og__image' ).forEach( function ( box ) {
			const field = box.querySelector( 'input[type=hidden]' );
			const mode = box.querySelector( '.eightam-og__image-mode' );
			const thumb = box.querySelector( '.eightam-og__thumb' );
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
				if ( 'custom' === mode.value ) {
					choose();
					return;
				}
				field.value = mode.value;
				show( '0' === mode.value ? box.dataset.siteUrl : '' );
				refresh();
			} );
			thumb.addEventListener( 'click', function () {
				if ( 'custom' === mode.value ) {
					choose();
				}
			} );
		} );
	}

	// Variants: added from the template, removed with their button.
	const variants = document.querySelector( '.eightam-og__variants' );
	const blank = document.getElementById( 'eightam-og-variant' );
	const add = document.getElementById( 'eightam-og-add-variant' );
	if ( variants && blank && add ) {
		add.addEventListener( 'click', function () {
			const html = blank.innerHTML.replace( /__i__/g, String( Date.now() ) );
			const holder = document.createElement( 'div' );
			holder.innerHTML = html;
			const row = holder.firstElementChild;
			variants.appendChild( row );
			colors( row );
			images( row );
			refresh();
		} );
		variants.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '.eightam-og__remove' );
			if ( button ) {
				button.closest( '.eightam-og__variant' ).remove();
				refresh();
			}
		} );
	}

	colors( form );
	images( form );

	// What the server cannot do: done here, then the preview drawn again.
	function base64( bytes ) {
		let text = '';
		for ( let i = 0; i < bytes.length; i += 0x8000 ) {
			text += String.fromCharCode.apply( null, bytes.subarray( i, i + 0x8000 ) );
		}
		return btoa( text );
	}

	function send( body ) {
		return fetch( config.convert, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( body ),
		} ).then( ( response ) => response.ok );
	}

	// An SVG drawn 480 pixels tall, at most 3600 wide, from its viewBox or
	// its size: an SVG with neither draws at 300 by 150.
	async function logo( job ) {
		const text = await ( await fetch( job.url, { credentials: 'same-origin' } ) ).text();
		const doc = new DOMParser().parseFromString( text, 'image/svg+xml' );
		const svg = doc.documentElement;
		let ratio = 2;
		const box = ( svg.getAttribute( 'viewBox' ) || '' ).split( /[\s,]+/ ).map( Number );
		if ( 4 === box.length && box[ 2 ] > 0 && box[ 3 ] > 0 ) {
			ratio = box[ 2 ] / box[ 3 ];
		} else if ( parseFloat( svg.getAttribute( 'width' ) ) > 0 && parseFloat( svg.getAttribute( 'height' ) ) > 0 ) {
			ratio = parseFloat( svg.getAttribute( 'width' ) ) / parseFloat( svg.getAttribute( 'height' ) );
		}
		let height = 480;
		let width = Math.round( height * ratio );
		if ( width > 3600 ) {
			width = 3600;
			height = Math.round( width / ratio );
		}
		svg.setAttribute( 'width', String( width ) );
		svg.setAttribute( 'height', String( height ) );
		const blob = new Blob( [ new XMLSerializer().serializeToString( svg ) ], { type: 'image/svg+xml' } );
		const src = URL.createObjectURL( blob );
		try {
			const img = new Image();
			img.src = src;
			await img.decode();
			const canvas = document.createElement( 'canvas' );
			canvas.width = width;
			canvas.height = height;
			canvas.getContext( '2d' ).drawImage( img, 0, 0, width, height );
			return send( { kind: 'logo', id: job.id, data: canvas.toDataURL( 'image/png' ) } );
		} finally {
			URL.revokeObjectURL( src );
		}
	}

	let decoder = null;
	function woff2() {
		if ( ! decoder ) {
			decoder = new Promise( function ( resolve, reject ) {
				window.Module = { onRuntimeInitialized: () => resolve( window.Module ) };
				const script = document.createElement( 'script' );
				script.src = config.woff2;
				script.onerror = reject;
				document.head.appendChild( script );
			} );
		}
		return decoder;
	}

	async function font( job ) {
		const [ module, response ] = await Promise.all( [ woff2(), fetch( job.url, { credentials: 'same-origin' } ) ] );
		const ttf = module.decompress( new Uint8Array( await response.arrayBuffer() ) );
		if ( ! ttf ) {
			return false;
		}
		return send( { kind: 'font', url: job.url, data: base64( new Uint8Array( ttf ) ) } );
	}

	async function work( jobs ) {
		const note = document.createElement( 'p' );
		note.className = 'eightam-og__working';
		note.textContent = config.working;
		preview.parentNode.after( note );
		let done = 0;
		for ( const job of jobs ) {
			try {
				if ( await ( 'logo' === job.kind ? logo( job ) : font( job ) ) ) {
					done++;
				}
			} catch ( error ) {
				window.console && window.console.warn( '8am OG Image could not prepare', job.url, error );
			}
		}
		note.remove();
		if ( done ) {
			refresh( true );
		}
	}

	refresh();
	if ( config.jobs && config.jobs.length ) {
		work( config.jobs );
	}
} )( jQuery );
