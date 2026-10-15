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
 * The templates, as slug => label and description.
 *
 * @return array<string,array{label:string,description:string}>
 */
function all(): array {
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
	$background = Brand\hex( (string) $settings['background'] ) ?: match ( $template ) {
		'paper' => $light,
		'bold'  => $accent,
		default => $dark,
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
	return $style;
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
