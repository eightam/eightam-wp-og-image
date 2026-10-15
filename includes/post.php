<?php
/**
 * A post's share image: the image of its own an editor chose, else its card,
 * else its featured image. And keeping the card in step with the post: drawn
 * in the background when the post is saved, deleted when it stops being
 * public or is deleted.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\register_fields', 30 );
/**
 * The three fields of the Share image panel, on every kind of post that has
 * it. Late on init, so the site's own post types are registered by then.
 */
function register_fields(): void {
	foreach ( field_types() as $type ) {
		$common = array(
			'single'            => true,
			'show_in_rest'      => true,
			'revisions_enabled' => post_type_supports( $type, 'revisions' ),
			'auth_callback'     => static fn( $allowed, string $key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
		);
		register_post_meta(
			$type,
			META_IMAGE,
			array_merge(
				$common,
				array(
					'type'              => 'integer',
					'default'           => 0,
					'description'       => __( 'An image shared in place of the card.', 'eightam-og-image' ),
					'sanitize_callback' => static fn( $value ): int => max( 0, (int) $value ),
				)
			)
		);
		register_post_meta(
			$type,
			META_TEXT,
			array_merge(
				$common,
				array(
					'type'              => 'string',
					'default'           => '',
					'description'       => __( 'The words on the card, in place of the title.', 'eightam-og-image' ),
					'sanitize_callback' => static fn( $value ): string => sanitize_textarea_field( (string) $value ),
				)
			)
		);
		register_post_meta(
			$type,
			META_PHOTO,
			array_merge(
				$common,
				array(
					'type'              => 'integer',
					'default'           => 0,
					'description'       => __( 'The photograph on the card: 0 for the featured image, -1 for none.', 'eightam-og-image' ),
					'sanitize_callback' => static fn( $value ): int => max( -1, (int) $value ),
				)
			)
		);
	}
}

add_filter( 'eightam_translate_meta', __NAMESPACE__ . '\\translate_meta', 10, 2 );
/**
 * The words on the card are words: 8am Translate translates them with the
 * post. The two images are the same in every language, and it copies them.
 *
 * @param string[] $keys      Keys named so far.
 * @param string   $post_type Post type.
 * @return string[]
 */
function translate_meta( array $keys, string $post_type ): array {
	if ( in_array( $post_type, field_types(), true ) ) {
		$keys[] = META_TEXT;
	}
	return $keys;
}

/**
 * A post's share image.
 *
 * @param \WP_Post $post Post.
 * @param bool     $draw Whether to draw its card if it is missing.
 * @return array{url:string,width:int,height:int,type:string,alt:string,card:bool}|null
 */
function post_image( \WP_Post $post, bool $draw = true ): ?array {
	$own = (int) get_post_meta( $post->ID, META_IMAGE, true );
	if ( $own > 0 ) {
		$image = attachment_image( $own );
		if ( $image ) {
			return $image;
		}
	}

	if ( in_array( $post->post_type, post_types(), true ) && is_post_publicly_viewable( $post ) && Canvas\available() ) {
		[ $card, $style ] = in_locale(
			locale_of( $post ),
			static function () use ( $post ): array {
				$card = post_card( $post );
				return array( $card, style( $card, $post ) );
			}
		);
		$image            = can_draw( $style ) ? card_image( 'post-' . $post->ID, $card, $style, $draw ) : null;
		if ( $image ) {
			return array(
				'url'    => $image['url'],
				'width'  => $image['width'],
				'height' => $image['height'],
				'type'   => $image['type'],
				'alt'    => alt( $card ),
				'card'   => true,
			);
		}
	}

	$featured = (int) get_post_thumbnail_id( $post );
	return $featured ? attachment_image( $featured ) : null;
}

/**
 * What a card says, as the words of its alt text.
 *
 * @param array<string,mixed> $card The card.
 */
function alt( array $card ): string {
	return implode( ' · ', array_filter( array( str_replace( "\n", ' ', (string) $card['title'] ), (string) $card['subtitle'] ) ) );
}

/**
 * An image of the media library as a share image, as it is: the smallest of
 * its sizes at least 1200 pixels wide, or the whole image.
 *
 * @param int $id Attachment ID.
 * @return array{url:string,width:int,height:int,type:string,alt:string,card:bool}|null
 */
function attachment_image( int $id ): ?array {
	if ( ! wp_attachment_is_image( $id ) ) {
		return null;
	}
	$meta  = wp_get_attachment_metadata( $id );
	$sizes = is_array( $meta ) ? (array) ( $meta['sizes'] ?? array() ) : array();
	uasort( $sizes, static fn( $a, $b ): int => ( (int) ( $a['width'] ?? 0 ) ) <=> ( (int) ( $b['width'] ?? 0 ) ) );

	$pick = 'full';
	foreach ( $sizes as $name => $size ) {
		if ( (int) ( $size['width'] ?? 0 ) >= 1200 && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && abs( (int) $size['width'] / max( 1, (int) $size['height'] ) - (int) $meta['width'] / (int) $meta['height'] ) < 0.02 ) {
			$pick = (string) $name;
			break;
		}
	}

	$src = wp_get_attachment_image_src( $id, $pick );
	if ( ! $src ) {
		return null;
	}
	$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	return array(
		'url'    => (string) $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'type'   => (string) get_post_mime_type( $id ),
		'alt'    => '' !== $alt ? $alt : (string) get_the_title( $id ),
		'card'   => false,
	);
}

add_action( 'wp_after_insert_post', __NAMESPACE__ . '\\saved', 10, 2 );
/**
 * After a post is saved, with its meta and terms: draw its card in the
 * background if it is public, delete its cards if it is not.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post.
 */
function saved( int $post_id, \WP_Post $post ): void {
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! in_array( $post->post_type, post_types(), true ) ) {
		return;
	}
	if ( ! is_post_publicly_viewable( $post ) ) {
		forget( 'post-' . $post_id );
		return;
	}

	/**
	 * Whether to draw a post's card as soon as it is saved. Without, it is
	 * drawn when the post is first viewed. Worth turning off for a type that
	 * is imported in the thousands.
	 *
	 * @param bool     $draw Whether to.
	 * @param \WP_Post $post The post.
	 */
	if ( ! apply_filters( 'eightam_og_image_draw_on_save', true, $post ) || ! Canvas\available() ) {
		return;
	}
	if ( ! wp_next_scheduled( HOOK, array( $post_id ) ) ) {
		wp_schedule_single_event( time(), HOOK, array( $post_id ) );
	}
}

add_action( HOOK, __NAMESPACE__ . '\\draw_post' );
/**
 * Draw one post's card: what the save scheduled.
 *
 * @param int $post_id Post ID.
 */
function draw_post( int $post_id ): void {
	$post = get_post( $post_id );
	if ( $post instanceof \WP_Post && is_post_publicly_viewable( $post ) ) {
		post_image( $post );
	}
}

add_action( 'before_delete_post', __NAMESPACE__ . '\\deleted' );
/**
 * A deleted post takes its cards with it.
 *
 * @param int $post_id Post ID.
 */
function deleted( int $post_id ): void {
	forget( 'post-' . $post_id );
}
