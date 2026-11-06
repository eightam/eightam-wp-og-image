<?php
/**
 * The template: one card, 1200 by 630, laid out from what it says and the
 * style it is drawn in.
 *
 * What a card says is its content: a title, and where there is one a kicker
 * over it, a line under it, a photograph, and the address it links to. How it
 * is drawn is its style: colours, typefaces, the logo, the bar of colour and
 * the mark. A site hands its style over through `eightam_og_image_style`,
 * one per brand if it has several; the layout is this file's and the same for
 * every card, which is what makes a site's shared links look like one set.
 *
 *     ┌──────────┬───────────────────────────────────────────────┐
 *     │   LOGO   │                                  photograph   │
 *     └──────────┘                                 fading in, or │
 *                                                   the mark     │
 *     KICKER                                                     │
 *     Title, as large as it fits,                                │
 *     on up to four lines                                        │
 *     a line under it                                            │
 *     example.org                                                │
 *     ████████████████████ the bar ███████████████████████████████
 *
 * The type sits at the foot of the card and the logo at its head, the way the
 * page it links to begins. The title is set as large as it fits — 88 pixels
 * for a short one, down to 48 for a long one — and balanced over its lines;
 * past four lines at the smallest size it ends in an ellipsis.
 *
 * Plain PHP on GD, like includes/canvas.php: no WordPress in here.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Template;

use Eightam\OgImage\Canvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WIDTH  = 1200;
const HEIGHT = 630;

/**
 * The style every card starts from: dark, with white type, a yellow kicker
 * and no logo. A site's style is merged over it key by key.
 *
 * @return array<string,mixed>
 */
function defaults(): array {
	return array(
		// Space between the edge of the card and the type.
		'margin'     => 64,
		'background' => '#161616',
		// Soft pools of colour on the background: centre (x, y) and radii
		// (rx, ry) as fractions of the card, the colour, its opacity at the
		// centre and where it has faded out, as a fraction of the radius.
		'glow'       => array(),
		'title'      => array(
			'font'  => '',
			'color' => '#ffffff',
			// The largest and the smallest size, in pixels.
			'sizes' => array( 88, 48 ),
			'line'  => 1.04,
			'lines' => 4,
		),
		'kicker'     => array(
			'font'     => '',
			'color'    => '#ffd500',
			'size'     => 17,
			'tracking' => 0.16,
			'upper'    => true,
		),
		'subtitle'   => array(
			'font'    => '',
			'color'   => '#ffffff',
			'opacity' => 0.78,
			'size'    => 30,
		),
		'meta'       => array(
			'font'    => '',
			'color'   => '#ffffff',
			'opacity' => 0.6,
			'size'    => 19,
		),
		// Bands of colour along the foot of the card, as wide as each other.
		'bar'        => array(
			'colors' => array(),
			'height' => 12,
		),
		// The logo, at the head of the card: as it is, or on a tab of colour
		// hanging from the top edge. A second logo can hang beside it on a
		// smaller tab of its own.
		'logo'       => array(
			'file'    => '',
			'height'  => 72,
			'tab'     => '',
			'padding' => 22,
			'radius'  => 18,
			// Without a logo file, these words in its place: the site's name,
			// in `font` at `size` pixels, in `color`.
			'text'    => '',
			'font'    => '',
			'size'    => 30,
			'color'   => '#ffffff',
			'second'  => array(
				'file'    => '',
				'height'  => 24,
				'tab'     => '',
				'padding' => 18,
			),
		),
		// A graphic drawn when there is no photograph, `size` pixels wide, in
		// the top right corner: `right` is how much of its width reaches past
		// the right edge, `top` how far down its top edge is, as a share of
		// its height. Out of the box it hangs off the corner, partly cropped.
		'mark'       => array(
			'file'  => '',
			'size'  => 560,
			'right' => 0.2,
			'top'   => -0.36,
		),
		// How the photograph is drawn: `fade`, reaching back under the type
		// and thinning out into the background; `split`, a clean edge; or
		// `cover`, across the whole card under a scrim the type reads on.
		'photo'      => array(
			'layout' => 'fade',
			// With `cover`, the scrim's colour; the background's if empty.
			'scrim'  => '',
			// The share of the card's width the photograph covers at full
			// strength.
			'width'  => 0.36,
			// With `fade`, how far it reaches back under the type beyond
			// that, thinning out, as a share of the card's width.
			'fade'   => 0.28,
		),
		// `classic`, the layout drawn above; or `bento`, a grid of tiles
		// with rounded corners, laid out as `bento` says.
		'layout'     => 'classic',
		'bento'      => array(
			// Rows of tile names, as CSS `grid-template-areas`: a name that
			// spans cells spans them in the grid. A tile is the rectangle
			// around its cells.
			'areas'   => array(),
			// How wide each column is and how tall each row, as shares (CSS
			// `fr`); equal if empty.
			'columns' => array(),
			'rows'    => array(),
			// Space between tiles, around them, and their corner radius.
			'gap'     => 16,
			'padding' => 32,
			'radius'  => 24,
			// Each tile, by name: `content` says what it shows —
			// `headline` (the kicker, the title and the line under it),
			// `photo` (the photograph; the mark or a pattern without one),
			// `logo`, a fact (a key of the card's `facts`, such as `fact1`:
			// a label over a value), `pattern` (a grid of squares, a few in
			// the accent) or `mark`. `background` fills it, or `gradient`,
			// two colours from top left to bottom right; `color` is its
			// type's, `accent` the pattern's highlight.
			'tiles'   => array(),
		),
	);
}

/**
 * A site's style over the defaults.
 *
 * @param array<string,mixed> $style A site's style.
 * @return array<string,mixed>
 */
function style( array $style ): array {
	return merge( defaults(), $style );
}

/**
 * One array over another, key by key and level by level. A list — the bar's
 * colours, the title's sizes — replaces the list under it whole.
 *
 * @param array<string,mixed> $base The array under.
 * @param array<string,mixed> $over The array over it.
 * @return array<string,mixed>
 */
function merge( array $base, array $over ): array {
	foreach ( $over as $key => $value ) {
		if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $value ) && ! array_is_list( $base[ $key ] ) ) {
			$base[ $key ] = merge( $base[ $key ], $value );
		} else {
			$base[ $key ] = $value;
		}
	}
	return $base;
}

/**
 * Draw a card.
 *
 * @param array<string,mixed> $card  What it says: title, kicker, subtitle,
 *                                   meta, and photo (a file, with an optional
 *                                   focus: x and y from 0 to 1).
 * @param array<string,mixed> $style How it is drawn; see defaults().
 */
function render( array $card, array $style ): \GdImage {
	$style  = style( $style );
	$glows  = array_map(
		static fn( $glow ): array => array(
			'x'       => (float) ( $glow['x'] ?? 0 ) * WIDTH,
			'y'       => (float) ( $glow['y'] ?? 0 ) * HEIGHT,
			'rx'      => (float) ( $glow['rx'] ?? 0.5 ) * WIDTH,
			'ry'      => (float) ( $glow['ry'] ?? 0.5 ) * HEIGHT,
			'color'   => (string) ( $glow['color'] ?? '#ffffff' ),
			'opacity' => (float) ( $glow['opacity'] ?? 0.1 ),
			'stop'    => (float) ( $glow['stop'] ?? 1 ),
		),
		array_filter( (array) $style['glow'], 'is_array' )
	);
	$canvas = Canvas\backdrop( WIDTH, HEIGHT, (string) $style['background'], array_values( $glows ) );

	if ( 'bento' === $style['layout'] && (array) $style['bento']['areas'] ) {
		draw_bento( $canvas, $card, $style );
		draw_bar( $canvas, $style );
		return $canvas;
	}

	$photo  = photo( $card );
	$column = WIDTH - 2 * (int) $style['margin'];
	if ( $photo ) {
		$column = draw_photo( $canvas, $photo, $card, $style );
	} else {
		$column = draw_mark( $canvas, $style, $column );
	}

	$top = draw_logo( $canvas, $style );
	draw_type( $canvas, $card, $style, $column, $top );
	draw_bar( $canvas, $style );

	return $canvas;
}

/**
 * The card's photograph, if it has one GD can read.
 *
 * @param array<string,mixed> $card The card.
 */
function photo( array $card ): ?\GdImage {
	$file = (string) ( $card['photo']['file'] ?? '' );
	return '' !== $file ? Canvas\load( $file ) : null;
}

/**
 * The photograph at the right of the card. Returns how wide the type may be.
 *
 * `fade` is the hero of a page: the photograph reaches back under the type
 * and thins out into the background, so the two halves share a border
 * instead of meeting at one; the type runs over the thinnest part. `split`
 * is a clean edge, for photographs a fade would muddy — an object on white.
 *
 * @param \GdImage            $canvas Canvas.
 * @param \GdImage            $photo  Photograph.
 * @param array<string,mixed> $card   The card.
 * @param array<string,mixed> $style  The style.
 */
function draw_photo( \GdImage $canvas, \GdImage $photo, array $card, array $style ): int {
	$margin = (int) $style['margin'];
	$full   = (int) round( WIDTH * max( 0.2, min( 0.7, (float) $style['photo']['width'] ) ) );
	$height = HEIGHT - bar_height( $style );
	$fx     = (float) ( $card['photo']['focus']['x'] ?? 0.5 );
	$fy     = (float) ( $card['photo']['focus']['y'] ?? 0.5 );

	if ( 'cover' === $style['photo']['layout'] ) {
		Canvas\cover( $canvas, $photo, 0, 0, WIDTH, $height, $fx, $fy );
		$scrim = '' !== (string) ( $style['photo']['scrim'] ?? '' ) ? (string) $style['photo']['scrim'] : (string) $style['background'];
		// Light at the head, for the logo; heavy at the foot, for the type.
		$stops = array(
			array( 0.0, 0.5 ),
			array( 0.22, 0.2 ),
			array( 0.45, 0.45 ),
			array( 0.7, 0.8 ),
			array( 1.0, 0.94 ),
		);
		for ( $y = 0; $y < $height; $y++ ) {
			imageline( $canvas, 0, $y, WIDTH - 1, $y, Canvas\color( $canvas, $scrim, ramp( $stops, $y / $height ) ) );
		}
		return min( WIDTH - 2 * $margin, 960 );
	}

	if ( 'split' === $style['photo']['layout'] ) {
		Canvas\cover( $canvas, $photo, WIDTH - $full, 0, $full, $height, $fx, $fy );
		return WIDTH - $full - 2 * $margin;
	}

	$fade  = (int) round( WIDTH * max( 0.0, min( 0.5, (float) $style['photo']['fade'] ) ) );
	$box   = $full + $fade;
	$left  = WIDTH - $box;
	$layer = Canvas\create( $box, $height, (string) $style['background'] );
	Canvas\cover( $layer, $photo, 0, 0, $box, $height, $fx, $fy );

	// How much of the photograph shows across the fade: the site's hero mask
	// with its scrim, 0.2 at 16% of the way, 0.6 at 32%, 0.9 at 46%, under a
	// scrim of the background thinning from 0.8 to nothing, multiplied out.
	$stops = array(
		array( 0.0, 0.0 ),
		array( 0.25, 0.08 ),
		array( 0.5, 0.36 ),
		array( 0.75, 0.74 ),
		array( 1.0, 1.0 ),
	);
	for ( $x = 0; $x < $fade; $x++ ) {
		$pct = (int) round( 100 * ramp( $stops, $x / $fade ) );
		if ( $pct > 0 ) {
			imagecopymerge( $canvas, $layer, $left + $x, 0, $x, 0, 1, $height, $pct );
		}
	}
	imagecopy( $canvas, $layer, $left + $fade, 0, $fade, 0, $full, $height );

	// The type may run over the part of the fade where less than half of
	// the photograph shows.
	return max( 360, $left + (int) round( $fade * 0.6 ) - $margin );
}

/**
 * A value along stops of [position, value], straight between them.
 *
 * @param array<int,array{0:float,1:float}> $stops Stops, in order.
 * @param float                             $t     Position, 0 to 1.
 */
function ramp( array $stops, float $t ): float {
	$previous = $stops[0];
	foreach ( $stops as $stop ) {
		if ( $t <= $stop[0] ) {
			$span = $stop[0] - $previous[0];
			return $span > 0 ? $previous[1] + ( $stop[1] - $previous[1] ) * ( $t - $previous[0] ) / $span : $stop[1];
		}
		$previous = $stop;
	}
	return (float) $previous[1];
}

/**
 * The mark, where there is no photograph: in the top right corner, clear of
 * the type, which is set at the foot of the card. Returns how wide the type
 * may be.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $style  The style.
 * @param int                 $column The width the type has without it.
 */
function draw_mark( \GdImage $canvas, array $style, int $column ): int {
	$file = (string) ( $style['mark']['file'] ?? '' );
	$mark = '' !== $file ? Canvas\load( $file ) : null;
	if ( ! $mark ) {
		return min( $column, 940 );
	}
	$width  = (int) $style['mark']['size'];
	$height = (int) round( $width * imagesy( $mark ) / max( 1, imagesx( $mark ) ) );
	$left   = WIDTH - (int) round( $width * ( 1 - (float) $style['mark']['right'] ) );
	$top    = (int) round( $height * (float) $style['mark']['top'] );
	Canvas\place( $canvas, $mark, $left, $top, $width, $height );

	// The type is set below the mark where it can be, and beside it where the
	// mark reaches down into the type's half of the card.
	return $top + $height < HEIGHT / 2 ? $column : min( $column, $left - 2 * (int) $style['margin'] + 40 );
}

/**
 * The logo at the head of the card. Returns where the type may begin.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $style  The style.
 */
function draw_logo( \GdImage $canvas, array $style ): int {
	$logo   = (array) $style['logo'];
	$margin = (int) $style['margin'];
	$image  = '' !== (string) ( $logo['file'] ?? '' ) ? Canvas\load( (string) $logo['file'] ) : null;
	$words  = trim( (string) ( $logo['text'] ?? '' ) );
	$font   = (string) ( $logo['font'] ?? '' );
	if ( ! $image && ( '' === $words || '' === $font || ! is_readable( $font ) ) ) {
		return $margin;
	}

	$padding = (int) $logo['padding'];
	$radius  = (int) $logo['radius'];
	if ( $image ) {
		$height = (int) $logo['height'];
		$width  = (int) round( $height * imagesx( $image ) / max( 1, imagesy( $image ) ) );
	} else {
		// The site's name, as tall as a capital and as wide as it sets.
		$size   = (float) ( $logo['size'] ?? 30 );
		$words  = Canvas\shorten( $font, $size, $words, 640 );
		$height = (int) round( $size * 0.74 );
		$width  = Canvas\width( $font, $size, $words );
	}
	$draw = static function ( int $x, int $y ) use ( $canvas, $image, $width, $height, $logo, $font, $words ): void {
		if ( $image ) {
			Canvas\place( $canvas, $image, $x, $y, $width, $height );
		} else {
			Canvas\text( $canvas, $font, (float) ( $logo['size'] ?? 30 ), (string) ( $logo['color'] ?? '#ffffff' ), $x, $y + $height, $words );
		}
	};

	if ( '' === (string) $logo['tab'] ) {
		$draw( $margin, (int) round( $margin * 0.8 ) );
		return (int) round( $margin * 0.8 ) + $height;
	}

	// A tab hanging from the top edge, as a logo hangs in a site's header.
	$tab_w = $width + 2 * $padding;
	$tab_h = $height + 2 * $padding;
	Canvas\rounded( $canvas, $margin, 0, $tab_w, $tab_h, array( 0, 0, $radius, $radius ), (string) $logo['tab'] );
	$draw( $margin + $padding, $padding );

	$second = (array) ( $logo['second'] ?? array() );
	$other  = '' !== (string) ( $second['file'] ?? '' ) ? Canvas\load( (string) $second['file'] ) : null;
	if ( $other ) {
		$s_height  = (int) $second['height'];
		$s_width   = (int) round( $s_height * imagesx( $other ) / max( 1, imagesy( $other ) ) );
		$s_padding = (int) $second['padding'];
		$s_tab_h   = $s_height + 2 * $s_padding;
		$s_left    = $margin + $tab_w;
		if ( '' !== (string) $second['tab'] ) {
			Canvas\rounded( $canvas, $s_left, 0, $s_width + 2 * $s_padding, $s_tab_h, array( 0, 0, $radius, 0 ), (string) $second['tab'] );
		}
		Canvas\place( $canvas, $other, $s_left + $s_padding, $s_padding, $s_width, $s_height );
	}

	return $tab_h;
}

/**
 * The type, set from the foot of the card upwards: the address, the line
 * under the title, the title, the kicker.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $card   The card.
 * @param array<string,mixed> $style  The style.
 * @param int                 $column How wide the type may be.
 * @param int                 $top    Where the type may begin.
 */
function draw_type( \GdImage $canvas, array $card, array $style, int $column, int $top ): void {
	$margin   = (int) $style['margin'];
	$title    = (array) $style['title'];
	$kicker   = (array) $style['kicker'];
	$subtitle = (array) $style['subtitle'];
	$meta     = (array) $style['meta'];
	$top     += 44;

	$baseline = HEIGHT - bar_height( $style ) - (int) round( $margin * 0.8 );

	// The address, or whatever the site puts at the foot.
	$meta_text = trim( (string) ( $card['meta'] ?? '' ) );
	if ( '' !== $meta_text && '' !== (string) $meta['font'] ) {
		Canvas\text( $canvas, (string) $meta['font'], (float) $meta['size'], (string) $meta['color'], $margin, $baseline, Canvas\shorten( (string) $meta['font'], (float) $meta['size'], $meta_text, $column ), (float) $meta['opacity'] );
		$baseline -= (int) round( (float) $meta['size'] * 0.74 + 30 );
	}

	// The line under the title: one line, with as many of its parts as fit.
	$sub_text = trim( (string) ( $card['subtitle'] ?? '' ) );
	$sub_font = (string) $subtitle['font'];
	if ( '' !== $sub_text && '' !== $sub_font ) {
		$sub_size  = (float) $subtitle['size'];
		$baseline -= (int) round( $sub_size * 0.22 );
		Canvas\text( $canvas, $sub_font, $sub_size, (string) $subtitle['color'], $margin, $baseline, parts( $sub_font, $sub_size, $sub_text, $column ), (float) $subtitle['opacity'] );
		$baseline -= (int) round( $sub_size * 0.76 + 22 );
	}

	$kicker_text = trim( (string) ( $card['kicker'] ?? '' ) );
	if ( '' !== $kicker_text && ! empty( $kicker['upper'] ) ) {
		$kicker_text = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $kicker_text ) : strtoupper( $kicker_text );
	}
	$kicker_font = (string) $kicker['font'];
	$kicker_room = '' !== $kicker_text && '' !== $kicker_font ? (int) round( (float) $kicker['size'] * 0.74 + 26 ) : 0;

	// The title: the largest size at which it fits the lines and the height
	// it has, balanced over its lines.
	$font  = (string) $title['font'];
	$text  = trim( (string) ( $card['title'] ?? '' ) );
	$fit   = fit( $font, $text, $title, $column, $baseline - $top - $kicker_room );
	$size  = $fit['size'];
	$line  = (int) round( $size * (float) $title['line'] );
	$first = $baseline - (int) round( $size * 0.22 ) - ( count( $fit['lines'] ) - 1 ) * $line;
	foreach ( $fit['lines'] as $i => $words ) {
		Canvas\text( $canvas, $font, $size, (string) $title['color'], $margin - (int) round( $size * 0.02 ), $first + $i * $line, $words );
	}

	if ( $kicker_room ) {
		$y = $first - (int) round( $size * 0.76 ) - 26;
		Canvas\tracked( $canvas, $kicker_font, (float) $kicker['size'], (string) $kicker['color'], $margin, $y, Canvas\shorten( $kicker_font, (float) $kicker['size'], $kicker_text, $column ), (float) $kicker['tracking'] );
	}
}

/**
 * A line of parts — "Ana Lima · Lisbon · 12 May 2026" — with the parts
 * from the end left out until it fits: a byline and a place rather than half
 * a date. A first part too long on its own is cut short.
 *
 * @param string $font  Font file.
 * @param float  $size  Size in pixels.
 * @param string $text  The line.
 * @param int    $width How wide it may be.
 */
function parts( string $font, float $size, string $text, int $width ): string {
	$parts = explode( ' · ', $text );
	$count = count( $parts );
	while ( $count > 1 && Canvas\width( $font, $size, implode( ' · ', $parts ) ) > $width ) {
		array_pop( $parts );
		--$count;
	}
	return Canvas\shorten( $font, $size, implode( ' · ', $parts ), $width );
}

/**
 * The size and lines a title is set in.
 *
 * Tried from the largest size down, four pixels at a time: the first size at
 * which the title takes no more than its lines, fits the height, and breaks
 * no word over the width. At the smallest size it is cut to its lines.
 *
 * @param string              $font   Font file.
 * @param string              $text   The title.
 * @param array<string,mixed> $title  The title's style.
 * @param int                 $width  How wide it may be.
 * @param int                 $height How much height it has, from the top of
 *                                    its first line to its last baseline.
 * @return array{size:int,lines:string[]}
 */
function fit( string $font, string $text, array $title, int $width, int $height ): array {
	[ $largest, $smallest ] = array_map( 'intval', array_pad( (array) $title['sizes'], 2, 48 ) );
	$most                   = max( 1, (int) $title['lines'] );
	$leading                = (float) $title['line'];

	for ( $size = $largest; $size >= $smallest; $size -= 4 ) {
		$lines = lines( $font, $size, $text, $width, true );
		$tall  = (int) round( $size * 0.76 + ( count( $lines ) - 1 ) * $size * $leading );
		$wide  = array_filter( $lines, static fn( string $line ): bool => Canvas\width( $font, $size, $line ) > $width );
		if ( count( $lines ) <= $most && $tall <= $height && ! $wide ) {
			return array(
				'size'  => $size,
				'lines' => $lines,
			);
		}
	}

	$size  = $smallest;
	$room  = max( 1, min( $most, 1 + (int) floor( ( $height - $size * 0.76 ) / ( $size * $leading ) ) ) );
	$lines = lines( $font, $size, $text, $width, false );
	if ( count( $lines ) > $room ) {
		$lines              = array_slice( $lines, 0, $room );
		$lines[ $room - 1 ] = Canvas\shorten( $font, $size, $lines[ $room - 1 ], $width, true );
	}
	return array(
		'size'  => $size,
		'lines' => $lines,
	);
}

/**
 * A title broken into lines: at the line breaks an editor typed, and then
 * wherever a line is wider than the width.
 *
 * @param string $font    Font file.
 * @param float  $size    Size in pixels.
 * @param string $text    The title.
 * @param int    $width   How wide a line may be.
 * @param bool   $balance Whether to even out each part's lines.
 * @return string[]
 */
function lines( string $font, float $size, string $text, int $width, bool $balance ): array {
	$lines = array();
	foreach ( explode( "\n", $text ) as $part ) {
		$lines = array_merge( $lines, $balance ? Canvas\balance( $font, $size, $part, $width ) : Canvas\wrap( $font, $size, $part, $width ) );
	}
	return $lines;
}

/**
 * The bar of colour along the foot of the card: one band per colour, as wide
 * as each other.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $style  The style.
 */
function draw_bar( \GdImage $canvas, array $style ): void {
	$colors = array_values( array_filter( (array) ( $style['bar']['colors'] ?? array() ), 'is_string' ) );
	$height = bar_height( $style );
	if ( ! $colors || ! $height ) {
		return;
	}
	$count = count( $colors );
	foreach ( $colors as $i => $hex ) {
		$left  = (int) round( $i * WIDTH / $count );
		$right = (int) round( ( $i + 1 ) * WIDTH / $count ) - 1;
		imagefilledrectangle( $canvas, $left, HEIGHT - $height, $right, HEIGHT - 1, Canvas\color( $canvas, $hex ) );
	}
}

/**
 * How tall the bar is: nothing without colours.
 *
 * @param array<string,mixed> $style The style.
 */
function bar_height( array $style ): int {
	return array_filter( (array) ( $style['bar']['colors'] ?? array() ) ) ? max( 0, (int) ( $style['bar']['height'] ?? 0 ) ) : 0;
}

/**
 * A bento card: the card divided into tiles with rounded corners, as
 * `bento` lays them out, each showing one thing.
 *
 *     ┌──────────────────────┐ ┌──────────┐ ┌──────────┐
 *     │ KICKER               │ │   LOGO   │ │ Label    │
 *     │                      │ └──────────┘ │ Value    │
 *     │                      │ ┌────────────┴──────────┤
 *     │ Title, as large as   │ │ photograph, or a      │
 *     │ it fits              │ │ pattern without one   │
 *     └──────────────────────┘ └───────────────────────┘
 *
 * A tile with nothing to show — a photograph, a fact or a logo the card
 * lacks — shows the mark, or a pattern of squares, instead, so the grid
 * stays whole.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $card   The card.
 * @param array<string,mixed> $style  The style.
 */
function draw_bento( \GdImage $canvas, array $card, array $style ): void {
	$bento   = (array) $style['bento'];
	$padding = max( 0, (int) $bento['padding'] );
	$rects   = areas( $bento, $padding, $padding, WIDTH - 2 * $padding, HEIGHT - bar_height( $style ) - 2 * $padding );
	$tiles   = (array) $bento['tiles'];
	$radius  = max( 0, (int) $bento['radius'] );

	// What each tile shows, once what the card lacks is replaced.
	$photo = photo( $card );
	$mark  = '' !== (string) ( $style['mark']['file'] ?? '' ) ? Canvas\load( (string) $style['mark']['file'] ) : null;
	$logo  = (array) $style['logo'];
	$facts = (array) ( $card['facts'] ?? array() );
	$plan  = array();
	$shown = array();
	foreach ( $rects as $name => $rect ) {
		$tile    = (array) ( $tiles[ $name ] ?? array() );
		$content = (string) ( $tile['content'] ?? $name );
		if ( 'photo' === $content && ! $photo ) {
			$content = $mark ? 'mark' : 'pattern';
		} elseif ( 'mark' === $content && ! $mark ) {
			$content = 'pattern';
		} elseif ( 'logo' === $content && '' === (string) ( $logo['file'] ?? '' ) && '' === trim( (string) ( $logo['text'] ?? '' ) ) ) {
			$content = 'pattern';
		} elseif ( ! in_array( $content, array( 'headline', 'photo', 'logo', 'pattern', 'mark' ), true ) ) {
			$value = trim( (string) ( $facts[ $content ]['value'] ?? '' ) );
			if ( '' === $value ) {
				$content = 'pattern';
			} else {
				$shown[] = $value;
			}
		}
		$plan[ $name ] = array( $content, $tile, $rect );
	}

	// The facts' values in one size, the largest at which each that fits on
	// one line fits, so the tiles read as a set; a longer value, a name
	// over two lines, is set no larger.
	$largest = 0;
	foreach ( $plan as [ $content, $tile, $rect ] ) {
		if ( isset( $facts[ $content ] ) && ! in_array( $content, array( 'headline', 'photo', 'logo', 'pattern', 'mark' ), true ) ) {
			$fit = fact_fit( $style, $tile, $rect, tile_padding( $tile, $rect ), (array) $facts[ $content ], 0 )['fit'];
			if ( 1 === count( $fit['lines'] ) ) {
				$largest = $largest ? min( $largest, $fit['size'] ) : $fit['size'];
			}
		}
	}

	foreach ( $plan as [ $content, $tile, $rect ] ) {
		[ $x, $y, $w, $h ] = $rect;
		$layer             = tile_layer( $tile, $w, $h, (string) $style['background'] );
		$pad               = tile_padding( $tile, $rect );

		if ( 'photo' === $content ) {
			Canvas\cover( $layer, $photo, 0, 0, $w, $h, (float) ( $card['photo']['focus']['x'] ?? 0.5 ), (float) ( $card['photo']['focus']['y'] ?? 0.5 ) );
		} elseif ( 'mark' === $content ) {
			$size = (int) round( min( $w, $h ) * 0.62 );
			[ $mw, $mh ] = contain( $mark, $size, $size );
			Canvas\place( $layer, $mark, intdiv( $w - $mw, 2 ), intdiv( $h - $mh, 2 ), $mw, $mh );
		}
		Canvas\paste_rounded( $canvas, $layer, $x, $y, $radius );

		match ( $content ) {
			'headline' => tile_headline( $canvas, $card, $style, $tile, $rect, $pad ),
			'logo'     => tile_logo( $canvas, $style, $tile, $rect, $pad ),
			'pattern'  => tile_pattern( $canvas, $card, $tile, $rect, $pad, $shown ),
			'photo', 'mark' => null,
			default    => tile_fact( $canvas, $style, $tile, $rect, $pad, (array) $facts[ $content ], $largest ),
		};
	}
}

/**
 * A line cut to a width: at a word where it can be, else, for one word too
 * long on its own — "Musterfrau-Oberholzer" in a narrow tile — within it.
 *
 * @param string $font  Font file.
 * @param float  $size  Size in pixels.
 * @param string $line  The line.
 * @param int    $width How wide it may be.
 */
function clip( string $font, float $size, string $line, int $width ): string {
	$line  = Canvas\shorten( $font, $size, $line, $width );
	$chars = preg_split( '//u', rtrim( $line, '…' ), -1, PREG_SPLIT_NO_EMPTY );
	$chars = is_array( $chars ) ? $chars : array();
	while ( count( $chars ) > 1 && Canvas\width( $font, $size, $line ) > $width ) {
		array_pop( $chars );
		$line = rtrim( implode( '', $chars ), ' -' ) . '…';
	}
	return $line;
}

/**
 * The space inside a tile: its own, or a tenth of its shorter side, between
 * 22 and 40 pixels.
 *
 * @param array<string,mixed> $tile The tile.
 * @param array<int,int>      $rect Where it is.
 */
function tile_padding( array $tile, array $rect ): int {
	return (int) ( $tile['padding'] ?? max( 22, min( 40, (int) round( min( $rect[2], $rect[3] ) * 0.11 ) ) ) );
}

/**
 * Where each tile of a grid is: its name => left, top, width and height.
 *
 * @param array<string,mixed> $bento  The grid: areas, columns, rows, gap.
 * @param int                 $x      The grid's left.
 * @param int                 $y      Its top.
 * @param int                 $width  Its width.
 * @param int                 $height Its height.
 * @return array<string,array{0:int,1:int,2:int,3:int}>
 */
function areas( array $bento, int $x, int $y, int $width, int $height ): array {
	$grid = array();
	foreach ( (array) $bento['areas'] as $row ) {
		$cells = preg_split( '/\s+/', trim( (string) $row ), -1, PREG_SPLIT_NO_EMPTY );
		if ( $cells ) {
			$grid[] = $cells;
		}
	}
	if ( ! $grid ) {
		return array();
	}
	$gap   = max( 0, (int) $bento['gap'] );
	$count = max( array_map( 'count', $grid ) );
	$cols  = tracks( (array) $bento['columns'], $count, $x, $width, $gap );
	$rows  = tracks( (array) $bento['rows'], count( $grid ), $y, $height, $gap );

	$spans = array();
	foreach ( $grid as $r => $cells ) {
		foreach ( $cells as $c => $name ) {
			if ( '.' === $name ) {
				continue;
			}
			$spans[ $name ] = isset( $spans[ $name ] )
				? array( min( $spans[ $name ][0], $c ), min( $spans[ $name ][1], $r ), max( $spans[ $name ][2], $c ), max( $spans[ $name ][3], $r ) )
				: array( $c, $r, $c, $r );
		}
	}
	$rects = array();
	foreach ( $spans as $name => [ $c0, $r0, $c1, $r1 ] ) {
		$left           = (int) round( $cols[ $c0 ][0] );
		$top            = (int) round( $rows[ $r0 ][0] );
		$rects[ $name ] = array( $left, $top, (int) round( $cols[ $c1 ][1] ) - $left, (int) round( $rows[ $r1 ][1] ) - $top );
	}
	return $rects;
}

/**
 * The start and end of each column or row: the length less the gaps, shared
 * by the tracks' weights.
 *
 * @param array<int,mixed> $weights Shares, as CSS `fr`; equal if missing.
 * @param int              $count   How many tracks.
 * @param int              $start   Where the first begins.
 * @param int              $length  The length of them all, gaps included.
 * @param int              $gap     The gap between two.
 * @return array<int,array{0:float,1:float}>
 */
function tracks( array $weights, int $count, int $start, int $length, int $gap ): array {
	$weights = array_map( static fn( $w ): float => max( 0.05, (float) $w ), array_pad( array_slice( array_values( $weights ), 0, $count ), $count, 1 ) );
	$sum     = array_sum( $weights );
	$room    = max( $count, $length - ( $count - 1 ) * $gap );
	$at      = (float) $start;
	$out     = array();
	foreach ( $weights as $weight ) {
		$size  = $room * $weight / $sum;
		$out[] = array( $at, $at + $size );
		$at   += $size + $gap;
	}
	return $out;
}

/**
 * A tile's ground: its gradient, its colour, or the card's background.
 *
 * @param array<string,mixed> $tile       The tile.
 * @param int                 $width      Width.
 * @param int                 $height     Height.
 * @param string              $background The card's background.
 */
function tile_layer( array $tile, int $width, int $height, string $background ): \GdImage {
	$gradient = array_values( array_filter( (array) ( $tile['gradient'] ?? array() ), 'is_string' ) );
	if ( 2 === count( $gradient ) ) {
		return Canvas\gradient( $width, $height, $gradient[0], $gradient[1] );
	}
	return Canvas\create( $width, $height, '' !== (string) ( $tile['background'] ?? '' ) ? (string) $tile['background'] : $background );
}

/**
 * The size a picture takes inside a box, whole.
 *
 * @param \GdImage $image  The picture.
 * @param int      $width  The box's width.
 * @param int      $height Its height.
 * @return array{0:int,1:int}
 */
function contain( \GdImage $image, int $width, int $height ): array {
	$scale = min( $width / max( 1, imagesx( $image ) ), $height / max( 1, imagesy( $image ) ) );
	return array( max( 1, (int) round( imagesx( $image ) * $scale ) ), max( 1, (int) round( imagesy( $image ) * $scale ) ) );
}

/**
 * The headline tile: the kicker at its head, the title as large as it fits
 * at its foot, and the line under the title below it.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $card   The card.
 * @param array<string,mixed> $style  The style.
 * @param array<string,mixed> $tile   The tile.
 * @param array<int,int>      $rect   Where it is.
 * @param int                 $pad    Space inside it.
 */
function tile_headline( \GdImage $canvas, array $card, array $style, array $tile, array $rect, int $pad ): void {
	[ $x, $y, $w, $h ] = $rect;
	$color             = (string) ( $tile['color'] ?? $style['title']['color'] );
	$column            = $w - 2 * $pad;
	$top               = $y + $pad;
	$bottom            = $y + $h - $pad;

	$kicker = (array) $style['kicker'];
	$text   = trim( (string) ( $card['kicker'] ?? '' ) );
	if ( '' !== $text && '' !== (string) $kicker['font'] ) {
		$text = ! empty( $kicker['upper'] ) ? ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text ) ) : $text;
		$size = (float) $kicker['size'];
		Canvas\tracked( $canvas, (string) $kicker['font'], $size, (string) ( $tile['kicker'] ?? $color ), $x + $pad, $top + (int) round( $size * 0.74 ), Canvas\shorten( (string) $kicker['font'], $size, $text, $column ), (float) $kicker['tracking'], (float) ( $tile['kicker_opacity'] ?? 1 ) );
		$top += (int) round( $size * 0.74 ) + 28;
	}

	$subtitle = (array) $style['subtitle'];
	$sub_text = trim( (string) ( $card['subtitle'] ?? '' ) );
	if ( '' !== $sub_text && '' !== (string) $subtitle['font'] && $h >= 220 ) {
		$size = min( (float) $subtitle['size'], 26.0 );
		Canvas\text( $canvas, (string) $subtitle['font'], $size, $color, $x + $pad, $bottom - (int) round( $size * 0.2 ), parts( (string) $subtitle['font'], $size, $sub_text, $column ), (float) $subtitle['opacity'] );
		$bottom -= (int) round( $size * 0.76 + 22 );
	}

	$title = array_merge(
		(array) $style['title'],
		array(
			'sizes' => (array) ( $tile['sizes'] ?? array( 80, 30 ) ),
			'lines' => (int) ( $tile['lines'] ?? 5 ),
		)
	);
	$font  = (string) $title['font'];
	$fit   = fit( $font, trim( (string) ( $card['title'] ?? '' ) ), $title, $column, $bottom - $top - 8 );
	$size  = $fit['size'];
	$line  = (int) round( $size * (float) $title['line'] );
	$first = $bottom - (int) round( $size * 0.22 ) - ( count( $fit['lines'] ) - 1 ) * $line;
	foreach ( $fit['lines'] as $i => $words ) {
		Canvas\text( $canvas, $font, $size, $color, $x + $pad - (int) round( $size * 0.02 ), $first + $i * $line, clip( $font, $size, $words, $column ) );
	}
}

/**
 * A fact's tile: its label at the head, its value as large as it fits at
 * the foot, as a dashboard shows a number.
 *
 * @param \GdImage            $canvas  Canvas.
 * @param array<string,mixed> $style   The style.
 * @param array<string,mixed> $tile    The tile.
 * @param array<int,int>      $rect    Where it is.
 * @param int                 $pad     Space inside it.
 * @param array<string,mixed> $fact    The fact: label and value.
 * @param int                 $largest The largest size its value may take,
 *                                     or 0 for the tile's own.
 */
function tile_fact( \GdImage $canvas, array $style, array $tile, array $rect, int $pad, array $fact, int $largest = 0 ): void {
	[ $x, $y, $w, $h ] = $rect;
	$color             = (string) ( $tile['color'] ?? $style['title']['color'] );
	$layout            = fact_fit( $style, $tile, $rect, $pad, $fact, $largest );

	$label = trim( (string) ( $fact['label'] ?? '' ) );
	$font  = (string) $style['meta']['font'];
	if ( '' !== $label && '' !== $font ) {
		Canvas\text( $canvas, $font, 20.0, $color, $x + $pad, $y + $pad + 15, Canvas\shorten( $font, 20.0, $label, $w - 2 * $pad ), (float) ( $tile['muted'] ?? 0.62 ) );
	}

	$font  = (string) $style['title']['font'];
	$size  = $layout['fit']['size'];
	$line  = (int) round( $size * 1.08 );
	$first = $y + $h - $pad - (int) round( $size * 0.2 ) - ( count( $layout['fit']['lines'] ) - 1 ) * $line;
	foreach ( $layout['fit']['lines'] as $i => $words ) {
		Canvas\text( $canvas, $font, $size, $color, $x + $pad - (int) round( $size * 0.02 ), $first + $i * $line, clip( $font, $size, $words, $w - 2 * $pad ) );
	}
}

/**
 * The size and lines of a fact's value in its tile.
 *
 * @param array<string,mixed> $style   The style.
 * @param array<string,mixed> $tile    The tile.
 * @param array<int,int>      $rect    Where it is.
 * @param int                 $pad     Space inside it.
 * @param array<string,mixed> $fact    The fact.
 * @param int                 $largest The largest size, or 0 for the tile's.
 * @return array{fit:array{size:int,lines:string[]}}
 */
function fact_fit( array $style, array $tile, array $rect, int $pad, array $fact, int $largest ): array {
	[ , , $w, $h ] = $rect;
	$top           = $pad + ( '' !== trim( (string) ( $fact['label'] ?? '' ) ) ? 37 : 0 );
	$sizes         = (array) ( $tile['sizes'] ?? array( min( 64, max( 28, (int) round( $h * 0.3 ) ) ), 22 ) );
	if ( $largest > 0 ) {
		$sizes[0] = min( (int) $sizes[0], $largest );
	}
	$title = array_merge(
		(array) $style['title'],
		array(
			'sizes' => $sizes,
			'lines' => 2,
			'line'  => 1.08,
		)
	);
	return array( 'fit' => fit( (string) $title['font'], trim( (string) ( $fact['value'] ?? '' ) ), $title, $w - 2 * $pad, $h - $pad - $top - 4 ) );
}

/**
 * The logo's tile: the logo in its middle, as large as the logo's height and
 * the tile allow; without a logo file, the site's name.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $style  The style.
 * @param array<string,mixed> $tile   The tile.
 * @param array<int,int>      $rect   Where it is.
 * @param int                 $pad    Space inside it.
 */
function tile_logo( \GdImage $canvas, array $style, array $tile, array $rect, int $pad ): void {
	[ $x, $y, $w, $h ] = $rect;
	$logo              = (array) $style['logo'];
	$image             = '' !== (string) ( $logo['file'] ?? '' ) ? Canvas\load( (string) $logo['file'] ) : null;
	if ( $image ) {
		[ $lw, $lh ] = contain( $image, $w - 2 * $pad, min( $h - 2 * $pad, (int) round( (int) $logo['height'] * 1.1 ) ) );
		Canvas\place( $canvas, $image, $x + intdiv( $w - $lw, 2 ), $y + intdiv( $h - $lh, 2 ), $lw, $lh );
		return;
	}
	$font = (string) ( $logo['font'] ?? '' );
	if ( '' === $font || ! is_readable( $font ) ) {
		return;
	}
	$size  = (float) ( $logo['size'] ?? 30 );
	$words = trim( (string) ( $logo['text'] ?? '' ) );
	while ( $size > 18 && Canvas\width( $font, $size, $words ) > $w - 2 * $pad ) {
		$size -= 2;
	}
	$words = Canvas\shorten( $font, $size, $words, $w - 2 * $pad );
	$width = Canvas\width( $font, $size, $words );
	Canvas\text( $canvas, $font, $size, (string) ( $tile['color'] ?? $logo['color'] ?? '#ffffff' ), $x + intdiv( $w - $width, 2 ), $y + intdiv( $h, 2 ) + (int) round( $size * 0.36 ), $words );
}

/**
 * A pattern's tile: rows of small squares at its foot, a few of them in the
 * accent, picked from the title so each card has its own; and the address
 * at its head, unless a fact already shows it.
 *
 * @param \GdImage            $canvas Canvas.
 * @param array<string,mixed> $card   The card.
 * @param array<string,mixed> $tile   The tile.
 * @param array<int,int>      $rect   Where it is.
 * @param int                 $pad    Space inside it.
 * @param string[]            $shown  The values the card's facts show.
 */
function tile_pattern( \GdImage $canvas, array $card, array $tile, array $rect, int $pad, array $shown ): void {
	[ $x, $y, $w, $h ] = $rect;
	$color             = (string) ( $tile['color'] ?? '#ffffff' );
	$top               = $y + $pad;

	$label = trim( (string) ( $tile['label'] ?? $card['meta'] ?? '' ) );
	$font  = (string) ( $tile['font'] ?? '' );
	if ( '' !== $label && '' !== $font && is_readable( $font ) && ! in_array( $label, $shown, true ) && $h >= 150 ) {
		$size = 20.0;
		Canvas\text( $canvas, $font, $size, $color, $x + $pad, $top + (int) round( $size * 0.74 ), Canvas\shorten( $font, $size, $label, $w - 2 * $pad ), (float) ( $tile['muted'] ?? 0.62 ) );
		$top += (int) round( $size * 0.74 ) + 24;
	}

	// Squares as large as the tile is, in up to three rows, or more in a
	// tall tile, which they would otherwise leave empty.
	$cell = max( 16, min( 26, (int) round( min( $w, $h ) / 14 ) ) );
	$gap  = (int) round( $cell * 0.4 );
	$cols = (int) floor( ( $w - 2 * $pad + $gap ) / ( $cell + $gap ) );
	$rows = min( max( 3, (int) round( $h / ( $cell + $gap ) * 0.45 ) ), (int) floor( ( $y + $h - $pad - $top + $gap ) / ( $cell + $gap ) ) );
	if ( $cols < 1 || $rows < 1 ) {
		return;
	}
	$seed   = crc32( (string) ( $card['title'] ?? '' ) ) & 0x7fffffff;
	$next   = static function () use ( &$seed ): int {
		$seed = ( $seed * 1103515245 + 12345 ) & 0x7fffffff;
		return $seed >> 8;
	};
	$muted  = (string) ( $tile['squares'] ?? $color );
	$accent = (string) ( $tile['accent'] ?? $color );
	$last   = $cols - 1 - $next() % max( 1, intdiv( $cols, 2 ) );
	$left   = $x + $pad;
	$bottom = $y + $h - $pad;
	for ( $r = 0; $r < $rows; $r++ ) {
		$count = $r === $rows - 1 && $rows > 1 ? $last : $cols;
		$cy    = $bottom - ( $rows - $r ) * ( $cell + $gap ) + $gap;
		for ( $c = 0; $c < $count; $c++ ) {
			$lit = 0 === $next() % 7;
			Canvas\rounded( $canvas, $left + $c * ( $cell + $gap ), $cy, $cell, $cell, array_fill( 0, 4, (int) round( $cell * 0.22 ) ), $lit ? $accent : $muted );
		}
	}
}
