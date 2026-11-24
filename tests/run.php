<?php
/**
 * Tests for the updater, without WordPress: php tests/run.php
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'Eightam\\OgImage\\FILE', '/srv/wp-content/plugins/eightam-og-image/eightam-og-image.php' );
	define( 'Eightam\\OgImage\\VERSION', '1.4.1' );

	$GLOBALS['t'] = array();

	function reset_state( $responses = array() ): void {
		$GLOBALS['t'] = array(
			'transients' => array(),
			'expiry'     => array(),
			'responses'  => $responses,
			'requests'   => array(),
		);
	}
	function add_filter( ...$args ) {}
	function add_action( ...$args ) {}
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
	function is_admin() {
		return false;
	}
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
	function get_site_transient( $key ) {
		return $GLOBALS['t']['transients'][ $key ] ?? false;
	}
	function set_site_transient( $key, $value, $expiry ) {
		$GLOBALS['t']['transients'][ $key ] = $value;
		$GLOBALS['t']['expiry'][ $key ]     = $expiry;
	}
	function delete_site_transient( $key ) {
		unset( $GLOBALS['t']['transients'][ $key ] );
	}
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['t']['requests'][] = $url;
		return array_shift( $GLOBALS['t']['responses'] ) ?? array( 'code' => 500, 'body' => '' );
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function wp_remote_retrieve_response_code( $r ) {
		return $r['code'];
	}
	function wp_remote_retrieve_body( $r ) {
		return $r['body'];
	}
	class WP_Error {}

	require dirname( __DIR__ ) . '/includes/updater.php';

	$failed = 0;
	function check( string $name, bool $ok ): void {
		global $failed;
		echo ( $ok ? '  ok   ' : '  FAIL ' ) . $name . "\n";
		$failed += $ok ? 0 : 1;
	}

	function ok( string $version ): array {
		return array(
			'code' => 200,
			'body' => json_encode(
				array(
					'name'         => '8am OG Image',
					'version'      => $version,
					'download_url' => "https://eightam-wp.b-cdn.net/eightam-og-image/eightam-og-image-$version.zip",
					'changelog'    => '<h4>' . $version . '</h4>',
				)
			),
		);
	}
	function transient( string $installed ): object {
		return (object) array(
			'checked'   => array( 'eightam-og-image/eightam-og-image.php' => $installed ),
			'response'  => array(),
			'no_update' => array(),
		);
	}

	use Eightam\OgImage\Updater;
	$file = 'eightam-og-image/eightam-og-image.php';

	echo "Updater\n";
	reset_state( array( ok( '1.5.0' ) ) );
	$tr = Updater\inject( transient( '1.4.1' ) );
	check( 'newer version offered', ( $tr->response[ $file ]->new_version ?? '' ) === '1.5.0' && ( $tr->response[ $file ]->package ?? '' ) === 'https://eightam-wp.b-cdn.net/eightam-og-image/eightam-og-image-1.5.0.zip' );
	check( 'slug is the Toolkit\'s', ( $tr->response[ $file ]->slug ?? '' ) === 'eightam-og-image' );
	check( 'cached for 12 h', $GLOBALS['t']['expiry'][ Updater\CACHE ] === 12 * 3600 );
	Updater\inject( transient( '1.4.1' ) );
	check( 'second check served from cache', count( $GLOBALS['t']['requests'] ) === 1 );

	reset_state( array( ok( '1.4.1' ) ) );
	$tr = Updater\inject( transient( '1.4.1' ) );
	check( 'same version under no_update', empty( $tr->response ) && isset( $tr->no_update[ $file ] ) );

	reset_state( array( ok( '1.5.0' ) ) );
	$empty = (object) array( 'checked' => array() );
	check( 'empty checked left alone, nothing fetched', Updater\inject( $empty ) === $empty && array() === $GLOBALS['t']['requests'] );

	reset_state( array( array( 'code' => 500, 'body' => '' ) ) );
	$tr = Updater\inject( transient( '1.4.1' ) );
	check( 'CDN error offers nothing', empty( $tr->response ) && empty( $tr->no_update ) );
	check( 'failure cached for 1 h', 'error' === $GLOBALS['t']['transients'][ Updater\CACHE ] && 3600 === $GLOBALS['t']['expiry'][ Updater\CACHE ] );
	Updater\inject( transient( '1.4.1' ) );
	check( 'failure not refetched', count( $GLOBALS['t']['requests'] ) === 1 );

	reset_state( array( array( 'code' => 200, 'body' => '{"version":"1.5.0"}' ) ) );
	check( 'manifest without download_url refused', null === Updater\manifest() );

	reset_state( array( ok( '1.5.0' ) ) );
	check( 'plugins_api ignores other slugs', false === Updater\details( false, 'plugin_information', (object) array( 'slug' => 'akismet' ) ) && array() === $GLOBALS['t']['requests'] );
	$info = Updater\details( false, 'plugin_information', (object) array( 'slug' => 'eightam-og-image' ) );
	check( 'plugins_api answers with the changelog', is_object( $info ) && '1.5.0' === $info->version && '<h4>1.5.0</h4>' === $info->sections['changelog'] );

	reset_state();
	$GLOBALS['t']['transients'][ Updater\CACHE ] = 'error';
	Updater\forget( null, array( 'type' => 'theme' ) );
	check( 'theme update keeps the cache', isset( $GLOBALS['t']['transients'][ Updater\CACHE ] ) );
	Updater\forget( null, array( 'type' => 'plugin' ) );
	check( 'plugin update clears the cache', ! isset( $GLOBALS['t']['transients'][ Updater\CACHE ] ) );

	echo $failed ? "\n$failed failed\n" : "\nAll passed\n";
	exit( $failed ? 1 : 0 );
}
