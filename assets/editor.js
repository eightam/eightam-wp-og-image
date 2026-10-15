/**
 * The Share image panel in the editor's sidebar.
 *
 * The card as a shared link will show it, drawn on the server from what is in
 * the editor — the title as typed, the featured image as chosen, saved or
 * not — and the three things an editor can change: the words on the card,
 * its photograph, and whether to use an image of their own instead.
 *
 * Plain JavaScript on WordPress's own packages, like the theme's details
 * panel: nothing to build.
 */
( function ( wp, settings ) {
	'use strict';

	if ( ! wp || ! settings ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var components = wp.components;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var MediaUpload = wp.blockEditor && wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor && wp.blockEditor.MediaUploadCheck;
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel || ! MediaUpload ) {
		return;
	}

	var KEYS = settings.keys;

	/**
	 * A value that follows another once it has stopped changing for a while:
	 * the preview is drawn when the typing stops, not at every letter.
	 */
	function useSettled( value, delay ) {
		var state = useState( value );
		useEffect(
			function () {
				var timer = setTimeout( function () {
					state[ 1 ]( value );
				}, delay );
				return function () {
					clearTimeout( timer );
				};
			},
			[ value ]
		);
		return state[ 0 ];
	}

	/**
	 * The pictures in the text as it stands, in the order a reader meets
	 * them, as the server looks for them: what a block keeps by attachment
	 * ID, then an image or cover kept only by its address, then the blocks
	 * inside. The server takes the first one large enough for a card when
	 * there is no featured image.
	 */
	function pictures( blocks, list ) {
		( blocks || [] ).forEach( function ( block ) {
			var attrs = block.attributes || {};
			[ 'id', 'mediaId', 'imageId' ].forEach( function ( key ) {
				if ( typeof attrs[ key ] === 'number' && attrs[ key ] > 0 ) {
					list.push( attrs[ key ] );
				}
			} );
			[ 'ids', 'imageIds' ].forEach( function ( key ) {
				( Array.isArray( attrs[ key ] ) ? attrs[ key ] : [] ).forEach( function ( id ) {
					if ( parseInt( id, 10 ) > 0 ) {
						list.push( parseInt( id, 10 ) );
					}
				} );
			} );
			if ( ( 'core/image' === block.name || 'core/cover' === block.name ) && ! attrs.id && attrs.url ) {
				list.push( String( attrs.url ) );
			}
			pictures( block.innerBlocks, list );
		} );
		return list;
	}

	/* A button that opens the media library ------------------------------- */

	function Choose( props ) {
		return el(
			MediaUploadCheck,
			null,
			el( MediaUpload, {
				allowedTypes: [ 'image' ],
				value: props.value || undefined,
				onSelect: function ( media ) {
					props.onSelect( media.id );
				},
				render: function ( media ) {
					return el(
						components.Button,
						{ variant: props.variant || 'secondary', onClick: media.open },
						props.label
					);
				},
			} )
		);
	}

	/* A picture from the media library, small ------------------------------ */

	function Thumbnail( props ) {
		var media = useSelect(
			function ( select ) {
				return props.id ? select( 'core' ).getMedia( props.id ) : null;
			},
			[ props.id ]
		);
		if ( ! media ) {
			return null;
		}
		var sizes = ( media.media_details && media.media_details.sizes ) || {};
		var src = ( sizes.medium_large || sizes.large || sizes.medium || media ).source_url;
		return el( 'img', {
			src: src,
			alt: '',
			style: {
				display: 'block',
				width: '100%',
				maxHeight: '180px',
				objectFit: 'contain',
				background: '#f0f0f0',
				margin: '0 0 12px',
			},
		} );
	}

	/* The card as drawn now: a new one for every address, so the spinner
	   shows until that one has loaded ------------------------------------- */

	function Preview( props ) {
		var loading = useState( true );
		var failed = useState( false );

		if ( failed[ 0 ] ) {
			return el(
				components.Notice,
				{ status: 'warning', isDismissible: false },
				__( 'The card could not be drawn.', 'eightam-og-image' )
			);
		}

		return el(
			'div',
			{
				style: {
					position: 'relative',
					aspectRatio: '1200 / 630',
					background: '#f0f0f0',
					margin: '0 0 12px',
				},
			},
			el( 'img', {
				src: props.src,
				alt: __( 'The card links to this page will show.', 'eightam-og-image' ),
				onLoad: function () {
					loading[ 1 ]( false );
				},
				onError: function () {
					failed[ 1 ]( true );
				},
				style: {
					display: 'block',
					width: '100%',
					height: 'auto',
					opacity: loading[ 0 ] ? 0.55 : 1,
					transition: 'opacity 0.2s',
				},
			} ),
			loading[ 0 ]
				? el(
						'div',
						{
							style: {
								position: 'absolute',
								inset: 0,
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
							},
						},
						el( components.Spinner )
				  )
				: null
		);
	}

	/* The panel -------------------------------------------------------------- */

	function Panel() {
		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				id: editor.getCurrentPostId(),
				title: editor.getEditedPostAttribute( 'title' ) || '',
				featured: editor.getEditedPostAttribute( 'featured_media' ) || 0,
				meta: editor.getEditedPostAttribute( 'meta' ) || {},
				first: JSON.stringify(
					pictures( select( 'core/block-editor' ).getBlocks(), [] ).slice( 0, 8 )
				),
			};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var choosing = useState( false );

		var own = parseInt( post.meta[ KEYS.image ], 10 ) || 0;
		var text = post.meta[ KEYS.text ] || '';
		var photo = parseInt( post.meta[ KEYS.photo ], 10 ) || 0;
		var mode = photo < 0 ? 'none' : photo > 0 || choosing[ 0 ] ? 'other' : 'featured';

		var src = useSettled(
			wp.url.addQueryArgs( settings.preview + post.id, {
				title: post.title,
				text: text,
				photo: photo,
				featured: post.featured,
				first: post.first,
				_wpnonce: settings.nonce,
			} ),
			700
		);

		function set( key, value ) {
			var meta = {};
			meta[ key ] = value;
			editPost( { meta: meta } );
		}

		var body;
		if ( own ) {
			body = [
				el(
					'p',
					{ key: 'about' },
					__( 'Links to this page show this image instead of the card.', 'eightam-og-image' )
				),
				el( Thumbnail, { key: 'image', id: own } ),
				el(
					components.Flex,
					{ key: 'actions', justify: 'flex-start' },
					el( Choose, {
						label: __( 'Replace', 'eightam-og-image' ),
						value: own,
						onSelect: function ( id ) {
							set( KEYS.image, id );
						},
					} ),
					el(
						components.Button,
						{
							variant: 'tertiary',
							onClick: function () {
								set( KEYS.image, 0 );
							},
						},
						__( 'Use the card again', 'eightam-og-image' )
					)
				),
			];
		} else {
			body = [
				settings.draws
					? el( Preview, { key: src, src: src } )
					: el(
							components.Notice,
							{ key: 'preview', status: 'warning', isDismissible: false },
							__( 'This server cannot draw cards. Links show the featured image.', 'eightam-og-image' )
					  ),
				el( components.TextareaControl, {
					key: 'text',
					label: __( 'Words on the card', 'eightam-og-image' ),
					help: __( 'Empty, the card shows the title. Start a new line where the card should break one.', 'eightam-og-image' ),
					placeholder: post.title,
					value: text,
					rows: 2,
					onChange: function ( value ) {
						set( KEYS.text, value );
					},
				} ),
				el( components.SelectControl, {
					key: 'photo',
					label: __( 'Photograph', 'eightam-og-image' ),
					help:
						'featured' === mode
							? __( 'Without a featured image, the first picture on the page stands in.', 'eightam-og-image' )
							: undefined,
					value: mode,
					options: [
						{ value: 'featured', label: __( 'The featured image', 'eightam-og-image' ) },
						{ value: 'other', label: __( 'Another image', 'eightam-og-image' ) },
						{ value: 'none', label: __( 'None', 'eightam-og-image' ) },
					],
					onChange: function ( value ) {
						choosing[ 1 ]( 'other' === value );
						if ( 'featured' === value ) {
							set( KEYS.photo, 0 );
						} else if ( 'none' === value ) {
							set( KEYS.photo, -1 );
						}
					},
				} ),
				'other' === mode
					? el(
							'div',
							{ key: 'other', style: { margin: '-8px 0 16px' } },
							el( Choose, {
								label: photo > 0 ? __( 'Choose another', 'eightam-og-image' ) : __( 'Choose an image', 'eightam-og-image' ),
								value: photo,
								onSelect: function ( id ) {
									set( KEYS.photo, id );
								},
							} )
					  )
					: null,
				el( Choose, {
					key: 'own',
					variant: 'link',
					label: __( 'Use an image of your own instead', 'eightam-og-image' ),
					onSelect: function ( id ) {
						set( KEYS.image, id );
					},
				} ),
			];
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'eightam-og-image',
				title: __( 'Share image', 'eightam-og-image' ),
				className: 'eightam-og-image-panel',
			},
			body
		);
	}

	wp.plugins.registerPlugin( 'eightam-og-image', { render: Panel } );
} )( window.wp, window.eightamOgImage );
