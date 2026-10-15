<?php
/**
 * Removes the cards, the typefaces fetched for them, the settings, the
 * fields and the schedule. The cards are this plugin's
 * files and nothing else draws them; the images editors chose in their place
 * stay in the media library, which is where they came from.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_unschedule_hook( 'eightam_og_image_draw' );
delete_option( 'eightam_og_image' );
delete_option( 'eightam_og_image_fonts' );

foreach ( array( '_eightam_og_image', '_eightam_og_text', '_eightam_og_photo' ) as $eightam_og_image_key ) {
	delete_post_meta_by_key( $eightam_og_image_key );
}

$eightam_og_image_dir = wp_get_upload_dir()['basedir'] . '/eightam-og-image';
foreach ( array( 'fonts', 'logos' ) as $eightam_og_image_sub ) {
	foreach ( (array) glob( $eightam_og_image_dir . '/' . $eightam_og_image_sub . '/*.{ttf,png}', GLOB_BRACE ) as $eightam_og_image_file ) {
		if ( is_string( $eightam_og_image_file ) ) {
			wp_delete_file( $eightam_og_image_file );
		}
	}
	@rmdir( $eightam_og_image_dir . '/' . $eightam_og_image_sub ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
}
if ( is_dir( $eightam_og_image_dir ) ) {
	foreach ( (array) scandir( $eightam_og_image_dir ) as $eightam_og_image_name ) {
		if ( is_string( $eightam_og_image_name ) && preg_match( '/\.(jpg|png)$/', $eightam_og_image_name ) ) {
			wp_delete_file( $eightam_og_image_dir . '/' . $eightam_og_image_name );
		}
	}
	@rmdir( $eightam_og_image_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
}
