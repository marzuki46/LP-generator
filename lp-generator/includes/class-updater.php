<?php
/**
 * GitHub-hosted plugin update checker.
 *
 * The plugin reads a signed-by-HTTPS manifest from its own repository and
 * exposes an "Update now" action in wp-admin/plugins.php when a newer package
 * is available. No credentials are stored in the plugin.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Updater
 */
final class Updater {

	/**
	 * Locked manifest location. Change only in plugin code.
	 */
	public const MANIFEST_URL = 'https://raw.githubusercontent.com/marzuki46/LP-generator/main/version.json';

	/**
	 * Plugin slug used by the WordPress updater.
	 */
	public const SLUG = 'lp-generator';

	/**
	 * Register update hooks.
	 */
	public static function hook(): void {
		add_filter( 'site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'plugins_api', array( self::class, 'plugin_information' ), 20, 3 );
	}

	/**
	 * Normalize and validate a manifest payload.
	 *
	 * @param mixed $data Raw manifest.
	 * @return array<string,string>
	 */
	public static function sanitize_manifest( $data ): array {
		if ( ! is_array( $data ) ) {
			return array();
		}
		$version = sanitize_text_field( (string) ( $data['version'] ?? '' ) );
		if ( '' === $version || ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return array();
		}
		$package = esc_url_raw( (string) ( $data['package'] ?? '' ) );
		if ( '' === $package || 0 !== strpos( $package, 'https://' ) ) {
			return array();
		}
		return array(
			'version'      => $version,
			'package'      => $package,
			'homepage'     => esc_url_raw( (string) ( $data['homepage'] ?? 'https://github.com/marzuki46/LP-generator' ) ),
			'requires'     => sanitize_text_field( (string) ( $data['requires'] ?? '6.4' ) ),
			'requires_php' => sanitize_text_field( (string) ( $data['requires_php'] ?? '8.1' ) ),
			'tested'       => sanitize_text_field( (string) ( $data['tested'] ?? '' ) ),
			'changelog'    => wp_kses_post( (string) ( $data['changelog'] ?? '' ) ),
		);
	}

	/**
	 * Fetch the cached manifest.
	 *
	 * @return array<string,string>
	 */
	public static function manifest(): array {
		$cached = get_site_transient( 'lpgen_update_manifest' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get(
			self::MANIFEST_URL,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( 'lpgen_update_manifest', array(), HOUR_IN_SECONDS );
			return array();
		}
		$manifest = self::sanitize_manifest( json_decode( wp_remote_retrieve_body( $response ), true ) );
		set_site_transient( 'lpgen_update_manifest', $manifest, 6 * HOUR_IN_SECONDS );
		return $manifest;
	}

	/**
	 * Add our update to the plugin update transient.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			return $transient;
		}
		$manifest = self::manifest();
		if ( empty( $manifest['version'] ) || ! version_compare( $manifest['version'], LPG_VERSION, '>' ) ) {
			return $transient;
		}
		$plugin = plugin_basename( LPG_FILE );
		$transient->response[ $plugin ] = (object) array(
			'id'           => $plugin,
			'slug'         => self::SLUG,
			'plugin'       => $plugin,
			'new_version'  => $manifest['version'],
			'url'          => $manifest['homepage'],
			'package'      => $manifest['package'],
			'requires'     => $manifest['requires'],
			'requires_php' => $manifest['requires_php'],
			'tested'       => $manifest['tested'],
			'icons'        => array(),
			'banners'      => array(),
		);
		return $transient;
	}

	/**
	 * Provide the "View details" popup content.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action API action.
	 * @param object $args   Request args.
	 * @return mixed
	 */
	public static function plugin_information( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || self::SLUG !== (string) ( $args->slug ?? '' ) ) {
			return $result;
		}
		$manifest = self::manifest();
		if ( empty( $manifest['version'] ) ) {
			return $result;
		}
		return (object) array(
			'name'          => 'LP Generator',
			'slug'          => self::SLUG,
			'version'       => $manifest['version'],
			'requires'      => $manifest['requires'],
			'requires_php'  => $manifest['requires_php'],
			'tested'        => $manifest['tested'],
			'homepage'      => $manifest['homepage'],
			'download_link' => $manifest['package'],
			'sections'      => array(
				'description' => __( 'Buat landing page dan funnel konten TOFU/MOFU dari satu campaign, lalu submit URL ke indexer.', 'lp-generator' ),
				'changelog'   => $manifest['changelog'],
			),
		);
	}
}