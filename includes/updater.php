<?php
/**
 * Updates from the plugin's public repository on GitHub.
 *
 * The plugin's header names `Update URI: https://github.com/eightam/…`, so
 * WordPress asks no one but this file about it: not WordPress.org, where a
 * plugin of the same slug could otherwise be offered in its place. When
 * WordPress checks for updates, `update_plugins_github.com` is asked, and
 * this file answers with the repository's latest release — its version and
 * its eightam-og-image.zip, which the release workflow builds
 * (.github/workflows/release.yml). WordPress compares the version, shows the
 * update on the Plugins screen and installs the zip like any other, and
 * automatic updates work if they are turned on for the plugin.
 *
 * The latest release is asked for at most every six hours, without a token:
 * GitHub allows sixty such requests an hour from one address. "Check again"
 * on Dashboard → Updates asks at once.
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

const REPOSITORY = 'eightam/eightam-wp-og-image';
const ASSET      = 'eightam-og-image.zip';
const CACHE      = 'eightam_og_image_release';

/**
 * The latest release: version, package, url, notes and date. Null if there
 * is none, or GitHub could not be asked; a failure is remembered for an hour.
 *
 * @param bool $fresh Ask GitHub even if the answer is remembered.
 * @return array{version:string,package:string,url:string,notes:string,date:string}|null
 */
function release( bool $fresh = false ): ?array {
	$cached = get_site_transient( CACHE );
	if ( ! $fresh && is_array( $cached ) ) {
		return $cached ? $cached : null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . REPOSITORY . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'eightam-og-image/' . VERSION . '; ' . home_url( '/' ),
			),
		)
	);
	$data = is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );

	$package = '';
	foreach ( is_array( $data ) ? (array) ( $data['assets'] ?? array() ) : array() as $asset ) {
		if ( ASSET === ( $asset['name'] ?? '' ) && str_starts_with( (string) ( $asset['browser_download_url'] ?? '' ), 'https://github.com/' . REPOSITORY . '/' ) ) {
			$package = (string) $asset['browser_download_url'];
		}
	}
	$version = is_array( $data ) ? ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ) : '';
	if ( '' === $package || ! preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
		set_site_transient( CACHE, array(), HOUR_IN_SECONDS );
		return null;
	}

	$release = array(
		'version' => $version,
		'package' => $package,
		'url'     => (string) ( $data['html_url'] ?? 'https://github.com/' . REPOSITORY ),
		'notes'   => (string) ( $data['body'] ?? '' ),
		'date'    => (string) ( $data['published_at'] ?? '' ),
	);
	set_site_transient( CACHE, $release, 6 * HOUR_IN_SECONDS );
	return $release;
}

/**
 * The plugin's file as WordPress names it, such as
 * eightam-og-image/eightam-og-image.php.
 */
function plugin(): string {
	return plugin_basename( FILE );
}

add_filter( 'update_plugins_github.com', __NAMESPACE__ . '\\check', 10, 3 );
/**
 * Answer WordPress's check for updates with the latest release.
 *
 * @param array|false $update      An answer so far.
 * @param array       $plugin_data The plugin's header.
 * @param string      $plugin_file Which plugin.
 * @return array|false
 */
function check( $update, $plugin_data, $plugin_file ) {
	if ( plugin() !== $plugin_file ) {
		return $update;
	}
	// "Check again" on Dashboard → Updates asks GitHub at once.
	$fresh   = is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's own flag.
	$release = release( $fresh );
	if ( ! $release ) {
		return $update;
	}
	return array(
		'id'           => 'https://github.com/' . REPOSITORY,
		'slug'         => dirname( plugin() ),
		'version'      => $release['version'],
		'url'          => 'https://github.com/' . REPOSITORY,
		'package'      => $release['package'],
		'requires'     => (string) ( $plugin_data['RequiresWP'] ?? '' ),
		'requires_php' => (string) ( $plugin_data['RequiresPHP'] ?? '' ),
	);
}

add_filter( 'plugins_api', __NAMESPACE__ . '\\details', 20, 3 );
/**
 * The "View details" window of the Plugins screen: the release's notes.
 *
 * @param false|object|array $result An answer so far.
 * @param string             $action What is asked.
 * @param object             $args   For which plugin.
 * @return false|object|array
 */
function details( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== dirname( plugin() ) ) {
		return $result;
	}
	$release = release();
	$header  = get_plugin_data( FILE, false, true );
	return (object) array(
		'name'          => (string) $header['Name'],
		'slug'          => dirname( plugin() ),
		'version'       => $release ? $release['version'] : VERSION,
		'author'        => (string) $header['Author'],
		'homepage'      => 'https://github.com/' . REPOSITORY,
		'requires'      => (string) $header['RequiresWP'],
		'requires_php'  => (string) $header['RequiresPHP'],
		'last_updated'  => $release ? $release['date'] : '',
		'download_link' => $release ? $release['package'] : '',
		'sections'      => array(
			'description' => wpautop( esc_html( (string) $header['Description'] ) ),
			'changelog'   => $release ? '<h4>' . esc_html( $release['version'] ) . '</h4>' . notes( $release['notes'] ) : '',
		),
	);
}

/**
 * A release's notes as HTML: its lines starting "* " as a list, the rest as
 * paragraphs, `code` as code.
 *
 * @param string $text The notes, as the release workflow writes them.
 */
function notes( string $text ): string {
	$html  = '';
	$items = array();
	$flush = static function () use ( &$items, &$html ): void {
		if ( $items ) {
			$html .= '<ul><li>' . implode( '</li><li>', $items ) . '</li></ul>';
			$items = array();
		}
	};
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$line = trim( (string) $line );
		$safe = (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( (string) preg_replace( '/^[*-]\s+/', '', $line ) ) );
		if ( preg_match( '/^[*-]\s+/', $line ) ) {
			$items[] = $safe;
		} else {
			$flush();
			$html .= '' !== $line ? '<p>' . $safe . '</p>' : '';
		}
	}
	$flush();
	return $html;
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

add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\forget', 10, 0 );
/**
 * After any update, ask GitHub afresh next time.
 */
function forget(): void {
	delete_site_transient( CACHE );
}
