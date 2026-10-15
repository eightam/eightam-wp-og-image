<?php
/**
 * The typefaces a card is set in: a family and a weight, found as a file GD
 * can draw.
 *
 * A family is looked for, in order:
 *
 * 1. in the files the theme names for it — theme.json's `fontFace`, a font
 *    installed through the Font Library, or one uploaded to Blocksy Pro's
 *    Custom Fonts — if one is TrueType, OpenType or WOFF, static, and of the
 *    weight wanted. A WOFF2 file, which PHP cannot unpack, is unpacked by
 *    the browser of an administrator who opens Settings → Share images
 *    (assets/settings.js), and its TrueType copy used from then on;
 * 2. on Google Fonts, which serves a static TrueType cut of any weight of its
 *    families: fetched once, by the server, never by a reader's browser;
 * 3. among the plugin's own, Plus Jakarta Sans, in assets/fonts/;
 * 4. among the server's (includes/card.php, system_font()).
 *
 * Each file found is made fit for GD (includes/sfnt.php: kerning written
 * into a `kern` table, WOFF unpacked) and kept in
 * uploads/eightam-og-image/fonts/, so the work is done once. A family that
 * cannot be found is not looked for again for a day.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Fonts;

use Eightam\OgImage\Sfnt;

use function Eightam\OgImage\folder;
use function Eightam\OgImage\settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// What was found for each family and weight: key => [file, source, time].
const OPTION = 'eightam_og_image_fonts';

/**
 * A family's file at a weight, or '' if there is none to be had.
 *
 * @param string                         $family  Family name, as in CSS.
 * @param int                            $weight  100 to 900.
 * @param bool                           $italic  Italic.
 * @param array<int,array<string,mixed>> $sources Files the site has for the
 *                                                family: file, weight (such
 *                                                as "700" or "100 900") and
 *                                                style.
 */
function file( string $family, int $weight, bool $italic = false, array $sources = array() ): string {
	return find( $family, $weight, $italic, $sources )['file'];
}

/**
 * A family's file at a weight, and where it came from: `theme`, `google`,
 * `bundled`, or '' for nowhere.
 *
 * @param string                         $family  Family name.
 * @param int                            $weight  Weight.
 * @param bool                           $italic  Italic.
 * @param array<int,array<string,mixed>> $sources The site's files for it.
 * @return array{file:string,source:string}
 */
function find( string $family, int $weight, bool $italic = false, array $sources = array() ): array {
	static $found = array();

	$family = clean( $family );
	$weight = max( 100, min( 900, (int) round( $weight / 100 ) * 100 ) );
	$none   = array(
		'file'   => '',
		'source' => '',
	);
	if ( '' === $family ) {
		return $none;
	}
	if ( 'plus jakarta sans' === strtolower( $family ) ) {
		return bundled( $weight );
	}

	$key = sanitize_title( $family ) . '-' . $weight . ( $italic ? 'i' : '' ) . '-' . substr( md5( (string) wp_json_encode( $sources ) ), 0, 6 );
	if ( isset( $found[ $key ] ) ) {
		return $found[ $key ];
	}

	$known = (array) get_option( OPTION, array() );
	$entry = (array) ( $known[ $key ] ?? array() );
	if ( $entry ) {
		$file = (string) ( $entry['file'] ?? '' );
		if ( '' !== $file && is_readable( $file ) ) {
			return $found[ $key ] = array( // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
				'file'   => $file,
				'source' => (string) ( $entry['source'] ?? '' ),
			);
		}
		if ( '' === $file && (int) ( $entry['time'] ?? 0 ) > time() - DAY_IN_SECONDS ) {
			return $found[ $key ] = $none; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
		}
	}

	$result = from_sources( $family, $weight, $italic, $sources, $key );
	if ( '' === $result['file'] && settings()['google_fonts'] ) {
		$result = from_google( $family, $weight, $italic, $key );
	}

	$known[ $key ] = array(
		'file'   => $result['file'],
		'source' => $result['source'],
		'time'   => time(),
	);
	update_option( OPTION, $known, false );
	return $found[ $key ] = $result; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
}

/**
 * The plugin's own typeface, Plus Jakarta Sans, at the nearest weight it
 * carries.
 *
 * @param int $weight Weight.
 * @return array{file:string,source:string}
 */
function bundled( int $weight ): array {
	$cut  = $weight >= 600 ? 'Bold' : 'Medium';
	$file = dirname( __DIR__ ) . '/assets/fonts/PlusJakartaSans-' . $cut . '.ttf';
	return array(
		'file'   => is_readable( $file ) ? $file : '',
		'source' => is_readable( $file ) ? 'bundled' : '',
	);
}

/**
 * A family name as Google Fonts and a file name take it: the first family of
 * a CSS font stack, without quotes, or '' for a generic or system one.
 *
 * @param string $family A family name or a CSS font-family stack.
 */
function clean( string $family ): string {
	$first = trim( explode( ',', $family )[0] ?? '' );
	$first = trim( $first, " \t\"'" );
	if ( '' === $first || ! preg_match( '/^[\p{L}\p{N} \-]+$/u', $first ) ) {
		return '';
	}
	$generic = array( 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', '-apple-system', 'blinkmacsystemfont', 'system default', 'default', 'inherit', 'initial' );
	return in_array( strtolower( $first ), $generic, true ) ? '' : $first;
}

/**
 * The site's own file for a family, made fit for GD.
 *
 * @param string                         $family  Family.
 * @param int                            $weight  Weight.
 * @param bool                           $italic  Italic.
 * @param array<int,array<string,mixed>> $sources The site's files.
 * @param string                         $key     Name to keep it under.
 * @return array{file:string,source:string}
 */
function from_sources( string $family, int $weight, bool $italic, array $sources, string $key ): array {
	$none = array(
		'file'   => '',
		'source' => '',
	);

	// The closest weight of the right style first; a range covers a weight.
	$ranked = array();
	foreach ( $sources as $source ) {
		$path = (string) ( $source['file'] ?? '' );
		if ( '' !== $path && preg_match( '/\.woff2$/i', $path ) ) {
			$path = unpacked( $path );
		}
		if ( '' === $path || ! is_readable( $path ) ) {
			continue;
		}
		$range = array_map( 'intval', preg_split( '/\s+/', trim( (string) ( $source['weight'] ?? '400' ) ) ) ?: array( 400 ) );
		$low   = min( $range );
		$high  = max( $range );
		$miss  = $weight < $low ? $low - $weight : ( $weight > $high ? $weight - $high : 0 );
		$style = 'italic' === (string) ( $source['style'] ?? 'normal' ) ? 1 : 0;
		$miss += ( $style !== (int) $italic ) ? 1000 : 0;
		// One file can stand for several weights: its closest counts.
		if ( ! isset( $ranked[ $path ] ) || $miss < $ranked[ $path ][0] ) {
			$ranked[ $path ] = array( $miss, $low < $high );
		}
	}
	uasort( $ranked, static fn( array $a, array $b ): int => $a[0] <=> $b[0] );

	foreach ( $ranked as $path => [ $miss, $variable_range ] ) {
		if ( $miss >= 200 ) {
			break;
		}
		$data = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$font = Sfnt\prepare( $data );
		// A variable font is drawn at its default weight only; Google Fonts
		// has the weight itself, if it has the family.
		if ( null === $font || ( ( $variable_range || Sfnt\is_variable( $font ) ) && settings()['google_fonts'] ) ) {
			continue;
		}
		$file = store( $key, $font );
		if ( '' !== $file ) {
			return array(
				'file'   => $file,
				'source' => 'theme',
			);
		}
	}
	return $none;
}

/**
 * A family's static TrueType cut from Google Fonts, made fit for GD. Tries
 * the weight wanted and then its neighbours, since a family has only some.
 *
 * @param string $family Family.
 * @param int    $weight Weight.
 * @param bool   $italic Italic.
 * @param string $key    Name to keep it under.
 * @return array{file:string,source:string}
 */
function from_google( string $family, int $weight, bool $italic, string $key ): array {
	$tries = array_unique( array( $weight, $weight + 100, $weight - 100, $weight + 200, $weight - 200, 400 ) );
	foreach ( $tries as $try ) {
		if ( $try < 100 || $try > 900 ) {
			continue;
		}
		$axis     = $italic ? 'ital,wght@1,' . $try : 'wght@' . $try;
		$response = wp_remote_get(
			'https://fonts.googleapis.com/css2?family=' . rawurlencode( $family ) . ':' . $axis,
			array(
				'timeout'    => 8,
				// An agent that cannot read WOFF2 is sent TrueType.
				'user-agent' => 'Mozilla/4.0',
			)
		);
		if ( is_wp_error( $response ) ) {
			break;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			continue;
		}
		if ( ! preg_match( '#url\((https://fonts\.gstatic\.com/[^)\s]+\.ttf)\)#', (string) wp_remote_retrieve_body( $response ), $match ) ) {
			continue;
		}
		$download = wp_remote_get( $match[1], array( 'timeout' => 15 ) );
		if ( is_wp_error( $download ) || 200 !== (int) wp_remote_retrieve_response_code( $download ) ) {
			break;
		}
		$font = Sfnt\prepare( (string) wp_remote_retrieve_body( $download ) );
		$file = null !== $font ? store( $key, $font ) : '';
		if ( '' !== $file ) {
			return array(
				'file'   => $file,
				'source' => 'google',
			);
		}
		break;
	}
	return array(
		'file'   => '',
		'source' => '',
	);
}

/**
 * The TrueType copy of a WOFF2 file, if a browser has unpacked it; ''.
 *
 * @param string $woff2 The WOFF2 file.
 */
function unpacked( string $woff2 ): string {
	$file = is_readable( $woff2 ) ? folder()['dir'] . '/fonts/woff2-' . md5_file( $woff2 ) . '.ttf' : '';
	return '' !== $file && is_readable( $file ) ? $file : '';
}

/**
 * Keep the TrueType copy of a WOFF2 file a browser unpacked, made fit for GD,
 * and look for the families again that could not be found.
 *
 * @param string $woff2 The WOFF2 file.
 * @param string $ttf   What the browser made of it.
 */
function keep_unpacked( string $woff2, string $ttf ): bool {
	$font = Sfnt\prepare( $ttf );
	if ( null === $font || ! is_readable( $woff2 ) ) {
		return false;
	}
	$file = store( 'woff2-' . md5_file( $woff2 ), $font );
	if ( '' === $file ) {
		return false;
	}
	$known = (array) get_option( OPTION, array() );
	update_option( OPTION, array_filter( $known, static fn( $entry ): bool => '' !== (string) ( $entry['file'] ?? '' ) ), false );
	return true;
}

/**
 * The WOFF2 files a family in use has, not yet unpacked, for the settings
 * page to unpack: those of the families that are set with the plugin's own
 * typeface for want of anything else.
 *
 * @param array<string,array<int,array<string,mixed>>> $families Family => sources.
 * @param string[]                                      $wanted   The families in use.
 * @return string[] Their addresses.
 */
function to_unpack( array $families, array $wanted ): array {
	$urls = array();
	foreach ( array_unique( array_filter( $wanted ) ) as $family ) {
		foreach ( (array) ( $families[ $family ] ?? array() ) as $source ) {
			$file = (string) ( $source['file'] ?? '' );
			if ( preg_match( '/\.woff2$/i', $file ) && '' === unpacked( $file ) ) {
				$url = (string) ( $source['url'] ?? '' );
				$urls[] = '' !== $url ? $url : str_replace( WP_CONTENT_DIR, content_url(), $file );
			}
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Keep a font in uploads/eightam-og-image/fonts/. Returns its path, or ''.
 *
 * @param string $key  Its name.
 * @param string $font Its bytes.
 */
function store( string $key, string $font ): string {
	$dir = folder()['dir'] . '/fonts';
	if ( ! wp_mkdir_p( $dir ) || ! wp_is_writable( $dir ) ) {
		return '';
	}
	$file = $dir . '/' . sanitize_file_name( $key ) . '.ttf';
	return false !== file_put_contents( $file, $font ) ? $file : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

/**
 * Forget what was found, and delete the files: each is looked for again when
 * a card next needs it.
 */
function forget(): void {
	delete_option( OPTION );
	$dir = folder()['dir'] . '/fonts';
	foreach ( is_dir( $dir ) ? (array) scandir( $dir ) : array() as $name ) {
		if ( is_string( $name ) && str_ends_with( $name, '.ttf' ) ) {
			wp_delete_file( $dir . '/' . $name );
		}
	}
}
