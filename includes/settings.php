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
		'logo_size'    => 'medium',
		'logo_tab'     => 'auto',
		'mark'         => -1,
		'tone'         => 'dark',
		'head_fill'    => 'gradient',
		'gap'          => 16,
		'radius'       => 24,
		'fact1'        => 'date',
		'fact1_field'  => '',
		'fact1_label'  => '',
		'fact2'        => 'term',
		'fact2_field'  => '',
		'fact2_label'  => '',
		'kicker'       => 'none',
		'subtitle'     => 'none',
		'meta'         => 'host',
		'first_image'  => true,
		'post_types'   => array(),
		'google_fonts' => true,
		'print'        => 'auto',
		'variants'     => array(),
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
	$out['tone']      = $pick( 'tone', array( 'dark', 'light', 'brand' ) );
	$out['head_fill'] = $pick( 'head_fill', array( 'gradient', 'accent', 'tile' ) );
	$out['gap']       = max( 0, min( 40, (int) ( $input['gap'] ?? $out['gap'] ) ) );
	$out['radius']    = max( 0, min( 48, (int) ( $input['radius'] ?? $out['radius'] ) ) );
	foreach ( array( 'fact1', 'fact2' ) as $key ) {
		$out[ $key ]            = $pick( $key, array_keys( fact_kinds() ) );
		$out[ $key . '_field' ] = substr( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $input[ $key . '_field' ] ?? '' ) ), 0, 64 );
		$out[ $key . '_label' ] = mb_substr( sanitize_text_field( (string) ( $input[ $key . '_label' ] ?? '' ) ), 0, 40 );
	}
	$out['logo_tab']  = $pick( 'logo_tab', array( 'auto', 'on', 'off' ) );
	$out['logo_size'] = $pick( 'logo_size', array( 'small', 'medium', 'large' ) );
	$out['kicker']   = $pick( 'kicker', array( 'none', 'type', 'term' ) );
	$out['subtitle'] = $pick( 'subtitle', array( 'none', 'date', 'author', 'author_date', 'excerpt' ) );
	$out['meta']     = $pick( 'meta', array( 'host', 'name', 'none' ) );
	$out['print']    = $pick( 'print', array( 'auto', 'always', 'never' ) );

	// A checkbox left unticked is not sent: absent is off, except in a
	// preview, which sends everything.
	foreach ( array( 'first_image', 'google_fonts' ) as $key ) {
		$out[ $key ] = ! empty( $input[ $key ] ) && 'false' !== $input[ $key ];
	}

	$out['variants'] = array();
	foreach ( is_array( $input['variants'] ?? null ) ? array_values( $input['variants'] ) : array() as $variant ) {
		$variant = sanitize_variant( (array) $variant, array_keys( Templates\all() ) );
		if ( $variant ) {
			$out['variants'][] = $variant;
		}
	}
	$out['variants'] = array_slice( $out['variants'], 0, 20 );

	$public            = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
	$out['post_types'] = array_values( array_intersect( array_map( 'strval', (array) ( $input['post_types'] ?? array() ) ), $public ) );
	if ( count( $out['post_types'] ) === count( $public ) ) {
		$out['post_types'] = array();
	}
	return $out;
}

/**
 * A variant as it may be stored, or null for one that names nothing.
 *
 * A variant is what a part of the site draws differently: `target` is a page
 * (`page:12`, the page and every page under it) or a kind of post
 * (`type:event`, its posts and its archive); `template`, `logo`, `background`
 * and `accent` replace the settings' where they are set ('' and -2 mean "as
 * the rest of the site").
 *
 * @param array<string,mixed> $variant   A variant from the form.
 * @param string[]            $templates The templates there are.
 * @return array<string,mixed>|null
 */
function sanitize_variant( array $variant, array $templates ): ?array {
	$target = (string) ( $variant['target'] ?? '' );
	if ( ! preg_match( '/^(page:\d+|type:[a-z0-9_-]+)$/', $target ) ) {
		return null;
	}
	$template = (string) ( $variant['template'] ?? '' );
	return array(
		'target'     => $target,
		'template'   => in_array( $template, $templates, true ) ? $template : '',
		'logo'       => max( -2, (int) ( $variant['logo'] ?? -2 ) ),
		'background' => Brand\hex( (string) ( $variant['background'] ?? '' ) ),
		'accent'     => Brand\hex( (string) ( $variant['accent'] ?? '' ) ),
	);
}

/**
 * The settings a card is drawn with: the site's, with the first variant
 * whose target the card is of laid over them.
 *
 * @param mixed $subject What the card is of.
 * @return array<string,mixed>
 */
function settings_for( $subject ): array {
	$settings = settings();
	$variant  = variant_for( $subject, (array) $settings['variants'] );
	if ( $variant ) {
		foreach ( array( 'template', 'background', 'accent' ) as $key ) {
			if ( '' !== $variant[ $key ] ) {
				$settings[ $key ] = $variant[ $key ];
			}
		}
		if ( -2 !== (int) $variant['logo'] ) {
			$settings['logo'] = (int) $variant['logo'];
		}
	}
	$forced = forced_template();
	if ( '' !== $forced ) {
		$settings['template'] = $forced;
	}
	return $settings;
}

/**
 * The variant a card is drawn in, or null.
 *
 * @param mixed                          $subject  What the card is of.
 * @param array<int,array<string,mixed>> $variants Variants, in order.
 * @return array<string,mixed>|null
 */
function variant_for( $subject, array $variants ): ?array {
	$type      = '';
	$ancestors = array();
	if ( $subject instanceof \WP_Post ) {
		$type      = $subject->post_type;
		$ancestors = array_map( 'intval', array_merge( array( $subject->ID ), get_post_ancestors( $subject ) ) );
	} elseif ( $subject instanceof \WP_Post_Type ) {
		$type = $subject->name;
	}
	foreach ( $variants as $variant ) {
		$target = (string) ( $variant['target'] ?? '' );
		if ( str_starts_with( $target, 'page:' ) && in_array( (int) substr( $target, 5 ), $ancestors, true ) ) {
			return $variant;
		}
		if ( '' !== $type && 'type:' . $type === $target ) {
			return $variant;
		}
	}
	return null;
}

/**
 * A template every card is drawn in for now, whatever its variant: the
 * settings page's thumbnails show each template so.
 *
 * @param string|null $template A template to force, '' to stop, or null to
 *                              ask.
 */
function forced_template( ?string $template = null ): string {
	static $forced = '';
	if ( null !== $template ) {
		$forced = $template;
	}
	return $forced;
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
	// The page's address, kept for the facts before the foot is changed.
	$host = (string) ( $card['meta'] ?? '' );

	if ( 'name' === $settings['meta'] ) {
		$card['meta'] = (string) get_bloginfo( 'name' );
	} elseif ( 'none' === $settings['meta'] ) {
		$card['meta'] = '';
	}

	// The facts a bento template shows on its tiles; only for a card drawn
	// in one, so another card is not drawn again when, say, its reading
	// time changes.
	if ( Templates\is_bento( (string) settings_for( $subject )['template'] ) ) {
		$card['facts'] = array_merge( facts( $subject, $settings, $host ), (array) ( $card['facts'] ?? array() ) );
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
 * The facts a bento tile can show, as kind => what the settings call it.
 *
 * @return array<string,string>
 */
function fact_kinds(): array {
	return array(
		'date'    => __( 'The date', 'eightam-og-image' ),
		'author'  => __( 'The author', 'eightam-og-image' ),
		'term'    => __( 'The category', 'eightam-og-image' ),
		'type'    => __( 'The kind of post', 'eightam-og-image' ),
		'reading' => __( 'The reading time', 'eightam-og-image' ),
		'site'    => __( 'The site’s name', 'eightam-og-image' ),
		'host'    => __( 'The address', 'eightam-og-image' ),
		'field'   => __( 'A custom field (ACF or post meta)', 'eightam-og-image' ),
	);
}

/**
 * The two facts of a card, as `fact1` and `fact2` => label and value. A
 * fact the card's page lacks — the date of a page, the category of an
 * archive — is replaced by the address, or the site's name if the other
 * fact is the address.
 *
 * @param mixed               $subject  What the card is of.
 * @param array<string,mixed> $settings Settings.
 * @param string              $host     The card's address.
 * @return array<string,array{label:string,value:string}>
 */
function facts( $subject, array $settings, string $host ): array {
	$facts = array();
	foreach ( array( 'fact1', 'fact2' ) as $slot ) {
		$facts[ $slot ] = fact( (string) $settings[ $slot ], $subject, $host, (string) $settings[ $slot . '_field' ], (string) $settings[ $slot . '_label' ] );
	}
	foreach ( array( 'fact1' => 'fact2', 'fact2' => 'fact1' ) as $slot => $other ) {
		if ( '' === $facts[ $slot ]['value'] ) {
			$taken          = '' === $host || ( $facts[ $other ]['value'] === $host ) || 'host' === (string) $settings[ $other ];
			$facts[ $slot ] = fact( $taken ? 'site' : 'host', $subject, $host );
		}
	}
	return $facts;
}

/**
 * One fact of a card: what the settings call `$kind`, for what the card is
 * of, with its label. An empty value where the page has no such thing.
 *
 * @param string $kind    A key of fact_kinds().
 * @param mixed  $subject What the card is of.
 * @param string $host    The card's address.
 * @param string $field   With `field`, the field's name.
 * @param string $label   A label to use instead of the fact's own.
 * @return array{label:string,value:string}
 */
function fact( string $kind, $subject, string $host, string $field = '', string $label = '' ): array {
	$post  = $subject instanceof \WP_Post ? $subject : null;
	$dated = $post && 'page' !== $post->post_type;
	$own   = '';
	$value = '';
	switch ( $kind ) {
		case 'date':
			$own   = __( 'Published', 'eightam-og-image' );
			$value = $dated ? (string) get_the_date( '', $post ) : '';
			break;
		case 'author':
			$own   = __( 'Author', 'eightam-og-image' );
			$value = $dated ? (string) get_the_author_meta( 'display_name', (int) $post->post_author ) : '';
			break;
		case 'term':
			$term  = $post ? first_term_of( $post ) : array( '', '' );
			$own   = '' !== $term[1] ? $term[1] : __( 'Category', 'eightam-og-image' );
			$value = $term[0];
			break;
		case 'type':
			$type  = $dated ? get_post_type_object( $post->post_type ) : null;
			$own   = __( 'Type', 'eightam-og-image' );
			$value = $type ? (string) $type->labels->singular_name : '';
			break;
		case 'reading':
			$own = __( 'Reading time', 'eightam-og-image' );
			// A protected post's text stays behind its password, whoever is
			// viewing: the card is a public file.
			if ( $dated && '' === $post->post_password ) {
				$count = (int) preg_match_all( '/[\p{L}\p{N}]+/u', wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
				$min   = max( 1, (int) ceil( $count / 200 ) );
				/* translators: %d: minutes. */
				$value = $count > 0 ? sprintf( _n( '%d min', '%d min', $min, 'eightam-og-image' ), $min ) : '';
			}
			break;
		case 'site':
			$own   = __( 'Website', 'eightam-og-image' );
			$value = (string) get_bloginfo( 'name' );
			break;
		case 'host':
			$own   = __( 'More at', 'eightam-og-image' );
			$value = $host;
			break;
		case 'field':
			[ $own, $value ] = field_value( $field, $subject );
			break;
	}
	return array(
		'label' => '' !== $label ? $label : $own,
		'value' => words( mb_substr( $value, 0, 80 ) ),
	);
}

/**
 * A custom field of a post or a term as a line of text, with its label: ACF's
 * value as ACF formats it where ACF is active, else the meta as it is
 * stored. A list becomes its parts joined by commas; a post, its title.
 *
 * @param string $field   The field's name.
 * @param mixed  $subject A post or a term.
 * @return array{0:string,1:string} Label and value.
 */
function field_value( string $field, $subject ): array {
	if ( '' === $field || ( ! $subject instanceof \WP_Post && ! $subject instanceof \WP_Term ) ) {
		return array( '', '' );
	}
	if ( $subject instanceof \WP_Post && '' !== $subject->post_password ) {
		return array( '', '' );
	}
	$label = '';
	if ( function_exists( 'get_field' ) && function_exists( 'get_field_object' ) ) {
		$value  = get_field( $field, $subject instanceof \WP_Post ? $subject->ID : $subject );
		$object = get_field_object( $field, $subject instanceof \WP_Post ? $subject->ID : $subject, false, false );
		$label  = is_array( $object ) ? (string) ( $object['label'] ?? '' ) : '';
	} else {
		$value = $subject instanceof \WP_Post ? get_post_meta( $subject->ID, $field, true ) : get_term_meta( $subject->term_id, $field, true );
	}
	$flat = static function ( $value ) use ( &$flat ): string {
		if ( $value instanceof \WP_Post ) {
			return (string) get_the_title( $value );
		}
		if ( $value instanceof \WP_Term ) {
			return $value->name;
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['label'] ) && is_scalar( $value['label'] ) ) {
				return (string) $value['label'];
			}
			return implode( ', ', array_filter( array_map( $flat, $value ), 'strlen' ) );
		}
		return is_scalar( $value ) && ! is_bool( $value ) ? (string) $value : '';
	};
	return array( $label, wp_strip_all_tags( $flat( $value ) ) );
}

/**
 * A post's first term in its first public taxonomy that has one, other than
 * its format: its category, for a post. Yoast's primary category where there
 * is one.
 *
 * @param \WP_Post $post Post.
 */
function first_term( \WP_Post $post ): string {
	return first_term_of( $post )[0];
}

/**
 * A post's first term, as first_term() finds it, and its taxonomy's name for
 * one term, such as Category.
 *
 * @param \WP_Post $post Post.
 * @return array{0:string,1:string}
 */
function first_term_of( \WP_Post $post ): array {
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
			return array( $term->name, (string) $taxonomy->labels->singular_name );
		}
	}
	return array( '', '' );
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
