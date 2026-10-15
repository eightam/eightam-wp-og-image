<?php
/**
 * Font files made fit for GD, in plain PHP.
 *
 * GD draws type with FreeType from one file, and kerns only from a legacy
 * `kern` table, never from GPOS, where every font made in the last fifteen
 * years keeps its kerning. A font straight from a theme or from Google Fonts
 * is therefore set unkerned: "To" and "AV" gape at the sizes a card sets a
 * title in. prepare() writes the pairs of the GPOS `kern` feature into a
 * `kern` table — only those between Latin-1 letters and the usual
 * punctuation, which is what a title is set in; every other glyph is still
 * drawn, unkerned — and turns a WOFF file into the TrueType file inside it.
 *
 * WOFF2 is not read: it is Brotli-compressed and its outlines transformed,
 * and PHP has neither. A theme's WOFF2 font is fetched from Google Fonts by
 * its family name instead (includes/fonts.php).
 *
 * No WordPress in here.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Sfnt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The characters a title is kerned for: ASCII, Latin-1, curly quotes,
 * dashes, the ellipsis, the bullet and the modifier apostrophe.
 *
 * @return int[]
 */
function latin(): array {
	return array_merge( range( 0x20, 0x7e ), range( 0xa0, 0xff ), array( 0x2018, 0x2019, 0x201c, 0x201d, 0x2013, 0x2014, 0x2026, 0x2022, 0x02bc ) );
}

/**
 * A font as GD can draw it: TrueType or OpenType, with a `kern` table where
 * the font has kerning. Null for what cannot be read.
 *
 * @param string $data The file's bytes.
 */
function prepare( string $data ): ?string {
	if ( str_starts_with( $data, 'wOFF' ) ) {
		$data = woff( $data );
		if ( null === $data ) {
			return null;
		}
	}
	if ( ! is_sfnt( $data ) ) {
		return null;
	}

	$tables = tables( $data );
	if ( isset( $tables['kern'] ) || ! isset( $tables['GPOS'], $tables['cmap'] ) ) {
		return $data;
	}
	$glyphs = glyphs( $data, $tables['cmap'][0], latin() );
	$pairs  = $glyphs ? pairs( $data, $tables['GPOS'][0], array_fill_keys( $glyphs, true ) ) : array();
	if ( ! $pairs ) {
		return $data;
	}

	$out = array();
	foreach ( $tables as $tag => [ $offset, $length ] ) {
		$out[ $tag ] = substr( $data, $offset, $length );
	}
	$out['kern'] = kern( $pairs );
	return build( substr( $data, 0, 4 ), $out );
}

/**
 * Whether bytes are a TrueType or OpenType font, not a collection.
 *
 * @param string $data Bytes.
 */
function is_sfnt( string $data ): bool {
	return strlen( $data ) > 12 && in_array( substr( $data, 0, 4 ), array( "\x00\x01\x00\x00", 'true', 'OTTO' ), true );
}

/**
 * Whether a font is variable: GD draws it at its default instance only.
 *
 * @param string $data A TrueType or OpenType font.
 */
function is_variable( string $data ): bool {
	return is_sfnt( $data ) && isset( tables( $data )['fvar'] );
}

/**
 * The tables of a font, as tag => [offset, length].
 *
 * @param string $data A TrueType or OpenType font.
 * @return array<string,array{0:int,1:int}>
 */
function tables( string $data ): array {
	$count  = u16( $data, 4 );
	$tables = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$record = 12 + 16 * $i;
		$offset = u32( $data, $record + 8 );
		$length = u32( $data, $record + 12 );
		if ( $offset + $length <= strlen( $data ) ) {
			$tables[ substr( $data, $record, 4 ) ] = array( $offset, $length );
		}
	}
	return $tables;
}

/**
 * The TrueType or OpenType font inside a WOFF file.
 *
 * @param string $data A WOFF file.
 */
function woff( string $data ): ?string {
	if ( strlen( $data ) < 44 ) {
		return null;
	}
	$count  = u16( $data, 12 );
	$tables = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$record   = 44 + 20 * $i;
		$tag      = substr( $data, $record, 4 );
		$offset   = u32( $data, $record + 4 );
		$packed   = u32( $data, $record + 8 );
		$unpacked = u32( $data, $record + 12 );
		$bytes    = substr( $data, $offset, $packed );
		if ( $packed < $unpacked ) {
			$bytes = function_exists( 'gzuncompress' ) ? @gzuncompress( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! is_string( $bytes ) || strlen( $bytes ) !== $unpacked ) {
				return null;
			}
		}
		$tables[ $tag ] = $bytes;
	}
	return build( substr( $data, 4, 4 ), $tables );
}

/**
 * The glyphs of some characters, from the font's Unicode cmap.
 *
 * @param string $data       Font.
 * @param int    $cmap       Where the cmap table begins.
 * @param int[]  $codepoints Characters.
 * @return int[] Glyph IDs, without .notdef.
 */
function glyphs( string $data, int $cmap, array $codepoints ): array {
	$best = 0;
	$rank = 0;
	$n    = u16( $data, $cmap + 2 );
	for ( $i = 0; $i < $n; $i++ ) {
		$record   = $cmap + 4 + 8 * $i;
		$platform = u16( $data, $record );
		$encoding = u16( $data, $record + 2 );
		$table    = $cmap + u32( $data, $record + 4 );
		$format   = u16( $data, $table );
		// A full-range table over a BMP one, Windows over Unicode.
		$score = match ( true ) {
			12 === $format && 3 === $platform && 10 === $encoding => 4,
			12 === $format && 0 === $platform => 3,
			4 === $format && 3 === $platform && 1 === $encoding => 2,
			4 === $format && 0 === $platform => 1,
			default => 0,
		};
		if ( $score > $rank ) {
			$rank = $score;
			$best = $table;
		}
	}
	if ( ! $rank ) {
		return array();
	}

	$glyphs = array();
	$format = u16( $data, $best );
	if ( 12 === $format ) {
		$groups = u32( $data, $best + 12 );
		foreach ( $codepoints as $cp ) {
			for ( $g = 0; $g < $groups; $g++ ) {
				$group = $best + 16 + 12 * $g;
				$start = u32( $data, $group );
				if ( $cp >= $start && $cp <= u32( $data, $group + 4 ) ) {
					$glyphs[] = u32( $data, $group + 8 ) + $cp - $start;
					break;
				}
			}
		}
	} else {
		$segments = u16( $data, $best + 6 ) >> 1;
		$ends     = $best + 14;
		$starts   = $ends + 2 * $segments + 2;
		$deltas   = $starts + 2 * $segments;
		$ranges   = $deltas + 2 * $segments;
		foreach ( $codepoints as $cp ) {
			for ( $s = 0; $s < $segments; $s++ ) {
				if ( u16( $data, $ends + 2 * $s ) < $cp ) {
					continue;
				}
				$start = u16( $data, $starts + 2 * $s );
				if ( $start > $cp ) {
					break;
				}
				$delta = u16( $data, $deltas + 2 * $s );
				$range = u16( $data, $ranges + 2 * $s );
				if ( 0 === $range ) {
					$glyph = ( $cp + $delta ) & 0xffff;
				} else {
					$glyph = u16( $data, $ranges + 2 * $s + $range + 2 * ( $cp - $start ) );
					$glyph = $glyph ? ( $glyph + $delta ) & 0xffff : 0;
				}
				$glyphs[] = $glyph;
				break;
			}
		}
	}
	return array_values( array_unique( array_filter( $glyphs ) ) );
}

/**
 * The pairs the GPOS `kern` feature moves, between the glyphs given, as
 * (left << 16 | right) => x advance in font units. Where two lookups name a
 * pair, the first wins.
 *
 * @param string           $data   Font.
 * @param int              $gpos   Where the GPOS table begins.
 * @param array<int,true>  $glyphs The glyphs to kern, as keys.
 * @return array<int,int>
 */
function pairs( string $data, int $gpos, array $glyphs ): array {
	$features = $gpos + u16( $data, $gpos + 6 );
	$list     = $gpos + u16( $data, $gpos + 8 );

	$lookups = array();
	$count   = u16( $data, $features );
	for ( $i = 0; $i < $count; $i++ ) {
		$record = $features + 2 + 6 * $i;
		if ( 'kern' !== substr( $data, $record, 4 ) ) {
			continue;
		}
		$feature = $features + u16( $data, $record + 4 );
		$indices = u16( $data, $feature + 2 );
		for ( $j = 0; $j < $indices; $j++ ) {
			$lookups[ u16( $data, $feature + 4 + 2 * $j ) ] = true;
		}
	}
	ksort( $lookups );

	$pairs = array();
	$total = u16( $data, $list );
	foreach ( array_keys( $lookups ) as $index ) {
		if ( $index >= $total ) {
			continue;
		}
		$lookup = $list + u16( $data, $list + 2 + 2 * $index );
		$type   = u16( $data, $lookup );
		$subs   = u16( $data, $lookup + 4 );
		for ( $s = 0; $s < $subs; $s++ ) {
			$sub  = $lookup + u16( $data, $lookup + 6 + 2 * $s );
			$kind = $type;
			if ( 9 === $kind ) {
				$kind = u16( $data, $sub + 2 );
				$sub += u32( $data, $sub + 4 );
			}
			if ( 2 === $kind ) {
				pair_pos( $data, $sub, $glyphs, $pairs );
			}
		}
	}

	// Moves too small to see at a card's sizes are left out.
	return array_filter( $pairs, static fn( int $value ): bool => abs( $value ) >= 10 );
}

/**
 * One PairPos subtable's pairs, added to those found so far.
 *
 * @param string          $data   Font.
 * @param int             $sub    Where the subtable begins.
 * @param array<int,true> $glyphs The glyphs to kern.
 * @param array<int,int>  $pairs  The pairs so far.
 */
function pair_pos( string $data, int $sub, array $glyphs, array &$pairs ): void {
	$format = u16( $data, $sub );
	$first  = u16( $data, $sub + 4 );
	$second = u16( $data, $sub + 6 );
	if ( ! ( $first & 0x4 ) ) {
		return;
	}
	$advance  = 2 * popcount( $first & 0x3 );
	$record   = 2 * popcount( $first & 0xff ) + 2 * popcount( $second & 0xff );
	$coverage = coverage( $data, $sub + u16( $data, $sub + 2 ), $glyphs );

	if ( 1 === $format ) {
		$sets = u16( $data, $sub + 8 );
		foreach ( $coverage as $left => $index ) {
			if ( $index >= $sets ) {
				continue;
			}
			$set   = $sub + u16( $data, $sub + 10 + 2 * $index );
			$count = u16( $data, $set );
			for ( $k = 0; $k < $count; $k++ ) {
				$at    = $set + 2 + $k * ( 2 + $record );
				$right = u16( $data, $at );
				$key   = $left << 16 | $right;
				if ( isset( $glyphs[ $right ] ) && ! isset( $pairs[ $key ] ) ) {
					$value = i16( $data, $at + 2 + $advance );
					if ( $value ) {
						$pairs[ $key ] = $value;
					}
				}
			}
		}
		return;
	}

	if ( 2 !== $format ) {
		return;
	}
	$classes1 = class_def( $data, $sub + u16( $data, $sub + 8 ), $glyphs );
	$classes2 = class_def( $data, $sub + u16( $data, $sub + 10 ), $glyphs );
	$count1   = u16( $data, $sub + 12 );
	$count2   = u16( $data, $sub + 14 );
	$records  = $sub + 16;

	$by_class = array();
	foreach ( array_keys( $glyphs ) as $glyph ) {
		$by_class[ $classes2[ $glyph ] ?? 0 ][] = $glyph;
	}
	foreach ( array_keys( $coverage ) as $left ) {
		$class1 = $classes1[ $left ] ?? 0;
		if ( $class1 >= $count1 ) {
			continue;
		}
		foreach ( $by_class as $class2 => $rights ) {
			if ( $class2 >= $count2 ) {
				continue;
			}
			$value = i16( $data, $records + ( $class1 * $count2 + $class2 ) * $record + $advance );
			if ( ! $value ) {
				continue;
			}
			foreach ( $rights as $right ) {
				$key = $left << 16 | $right;
				if ( ! isset( $pairs[ $key ] ) ) {
					$pairs[ $key ] = $value;
				}
			}
		}
	}
}

/**
 * A Coverage table, as glyph => coverage index, for the glyphs given.
 *
 * @param string          $data   Font.
 * @param int             $table  Where it begins.
 * @param array<int,true> $glyphs The glyphs wanted.
 * @return array<int,int>
 */
function coverage( string $data, int $table, array $glyphs ): array {
	$out   = array();
	$count = u16( $data, $table + 2 );
	if ( 1 === u16( $data, $table ) ) {
		for ( $i = 0; $i < $count; $i++ ) {
			$glyph = u16( $data, $table + 4 + 2 * $i );
			if ( isset( $glyphs[ $glyph ] ) ) {
				$out[ $glyph ] = $i;
			}
		}
		return $out;
	}
	for ( $i = 0; $i < $count; $i++ ) {
		$range = $table + 4 + 6 * $i;
		$start = u16( $data, $range );
		$end   = u16( $data, $range + 2 );
		$index = u16( $data, $range + 4 );
		foreach ( array_keys( $glyphs ) as $glyph ) {
			if ( $glyph >= $start && $glyph <= $end ) {
				$out[ $glyph ] = $index + $glyph - $start;
			}
		}
	}
	return $out;
}

/**
 * A ClassDef table, as glyph => class, for the glyphs given. A glyph it does
 * not name is in class 0.
 *
 * @param string          $data   Font.
 * @param int             $table  Where it begins.
 * @param array<int,true> $glyphs The glyphs wanted.
 * @return array<int,int>
 */
function class_def( string $data, int $table, array $glyphs ): array {
	$out = array();
	if ( 1 === u16( $data, $table ) ) {
		$start = u16( $data, $table + 2 );
		$count = u16( $data, $table + 4 );
		foreach ( array_keys( $glyphs ) as $glyph ) {
			if ( $glyph >= $start && $glyph < $start + $count ) {
				$out[ $glyph ] = u16( $data, $table + 6 + 2 * ( $glyph - $start ) );
			}
		}
		return $out;
	}
	$count = u16( $data, $table + 2 );
	for ( $i = 0; $i < $count; $i++ ) {
		$range = $table + 4 + 6 * $i;
		$start = u16( $data, $range );
		$end   = u16( $data, $range + 2 );
		$class = u16( $data, $range + 4 );
		foreach ( array_keys( $glyphs ) as $glyph ) {
			if ( $glyph >= $start && $glyph <= $end ) {
				$out[ $glyph ] = $class;
			}
		}
	}
	return $out;
}

/**
 * A `kern` table, version 0, of format 0 subtables: as many as the pairs
 * need, since one holds at most 10,920.
 *
 * @param array<int,int> $pairs (left << 16 | right) => value.
 */
function kern( array $pairs ): string {
	ksort( $pairs );
	$chunks = array_chunk( $pairs, 10920, true );
	$out    = pack( 'nn', 0, count( $chunks ) );
	foreach ( $chunks as $chunk ) {
		$n     = count( $chunk );
		$power = 1;
		$log   = 0;
		while ( $power * 2 <= $n ) {
			$power *= 2;
			++$log;
		}
		$out .= pack( 'nnnnnnn', 0, 14 + 6 * $n, 0x0001, $n, 6 * $power, $log, 6 * ( $n - $power ) );
		foreach ( $chunk as $key => $value ) {
			$out .= pack( 'nnn', $key >> 16, $key & 0xffff, $value & 0xffff );
		}
	}
	return $out;
}

/**
 * A font file from its tables: the directory sorted by tag, each table on a
 * four-byte boundary with its checksum, and the head table's adjustment.
 *
 * @param string               $flavor The first four bytes: TrueType or CFF.
 * @param array<string,string> $tables Tag => bytes.
 */
function build( string $flavor, array $tables ): string {
	ksort( $tables, SORT_STRING );
	if ( isset( $tables['head'] ) && strlen( $tables['head'] ) >= 12 ) {
		$tables['head'] = substr_replace( $tables['head'], "\0\0\0\0", 8, 4 );
	}

	$n     = count( $tables );
	$power = 1;
	$log   = 0;
	while ( $power * 2 <= $n ) {
		$power *= 2;
		++$log;
	}
	$head   = $flavor . pack( 'nnnn', $n, 16 * $power, $log, 16 * $n - 16 * $power );
	$offset = 12 + 16 * $n;
	$dir    = '';
	$body   = '';
	$where  = array();
	foreach ( $tables as $tag => $bytes ) {
		$length        = strlen( $bytes );
		$padded        = $bytes . str_repeat( "\0", ( 4 - $length % 4 ) % 4 );
		$dir          .= str_pad( (string) $tag, 4 ) . pack( 'NNN', checksum( $padded ), $offset, $length );
		$where[ $tag ] = $offset;
		$body         .= $padded;
		$offset       += strlen( $padded );
	}
	$font = $head . $dir . $body;

	if ( isset( $where['head'] ) ) {
		$adjust = ( 0xb1b0afba - checksum( $font ) ) & 0xffffffff;
		$font   = substr_replace( $font, pack( 'N', $adjust ), $where['head'] + 8, 4 );
	}
	return $font;
}

/**
 * The checksum of a table: its 32-bit words summed, as the format says.
 *
 * @param string $bytes Bytes, a multiple of four long.
 */
function checksum( string $bytes ): int {
	$bytes .= str_repeat( "\0", ( 4 - strlen( $bytes ) % 4 ) % 4 );
	$sum    = 0;
	foreach ( str_split( $bytes, 65536 ) as $chunk ) {
		foreach ( (array) unpack( 'N*', $chunk ) as $word ) {
			$sum = ( $sum + (int) $word ) & 0xffffffff;
		}
	}
	return $sum;
}

/**
 * How many bits are set.
 *
 * @param int $value Value.
 */
function popcount( int $value ): int {
	return substr_count( decbin( $value ), '1' );
}

/**
 * An unsigned 16-bit number.
 *
 * @param string $data   Bytes.
 * @param int    $offset Where.
 */
function u16( string $data, int $offset ): int {
	return $offset + 2 <= strlen( $data ) ? (int) unpack( 'n', $data, $offset )[1] : 0;
}

/**
 * A signed 16-bit number.
 *
 * @param string $data   Bytes.
 * @param int    $offset Where.
 */
function i16( string $data, int $offset ): int {
	$value = u16( $data, $offset );
	return $value >= 0x8000 ? $value - 0x10000 : $value;
}

/**
 * An unsigned 32-bit number.
 *
 * @param string $data   Bytes.
 * @param int    $offset Where.
 */
function u32( string $data, int $offset ): int {
	return $offset + 4 <= strlen( $data ) ? (int) unpack( 'N', $data, $offset )[1] : 0;
}
