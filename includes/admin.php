<?php
/**
 * Where editors meet the cards: the Share image panel in the editor's
 * sidebar, and a notice for administrators when the server cannot draw.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\editor_assets' );
/**
 * The Share image panel, for the kinds of post that have its fields. Plain
 * JavaScript on WordPress's own packages: nothing to build.
 */
function editor_assets(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->post_type ) || ! in_array( (string) $screen->post_type, field_types(), true ) ) {
		return;
	}
	wp_enqueue_script(
		'eightam-og-image-editor',
		plugins_url( 'assets/editor.js', FILE ),
		array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-block-editor', 'wp-i18n', 'wp-url' ),
		VERSION,
		true
	);
	wp_add_inline_script(
		'eightam-og-image-editor',
		'window.eightamOgImage = ' . wp_json_encode(
			array(
				'preview' => rest_url( ROUTE . '/preview/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'draws'   => can_draw(),
				'keys'    => array(
					'image' => META_IMAGE,
					'text'  => META_TEXT,
					'photo' => META_PHOTO,
				),
			)
		) . ';',
		'before'
	);
	wp_set_script_translations( 'eightam-og-image-editor', 'eightam-og-image' );
}

add_action( 'admin_notices', __NAMESPACE__ . '\\notice' );
/**
 * Say so, on the dashboard and the plugins screen, when cards cannot be drawn
 * here, and what links show instead.
 */
function notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! current_user_can( 'activate_plugins' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || can_draw() ) {
		return;
	}
	$why = Canvas\available()
		? __( 'there is no TrueType font to draw with.', 'eightam-og-image' )
		: __( 'PHP’s GD extension with FreeType is missing.', 'eightam-og-image' );
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: why, such as "PHP’s GD extension with FreeType is missing." */
				__( '8am OG Image cannot draw share images on this server: %s Links to a page show its featured image instead.', 'eightam-og-image' ),
				$why
			)
		)
	);
}
