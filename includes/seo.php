<?php
/**
 * The card in an SEO plugin's tags. Where an SEO plugin prints the Open
 * Graph tags, this plugin prints none of its own (includes/head.php), so
 * the plugin's og:image and twitter:image are pointed at the card instead,
 * and everything else it prints stays as it is.
 *
 * SEOPress: `seopress_social_og_thumb` carries its og:image tags (the image,
 * its secure address, size and alt text) as HTML, and its newer services the
 * address alone, read from the first `content`; `seopress_social_twitter_card_thumb`
 * carries its twitter:image tag. An image an editor chose in SEOPress's own
 * Social box for a post or a term wins over the card, as it would over the
 * featured image.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The share image to put into an SEO plugin's tags, or null to leave them
 * be: when this plugin prints its own, or an editor chose one in the SEO
 * plugin, or there is no card or image of this plugin's for the screen.
 *
 * @param string[] $meta_keys The SEO plugin's own image fields.
 * @return array{url:string,width:int,height:int,type:string,alt:string}|null
 */
function seo_image( array $meta_keys ): ?array {
	if ( prints() || is_404() || is_feed() ) {
		return null;
	}

	/**
	 * Whether to put the card into an SEO plugin's og:image.
	 *
	 * @param bool $replace Whether to.
	 */
	if ( ! apply_filters( 'eightam_og_image_seo_plugin', true ) ) {
		return null;
	}

	$subject = get_queried_object();
	foreach ( $meta_keys as $meta_key ) {
		if ( $subject instanceof \WP_Post && '' !== (string) get_post_meta( $subject->ID, $meta_key, true ) ) {
			return null;
		}
		if ( $subject instanceof \WP_Term && '' !== (string) get_term_meta( $subject->term_id, $meta_key, true ) ) {
			return null;
		}
	}

	$image = screen_image();
	// The featured image as it is, which the SEO plugin shows anyway, is
	// left to it; only a card, or an image an editor chose here, replaces.
	$own = $subject instanceof \WP_Post && (int) get_post_meta( $subject->ID, META_IMAGE, true ) > 0;
	return $image && ( ! empty( $image['card'] ) || $own ) ? $image : null;
}

add_filter( 'seopress_social_og_thumb', __NAMESPACE__ . '\\seopress_og', 20 );
/**
 * SEOPress's og:image tags, for the card.
 *
 * @param mixed $html Its tags, or its image's address.
 * @return mixed
 */
function seopress_og( $html ) {
	$image = seo_image( array( '_seopress_social_fb_img' ) );
	if ( ! $image ) {
		return $html;
	}
	$tags = array(
		array( 'og:image', $image['url'] ),
	);
	if ( str_starts_with( $image['url'], 'https://' ) ) {
		$tags[] = array( 'og:image:secure_url', $image['url'] );
	}
	$tags[] = array( 'og:image:width', (string) $image['width'] );
	$tags[] = array( 'og:image:height', (string) $image['height'] );
	$tags[] = array( 'og:image:type', $image['type'] );
	if ( '' !== $image['alt'] ) {
		$tags[] = array( 'og:image:alt', $image['alt'] );
	}
	$out = '';
	foreach ( $tags as [ $property, $content ] ) {
		$out .= '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
	}
	return $out;
}

add_filter( 'seopress_social_twitter_card_thumb', __NAMESPACE__ . '\\seopress_twitter', 20 );
/**
 * SEOPress's twitter:image tag, for the card.
 *
 * @param mixed $html Its tag, or its image's address.
 * @return mixed
 */
function seopress_twitter( $html ) {
	$image = seo_image( array( '_seopress_social_twitter_img', '_seopress_social_fb_img' ) );
	return $image ? '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '">' : $html;
}

add_filter( 'seopress_social_twitter_card_summary', __NAMESPACE__ . '\\seopress_twitter_card', 20 );
/**
 * X shows a card wide, as it is drawn, only with `summary_large_image`;
 * with `summary` it crops it to a small square.
 *
 * @param mixed $html SEOPress's twitter:card tag.
 * @return mixed
 */
function seopress_twitter_card( $html ) {
	return seo_image( array( '_seopress_social_twitter_img', '_seopress_social_fb_img' ) ) ? '<meta name="twitter:card" content="summary_large_image">' : $html;
}
