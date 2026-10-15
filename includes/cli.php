<?php
/**
 * The WP-CLI commands: wp eightam-og-image draw, card, status, flush.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draw the cards that are missing or out of date.
 *
 * Without post IDs, every published post of every kind that has a card and
 * is drawn when saved; a kind that is drawn when first viewed instead (see
 * `eightam_og_image_draw_on_save`) only when named with --type.
 *
 * ## OPTIONS
 *
 * [<post>...]
 * : Post IDs.
 *
 * [--type=<type>]
 * : Only posts of this type.
 *
 * [--force]
 * : Draw them again even where they are up to date.
 *
 * ## EXAMPLES
 *
 *     wp eightam-og-image draw
 *     wp eightam-og-image draw 12 40
 *     wp eightam-og-image draw --type=product
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Flags.
 */
function cli_draw( array $args, array $assoc_args ): void {
	if ( ! can_draw() ) {
		\WP_CLI::error( 'This server cannot draw cards: ' . ( Canvas\available() ? 'no font.' : 'GD with FreeType is missing.' ) );
	}

	$type  = (string) ( $assoc_args['type'] ?? '' );
	$named = array_map( 'intval', $args );
	if ( $named ) {
		$ids = $named;
	} else {
		$ids = get_posts(
			array(
				'post_type'        => '' !== $type ? $type : post_types(),
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
	}

	$counts = array(
		'drawn'   => 0,
		'current' => 0,
		'skipped' => 0,
	);
	foreach ( array_map( 'intval', $ids ) as $id ) {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, post_types(), true ) || ! is_post_publicly_viewable( $post ) ) {
			\WP_CLI::warning( sprintf( '%d has no card: it is not a public post of a kind that has one.', $id ) );
			++$counts['skipped'];
			continue;
		}
		/** This filter is documented in includes/post.php */
		if ( ! $named && '' === $type && ! apply_filters( 'eightam_og_image_draw_on_save', true, $post ) ) {
			++$counts['skipped'];
			continue;
		}
		if ( ! empty( $assoc_args['force'] ) ) {
			forget( 'post-' . $id );
		}

		[ $card, $style ] = in_locale(
			locale_of( $post ),
			static function () use ( $post ): array {
				$card = post_card( $post );
				return array( $card, style( $card, $post ) );
			}
		);
		$fresh            = is_file( folder()['dir'] . '/' . file_name( 'post-' . $id, $card, $style ) );
		$image            = $fresh ? null : card_image( 'post-' . $id, $card, $style, true );
		if ( $fresh ) {
			++$counts['current'];
		} elseif ( $image && $image['fresh'] ) {
			++$counts['drawn'];
			\WP_CLI::log( sprintf( '+ %d  %s', $id, str_replace( "\n", ' ', (string) $card['title'] ) ) );
		} else {
			\WP_CLI::warning( sprintf( '%d could not be drawn.', $id ) );
			++$counts['skipped'];
		}
	}

	\WP_CLI::success( sprintf( '%d drawn, %d up to date, %d skipped.', $counts['drawn'], $counts['current'], $counts['skipped'] ) );
}

/**
 * Draw one post's card to a file of your choosing, and keep nothing: for
 * trying a style. The card is drawn from the post as it is saved, whatever
 * its status.
 *
 * ## OPTIONS
 *
 * <post>
 * : Post ID.
 *
 * [--file=<file>]
 * : Where to write it. Default: card-<post>.jpg in the current directory.
 *
 * ## EXAMPLES
 *
 *     wp eightam-og-image card 12 --file=/tmp/card.png
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Flags.
 */
function cli_card( array $args, array $assoc_args ): void {
	$post = get_post( (int) ( $args[0] ?? 0 ) );
	if ( ! $post instanceof \WP_Post ) {
		\WP_CLI::error( 'No such post.' );
	}
	[ $card, $style ] = in_locale(
		locale_of( $post ),
		static function () use ( $post ): array {
			$card = post_card( $post );
			return array( $card, style( $card, $post ) );
		}
	);
	if ( ! can_draw( $style ) ) {
		\WP_CLI::error( 'This server cannot draw cards: ' . ( Canvas\available() ? 'no font.' : 'GD with FreeType is missing.' ) );
	}

	$file = (string) ( $assoc_args['file'] ?? 'card-' . $post->ID . '.jpg' );
	$time = microtime( true );
	if ( ! Canvas\save( image( $card, $style ), $file ) ) {
		\WP_CLI::error( 'Could not write ' . $file );
	}
	foreach ( array( 'kicker', 'title', 'subtitle', 'meta' ) as $key ) {
		\WP_CLI::log( str_pad( $key, 9 ) . str_replace( "\n", ' / ', (string) $card[ $key ] ) );
	}
	\WP_CLI::log( str_pad( 'photo', 9 ) . ( $card['photo']['file'] ?? '—' ) );
	\WP_CLI::success( sprintf( 'Wrote %s in %d ms.', $file, (int) round( ( microtime( true ) - $time ) * 1000 ) ) );
}

/**
 * Whether cards can be drawn here, with what, and how many there are.
 *
 * ## EXAMPLES
 *
 *     wp eightam-og-image status
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Flags.
 */
function cli_status( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$gd    = function_exists( 'gd_info' ) ? gd_info() : array();
	$style = style( tidy( array() ), null );
	\WP_CLI::log( 'GD        ' . ( $gd ? (string) $gd['GD Version'] . ( ! empty( $gd['FreeType Support'] ) ? ', FreeType' : ', no FreeType' ) : 'missing' ) );
	foreach ( array( 'title', 'kicker', 'subtitle', 'meta' ) as $role ) {
		\WP_CLI::log( str_pad( $role, 10 ) . ( '' !== (string) $style[ $role ]['font'] ? (string) $style[ $role ]['font'] : '—' ) );
	}

	$dir   = folder()['dir'];
	$count = 0;
	$bytes = 0;
	foreach ( is_dir( $dir ) ? (array) scandir( $dir ) : array() as $name ) {
		if ( is_string( $name ) && preg_match( '/-[0-9a-f]{12}\.(jpg|png)$/', $name ) ) {
			++$count;
			$bytes += (int) filesize( $dir . '/' . $name );
		}
	}
	\WP_CLI::log( sprintf( 'Cards     %d in %s, %s', $count, $dir, $bytes ? (string) size_format( $bytes ) : '0 B' ) );
	if ( can_draw( $style ) ) {
		\WP_CLI::success( 'Cards can be drawn.' );
	} else {
		\WP_CLI::warning( 'Cards cannot be drawn here; links show the featured image.' );
	}
}

/**
 * Delete every card. Each is drawn again when its page is next viewed, or
 * with `wp eightam-og-image draw`.
 *
 * ## OPTIONS
 *
 * [--yes]
 * : Do not ask.
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Flags.
 */
function cli_flush( array $args, array $assoc_args ): void {
	\WP_CLI::confirm( 'Delete every card?', $assoc_args );
	\WP_CLI::success( sprintf( 'Deleted %d cards.', flush() ) );
}

\WP_CLI::add_command( 'eightam-og-image draw', __NAMESPACE__ . '\\cli_draw' );
\WP_CLI::add_command( 'eightam-og-image card', __NAMESPACE__ . '\\cli_card' );
\WP_CLI::add_command( 'eightam-og-image status', __NAMESPACE__ . '\\cli_status' );
\WP_CLI::add_command( 'eightam-og-image flush', __NAMESPACE__ . '\\cli_flush' );
