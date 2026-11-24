<?php
/**
 * Updates from the 8am CDN (Bunny).
 *
 * The release workflow (.github/workflows/release.yml) uploads each version
 * as eightam-og-image-X.Y.Z.zip to https://eightam-wp.b-cdn.net/eightam-og-image/
 * and then latest.json, which names it. This file reads latest.json and puts
 * a newer version into WordPress's list of plugin updates, so the Plugins
 * screen offers it, "View details" shows its changelog, and automatic updates
 * work when they are turned on. The 8am Toolkit reads the same file; both
 * write the same entry, so the plugin updates with or without it.
 *
 * The header's `Update URI` names the CDN, which keeps WordPress.org from
 * offering a plugin of the same slug in its place.
 *
 * latest.json is fetched at most every twelve hours, or one hour after a
 * failure. "Check again" on Dashboard → Updates fetches it at once. Hooked on
 * every request, not only in the admin: automatic updates run from cron.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage\Updater;

use const Eightam\OgImage\FILE;
use const Eightam\OgImage\VERSION;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG         = 'eightam-og-image';
const MANIFEST_URL = 'https://eightam-wp.b-cdn.net/eightam-og-image/latest.json';
const CACHE        = 'eightam_og_image_manifest';

/**
 * latest.json, or null if it could not be read; a failure is remembered for
 * an hour.
 *
 * @param bool $fresh Fetch it even if it is remembered.
 */
function manifest( bool $fresh = false ): ?object {
	$cached = get_site_transient( CACHE );
	if ( ! $fresh && false !== $cached ) {
		return is_object( $cached ) ? $cached : null;
	}

	$response = wp_remote_get(
		(string) apply_filters( 'eightam_og_image_update_manifest_url', MANIFEST_URL ),
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);
	$data     = null;
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$json = json_decode( (string) wp_remote_retrieve_body( $response ) );
		if ( is_object( $json ) && ! empty( $json->version ) && ! empty( $json->download_url ) ) {
			$data = $json;
		}
	}

	set_site_transient( CACHE, $data ?? 'error', $data ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $data;
}

/**
 * The plugin's file as WordPress names it, such as
 * eightam-og-image/eightam-og-image.php.
 */
function plugin(): string {
	return plugin_basename( FILE );
}

add_filter( 'site_transient_update_plugins', __NAMESPACE__ . '\\inject' );
/**
 * Put the CDN's version into WordPress's list of plugin updates: under
 * `response` when it is newer, under `no_update` otherwise, which lets the
 * Plugins screen offer automatic updates.
 *
 * @param mixed $transient The update_plugins site transient.
 * @return mixed
 */
function inject( $transient ) {
	static $forced = false;
	if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
		return $transient;
	}
	// "Check again" on Dashboard → Updates fetches latest.json at once, once per request.
	$fresh  = ! $forced && is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's own flag.
	$forced = $forced || $fresh;
	$m      = manifest( $fresh );
	if ( ! $m ) {
		return $transient;
	}

	$file    = plugin();
	$current = (string) ( $transient->checked[ $file ] ?? VERSION );
	$item    = (object) array(
		'id'           => $file,
		'slug'         => SLUG,
		'plugin'       => $file,
		'new_version'  => (string) $m->version,
		'url'          => (string) ( $m->homepage ?? '' ),
		'package'      => (string) $m->download_url,
		'tested'       => (string) ( $m->tested ?? '' ),
		'requires'     => (string) ( $m->requires ?? '' ),
		'requires_php' => (string) ( $m->requires_php ?? '' ),
	);

	if ( version_compare( (string) $m->version, $current, '>' ) ) {
		$transient->response[ $file ] = $item;
		unset( $transient->no_update[ $file ] );
	} else {
		$transient->no_update[ $file ] = $item;
	}
	return $transient;
}

add_filter( 'plugins_api', __NAMESPACE__ . '\\details', 20, 3 );
/**
 * The "View details" window of the Plugins screen.
 *
 * @param false|object|array $result An answer so far.
 * @param string             $action What is asked.
 * @param object             $args   For which plugin.
 * @return false|object|array
 */
function details( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || SLUG !== ( $args->slug ?? '' ) ) {
		return $result;
	}
	$m = manifest();
	if ( ! $m ) {
		return $result;
	}
	return (object) array(
		'name'          => (string) ( $m->name ?? '8am OG Image' ),
		'slug'          => SLUG,
		'version'       => (string) $m->version,
		'author'        => '<a href="https://8am.ch">8am</a>',
		'homepage'      => (string) ( $m->homepage ?? '' ),
		'download_link' => (string) $m->download_url,
		'tested'        => (string) ( $m->tested ?? '' ),
		'requires'      => (string) ( $m->requires ?? '' ),
		'requires_php'  => (string) ( $m->requires_php ?? '' ),
		'last_updated'  => (string) ( $m->last_updated ?? '' ),
		'sections'      => array(
			'description' => esc_html( (string) ( $m->description ?? '' ) ),
			'changelog'   => (string) ( $m->changelog ?? '' ),
		),
	);
}

add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\keep_folder', 10, 4 );
/**
 * Install an update into the folder the plugin is in. The zip holds
 * `eightam-og-image/`; a copy installed from GitHub's own archive sits in
 * `eightam-wp-og-image-main/`, and would otherwise be deactivated by an
 * update that moved it.
 *
 * @param string|\WP_Error $source        Where the zip was unpacked.
 * @param string           $remote_source The folder it was unpacked in.
 * @param \WP_Upgrader     $upgrader      The upgrader.
 * @param array            $hook_extra    Which plugin.
 * @return string|\WP_Error
 */
function keep_folder( $source, $remote_source, $upgrader, $hook_extra ) {
	global $wp_filesystem;
	if ( is_wp_error( $source ) || plugin() !== ( $hook_extra['plugin'] ?? '' ) || ! $wp_filesystem ) {
		return $source;
	}
	$wanted = trailingslashit( $remote_source ) . dirname( plugin() ) . '/';
	if ( untrailingslashit( (string) $source ) === untrailingslashit( $wanted ) ) {
		return $source;
	}
	return $wp_filesystem->move( (string) $source, $wanted, true ) ? $wanted : new \WP_Error( 'eightam_og_image_folder', __( 'The update could not be moved into the plugin’s folder.', 'eightam-og-image' ) );
}

add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\forget', 10, 2 );
/**
 * After a plugin update, fetch latest.json afresh next time.
 *
 * @param mixed $upgrader The upgrader.
 * @param array $options  What was done.
 */
function forget( $upgrader = null, $options = array() ): void {
	if ( 'plugin' === ( $options['type'] ?? '' ) ) {
		delete_site_transient( CACHE );
		delete_site_transient( 'eightam_og_image_release' ); // The GitHub updater's, up to 1.4.1.
	}
}
