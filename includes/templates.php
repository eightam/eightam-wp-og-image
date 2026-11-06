<?php
/**
 * The templates an administrator chooses from in Settings → Share images,
 * each a way of drawing the brand onto the one layout of
 * includes/template.php:
 *
 * - Spotlight: the brand's darkest colour, light type, the photograph fading
 *   in from the right, the logo on a light tab, a bar of the accents.
 * - Paper: the brand's lightest colour, dark type, the photograph beside it
 *   with a clean edge, the logo as it is, a bar of the accent.
 * - Full bleed: the photograph across the whole card under a dark scrim,
 *   light type over it.
 * - Brand colour: the accent as the background, the photograph beside it.
 *
 * And four bento templates, the card divided into tiles with rounded corners
 * (includes/template.php, draw_bento()): Bento, Mosaic, Magazine and
 * Banner, each a different grid of the same tiles — the title, the
 * photograph, the logo and one or two facts — in a dark, light or brand
 * colour tone.
 *
 * A template turns the settings and the brand into a style; the colours and
 * typefaces an administrator picked win over the brand's.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Templates;

use Eightam\OgImage\Brand;
use Eightam\OgImage\Canvas;
use Eightam\OgImage\Fonts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The templates, as slug => label, description and kind (`classic` or
 * `bento`).
 *
 * @return array<string,array{label:string,description:string,kind:string}>
 */
function all(): array {
	$classic = array_map( static fn( array $t ): array => $t + array( 'kind' => 'classic' ), classic() );
	$bento   = array_map( static fn( array $t ): array => $t + array( 'kind' => 'bento' ), array_map( static fn( array $l ): array => array_intersect_key( $l, array_flip( array( 'label', 'description' ) ) ), layouts() ) );
	return $classic + $bento;
}

/**
 * Whether a template is one of the bento templates.
 *
 * @param string $template Template.
 */
function is_bento( string $template ): bool {
	return isset( layouts()[ $template ] );
}

/**
 * The bento templates: each a grid of named tiles, as
 * Template\draw_bento() takes it. `head` is the title's tile, `photo` the
 * photograph's, `logo` the logo's, `fact1` and `fact2` the facts chosen in
 * the settings.
 *
 * @return array<string,array{label:string,description:string,areas:string[],columns:float[],rows:float[]}>
 */
function layouts(): array {
	return array(
		'bento'    => array(
			'label'       => __( 'Bento', 'eightam-og-image' ),
			'description' => __( 'The title on a large tile; the logo, a fact and the photograph beside it.', 'eightam-og-image' ),
			'areas'       => array( 'head head logo fact1', 'head head photo photo' ),
			'columns'     => array(),
			'rows'        => array(),
		),
		'mosaic'   => array(
			'label'       => __( 'Mosaic', 'eightam-og-image' ),
			'description' => __( 'The photograph down the left; the title, the logo and two facts beside it.', 'eightam-og-image' ),
			'areas'       => array( 'photo photo head head head', 'photo photo head head head', 'photo photo logo fact1 fact2' ),
			'columns'     => array(),
			'rows'        => array( 1, 1, 1.1 ),
		),
		'magazine' => array(
			'label'       => __( 'Magazine', 'eightam-og-image' ),
			'description' => __( 'The title across, the photograph down the right; the logo and two facts below.', 'eightam-og-image' ),
			'areas'       => array( 'head head head photo', 'head head head photo', 'logo fact1 fact2 photo' ),
			'columns'     => array( 1, 1, 1, 1.25 ),
			'rows'        => array( 1, 1, 1.05 ),
		),
		'banner'   => array(
			'label'       => __( 'Banner', 'eightam-og-image' ),
			'description' => __( 'The photograph across the top; the title, the logo and a fact below it.', 'eightam-og-image' ),
			'areas'       => array( 'photo photo photo logo', 'head head head fact1' ),
			'columns'     => array(),
			'rows'        => array( 0.85, 1.15 ),
		),
	);
}

/**
 * The classic templates, as slug => label and description.
 *
 * @return array<string,array{label:string,description:string}>
 */
function classic(): array {
	return array(
		'spotlight' => array(
			'label'       => __( 'Spotlight', 'eightam-og-image' ),
			'description' => __( 'Dark, with the photograph fading in from the right.', 'eightam-og-image' ),
		),
		'paper'     => array(
			'label'       => __( 'Paper', 'eightam-og-image' ),
			'description' => __( 'Light, with the photograph beside the title on a clean edge.', 'eightam-og-image' ),
		),
		'cover'     => array(
			'label'       => __( 'Full bleed', 'eightam-og-image' ),
			'description' => __( 'The photograph across the whole card, the title over it.', 'eightam-og-image' ),
		),
		'bold'      => array(
			'label'       => __( 'Brand colour', 'eightam-og-image' ),
			'description' => __( 'The accent colour as the background.', 'eightam-og-image' ),
		),
	);
}

/**
 * The style of the settings' template, in the brand.
 *
 * @param array<string,mixed> $settings Settings (includes/settings.php).
 * @param array<string,mixed> $brand    Brand (includes/brand.php).
 * @param mixed               $subject  What the card is of: the page's logo
 *                                      can be its own.
 * @return array<string,mixed>
 */
function style( array $settings, array $brand, $subject = null ): array {
	$template = isset( all()[ $settings['template'] ] ) ? (string) $settings['template'] : 'spotlight';
	$c        = (array) $brand['colors'];
	$dark     = Brand\hex( (string) $c['dark'] ) ?: '#161616';
	$light    = Brand\hex( (string) $c['light'] ) ?: '#ffffff';
	$accent   = Brand\hex( (string) $c['accent'] ) ?: '#3858e9';
	$accent2  = Brand\hex( (string) $c['accent2'] );

	// The background each template starts from, unless one was picked.
	$tone       = tone( $settings, $template );
	$background = Brand\hex( (string) $settings['background'] ) ?: match ( $template ) {
		'paper' => $light,
		'bold'  => $accent,
		default => is_bento( $template ) ? match ( $tone ) {
			'light' => Brand\mix( $light, $dark, 0.07 ),
			'brand' => $accent,
			default => $dark,
		} : $dark,
	};
	$on_dark = Brand\is_dark( $background );

	$text = Brand\hex( (string) $settings['text'] ) ?: Brand\readable(
		$background,
		$on_dark ? array( $light, '#ffffff' ) : array( (string) $c['heading'], $dark, (string) $c['text'] )
	);

	// The kicker in the accent where it reads, else in the type's colour.
	$picked = Brand\hex( (string) $settings['accent'] );
	$kicker = 'bold' === $template && '' === $picked
		? Brand\readable( $background, array( $accent2, $text ), 3 )
		: Brand\readable( $background, array_filter( array( $picked ?: $accent, $accent2, Brand\mix( $picked ?: $accent, $on_dark ? '#ffffff' : '#000000', 0.35 ), $text ) ), 3 );
	if ( '' !== $picked && Brand\contrast( $background, $picked ) >= 1.5 ) {
		$kicker = $picked;
	}

	$fonts = fonts( $settings, $brand );
	$style = array(
		'background' => $background,
		'glow'       => array(),
		'title'      => array(
			'font'  => $fonts['title'],
			'color' => $text,
		),
		'kicker'     => array(
			'font'  => $fonts['kicker'],
			'color' => $kicker,
		),
		'subtitle'   => array(
			'font'    => $fonts['subtitle'],
			'color'   => $text,
			'opacity' => 0.8,
		),
		'meta'       => array(
			'font'    => $fonts['meta'],
			'color'   => $text,
			'opacity' => 0.62,
		),
		'bar'        => array(
			'colors' => bar( $settings, $template, $accent, $accent2, $kicker ),
			'height' => 12,
		),
		'photo'      => match ( $template ) {
			'paper' => array(
				'layout' => 'split',
				'width'  => 0.42,
			),
			'bold'  => array(
				'layout' => 'split',
				'width'  => 0.4,
			),
			'cover' => array(
				'layout' => 'cover',
				'scrim'  => $on_dark ? $background : $dark,
			),
			default => array(
				'layout' => 'fade',
				'width'  => 0.36,
				'fade'   => 0.28,
			),
		},
	);

	// A pool of the accent in a dark card's lower corner, for a card with no
	// photograph to look at.
	if ( in_array( $template, array( 'spotlight', 'cover' ), true ) && Brand\contrast( $background, $accent ) > 1.4 ) {
		$style['glow'][] = array(
			'x'       => 0.05,
			'y'       => 1.1,
			'rx'      => 0.75,
			'ry'      => 1.0,
			'color'   => $accent,
			'opacity' => 0.3,
		);
	}
	if ( 'cover' === $template ) {
		// Over a photograph the type is light whatever the background.
		$style['title']['color']    = Brand\hex( (string) $settings['text'] ) ?: '#ffffff';
		$style['subtitle']['color'] = $style['title']['color'];
		$style['meta']['color']     = $style['title']['color'];
	}

	$style['logo'] = logo( $settings, $brand, $template, $background, $light, $fonts['title'], $subject );
	$style['mark'] = mark( $settings, $brand );

	if ( is_bento( $template ) ) {
		$style = bento( $style, $settings, $brand, $template, $tone, $subject );
	}
	return $style;
}

/**
 * The tone of a bento template: dark, light, or the brand's accent.
 *
 * @param array<string,mixed> $settings Settings.
 * @param string              $template Template.
 */
function tone( array $settings, string $template ): string {
	$tone = (string) ( $settings['tone'] ?? 'dark' );
	return is_bento( $template ) && in_array( $tone, array( 'dark', 'light', 'brand' ), true ) ? $tone : 'dark';
}

/**
 * A bento template's style: its grid, and each tile's colours, worked out
 * from the card's background so every tile reads on it.
 *
 * The tiles are a step lighter than a dark background, and a step lighter
 * or darker than a light one; the title's tile is filled with the accent,
 * flat or as a gradient, where light or dark type reads on all of it.
 *
 * @param array<string,mixed> $style    The style so far.
 * @param array<string,mixed> $settings Settings.
 * @param array<string,mixed> $brand    Brand.
 * @param string              $template Template.
 * @param string              $tone     Tone.
 * @param mixed               $subject  What the card is of.
 * @return array<string,mixed>
 */
function bento( array $style, array $settings, array $brand, string $template, string $tone, $subject ): array {
	$c          = (array) $brand['colors'];
	$dark       = Brand\hex( (string) $c['dark'] ) ?: '#161616';
	$light      = Brand\hex( (string) $c['light'] ) ?: '#ffffff';
	$accent     = Brand\hex( (string) $settings['accent'] ) ?: ( Brand\hex( (string) $c['accent'] ) ?: '#3858e9' );
	$accent2    = Brand\hex( (string) $c['accent2'] );
	$background = (string) $style['background'];
	$layout     = layouts()[ $template ];

	$tile = Brand\is_dark( $background )
		? Brand\mix( $background, '#ffffff', 0.08 )
		: ( Brand\luminance( $background ) > 0.9 ? Brand\mix( $background, $dark, 0.05 ) : Brand\mix( $background, '#ffffff', 0.7 ) );
	$on_tile = Brand\hex( (string) $settings['text'] ) ?: type_on( $tile, $brand );

	// The title's tile: the accent, flat or as a gradient, or a tile like
	// the others. On the brand tone the accent is the background already,
	// so the title's tile is the dark colour instead.
	$fill = (string) ( $settings['head_fill'] ?? 'gradient' );
	$base = 'brand' === $tone || Brand\contrast( $background, $accent ) < 1.3 ? ( Brand\is_dark( $background ) ? Brand\mix( $dark, '#ffffff', 0.16 ) : $dark ) : $accent;
	$head = array( 'background' => $base );
	if ( 'tile' === $fill ) {
		$head = array( 'background' => $tile );
	} elseif ( 'gradient' === $fill ) {
		$end = '' !== $accent2 && 'brand' !== $tone && Brand\distance( $accent, $accent2 ) > 40 && $base === $accent ? $accent2 : Brand\mix( $base, Brand\is_dark( $base ) ? '#000000' : '#ffffff', 0.3 );
		$end = Brand\mix( $base, $end, 0.85 );
		if ( worst( array( $base, $end ), type_on( $base, $brand ) ) >= 3 ) {
			$head = array( 'gradient' => array( $base, $end ) );
		}
	}
	$head_ground = $head['gradient'] ?? array( $head['background'] );
	$on_head     = Brand\hex( (string) $settings['text'] ) && worst( $head_ground, (string) $settings['text'] ) >= 3 ? Brand\hex( (string) $settings['text'] ) : type_on( $head_ground[0], $brand, $head_ground );
	$kicker      = (string) $style['kicker']['color'];
	$kicker      = 'tile' === $fill && worst( $head_ground, $kicker ) >= 3 ? $kicker : $on_head;

	// The logo on a tile of the light colour where it would not read on
	// the others, as the classic templates hang it on a tab.
	$logo     = logo( $settings, $brand, $template, $tile, $light, (string) $style['title']['font'], $subject );
	$logo_bg  = '' !== (string) $logo['tab'] ? (string) $logo['tab'] : $tile;
	$logo_box = array(
		'content'    => 'logo',
		'background' => $logo_bg,
		'color'      => type_on( $logo_bg, $brand ),
	);
	$logo['tab'] = '';

	$pattern_accent = Brand\readable( $tile, array_filter( array( $accent, $accent2, $on_tile ) ), 1.6 );
	$plain          = array(
		'background' => $tile,
		'color'      => $on_tile,
		'muted'      => 0.62,
		'font'       => (string) $style['meta']['font'],
		'squares'    => Brand\mix( $tile, $on_tile, 0.12 ),
		'accent'     => $pattern_accent,
	);

	$gap = max( 0, min( 40, (int) ( $settings['gap'] ?? 16 ) ) );

	$style['layout'] = 'bento';
	$style['logo']   = $logo;
	$style['bento']  = array(
		'areas'   => $layout['areas'],
		'columns' => $layout['columns'],
		'rows'    => $layout['rows'],
		'gap'     => $gap,
		'padding' => min( 40, 2 * $gap ),
		'radius'  => max( 0, min( 48, (int) ( $settings['radius'] ?? 24 ) ) ),
		'tiles'   => array(
			'head'  => $head + array(
				'content'        => 'headline',
				'color'          => $on_head,
				'kicker'         => $kicker,
				'kicker_opacity' => $kicker === $on_head ? 0.82 : 1,
				'sizes'          => 'banner' === $template ? array( 72, 30 ) : array( 76, 30 ),
			),
			'logo'  => $logo_box,
			'photo' => $plain + array( 'content' => 'photo' ),
			'fact1' => $plain + array( 'content' => 'fact1' ),
			'fact2' => $plain + array( 'content' => 'fact2' ),
		),
	);
	return $style;
}

/**
 * The type colour that reads on a ground: the brand's light or dark type.
 *
 * @param string   $ground The colour under it.
 * @param array    $brand  Brand.
 * @param string[] $all    Every colour under it, for a gradient: the type
 *                         must read on the worst of them.
 */
function type_on( string $ground, array $brand, array $all = array() ): string {
	$c          = (array) $brand['colors'];
	$candidates = Brand\is_dark( $ground )
		? array( (string) $c['light'], '#ffffff' )
		: array( (string) $c['heading'], (string) $c['dark'], (string) $c['text'], '#111111' );
	$all        = $all ? $all : array( $ground );
	foreach ( $candidates as $color ) {
		$color = Brand\hex( $color );
		if ( '' !== $color && worst( $all, $color ) >= 4.5 ) {
			return $color;
		}
	}
	return worst( $all, '#ffffff' ) >= worst( $all, '#111111' ) ? '#ffffff' : '#111111';
}

/**
 * The lowest contrast a colour has against any of some others.
 *
 * @param string[] $grounds Colours.
 * @param string   $color   The colour on them.
 */
function worst( array $grounds, string $color ): float {
	return min( array_map( static fn( string $g ): float => Brand\contrast( $g, $color ), $grounds ) );
}

/**
 * The typefaces: the title in the heading family, the rest in the body's,
 * each a file GD can draw, or the plugin's own Plus Jakarta Sans.
 *
 * @param array<string,mixed> $settings Settings.
 * @param array<string,mixed> $brand    Brand.
 * @return array{title:string,kicker:string,subtitle:string,meta:string,sources:array<string,string>}
 */
function fonts( array $settings, array $brand ): array {
	$heading = (array) $brand['fonts']['heading'];
	$body    = (array) $brand['fonts']['body'];

	$title_family = '' !== (string) $settings['title_font'] ? (string) $settings['title_font'] : (string) $heading['family'];
	$title_weight = (int) $settings['title_weight'] ?: (int) $heading['weight'];
	$text_family  = '' !== (string) $settings['text_font'] ? (string) $settings['text_font'] : (string) $body['family'];
	$text_weight  = (int) $settings['text_weight'] ?: 500;

	$families = (array) $brand['families'];
	$get      = static function ( string $family, int $weight ) use ( $families ): array {
		$found = Fonts\find( $family, $weight, false, (array) ( $families[ Fonts\clean( $family ) ] ?? array() ) );
		return '' !== $found['file'] ? $found : Fonts\bundled( $weight );
	};

	$title    = $get( $title_family, max( 400, $title_weight ) );
	$kicker   = $get( $text_family, 700 );
	$subtitle = $get( $text_family, $text_weight );
	return array(
		'title'    => $title['file'],
		'kicker'   => $kicker['file'],
		'subtitle' => $subtitle['file'],
		'meta'     => $subtitle['file'],
		'sources'  => array(
			'title' => $title['source'],
			'text'  => $subtitle['source'],
		),
	);
}

/**
 * The bands along the foot of the card.
 *
 * @param array<string,mixed> $settings Settings.
 * @param string              $template Template.
 * @param string              $accent   The brand's accent.
 * @param string              $accent2  Its second accent, or ''.
 * @param string              $kicker   The kicker's colour.
 * @return string[]
 */
function bar( array $settings, string $template, string $accent, string $accent2, string $kicker ): array {
	if ( 'none' === $settings['bar'] ) {
		return array();
	}
	if ( 'custom' === $settings['bar'] ) {
		return array_values( array_filter( array_map( 'Eightam\\OgImage\\Brand\\hex', (array) $settings['bar_colors'] ) ) );
	}
	return match ( $template ) {
		'spotlight' => array_values( array_filter( array( $kicker, $accent2 ) ) ),
		'paper'     => array( $kicker ),
		default     => array(),
	};
}

/**
 * The logo: the one picked, or the site's, as a file GD can read; without
 * one, the site's name set in the title's typeface.
 *
 * @param array<string,mixed> $settings   Settings.
 * @param array<string,mixed> $brand      Brand.
 * @param string              $template   Template.
 * @param string              $background The card's background.
 * @param string              $light      The brand's light colour.
 * @param string              $font       The title's font file.
 * @param mixed               $subject    What the card is of.
 * @return array<string,mixed>
 */
function logo( array $settings, array $brand, string $template, string $background, string $light, string $font, $subject = null ): array {
	$id   = (int) $settings['logo'];
	$id   = 0 === $id ? ( $subject ? Brand\logo( $subject ) : (int) $brand['logo'] ) : $id;
	$file = $id > 0 ? image_file( $id ) : '';
	$size = (string) $settings['logo_size'];

	// A logo is drawn for the site's own background, which is light more
	// often than not: on a dark card it hangs on a tab of the light colour,
	// and on the accent too, which is often the logo's own colour.
	$tab = match ( (string) $settings['logo_tab'] ) {
		'on'    => true,
		'off'   => false,
		default => '' !== $file && ( 'bold' === $template || Brand\is_dark( $background ) ) && ! Brand\is_dark( $light ),
	};
	$tab_color = $tab ? $light : '';
	$on        = $tab ? $light : $background;

	return array(
		'file'    => -1 === (int) $settings['logo'] ? '' : $file,
		'height'  => match ( $size ) {
			'small' => 56,
			'large' => 108,
			default => 80,
		},
		'tab'     => -1 === (int) $settings['logo'] ? '' : $tab_color,
		'padding' => 'large' === $size ? 26 : 22,
		'radius'  => 16,
		'text'    => -1 === (int) $settings['logo'] || '' !== $file ? '' : (string) get_bloginfo( 'name' ),
		'font'    => $font,
		'size'    => match ( $size ) {
			'small' => 26,
			'large' => 42,
			default => 32,
		},
		'color'   => Brand\readable( $on, array( 'cover' === $template ? '#ffffff' : (string) $brand['colors']['heading'], (string) $brand['colors']['dark'] ) ),
	);
}

/**
 * The mark drawn where there is no photograph: none, the site icon, or an
 * image picked.
 *
 * @param array<string,mixed> $settings Settings.
 * @param array<string,mixed> $brand    Brand.
 * @return array<string,mixed>
 */
function mark( array $settings, array $brand ): array {
	$id = (int) $settings['mark'];
	$id = 0 === $id ? (int) $brand['icon'] : $id;
	return array(
		'file'  => $id > 0 ? image_file( $id ) : '',
		'size'  => 420,
		'right' => 0.18,
		'top'   => -0.18,
	);
}

/**
 * An image of the media library as a file GD can read. An SVG is drawn to a
 * PNG once with Imagick, where it can; otherwise it is left out.
 *
 * @param int $id Attachment ID.
 */
function image_file( int $id ): string {
	$file = (string) get_attached_file( $id );
	if ( '' === $file || ! is_readable( $file ) ) {
		return '';
	}
	if ( ! preg_match( '/\.svgz?$/i', $file ) ) {
		return null !== Canvas\load( $file ) ? $file : '';
	}
	return svg_png( $file );
}

/**
 * An SVG file as a PNG GD can read, or ''. The PNG an administrator's browser
 * drew of it on the settings page (logo_png()) comes first: a browser draws
 * SVG exactly, CSS, clipping paths and all. Without one, Imagick draws it
 * where its SVG reader is installed, which is good enough for simple files.
 *
 * @param string $file SVG file.
 */
function svg_png( string $file ): string {
	$dir  = \Eightam\OgImage\folder()['dir'] . '/logos';
	$hash = md5_file( $file );
	foreach ( array( $dir . '/' . $hash . '.png', $dir . '/' . $hash . '-imagick.png' ) as $png ) {
		if ( is_readable( $png ) ) {
			return $png;
		}
	}
	$png = $dir . '/' . $hash . '-imagick.png';
	if ( ! class_exists( '\Imagick' ) || ! in_array( 'SVG', \Imagick::queryFormats( 'SVG' ), true ) || ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	try {
		$image = new \Imagick();
		$image->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
		$image->setResolution( 600, 600 );
		$image->readImage( $file );
		$image->setImageFormat( 'png32' );
		$image->scaleImage( 0, 400 );
		$image->writeImage( $png );
		$image->clear();
	} catch ( \Throwable $error ) {
		return '';
	}
	return is_readable( $png ) ? $png : '';
}

/**
 * Keep the PNG a browser drew of an SVG logo: checked, drawn again with GD,
 * so nothing but pixels is kept, and named after the SVG file's content.
 *
 * @param int    $id  The SVG's attachment ID.
 * @param string $png What the browser drew.
 */
function logo_png( int $id, string $png ): bool {
	$file = (string) get_attached_file( $id );
	if ( '' === $file || ! preg_match( '/\.svgz?$/i', $file ) || ! is_readable( $file ) ) {
		return false;
	}
	$info = @getimagesizefromstring( $png ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $info || IMAGETYPE_PNG !== $info[2] || $info[0] > 4000 || $info[1] > 2000 ) {
		return false;
	}
	$image = @imagecreatefromstring( $png ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$dir   = \Eightam\OgImage\folder()['dir'] . '/logos';
	if ( ! $image instanceof \GdImage || ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	imagesavealpha( $image, true );
	return imagepng( $image, $dir . '/' . md5_file( $file ) . '.png', 9 );
}

/**
 * The SVG logos the site may show that no browser has drawn yet, for the
 * settings page to draw.
 *
 * @param int[] $ids Attachment IDs.
 * @return array<int,array{id:int,url:string}>
 */
function svgs_to_draw( array $ids ): array {
	$out = array();
	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		$file = (string) get_attached_file( (int) $id );
		if ( '' !== $file && preg_match( '/\.svgz?$/i', $file ) && is_readable( $file ) && ! is_readable( \Eightam\OgImage\folder()['dir'] . '/logos/' . md5_file( $file ) . '.png' ) ) {
			$out[] = array(
				'id'  => (int) $id,
				'url' => (string) wp_get_attachment_url( (int) $id ),
			);
		}
	}
	return $out;
}
