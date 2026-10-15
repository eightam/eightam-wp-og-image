<?php
/**
 * The tags in the head of every page that tell a link preview what to show:
 * Open Graph (og:title, og:description, og:url, og:image and its size, type
 * and alt text) and Twitter's card type, which X reads the og: tags for.
 *
 * Where an SEO plugin prints its own, this prints nothing, so there is never
 * a second set; `eightam_og_image_print` decides.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this plugin prints the tags: not where an SEO plugin does.
 */
function prints(): bool {
	$seo = defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );

	/**
	 * Whether to print the Open Graph and Twitter tags.
	 *
	 * @param bool $print Whether to; false where an SEO plugin is active.
	 */
	return (bool) apply_filters( 'eightam_og_image_print', ! $seo );
}

add_filter( 'jetpack_enable_open_graph', __NAMESPACE__ . '\\jetpack', 100 );
/**
 * Jetpack prints Open Graph tags of its own unless told not to.
 *
 * @param bool $enabled Whether Jetpack prints them.
 */
function jetpack( $enabled ): bool {
	return prints() ? false : (bool) $enabled;
}

add_action( 'wp_head', __NAMESPACE__ . '\\head', 5 );
/**
 * Print the tags.
 */
function head(): void {
	if ( is_404() || is_feed() || ! prints() ) {
		return;
	}
	foreach ( tags() as $tag ) {
		printf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			esc_attr( $tag[0] ),
			esc_attr( $tag[1] ),
			esc_attr( $tag[2] )
		);
	}
}

/**
 * The tags for the screen being viewed, as [attribute, name, content].
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function tags(): array {
	$subject = get_queried_object();
	$post    = $subject instanceof \WP_Post ? $subject : null;

	if ( $post ) {
		$front   = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID;
		$title   = $front ? (string) get_bloginfo( 'name' ) : words( get_the_title( $post ) );
		$url     = (string) wp_get_canonical_url( $post );
		$url     = '' !== $url ? $url : (string) get_permalink( $post );
		$type    = $front || 'page' === $post->post_type || (int) get_option( 'page_for_posts' ) === $post->ID ? 'website' : 'article';
		$summary = description( $post );
	} else {
		[ $card, , $subject ] = screen_card();
		$title                = $subject ? str_replace( "\n", ' ', $card['title'] ) : (string) get_bloginfo( 'name' );
		$url                  = screen_url( $subject );
		$type                 = 'website';
		$summary              = description( $subject );
	}
	$image = screen_image();

	$tags = array(
		array( 'property', 'og:type', $type ),
		array( 'property', 'og:site_name', (string) get_bloginfo( 'name' ) ),
		array( 'property', 'og:title', $title ),
	);
	if ( '' !== $summary ) {
		$tags[] = array( 'property', 'og:description', $summary );
	}
	$tags[] = array( 'property', 'og:url', $url );

	$locale = (string) get_locale();
	if ( preg_match( '/^[a-z]{2,3}_[A-Z]{2}$/', $locale ) ) {
		$tags[] = array( 'property', 'og:locale', $locale );
	}

	if ( $image ) {
		$tags[] = array( 'property', 'og:image', $image['url'] );
		$tags[] = array( 'property', 'og:image:width', (string) $image['width'] );
		$tags[] = array( 'property', 'og:image:height', (string) $image['height'] );
		$tags[] = array( 'property', 'og:image:type', $image['type'] );
		if ( '' !== $image['alt'] ) {
			$tags[] = array( 'property', 'og:image:alt', $image['alt'] );
		}
	}

	if ( $post && 'article' === $type ) {
		$tags[] = array( 'property', 'article:published_time', (string) get_post_time( 'c', true, $post ) );
		$tags[] = array( 'property', 'article:modified_time', (string) get_post_modified_time( 'c', true, $post ) );
	}

	$tags[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );

	/**
	 * The tags printed in the head, as [attribute, name, content].
	 *
	 * @param array                                $tags    Tags.
	 * @param \WP_Post|\WP_Post_Type|\WP_Term|null $subject What the screen is.
	 */
	$tags = (array) apply_filters( 'eightam_og_image_tags', $tags, $subject );

	return array_values(
		array_filter(
			$tags,
			static fn( $tag ): bool => is_array( $tag ) && 3 === count( $tag ) && '' !== (string) $tag[2]
		)
	);
}

/**
 * The share image of the screen being viewed: a post's (its own image, its
 * card, or its featured image), or the card of an archive, a term or the
 * site. Worked out once a request.
 *
 * @return array{url:string,width:int,height:int,type:string,alt:string,card?:bool}|null
 */
function screen_image(): ?array {
	static $image = false;
	if ( false !== $image ) {
		return $image;
	}
	$subject = get_queried_object();
	if ( $subject instanceof \WP_Post ) {
		return $image = post_image( $subject ); // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
	}
	[ $card, $key, $subject ] = screen_card();
	$style                    = style( $card, $subject );
	$drawn                    = Canvas\available() && can_draw( $style ) ? card_image( $key, $card, $style, true ) : null;
	return $image = $drawn ? array( // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
		'url'    => $drawn['url'],
		'width'  => $drawn['width'],
		'height' => $drawn['height'],
		'type'   => $drawn['type'],
		'alt'    => alt( $card ),
		'card'   => true,
	) : null;
}

/**
 * The address of a screen that is not a post.
 *
 * @param mixed $subject What the screen is.
 */
function screen_url( $subject ): string {
	if ( $subject instanceof \WP_Post_Type ) {
		return (string) get_post_type_archive_link( $subject->name );
	}
	if ( $subject instanceof \WP_Term ) {
		$link = get_term_link( $subject );
		return is_string( $link ) ? $link : home_url( '/' );
	}
	return home_url( '/' );
}

/**
 * A line or two saying what the page is: a post's excerpt, or the start of
 * its text, a term's description, a type's, or the site's tagline.
 *
 * @param mixed $subject What the screen is.
 */
function description( $subject ): string {
	$text = '';
	if ( $subject instanceof \WP_Post ) {
		$front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $subject->ID;
		if ( $front ) {
			$text = (string) get_bloginfo( 'description' );
		} elseif ( '' !== trim( $subject->post_excerpt ) ) {
			$text = $subject->post_excerpt;
		} elseif ( '' === $subject->post_password ) {
			// Only the blocks that are words, drawn without running the
			// content filters, which would enqueue a page's scripts.
			$text = excerpt_remove_blocks( strip_shortcodes( $subject->post_content ) );
		}
	} elseif ( $subject instanceof \WP_Term ) {
		$text = $subject->description;
	} elseif ( $subject instanceof \WP_Post_Type ) {
		$text = $subject->description;
	} else {
		$text = (string) get_bloginfo( 'description' );
	}

	// A heading and the paragraph after it are two runs of words, not one.
	$text = (string) preg_replace( '#<br\s*/?>|</(p|h[1-6]|li|dt|dd|div|figcaption|blockquote|td|th|section|header|footer)>#i', ' ', $text );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	$text = wp_trim_words( $text, 32, '…' );

	/**
	 * The description in og:description.
	 *
	 * @param string                               $text    Description.
	 * @param \WP_Post|\WP_Post_Type|\WP_Term|null $subject What the screen is.
	 */
	return trim( (string) apply_filters( 'eightam_og_image_description', $text, $subject ) );
}
