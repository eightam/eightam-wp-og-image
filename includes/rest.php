<?php
/**
 * The preview the Share image panel shows: a post's card drawn from what is in
 * the editor, saved or not, and sent as an image. Nothing is stored, so a
 * draft's card never lands in uploads before the draft is published.
 *
 *     GET /wp-json/eightam-og-image/v1/preview/<post>?title=…&text=…&photo=…&featured=…&first=[…]
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROUTE = 'eightam-og-image/v1';

add_action( 'rest_api_init', __NAMESPACE__ . '\\routes' );
/**
 * The preview route.
 */
function routes(): void {
	register_rest_route(
		ROUTE,
		'/preview/(?P<id>\d+)',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\preview',
			'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['id'] ),
			'args'                => array(
				'title'    => array(
					'type'    => 'string',
					'default' => null,
				),
				'text'     => array(
					'type'    => 'string',
					'default' => null,
				),
				'photo'    => array(
					'type'    => 'integer',
					'default' => null,
				),
				'featured' => array(
					'type'    => 'integer',
					'default' => null,
				),
				// The pictures in the text as it stands, in order, as a JSON
				// list of attachment IDs and addresses: the first large
				// enough stands in for a missing featured image.
				'first'    => array(
					'type'    => 'string',
					'default' => null,
				),
			),
		)
	);
}

/**
 * The route Settings → Share images draws its preview and its template
 * thumbnails with: a post's card, or the front page's with `post` 0, drawn
 * with the settings in the form, as JSON, saved or not.
 *
 *     GET /wp-json/eightam-og-image/v1/sample?post=12&settings={…}&template=paper
 */
function sample_route(): void {
	register_rest_route(
		ROUTE,
		'/sample',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\sample',
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'args'                => array(
				'post'     => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'settings' => array(
					'type'    => 'string',
					'default' => '',
				),
				'template' => array(
					'type'    => 'string',
					'default' => '',
				),
				'width'    => array(
					'type'    => 'integer',
					'default' => 1200,
				),
			),
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\sample_route' );

add_action( 'rest_api_init', __NAMESPACE__ . '\\convert_route' );
/**
 * The route the settings page sends what the browser made to: a PNG of an
 * SVG logo, which GD cannot read, and the TrueType font inside a WOFF2 file,
 * which PHP cannot unpack. Both are checked and made again here before they
 * are kept.
 *
 *     POST /wp-json/eightam-og-image/v1/convert  { kind: logo, id, data }
 *     POST /wp-json/eightam-og-image/v1/convert  { kind: font, url, data }
 */
function convert_route(): void {
	register_rest_route(
		ROUTE,
		'/convert',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\convert',
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'args'                => array(
				'kind' => array(
					'type'     => 'string',
					'enum'     => array( 'logo', 'font' ),
					'required' => true,
				),
				'id'   => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'url'  => array(
					'type'    => 'string',
					'default' => '',
				),
				// Base64, at most 8 MB of it.
				'data' => array(
					'type'      => 'string',
					'required'  => true,
					'maxLength' => 8 * 1024 * 1024,
				),
			),
		)
	);
}

/**
 * Keep what the browser made.
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function convert( \WP_REST_Request $request ) {
	$data = base64_decode( (string) preg_replace( '#^data:[^,]*,#', '', (string) $request['data'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- bytes the browser sent.
	if ( false === $data || '' === $data ) {
		return new \WP_Error( 'eightam_og_image_data', 'No data.', array( 'status' => 400 ) );
	}

	if ( 'logo' === $request['kind'] ) {
		$id = (int) $request['id'];
		if ( ! current_user_can( 'read_post', $id ) || ! Templates\logo_png( $id, $data ) ) {
			return new \WP_Error( 'eightam_og_image_logo', 'That logo could not be kept.', array( 'status' => 400 ) );
		}
		return new \WP_REST_Response( array( 'kept' => true ) );
	}

	$file = Brand\font_path( (string) $request['url'] );
	if ( '' === $file || ! preg_match( '/\.woff2$/i', $file ) || ! Fonts\keep_unpacked( $file, $data ) ) {
		return new \WP_Error( 'eightam_og_image_font', 'That font could not be kept.', array( 'status' => 400 ) );
	}
	return new \WP_REST_Response( array( 'kept' => true ) );
}

/**
 * Draw a sample.
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function sample( \WP_REST_Request $request ) {
	if ( ! Canvas\available() ) {
		return new \WP_Error( 'eightam_og_image_no_gd', __( 'This server cannot draw share images: PHP’s GD extension with FreeType is missing.', 'eightam-og-image' ), array( 'status' => 501 ) );
	}
	$given = json_decode( (string) $request['settings'], true );
	$given = is_array( $given ) ? $given : settings();
	settings( $given );
	if ( isset( Templates\all()[ (string) $request['template'] ] ) ) {
		forced_template( (string) $request['template'] );
	}

	try {
		$post = (int) $request['post'] > 0 ? get_post( (int) $request['post'] ) : null;
		if ( $post instanceof \WP_Post && current_user_can( 'read_post', $post->ID ) ) {
			$card  = post_card( $post );
			$style = style( $card, $post );
		} else {
			$card = tidy(
				(array) apply_filters(
					'eightam_og_image_card',
					array(
						'title' => words( site_line() ),
						'meta'  => host( home_url( '/' ) ),
					),
					null
				)
			);
			$style = style( $card, null );
		}
		if ( ! can_draw( $style ) ) {
			return new \WP_Error( 'eightam_og_image_no_font', __( 'There is no font to draw share images with.', 'eightam-og-image' ), array( 'status' => 501 ) );
		}
		wp_raise_memory_limit( 'image' );
		$image = image( $card, $style );
	} finally {
		settings( array() );
		forced_template( '' );
	}

	$width = max( 300, min( Template\WIDTH, (int) $request['width'] ) );
	if ( $width < Template\WIDTH ) {
		$small = imagescale( $image, $width, (int) round( $width * Template\HEIGHT / Template\WIDTH ), IMG_BICUBIC );
		$image = $small instanceof \GdImage ? $small : $image;
	}
	ob_start();
	imagejpeg( $image, null, 84 );
	$response = new \WP_REST_Response( (string) ob_get_clean() );
	$response->header( 'Content-Type', 'image/jpeg' );
	$response->header( 'Cache-Control', 'private, no-store' );
	return $response;
}

/**
 * Draw the preview.
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function preview( \WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, post_types(), true ) ) {
		return new \WP_Error( 'eightam_og_image_no_card', __( 'This kind of post has no share image.', 'eightam-og-image' ), array( 'status' => 404 ) );
	}
	if ( ! Canvas\available() ) {
		return new \WP_Error( 'eightam_og_image_no_gd', __( 'This server cannot draw share images: PHP’s GD extension with FreeType is missing.', 'eightam-og-image' ), array( 'status' => 501 ) );
	}

	$unsaved = array_filter(
		array(
			'title'    => $request['title'],
			'text'     => $request['text'],
			'photo'    => $request['photo'],
			'featured' => $request['featured'],
		),
		static fn( $value ): bool => null !== $value
	);

	// A picture is drawn only if the editor may see it in the media library.
	foreach ( array( 'photo', 'featured' ) as $key ) {
		$id = (int) ( $unsaved[ $key ] ?? 0 );
		if ( $id > 0 && ( ! wp_attachment_is_image( $id ) || ! current_user_can( 'read_post', $id ) ) ) {
			return new \WP_Error( 'eightam_og_image_no_photo', __( 'That image cannot be used.', 'eightam-og-image' ), array( 'status' => 403 ) );
		}
	}
	if ( null !== $request['first'] ) {
		$unsaved['first'] = first_of( (string) $request['first'] );
	}

	[ $card, $style ] = in_locale(
		locale_of( $post ),
		static function () use ( $post, $unsaved ): array {
			$card = post_card( $post, $unsaved );
			return array( $card, style( $card, $post ) );
		}
	);
	if ( ! can_draw( $style ) ) {
		return new \WP_Error( 'eightam_og_image_no_font', __( 'There is no font to draw share images with.', 'eightam-og-image' ), array( 'status' => 501 ) );
	}

	wp_raise_memory_limit( 'image' );
	ob_start();
	imagejpeg( image( $card, $style ), null, 82 );
	$jpeg = (string) ob_get_clean();

	$response = new \WP_REST_Response( $jpeg );
	$response->header( 'Content-Type', 'image/jpeg' );
	$response->header( 'Cache-Control', 'private, no-store' );
	return $response;
}

/**
 * The first picture of the editor's list that the card would use: one the
 * editor may see and large enough, as first_image() chooses from saved text.
 *
 * @param string $json A JSON list of attachment IDs and addresses.
 * @return int|array{file:string}|null
 */
function first_of( string $json ) {
	$list = json_decode( $json, true );
	foreach ( is_array( $list ) ? array_slice( $list, 0, 8 ) : array() as $candidate ) {
		if ( is_int( $candidate ) ) {
			if ( current_user_can( 'read_post', $candidate ) && large_enough( $candidate ) ) {
				return $candidate;
			}
		} elseif ( is_string( $candidate ) ) {
			$file = site_file( $candidate );
			if ( '' !== $file && large_enough( $file ) ) {
				return array( 'file' => $file );
			}
		}
	}
	return null;
}

add_filter( 'rest_pre_serve_request', __NAMESPACE__ . '\\serve', 10, 3 );
/**
 * Send the preview as the image it is, not as JSON. The server has sent the
 * response's headers, the image type among them, by the time it asks.
 *
 * @param bool              $served  Whether the request has been served.
 * @param \WP_HTTP_Response $result  Result.
 * @param \WP_REST_Request  $request Request.
 */
function serve( $served, $result, $request ): bool {
	if ( $served || ! $request instanceof \WP_REST_Request || ! preg_match( '#^/' . preg_quote( ROUTE, '#' ) . '/(preview/|sample$)#', $request->get_route() ) ) {
		return (bool) $served;
	}
	if ( ! $result instanceof \WP_REST_Response || $result->is_error() || ! is_string( $result->get_data() ) ) {
		return (bool) $served;
	}
	echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput -- image bytes.
	return true;
}
