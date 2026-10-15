<?php
/**
 * The site's brand, read from its theme: the colours, the typefaces and the
 * logo a card is drawn with when nobody has chosen others.
 *
 * - Blocksy keeps its brand in the Customizer: eight palette colours
 *   (`colorPalette`; the first two its accents, the third its text, the
 *   fourth its headings, the last three its light backgrounds) and a family
 *   and weight for the body and each heading (`rootTypography`,
 *   `h1Typography`), Google families by name.
 * - A theme with theme.json keeps it in its global styles, as the Site
 *   Editor changes them: the body's background and text, the link's colour,
 *   the button's, the palette, and the font families with the files of
 *   their faces.
 * - Any other theme has only its logo; the colours are a neutral set.
 *
 * Everything here is a suggestion: Settings → Share images can change any of
 * it, and `eightam_og_image_brand` sees it first.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Brand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's brand.
 *
 * @return array{source:string,label:string,colors:array<string,string>,palette:array<string,string>,fonts:array<string,array<string,mixed>>,families:array<string,array<int,array<string,mixed>>>,logo:int,icon:int}
 */
function get(): array {
	static $brand = null;
	if ( null !== $brand ) {
		return $brand;
	}

	if ( is_blocksy() ) {
		$brand = blocksy();
	} elseif ( function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json() ) {
		$brand = theme_json();
	} else {
		$brand = neutral();
	}
	$brand['logo'] = (int) get_theme_mod( 'custom_logo', 0 );
	$brand['icon'] = (int) get_option( 'site_icon', 0 );

	/**
	 * The brand read from the theme: source, label, colors (background,
	 * text, heading, accent, accent2, light, dark), palette (hex => name),
	 * fonts (heading and body: family, weight, italic, sources), families
	 * (name => sources), logo and icon (attachment IDs).
	 *
	 * @param array $brand The brand.
	 */
	$brand = (array) apply_filters( 'eightam_og_image_brand', $brand );
	return $brand;
}

/**
 * Whether the theme is Blocksy, or a child of it.
 */
function is_blocksy(): bool {
	return 'blocksy' === get_template();
}

/**
 * Blocksy's brand, from the Customizer, over Blocksy's own defaults.
 *
 * @return array<string,mixed>
 */
function blocksy(): array {
	$defaults = array(
		'color1' => '#2872fa',
		'color2' => '#1559ed',
		'color3' => '#3a4f66',
		'color4' => '#192a3d',
		'color5' => '#e1e8ed',
		'color6' => '#f2f5f7',
		'color7' => '#fafbfc',
		'color8' => '#ffffff',
	);
	$stored   = (array) get_theme_mod( 'colorPalette', array() );
	$colors   = array();
	foreach ( $defaults as $slot => $fallback ) {
		$value           = is_array( $stored[ $slot ] ?? null ) ? (string) ( $stored[ $slot ]['color'] ?? '' ) : '';
		$colors[ $slot ] = hex( $value ) ?: $fallback;
	}

	$palette = array();
	foreach ( $colors as $slot => $value ) {
		/* translators: %d: the number of a Blocksy palette colour, 1 to 8. */
		$palette[ $value ] = $palette[ $value ] ?? sprintf( __( 'Palette colour %d', 'eightam-og-image' ), (int) substr( $slot, 5 ) );
	}

	$root    = typography( (array) get_theme_mod( 'rootTypography', array() ), 'System Default', 400 );
	$heading = typography( (array) get_theme_mod( 'h1Typography', array() ), $root['family'], 700 );

	return array(
		'source'   => 'blocksy',
		'label'    => 'Blocksy',
		'colors'   => array(
			'background' => $colors['color8'],
			'text'       => $colors['color3'],
			'heading'    => $colors['color4'],
			'accent'     => $colors['color1'],
			'accent2'    => $colors['color2'],
			'light'      => $colors['color6'],
			'dark'       => darkest( array( $colors['color4'], $colors['color3'] ) ),
		),
		'palette'  => $palette,
		'fonts'    => array(
			'heading' => $heading,
			'body'    => $root,
		),
		'families' => array_fill_keys( array_filter( array_unique( array( $heading['family'], $root['family'] ) ) ), array() ),
	);
}

/**
 * A Blocksy typography setting as a family and weight. "Default" inherits.
 *
 * @param array<string,mixed> $value    The setting.
 * @param string              $family   The family it inherits.
 * @param int                 $weight   The weight it inherits.
 * @return array{family:string,weight:int,italic:bool,sources:array<int,mixed>}
 */
function typography( array $value, string $family, int $weight ): array {
	$name      = (string) ( $value['family'] ?? 'Default' );
	$variation = (string) ( $value['variation'] ?? 'Default' );
	$italic    = false;
	if ( preg_match( '/^([ni])([1-9])$/', $variation, $match ) ) {
		$italic = 'i' === $match[1];
		$weight = 100 * (int) $match[2];
	}
	return array(
		'family'  => 'Default' === $name ? $family : $name,
		'weight'  => $weight,
		'italic'  => $italic,
		'sources' => array(),
	);
}

/**
 * The brand of a theme with theme.json, from its global styles as the Site
 * Editor leaves them.
 *
 * @return array<string,mixed>
 */
function theme_json(): array {
	$settings = (array) wp_get_global_settings();
	$styles   = (array) wp_get_global_styles();

	// The palette: the user's colours over the theme's; core's only where
	// the theme has none.
	$sets    = (array) ( $settings['color']['palette'] ?? array() );
	$entries = array_merge( (array) ( $sets['theme'] ?? array() ), (array) ( $sets['custom'] ?? array() ) );
	if ( ! $entries ) {
		$entries = (array) ( $sets['default'] ?? array() );
	}
	$by_slug = array();
	$palette = array();
	foreach ( $entries as $entry ) {
		$value = hex( (string) ( $entry['color'] ?? '' ) );
		if ( '' === $value ) {
			continue;
		}
		$by_slug[ (string) ( $entry['slug'] ?? '' ) ] = $value;
		$palette[ $value ]                            = (string) ( $entry['name'] ?? $entry['slug'] ?? $value );
	}
	$color = static fn( $value ): string => resolve( $value, $by_slug, 'color' );
	$slug  = static function ( array $slugs ) use ( $by_slug ): string {
		foreach ( $slugs as $s ) {
			if ( isset( $by_slug[ $s ] ) ) {
				return $by_slug[ $s ];
			}
		}
		return '';
	};

	$background = $color( $styles['color']['background'] ?? '' ) ?: $slug( array( 'base', 'background', 'white', 'light' ) ) ?: '#ffffff';
	$text       = $color( $styles['color']['text'] ?? '' ) ?: $slug( array( 'contrast', 'foreground', 'black', 'dark' ) ) ?: '#111111';
	$heading    = $color( $styles['elements']['heading']['color']['text'] ?? '' ) ?: $text;

	// The accent: the link's colour where it is not the text's, else the
	// button's, else a palette colour named for it, else the most colourful.
	$accents = array_filter(
		array(
			$color( $styles['elements']['link']['color']['text'] ?? '' ),
			$color( $styles['elements']['button']['color']['background'] ?? '' ),
			$slug( array( 'primary', 'accent', 'accent-1', 'vivid-cyan-blue', 'secondary', 'accent-2', 'tertiary', 'accent-3' ) ),
		),
		static fn( string $hex ): bool => '' !== $hex && $hex !== $text && $hex !== $background
	);
	$vivid   = array_keys( $palette );
	usort( $vivid, static fn( string $a, string $b ): int => saturation( $b ) <=> saturation( $a ) );
	$accent  = (string) ( array_values( $accents )[0] ?? '' );
	if ( '' === $accent || saturation( $accent ) < 0.15 ) {
		$accent = saturation( (string) ( $vivid[0] ?? '' ) ) >= 0.25 ? (string) $vivid[0] : ( '' !== $accent ? $accent : $text );
	}
	$accent2 = '';
	foreach ( $vivid as $candidate ) {
		if ( $candidate !== $accent && saturation( $candidate ) >= 0.25 && distance( $candidate, $accent ) > 60 ) {
			$accent2 = $candidate;
			break;
		}
	}

	// The typefaces: their families, with the files of their faces.
	$families = array();
	$by_font  = array();
	$fsets    = (array) ( $settings['typography']['fontFamilies'] ?? array() );
	foreach ( array_merge( (array) ( $fsets['theme'] ?? array() ), (array) ( $fsets['custom'] ?? array() ) ) as $entry ) {
		$name = \Eightam\OgImage\Fonts\clean( (string) ( $entry['fontFamily'] ?? '' ) ) ?: \Eightam\OgImage\Fonts\clean( (string) ( $entry['name'] ?? '' ) );
		if ( '' === $name ) {
			continue;
		}
		$sources = array();
		foreach ( (array) ( $entry['fontFace'] ?? array() ) as $face ) {
			foreach ( (array) ( $face['src'] ?? array() ) as $src ) {
				$file = font_path( (string) $src );
				if ( '' !== $file ) {
					$sources[] = array(
						'file'   => $file,
						'weight' => (string) ( $face['fontWeight'] ?? '400' ),
						'style'  => (string) ( $face['fontStyle'] ?? 'normal' ),
					);
				}
			}
		}
		$families[ $name ]                             = array_merge( $families[ $name ] ?? array(), $sources );
		$by_font[ (string) ( $entry['slug'] ?? '' ) ] = $name;
	}
	$family = static function ( $value ) use ( $by_font ): string {
		$value = (string) $value;
		if ( preg_match( '/(?:var:preset\|font-family\||--wp--preset--font-family--)([a-z0-9-]+)/', $value, $match ) ) {
			return $by_font[ $match[1] ] ?? '';
		}
		return \Eightam\OgImage\Fonts\clean( $value );
	};

	$body_family    = $family( $styles['typography']['fontFamily'] ?? '' );
	$heading_family = $family( $styles['elements']['heading']['typography']['fontFamily'] ?? '' ) ?: $family( $styles['elements']['h1']['typography']['fontFamily'] ?? '' ) ?: $body_family;
	$heading_weight = (int) ( $styles['elements']['h1']['typography']['fontWeight'] ?? $styles['elements']['heading']['typography']['fontWeight'] ?? 0 );
	$body_weight    = (int) ( $styles['typography']['fontWeight'] ?? 0 );

	return array(
		'source'   => 'theme.json',
		'label'    => (string) wp_get_theme()->get( 'Name' ),
		'colors'   => array(
			'background' => $background,
			'text'       => $text,
			'heading'    => $heading,
			'accent'     => $accent,
			'accent2'    => $accent2,
			'light'      => lightest( array( $background, $slug( array( 'base-2', 'accent-5', 'tertiary', 'light' ) ) ) ),
			'dark'       => darkest( array( $heading, $text, $slug( array( 'contrast', 'contrast-2', 'black', 'dark' ) ) ) ),
		),
		'palette'  => $palette,
		'fonts'    => array(
			'heading' => array(
				'family'  => $heading_family,
				'weight'  => $heading_weight ?: 700,
				'italic'  => false,
				'sources' => $families[ $heading_family ] ?? array(),
			),
			'body'    => array(
				'family'  => $body_family,
				'weight'  => $body_weight ?: 400,
				'italic'  => false,
				'sources' => $families[ $body_family ] ?? array(),
			),
		),
		'families' => $families,
	);
}

/**
 * A neutral brand, for a theme that keeps none where it can be read.
 *
 * @return array<string,mixed>
 */
function neutral(): array {
	return array(
		'source'   => 'none',
		'label'    => (string) wp_get_theme()->get( 'Name' ),
		'colors'   => array(
			'background' => '#ffffff',
			'text'       => '#1e1e1e',
			'heading'    => '#1e1e1e',
			'accent'     => '#3858e9',
			'accent2'    => '',
			'light'      => '#f6f6f4',
			'dark'       => '#161616',
		),
		'palette'  => array(),
		'fonts'    => array(
			'heading' => array(
				'family'  => '',
				'weight'  => 700,
				'italic'  => false,
				'sources' => array(),
			),
			'body'    => array(
				'family'  => '',
				'weight'  => 400,
				'italic'  => false,
				'sources' => array(),
			),
		),
		'families' => array(),
	);
}

/**
 * A theme.json value as a colour: a hex, or a preset named in either of the
 * two ways theme.json names one.
 *
 * @param mixed                $value   The value.
 * @param array<string,string> $presets Slug => hex.
 * @param string               $kind    The preset's kind.
 */
function resolve( $value, array $presets, string $kind ): string {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( preg_match( '/(?:var:preset\|' . $kind . '\||--wp--preset--' . $kind . '--)([a-z0-9-]+)/', $value, $match ) ) {
		return $presets[ $match[1] ] ?? '';
	}
	return hex( $value );
}

/**
 * The file behind a font face's src: a theme's `file:./…`, or an address in
 * this site's wp-content. '' for anything else.
 *
 * @param string $src The src.
 */
function font_path( string $src ): string {
	if ( str_starts_with( $src, 'file:./' ) ) {
		$file = get_theme_file_path( substr( $src, 7 ) );
		return is_readable( $file ) ? $file : '';
	}
	$content = set_url_scheme( content_url(), 'http' );
	$src     = set_url_scheme( (string) strtok( $src, '?#' ), 'http' );
	if ( ! str_starts_with( $src, $content . '/' ) ) {
		return '';
	}
	$relative = rawurldecode( substr( $src, strlen( $content ) ) );
	if ( str_contains( $relative, '..' ) ) {
		return '';
	}
	$file = WP_CONTENT_DIR . $relative;
	return is_readable( $file ) ? $file : '';
}

/**
 * A colour as #rrggbb, from #rgb, #rrggbb, #rrggbbaa or rgb(); '' for what
 * is not one.
 *
 * @param string $value Colour.
 */
function hex( string $value ): string {
	$value = strtolower( trim( $value ) );
	if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $m ) ) {
		return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	if ( preg_match( '/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/', $value, $m ) ) {
		return '#' . $m[1];
	}
	if ( preg_match( '/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/', $value, $m ) ) {
		return sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
	}
	return '';
}

/**
 * A colour as red, green and blue, 0 to 255.
 *
 * @param string $hex #rrggbb.
 * @return array{0:int,1:int,2:int}
 */
function rgb( string $hex ): array {
	$hex = ltrim( hex( $hex ) ?: '#000000', '#' );
	return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Relative luminance, as WCAG works it out.
 *
 * @param string $hex Colour.
 */
function luminance( string $hex ): float {
	$channel = static function ( int $value ): float {
		$c = $value / 255;
		return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
	};
	[ $r, $g, $b ] = rgb( $hex );
	return 0.2126 * $channel( $r ) + 0.7152 * $channel( $g ) + 0.0722 * $channel( $b );
}

/**
 * The contrast between two colours, 1 to 21.
 *
 * @param string $a Colour.
 * @param string $b Colour.
 */
function contrast( string $a, string $b ): float {
	$la = luminance( $a );
	$lb = luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * How colourful a colour is: HSL saturation, 0 to 1, low for near-black and
 * near-white.
 *
 * @param string $hex Colour.
 */
function saturation( string $hex ): float {
	if ( '' === hex( $hex ) ) {
		return 0.0;
	}
	[ $r, $g, $b ] = array_map( static fn( int $v ): float => $v / 255, rgb( $hex ) );
	$max           = max( $r, $g, $b );
	$min           = min( $r, $g, $b );
	$light         = ( $max + $min ) / 2;
	if ( $max === $min ) {
		return 0.0;
	}
	$sat = $light > 0.5 ? ( $max - $min ) / ( 2 - $max - $min ) : ( $max - $min ) / ( $max + $min );
	// A colour almost black or white reads as neither.
	return $sat * min( 1.0, 4 * min( $light, 1 - $light ) );
}

/**
 * How far apart two colours are, roughly as the eye sees it.
 *
 * @param string $a Colour.
 * @param string $b Colour.
 */
function distance( string $a, string $b ): float {
	[ $r1, $g1, $b1 ] = rgb( $a );
	[ $r2, $g2, $b2 ] = rgb( $b );
	return sqrt( 2 * ( $r1 - $r2 ) ** 2 + 4 * ( $g1 - $g2 ) ** 2 + 3 * ( $b1 - $b2 ) ** 2 ) / 3;
}

/**
 * Two colours mixed: `$amount` of the second in the first.
 *
 * @param string $a      Colour.
 * @param string $b      Colour.
 * @param float  $amount 0 to 1.
 */
function mix( string $a, string $b, float $amount ): string {
	$x = rgb( $a );
	$y = rgb( $b );
	return sprintf(
		'#%02x%02x%02x',
		(int) round( $x[0] + ( $y[0] - $x[0] ) * $amount ),
		(int) round( $x[1] + ( $y[1] - $x[1] ) * $amount ),
		(int) round( $x[2] + ( $y[2] - $x[2] ) * $amount )
	);
}

/**
 * The darkest of some colours.
 *
 * @param string[] $colors Colours; empty ones are passed over.
 */
function darkest( array $colors ): string {
	$colors = array_values( array_filter( array_map( __NAMESPACE__ . '\\hex', $colors ) ) );
	usort( $colors, static fn( string $a, string $b ): int => luminance( $a ) <=> luminance( $b ) );
	return $colors[0] ?? '#161616';
}

/**
 * The lightest of some colours.
 *
 * @param string[] $colors Colours; empty ones are passed over.
 */
function lightest( array $colors ): string {
	$colors = array_values( array_filter( array_map( __NAMESPACE__ . '\\hex', $colors ) ) );
	usort( $colors, static fn( string $a, string $b ): int => luminance( $b ) <=> luminance( $a ) );
	return $colors[0] ?? '#ffffff';
}

/**
 * Of some colours, the first that reads on a background with the contrast
 * asked for; else whichever of white and the darkest reads better.
 *
 * @param string   $background Background.
 * @param string[] $colors     Colours, in order of preference.
 * @param float    $ratio      The contrast wanted.
 */
function readable( string $background, array $colors, float $ratio = 4.5 ): string {
	foreach ( $colors as $color ) {
		$color = hex( (string) $color );
		if ( '' !== $color && contrast( $background, $color ) >= $ratio ) {
			return $color;
		}
	}
	return contrast( $background, '#ffffff' ) >= contrast( $background, '#111111' ) ? '#ffffff' : '#111111';
}

/**
 * Whether a colour is dark: white type reads better on it than black.
 *
 * @param string $hex Colour.
 */
function is_dark( string $hex ): bool {
	return contrast( $hex, '#ffffff' ) >= contrast( $hex, '#111111' );
}
