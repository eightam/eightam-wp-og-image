<?php
/**
 * The cards on disk: uploads/eightam-og-image/{key}-{hash}.{jpg|png}.
 *
 * The key says whose card it is — `post-12`, `type-event-de_de`,
 * `term-40-en_us`, `site-en_us` — and the hash is of everything drawn on it:
 * the words, the photograph's file, the style and the files the style names,
 * and this plugin's version. A card whose file exists is up to date; a change
 * to any of it asks for a file that does not exist yet, and drawing that file
 * deletes the key's older ones. Nothing else records what was drawn.
 *
 * A card with a photograph is a JPEG, one of flat colour a PNG.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the cards are kept.
 *
 * @return array{dir:string,url:string}
 */
function folder(): array {
	$uploads = wp_get_upload_dir();
	return array(
		'dir' => (string) $uploads['basedir'] . '/' . FOLDER,
		'url' => (string) $uploads['baseurl'] . '/' . FOLDER,
	);
}

/**
 * The file a card is drawn to.
 *
 * @param string              $key   Whose card.
 * @param array<string,mixed> $card  The card.
 * @param array<string,mixed> $style Its style.
 */
function file_name( string $key, array $card, array $style ): string {
	return $key . '-' . fingerprint( $card, $style ) . ( null !== $card['photo'] ? '.jpg' : '.png' );
}

/**
 * Twelve characters of a hash of everything drawn on a card.
 *
 * A file the style names counts by its content, so a new logo or a new cut of
 * a font draws every card again, and a deploy that only touches the files'
 * dates draws none. A photograph counts by its name and size: WordPress
 * gives an edited image a new name. The plugin's version counts too, so a
 * release that changes how the template draws draws every card again.
 *
 * @param array<string,mixed> $card  The card.
 * @param array<string,mixed> $style Its style.
 */
function fingerprint( array $card, array $style ): string {
	$files = array();
	array_walk_recursive(
		$style,
		static function ( $value, $key ) use ( &$files ): void {
			if ( in_array( $key, array( 'font', 'file' ), true ) && is_string( $value ) && '' !== $value ) {
				$files[] = file_hash( $value );
			}
		}
	);
	$photo = (string) ( $card['photo']['file'] ?? '' );

	return substr(
		md5(
			(string) wp_json_encode(
				array(
					VERSION,
					$card['title'],
					$card['kicker'],
					$card['subtitle'],
					$card['meta'],
					'' !== $photo ? array( wp_basename( $photo ), (int) @filesize( $photo ), $card['photo']['focus'] ) : null, // phpcs:ignore WordPress.PHP.NoSilencedErrors
					$style,
					$files,
				)
			)
		),
		0,
		12
	);
}

/**
 * A hash of a file's content, worked out once a request.
 *
 * @param string $file Path.
 */
function file_hash( string $file ): string {
	static $hashes = array();
	if ( ! isset( $hashes[ $file ] ) ) {
		$hashes[ $file ] = is_readable( $file ) ? (string) md5_file( $file ) : '';
	}
	return $hashes[ $file ];
}

/**
 * A card's image: its file if it is drawn, drawn now if it may be, or else
 * the last one drawn for the key, which is out of date but better than none.
 *
 * @param string              $key   Whose card.
 * @param array<string,mixed> $card  The card.
 * @param array<string,mixed> $style Its style.
 * @param bool                $draw  Whether to draw it if it is missing.
 * @return array{url:string,file:string,width:int,height:int,type:string,fresh:bool}|null
 */
function card_image( string $key, array $card, array $style, bool $draw ): ?array {
	$folder = folder();
	$name   = file_name( $key, $card, $style );
	$file   = $folder['dir'] . '/' . $name;
	$fresh  = is_file( $file );

	if ( ! $fresh && $draw && draw( $file, $card, $style ) ) {
		forget( $key, $name );
		$fresh = true;
	}
	if ( ! $fresh ) {
		$file = latest( $key );
		if ( '' === $file ) {
			return null;
		}
		$name = wp_basename( $file );
	}

	return array(
		'url'    => $folder['url'] . '/' . $name,
		'file'   => $file,
		'width'  => Template\WIDTH,
		'height' => Template\HEIGHT,
		'type'   => str_ends_with( $name, '.png' ) ? 'image/png' : 'image/jpeg',
		'fresh'  => $fresh,
	);
}

/**
 * Draw a card to a file. Two requests that want the same card at once draw
 * it once: the second finds it being drawn and goes without.
 *
 * @param string              $file  Path.
 * @param array<string,mixed> $card  The card.
 * @param array<string,mixed> $style Its style.
 */
function draw( string $file, array $card, array $style ): bool {
	if ( ! can_draw( $style ) ) {
		return false;
	}
	$lock = 'eightam_og_image_' . md5( $file );
	if ( get_transient( $lock ) ) {
		return false;
	}
	set_transient( $lock, 1, MINUTE_IN_SECONDS );

	$done = false;
	try {
		wp_raise_memory_limit( 'image' );
		if ( ! wp_mkdir_p( dirname( $file ) ) || ! wp_is_writable( dirname( $file ) ) ) {
			return false;
		}
		// Written beside it and moved into place, so a crawler never reads
		// half a file.
		$temp = dirname( $file ) . '/.' . wp_generate_password( 8, false ) . '-' . wp_basename( $file );
		$done = Canvas\save( image( $card, $style ), $temp ) && rename( $temp, $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- an atomic move, on a front end that has no WP_Filesystem.
		if ( ! $done && is_file( $temp ) ) {
			wp_delete_file( $temp );
		}
	} catch ( \Throwable $error ) {
		$done = false;
	} finally {
		delete_transient( $lock );
	}
	return $done;
}

/**
 * A card drawn, in memory.
 *
 * @param array<string,mixed> $card  The card.
 * @param array<string,mixed> $style Its style.
 */
function image( array $card, array $style ): \GdImage {
	return Template\render( $card, $style );
}

/**
 * A key's files, newest first.
 *
 * @param string $key Whose card.
 * @return string[] Paths.
 */
function files( string $key ): array {
	$dir = folder()['dir'];
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$pattern = '/^' . preg_quote( $key, '/' ) . '-[0-9a-f]{12}\.(jpg|png)$/';
	$files   = array();
	foreach ( (array) scandir( $dir ) as $name ) {
		if ( is_string( $name ) && preg_match( $pattern, $name ) ) {
			$files[ $dir . '/' . $name ] = (int) filemtime( $dir . '/' . $name );
		}
	}
	arsort( $files );
	return array_keys( $files );
}

/**
 * The last card drawn for a key, or ''.
 *
 * @param string $key Whose card.
 */
function latest( string $key ): string {
	return files( $key )[0] ?? '';
}

/**
 * Delete a key's cards, but for one.
 *
 * @param string $key  Whose card.
 * @param string $keep The file name to keep.
 */
function forget( string $key, string $keep = '' ): void {
	foreach ( files( $key ) as $file ) {
		if ( wp_basename( $file ) !== $keep ) {
			wp_delete_file( $file );
		}
	}
}

/**
 * Delete every card. Each is drawn again when its page is next viewed.
 *
 * @return int How many files were deleted.
 */
function flush(): int {
	$dir = folder()['dir'];
	if ( ! is_dir( $dir ) ) {
		return 0;
	}
	$count = 0;
	foreach ( (array) scandir( $dir ) as $name ) {
		if ( is_string( $name ) && preg_match( '/-[0-9a-f]{12}\.(jpg|png)$/', $name ) ) {
			wp_delete_file( $dir . '/' . $name );
			++$count;
		}
	}
	return $count;
}
