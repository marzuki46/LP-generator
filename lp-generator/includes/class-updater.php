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
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'plugins_api', array( self::class, 'plugin_information' ), 20, 3 );
		if ( defined( 'LPG_FILE' ) ) {
			add_filter( 'plugin_action_links_' . plugin_basename( LPG_FILE ), array( self::class, 'plugin_action_links' ) );
		}
	}

	/**
	 * Add settings & check update action links to Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=lpg-settings' );
		$custom_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'lp-generator' ) . '</a>',
			'<a href="' . esc_url( add_query_arg( 'lpgen_check_update', '1', $settings_url ) ) . '">' . esc_html__( 'Cek Update', 'lp-generator' ) . '</a>',
		);
		return array_merge( $custom_links, $links );
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
	 * Fetch the manifest, optionally bypassing cache.
	 *
	 * @param bool $force Bypass cache.
	 * @return array<string,string>
	 */
	public static function manifest( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_site_transient( 'lpgen_update_manifest' );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}
		$response = wp_remote_get(
			self::MANIFEST_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'        => 'application/json',
					'Cache-Control' => 'no-cache, no-store, must-revalidate',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( 'lpgen_update_manifest', array(), 5 * MINUTE_IN_SECONDS );
			return array();
		}
		$manifest = self::sanitize_manifest( json_decode( (string) wp_remote_retrieve_body( $response ), true ) );
		set_site_transient( 'lpgen_update_manifest', $manifest, 15 * MINUTE_IN_SECONDS );
		return $manifest;
	}

	/**
	 * Perform a forced update check and return status.
	 *
	 * @return array{ok:bool,message:string,current_version:string,latest_version:string,has_update:bool,update_url:string,package:string}
	 */
	public static function check_for_updates(): array {
		delete_site_transient( 'lpgen_update_manifest' );
		delete_site_transient( 'update_plugins' );
		$manifest   = self::manifest( true );
		$current    = defined( 'LPG_VERSION' ) ? LPG_VERSION : '1.0.0';
		$latest     = (string) ( $manifest['version'] ?? '' );
		$has_update = '' !== $latest && version_compare( $latest, $current, '>' );

		$update_url = '';
		if ( defined( 'LPG_FILE' ) ) {
			$plugin_file = plugin_basename( LPG_FILE );
			$update_url  = wp_nonce_url(
				admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $plugin_file ) ),
				'upgrade-plugin_' . $plugin_file
			);

			// Prime the WordPress update cache immediately so plugins.php also displays it.
			$current_updates = get_site_transient( 'update_plugins' );
			if ( ! is_object( $current_updates ) ) {
				$current_updates = new \stdClass();
			}
			$updated_transient = self::inject_update( $current_updates );
			set_site_transient( 'update_plugins', $updated_transient );
		}

		if ( '' === $latest ) {
			return array(
				'ok'              => false,
				'message'         => __( 'Gagal memeriksa pembaruan dari GitHub manifest. Periksa koneksi server Anda.', 'lp-generator' ),
				'current_version' => $current,
				'latest_version'  => $current,
				'has_update'      => false,
				'update_url'      => '',
				'package'         => '',
			);
		}

		return array(
			'ok'              => true,
			'message'         => $has_update
				? sprintf( __( 'Versi baru v%s tersedia! (Versi terpasang: v%s)', 'lp-generator' ), $latest, $current )
				: sprintf( __( 'Plugin sudah menggunakan versi terbaru (v%s).', 'lp-generator' ), $current ),
			'current_version' => $current,
			'latest_version'  => $latest,
			'has_update'      => $has_update,
			'update_url'      => $update_url,
			'package'         => (string) ( $manifest['package'] ?? '' ),
		);
	}

	/**
	 * Add our update to the plugin update transient.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$manifest = self::manifest();
		if ( empty( $manifest['version'] ) || ! version_compare( $manifest['version'], LPG_VERSION, '>' ) ) {
			return $transient;
		}
		$plugin = plugin_basename( LPG_FILE );
		if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
			unset( $transient->no_update[ $plugin ] );
		}
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
