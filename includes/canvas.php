<?php
/**
 * Drawing: what the template draws with, in GD and nothing else.
 *
 * Plain PHP on the GD extension, with no WordPress in it, so the card can be
 * drawn and tested anywhere PHP runs. GD is on every server WordPress runs
 * on; Imagick is not, and nothing here needs it.
 *
 * Lengths are pixels throughout. GD takes type sizes in points at 96 dpi, so
 * a size in pixels is three quarters of that in points; text() and the
 * measuring functions convert, and nothing else has to know.
 *
 * GD kerns from a font's `kern` table and never from GPOS, and it draws a
 * variable font at its default instance only. A font handed to the card is
 * therefore a static TrueType file with a `kern` table: see the readme.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Canvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this PHP can draw a card: GD with FreeType for the type, and JPEG
 * and PNG for the files.
 */
function available(): bool {
	return function_exists( 'imagettftext' ) && function_exists( 'imagejpeg' ) && function_exists( 'imagepng' ) && function_exists( 'imagecreatetruecolor' );
}

/**
 * A blank canvas, filled.
 *
 * @param int    $width  Width.
 * @param int    $height Height.
 * @param string $hex    Fill, as #rrggbb.
 */
function create( int $width, int $height, string $hex ): \GdImage {
	$image = imagecreatetruecolor( $width, $height );
	imagealphablending( $image, false );
	imagefilledrectangle( $image, 0, 0, $width - 1, $height - 1, color( $image, $hex ) );
	imagealphablending( $image, true );
	return $image;
}

/**
 * A colour as red, green and blue. Takes #rgb and #rrggbb; anything else is
 * black.
 *
 * @param string $hex Colour.
 * @return array{0:int,1:int,2:int}
 */
function rgb( string $hex ): array {
	$hex = ltrim( trim( $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
		return array( 0, 0, 0 );
	}
	return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * A colour of an image, with an opacity from 0 to 1.
 *
 * @param \GdImage $image   Image.
 * @param string   $hex     Colour.
 * @param float    $opacity Opacity.
 */
function color( \GdImage $image, string $hex, float $opacity = 1.0 ): int {
	[ $r, $g, $b ] = rgb( $hex );
	$alpha         = (int) round( 127 * ( 1 - max( 0.0, min( 1.0, $opacity ) ) ) );
	return (int) imagecolorallocatealpha( $image, $r, $g, $b, $alpha );
}

/**
 * An image file as a true-colour image the right way up, or null for a file
 * GD cannot read.
 *
 * A photograph straight from a camera can be stored on its side with an EXIF
 * note saying so; WordPress turns its sizes upright, but not the original.
 *
 * @param string $file Path.
 */
function load( string $file ): ?\GdImage {
	$info = is_readable( $file ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $info ) {
		return null;
	}

	$image = false;
	switch ( $info[2] ) {
		case IMAGETYPE_JPEG:
			$image = @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			break;
		case IMAGETYPE_PNG:
			$image = @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			break;
		case IMAGETYPE_GIF:
			$image = @imagecreatefromgif( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			break;
		case IMAGETYPE_WEBP:
			$image = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			break;
		default:
			if ( defined( 'IMAGETYPE_AVIF' ) && IMAGETYPE_AVIF === $info[2] && function_exists( 'imagecreatefromavif' ) ) {
				$image = @imagecreatefromavif( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
	}
	if ( ! $image instanceof \GdImage ) {
		return null;
	}

	if ( ! imageistruecolor( $image ) ) {
		imagepalettetotruecolor( $image );
	}
	imagesavealpha( $image, true );
	imagealphablending( $image, true );

	return IMAGETYPE_JPEG === $info[2] ? upright( $image, $file ) : $image;
}

/**
 * A JPEG turned the way its EXIF orientation says.
 *
 * @param \GdImage $image Image.
 * @param string   $file  Its file.
 */
function upright( \GdImage $image, string $file ): \GdImage {
	if ( ! function_exists( 'exif_read_data' ) ) {
		return $image;
	}
	$exif        = @exif_read_data( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$orientation = is_array( $exif ) ? (int) ( $exif['Orientation'] ?? 1 ) : 1;

	if ( in_array( $orientation, array( 2, 4, 5, 7 ), true ) ) {
		imageflip( $image, IMG_FLIP_HORIZONTAL );
	}
	$angle = array(
		3 => 180,
		4 => 180,
		5 => 90,
		6 => 270,
		7 => 270,
		8 => 90,
	)[ $orientation ] ?? 0;
	if ( $angle ) {
		$turned = imagerotate( $image, $angle, 0 );
		if ( $turned instanceof \GdImage ) {
			$image = $turned;
		}
	}
	return $image;
}

/**
 * A picture scaled to cover a box and cropped to it, the way CSS draws an
 * image with `object-fit: cover`.
 *
 * The position is `object-position` in fractions: 0.5, 0.5 keeps the middle,
 * 0.3, 0.2 keeps the point 30% across and 20% down at 30% across and 20% down
 * the box. A focal point written for the site's CSS crops the card the same
 * way.
 *
 * @param \GdImage $canvas Canvas.
 * @param \GdImage $source Picture.
 * @param int      $x      Box left.
 * @param int      $y      Box top.
 * @param int      $width  Box width.
 * @param int      $height Box height.
 * @param float    $fx     Horizontal position, 0 to 1.
 * @param float    $fy     Vertical position, 0 to 1.
 */
function cover( \GdImage $canvas, \GdImage $source, int $x, int $y, int $width, int $height, float $fx = 0.5, float $fy = 0.5 ): void {
	$sw    = imagesx( $source );
	$sh    = imagesy( $source );
	$scale = max( $width / $sw, $height / $sh );
	$cw    = min( $sw, (int) round( $width / $scale ) );
	$ch    = min( $sh, (int) round( $height / $scale ) );
	$cx    = (int) round( ( $sw - $cw ) * max( 0.0, min( 1.0, $fx ) ) );
	$cy    = (int) round( ( $sh - $ch ) * max( 0.0, min( 1.0, $fy ) ) );

	imagecopyresampled( $canvas, $source, $x, $y, $cx, $cy, $width, $height, $cw, $ch );
}

/**
 * A picture scaled into a box, whole, with its alpha: a logo.
 *
 * @param \GdImage $canvas Canvas.
 * @param \GdImage $source Picture.
 * @param int      $x      Left.
 * @param int      $y      Top.
 * @param int      $width  Width.
 * @param int      $height Height.
 */
function place( \GdImage $canvas, \GdImage $source, int $x, int $y, int $width, int $height ): void {
	imagealphablending( $canvas, true );
	imagecopyresampled( $canvas, $source, $x, $y, 0, 0, $width, $height, imagesx( $source ), imagesy( $source ) );
}

/**
 * A canvas of one colour with soft pools of other colours on it: elliptical
 * radial gradients, each from its colour at its centre to nothing at `stop`
 * of its radius, as CSS draws `radial-gradient(rx ry at x y, colour,
 * transparent stop)`.
 *
 * Worked out at a tenth of the size and scaled up, which is smooth at any
 * radius and costs a hundredth of the pixels.
 *
 * @param int                            $width  Width.
 * @param int                            $height Height.
 * @param string                         $hex    The colour under the pools.
 * @param array<int,array<string,mixed>> $glows  Pools: x, y, rx, ry in
 *                                                pixels, color, opacity, stop.
 */
function backdrop( int $width, int $height, string $hex, array $glows ): \GdImage {
	if ( ! $glows ) {
		return create( $width, $height, $hex );
	}

	$step  = 10;
	$sw    = (int) ceil( $width / $step ) + 1;
	$sh    = (int) ceil( $height / $step ) + 1;
	$small = imagecreatetruecolor( $sw, $sh );
	$base  = rgb( $hex );
	$pools = array();
	foreach ( $glows as $glow ) {
		$pools[] = array(
			'x'       => (float) ( $glow['x'] ?? 0 ),
			'y'       => (float) ( $glow['y'] ?? 0 ),
			'rx'      => max( 1.0, (float) ( $glow['rx'] ?? 1 ) ),
			'ry'      => max( 1.0, (float) ( $glow['ry'] ?? 1 ) ),
			'rgb'     => rgb( (string) ( $glow['color'] ?? '#ffffff' ) ),
			'opacity' => max( 0.0, min( 1.0, (float) ( $glow['opacity'] ?? 0.1 ) ) ),
			'stop'    => max( 0.01, (float) ( $glow['stop'] ?? 1 ) ),
		);
	}

	for ( $j = 0; $j < $sh; $j++ ) {
		for ( $i = 0; $i < $sw; $i++ ) {
			$c = $base;
			foreach ( $pools as $pool ) {
				$d = sqrt( ( ( $i * $step - $pool['x'] ) / $pool['rx'] ) ** 2 + ( ( $j * $step - $pool['y'] ) / $pool['ry'] ) ** 2 );
				$a = $pool['opacity'] * max( 0.0, 1 - $d / $pool['stop'] );
				if ( $a > 0 ) {
					$c = array(
						$c[0] + ( $pool['rgb'][0] - $c[0] ) * $a,
						$c[1] + ( $pool['rgb'][1] - $c[1] ) * $a,
						$c[2] + ( $pool['rgb'][2] - $c[2] ) * $a,
					);
				}
			}
			imagesetpixel( $small, $i, $j, imagecolorallocate( $small, (int) round( $c[0] ), (int) round( $c[1] ), (int) round( $c[2] ) ) );
		}
	}

	$large  = imagescale( $small, $sw * $step, $sh * $step, IMG_BILINEAR_FIXED );
	$canvas = imagecreatetruecolor( $width, $height );
	imagealphablending( $canvas, false );
	if ( $large instanceof \GdImage ) {
		imagecopy( $canvas, $large, 0, 0, 0, 0, $width, $height );
	} else {
		imagefilledrectangle( $canvas, 0, 0, $width - 1, $height - 1, color( $canvas, $hex ) );
	}
	imagealphablending( $canvas, true );
	return $canvas;
}

/**
 * A rectangle with rounded corners, smooth at the edges: drawn four times the
 * size and scaled down, since GD draws no smooth shapes of its own.
 *
 * @param \GdImage       $canvas Canvas.
 * @param int            $x      Left.
 * @param int            $y      Top.
 * @param int            $width  Width.
 * @param int            $height Height.
 * @param array<int,int> $radii  Top left, top right, bottom right, bottom left.
 * @param string         $hex    Colour.
 */
function rounded( \GdImage $canvas, int $x, int $y, int $width, int $height, array $radii, string $hex ): void {
	if ( $width < 1 || $height < 1 ) {
		return;
	}
	$s     = 4;
	$shape = imagecreatetruecolor( $width * $s, $height * $s );
	imagealphablending( $shape, false );
	imagesavealpha( $shape, true );
	imagefilledrectangle( $shape, 0, 0, $width * $s - 1, $height * $s - 1, imagecolorallocatealpha( $shape, 0, 0, 0, 127 ) );
	$fill = color( $shape, $hex );

	$w     = $width * $s;
	$h     = $height * $s;
	$clear = imagecolorallocatealpha( $shape, 0, 0, 0, 127 );
	$r     = array_map( static fn( $radius ): int => (int) min( max( 0, (int) $radius ) * $s, $w / 2, $h / 2 ), array_pad( array_values( $radii ), 4, 0 ) );

	// The whole rectangle, then each corner cut away and drawn again as the
	// quarter of a circle.
	imagefilledrectangle( $shape, 0, 0, $w - 1, $h - 1, $fill );
	$corners = array(
		array( 0, 0, $r[0], $r[0], $r[0] ),
		array( $w - $r[1], 0, $w - $r[1], $r[1], $r[1] ),
		array( $w - $r[2], $h - $r[2], $w - $r[2], $h - $r[2], $r[2] ),
		array( 0, $h - $r[3], $r[3], $h - $r[3], $r[3] ),
	);
	foreach ( $corners as [ $left, $top, $cx, $cy, $radius ] ) {
		if ( $radius > 0 ) {
			imagefilledrectangle( $shape, $left, $top, $left + $radius - 1, $top + $radius - 1, $clear );
			imagefilledellipse( $shape, $cx, $cy, $radius * 2, $radius * 2, $fill );
		}
	}

	imagealphablending( $canvas, true );
	imagecopyresampled( $canvas, $shape, $x, $y, 0, 0, $width, $height, $w, $h );
}

/**
 * Text as GD takes it: UTF-8 with every ampersand written as an entity,
 * since GD reads `&#8217;` and `&amp;` in a string as the characters they
 * name.
 *
 * @param string $text Text.
 */
function literal( string $text ): string {
	return str_replace( '&', '&#38;', $text );
}

/**
 * Where text reaches from its origin: the left and right edges of its ink and
 * its advance, the width it takes up before whatever follows it.
 *
 * The advance is measured against a capital H set after the text, since the
 * box GD reports ends at the last glyph's ink and not at its advance.
 *
 * @param string $font Font file.
 * @param float  $px   Size in pixels.
 * @param string $text Text.
 * @return array{left:int,right:int,advance:int}
 */
function measure( string $font, float $px, string $text ): array {
	static $cache = array();
	$key          = $font . '|' . $px . '|' . $text;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	if ( '' === $text ) {
		return array(
			'left'    => 0,
			'right'   => 0,
			'advance' => 0,
		);
	}

	$box     = imagettfbbox( $px * 0.75, 0, $font, literal( $text ) );
	$with    = imagettfbbox( $px * 0.75, 0, $font, literal( $text . 'H' ) );
	$h       = imagettfbbox( $px * 0.75, 0, $font, 'H' );
	$measure = array(
		'left'    => $box ? (int) min( $box[0], $box[6] ) : 0,
		'right'   => $box ? (int) max( $box[2], $box[4] ) : 0,
		'advance' => ( $with && $h ) ? (int) ( $with[2] - $h[2] ) : 0,
	);

	if ( count( $cache ) > 2000 ) {
		$cache = array();
	}
	$cache[ $key ] = $measure;
	return $measure;
}

/**
 * The width of a line of text, from its origin to the end of its ink.
 *
 * @param string $font Font file.
 * @param float  $px   Size in pixels.
 * @param string $text Text.
 */
function width( string $font, float $px, string $text ): int {
	return measure( $font, $px, $text )['right'];
}

/**
 * Text broken into lines no wider than a width, at spaces.
 *
 * A word wider than the line on its own gets a line to itself and overflows
 * it; the template tries a smaller size before it lets that stand.
 *
 * @param string $font  Font file.
 * @param float  $px    Size in pixels.
 * @param string $text  Text.
 * @param int    $width Line width.
 * @return string[]
 */
function wrap( string $font, float $px, string $text, int $width ): array {
	$words = preg_split( '/[ \t\r\n]+/u', trim( $text ) );
	$words = is_array( $words ) ? $words : array();
	$lines = array();
	$line  = '';
	foreach ( $words as $word ) {
		if ( '' === $word ) {
			continue;
		}
		$try = '' === $line ? $word : $line . ' ' . $word;
		if ( '' !== $line && width( $font, $px, $try ) > $width ) {
			$lines[] = $line;
			$line    = $word;
		} else {
			$line = $try;
		}
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}
	return $lines;
}

/**
 * Text broken into as few lines as fit a width, and those lines made as even
 * as they can be — what CSS calls `text-wrap: balance`. A title of two lines
 * then breaks near its middle, rather than leaving one word under a full line.
 *
 * @param string $font  Font file.
 * @param float  $px    Size in pixels.
 * @param string $text  Text.
 * @param int    $width Line width.
 * @return string[]
 */
function balance( string $font, float $px, string $text, int $width ): array {
	$lines = wrap( $font, $px, $text, $width );
	if ( count( $lines ) < 2 ) {
		return $lines;
	}

	$low  = (int) floor( $width / 2 );
	$high = $width;
	$best = $lines;
	while ( $high - $low > 4 ) {
		$mid   = (int) floor( ( $low + $high ) / 2 );
		$try   = wrap( $font, $px, $text, $mid );
		$wider = array_filter( $try, static fn( string $line ): bool => width( $font, $px, $line ) > $mid && str_contains( $line, ' ' ) );
		if ( count( $try ) <= count( $lines ) && ! $wider ) {
			$best = $try;
			$high = $mid;
		} else {
			$low = $mid;
		}
	}
	return $best;
}

/**
 * A line cut short with an ellipsis where it is wider than a width, word by
 * word. With `$more`, the line is the last of several that do not all fit,
 * and ends in an ellipsis whatever its width.
 *
 * @param string $font  Font file.
 * @param float  $px    Size in pixels.
 * @param string $line  The line.
 * @param int    $width Line width.
 * @param bool   $more  Whether text follows that is left out.
 */
function shorten( string $font, float $px, string $line, int $width, bool $more = false ): string {
	if ( ! $more && width( $font, $px, $line ) <= $width ) {
		return $line;
	}
	$words = explode( ' ', $line );
	while ( $words ) {
		$try = rtrim( implode( ' ', $words ), ' ,.;:–—-' ) . '…';
		if ( width( $font, $px, $try ) <= $width || 1 === count( $words ) ) {
			return $try;
		}
		array_pop( $words );
	}
	return '…';
}

/**
 * One line of text, its baseline at y.
 *
 * @param \GdImage $canvas  Canvas.
 * @param string   $font    Font file.
 * @param float    $px      Size in pixels.
 * @param string   $hex     Colour.
 * @param int      $x       Origin.
 * @param int      $y       Baseline.
 * @param string   $text    Text.
 * @param float    $opacity Opacity.
 */
function text( \GdImage $canvas, string $font, float $px, string $hex, int $x, int $y, string $text, float $opacity = 1.0 ): void {
	imagealphablending( $canvas, true );
	imagettftext( $canvas, $px * 0.75, 0, $x, $y, color( $canvas, $hex, $opacity ), $font, literal( $text ) );
}

/**
 * One line of text with space between its letters, as CSS `letter-spacing`
 * sets a kicker. GD has no tracking, so each character is set on its own at
 * the advance of the text before it, kerning included, plus the tracking.
 *
 * @param \GdImage $canvas   Canvas.
 * @param string   $font     Font file.
 * @param float    $px       Size in pixels.
 * @param string   $hex      Colour.
 * @param int      $x        Origin.
 * @param int      $y        Baseline.
 * @param string   $text     Text.
 * @param float    $tracking Space added after each character, in em.
 * @param float    $opacity  Opacity.
 * @return int The width the text took.
 */
function tracked( \GdImage $canvas, string $font, float $px, string $hex, int $x, int $y, string $text, float $tracking, float $opacity = 1.0 ): int {
	$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$chars = is_array( $chars ) ? $chars : array();
	$color = color( $canvas, $hex, $opacity );
	$extra = $tracking * $px;
	$done  = '';
	imagealphablending( $canvas, true );
	foreach ( $chars as $i => $char ) {
		$at = $x + measure( $font, $px, $done )['advance'] + (int) round( $i * $extra );
		if ( ' ' !== $char ) {
			imagettftext( $canvas, $px * 0.75, 0, $at, $y, $color, $font, literal( $char ) );
		}
		$done .= $char;
	}
	return tracked_width( $font, $px, $text, $tracking );
}

/**
 * The width tracked() gives a text.
 *
 * @param string $font     Font file.
 * @param float  $px       Size in pixels.
 * @param string $text     Text.
 * @param float  $tracking Tracking in em.
 */
function tracked_width( string $font, float $px, string $text, float $tracking ): int {
	// Characters, not bytes, and without mbstring, which PHP may lack.
	$count = max( 0, (int) preg_match_all( '/./us', $text ) - 1 );
	return measure( $font, $px, $text )['right'] + (int) round( $count * $tracking * $px );
}

/**
 * Write the canvas to a file: a JPEG for a card with a photograph in it, a
 * PNG for one of flat colour, which a JPEG would blur at the edges of the
 * type.
 *
 * @param \GdImage $canvas Canvas.
 * @param string   $file   Path; its extension says which.
 * @param int      $jpeg   JPEG quality.
 */
function save( \GdImage $canvas, string $file, int $jpeg = 86 ): bool {
	if ( str_ends_with( strtolower( $file ), '.png' ) ) {
		imagesavealpha( $canvas, false );
		return imagepng( $canvas, $file, 6 );
	}
	imageinterlace( $canvas, true );
	return imagejpeg( $canvas, $file, max( 1, min( 100, $jpeg ) ) );
}
