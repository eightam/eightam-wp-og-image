<?php
/**
 * The settings: one option, `eightam_og_image`, written from Settings →
 * Share images (includes/settings-page.php) and read here.
 *
 * A colour or a typeface left empty is the brand's (includes/brand.php); a
 * logo of 0 is the site's, -1 none. What a card says beyond its title — the
 * kicker, the line under it, the foot — is a setting too, applied through
 * `eightam_og_image_card` early, so a site's own filter still has the last
 * word.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'eightam_og_image';

/**
 * The settings, as stored over the defaults; or, while a preview is drawn,
 * the ones it is drawn with.
 *
 * @param array<string,mixed>|null $preview Settings to use until called with
 *                                          an empty array, for a preview.
 * @return array<string,mixed>
 */
function settings( ?array $preview = null ): array {
	static $override = null;
	if ( null !== $preview ) {
		$override = $preview ? sanitize_settings( $preview ) : null;
	}
	if ( null !== $override ) {
		return $override;
	}
	return array_merge( default_settings(), (array) get_option( OPTION, array() ) );
}

/**
 * The settings a fresh install starts with.
 *
 * @return array<string,mixed>
 */
function default_settings(): array {
	return array(
		'template'     => 'spotlight',
		'background'   => '',
		'text'         => '',
		'accent'       => '',
		'bar'          => 'auto',
		'bar_colors'   => array(),
		'title_font'   => '',
		'title_weight' => 0,
		'text_font'    => '',
		'text_weight'  => 0,
		'logo'         => 0,
		'logo_tab'     => 'auto',
		'mark'         => -1,
		'kicker'       => 'none',
		'subtitle'     => 'none',
		'meta'         => 'host',
		'first_image'  => true,
		'post_types'   => array(),
		'google_fonts' => true,
		'print'        => 'auto',
	);
}

/**
 * Settings as they may be stored: every key known, every value one of its
 * kind.
 *
 * @param array<string,mixed> $input Settings from a form or a preview.
 * @return array<string,mixed>
 */
function sanitize_settings( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = default_settings();
	$pick  = static fn( string $key, array $allowed ) => in_array( (string) ( $input[ $key ] ?? '' ), $allowed, true ) ? (string) $input[ $key ] : $out[ $key ];

	$out['template'] = $pick( 'template', array_keys( Templates\all() ) );
	foreach ( array( 'background', 'text', 'accent' ) as $key ) {
		$out[ $key ] = Brand\hex( (string) ( $input[ $key ] ?? '' ) );
	}
	$out['bar']        = $pick( 'bar', array( 'auto', 'none', 'custom' ) );
	$colors            = is_array( $input['bar_colors'] ?? null ) ? $input['bar_colors'] : preg_split( '/[\s,]+/', (string) ( $input['bar_colors'] ?? '' ) );
	$out['bar_colors'] = array_slice( array_values( array_filter( array_map( static fn( $hex ): string => Brand\hex( (string) $hex ), (array) $colors ) ) ), 0, 6 );

	foreach ( array( 'title_font', 'text_font' ) as $key ) {
		$out[ $key ] = Fonts\clean( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ) );
	}
	foreach ( array( 'title_weight', 'text_weight' ) as $key ) {
		$weight      = (int) ( $input[ $key ] ?? 0 );
		$out[ $key ] = $weight >= 100 && $weight <= 900 ? (int) round( $weight / 100 ) * 100 : 0;
	}

	foreach ( array( 'logo', 'mark' ) as $key ) {
		$out[ $key ] = max( -1, (int) ( $input[ $key ] ?? $out[ $key ] ) );
	}
	$out['logo_tab'] = $pick( 'logo_tab', array( 'auto', 'on', 'off' ) );
	$out['kicker']   = $pick( 'kicker', array( 'none', 'type', 'term' ) );
	$out['subtitle'] = $pick( 'subtitle', array( 'none', 'date', 'author', 'author_date', 'excerpt' ) );
	$out['meta']     = $pick( 'meta', array( 'host', 'name', 'none' ) );
	$out['print']    = $pick( 'print', array( 'auto', 'always', 'never' ) );

	// A checkbox left unticked is not sent: absent is off, except in a
	// preview, which sends everything.
	foreach ( array( 'first_image', 'google_fonts' ) as $key ) {
		$out[ $key ] = ! empty( $input[ $key ] ) && 'false' !== $input[ $key ];
	}

	$public            = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
	$out['post_types'] = array_values( array_intersect( array_map( 'strval', (array) ( $input['post_types'] ?? array() ) ), $public ) );
	if ( count( $out['post_types'] ) === count( $public ) ) {
		$out['post_types'] = array();
	}
	return $out;
}

add_action( 'init', __NAMESPACE__ . '\\register_settings' );
/**
 * The option, for options.php to write.
 */
function register_settings(): void {
	register_setting(
		'eightam_og_image',
		OPTION,
		array(
			'type'              => 'object',
			'default'           => default_settings(),
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
			'show_in_rest'      => false,
		)
	);
}

add_filter( 'eightam_og_image_post_types', __NAMESPACE__ . '\\setting_post_types', 5 );
/**
 * The post types the settings give a card: all, unless some were picked.
 *
 * @param string[] $types Post types.
 * @return string[]
 */
function setting_post_types( $types ): array {
	$picked = (array) settings()['post_types'];
	return $picked ? array_values( array_intersect( (array) $types, $picked ) ) : (array) $types;
}

add_filter( 'eightam_og_image_first_image', __NAMESPACE__ . '\\setting_first_image', 5 );
/**
 * No picture from the text, if the settings say so.
 *
 * @param mixed $found The picture found.
 * @return mixed
 */
function setting_first_image( $found ) {
	return settings()['first_image'] ? $found : null;
}

add_filter( 'eightam_og_image_print', __NAMESPACE__ . '\\setting_print', 5 );
/**
 * The tags printed always, never, or unless an SEO plugin prints its own.
 *
 * @param bool $print Whether to print them.
 */
function setting_print( $print ): bool {
	return match ( settings()['print'] ) {
		'always' => true,
		'never'  => false,
		default  => (bool) $print,
	};
}

add_filter( 'eightam_og_image_card', __NAMESPACE__ . '\\setting_card', 5, 2 );
/**
 * What the settings put on a card beside its title.
 *
 * @param array<string,mixed>                  $card    The card.
 * @param \WP_Post|\WP_Post_Type|\WP_Term|null $subject What it is the card of.
 * @return array<string,mixed>
 */
function setting_card( $card, $subject ): array {
	$card     = (array) $card;
	$settings = settings();

	if ( 'name' === $settings['meta'] ) {
		$card['meta'] = (string) get_bloginfo( 'name' );
	} elseif ( 'none' === $settings['meta'] ) {
		$card['meta'] = '';
	}

	if ( ! $subject instanceof \WP_Post ) {
		return $card;
	}
	$front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $subject->ID;
	if ( $front ) {
		return $card;
	}

	if ( '' === (string) ( $card['kicker'] ?? '' ) ) {
		if ( 'type' === $settings['kicker'] && 'page' !== $subject->post_type ) {
			$type           = get_post_type_object( $subject->post_type );
			$card['kicker'] = $type ? (string) $type->labels->singular_name : '';
		} elseif ( 'term' === $settings['kicker'] ) {
			$card['kicker'] = first_term( $subject );
		}
	}

	if ( '' === (string) ( $card['subtitle'] ?? '' ) && 'page' !== $subject->post_type ) {
		$author = (string) get_the_author_meta( 'display_name', (int) $subject->post_author );
		$date   = (string) get_the_date( '', $subject );
		$card['subtitle'] = match ( $settings['subtitle'] ) {
			'date'        => $date,
			'author'      => $author,
			'author_date' => implode( ' · ', array_filter( array( $author, $date ) ) ),
			'excerpt'     => post_password_required( $subject ) ? '' : words( (string) get_the_excerpt( $subject ) ),
			default       => '',
		};
	}
	return $card;
}

/**
 * A post's first term in its first public taxonomy that has one, other than
 * its format: its category, for a post. Yoast's primary category where there
 * is one.
 *
 * @param \WP_Post $post Post.
 */
function first_term( \WP_Post $post ): string {
	foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
		if ( ! $taxonomy->public || 'post_format' === $taxonomy->name ) {
			continue;
		}
		$primary = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_' . $taxonomy->name, true );
		$term    = $primary ? get_term( $primary, $taxonomy->name ) : null;
		if ( ! $term instanceof \WP_Term ) {
			$terms = get_the_terms( $post, $taxonomy->name );
			$term  = is_array( $terms ) ? ( $terms[0] ?? null ) : null;
		}
		if ( $term instanceof \WP_Term && 'uncategorized' !== $term->slug ) {
			return $term->name;
		}
	}
	return '';
}

add_action( 'update_option_' . OPTION, __NAMESPACE__ . '\\settings_changed', 10, 0 );
add_action( 'add_option_' . OPTION, __NAMESPACE__ . '\\settings_changed', 10, 0 );
/**
 * New settings, new fonts perhaps: forget the fonts that were not found, so
 * a family typed in is looked for at once.
 */
function settings_changed(): void {
	$known = (array) get_option( Fonts\OPTION, array() );
	$known = array_filter( $known, static fn( $entry ): bool => '' !== (string) ( $entry['file'] ?? '' ) );
	update_option( Fonts\OPTION, $known, false );
}
