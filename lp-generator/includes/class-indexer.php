<?php
/**
 * Instant indexing submitter: Google PuSH + IndexNow with a 10s delay.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Indexer
 */
final class Indexer {

	/**
	 * Locked WebSub/Google PuSH hub endpoint.
	 */
	public const PUSH_HUB = 'https://pubsubhubbub.appspot.com/publish';

	/**
	 * Default IndexNow endpoint.
	 */
	public const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Free WebSub hubs. They receive a ping and fetch the public RSS feed.
	 *
	 * @var string[]
	 */
	public const RSS_ENDPOINTS = array(
		'https://pubsubhubbub.appspot.com/publish',
		'https://pubsubhubbub.superfeedr.com/',
	);

	/**
	 * Build the x-www-form-urlencoded body for Google PuSH publish.
	 *
	 * @param string $feed_url Feed URL.
	 * @return array<string,string>
	 */
	public static function build_push_body( string $feed_url ): array {
		return array(
			'hub.mode' => 'publish',
			'hub.url'  => $feed_url,
		);
	}

	/**
	 * Build the IndexNow JSON payload.
	 *
	 * @param string $url          Published permalink.
	 * @param string $key          IndexNow key.
	 * @param string $key_location Optional full URL of key file.
	 * @param string $host         Site host.
	 * @return array<string,mixed>
	 */
	public static function build_indexnow_payload( string $url, string $key, string $key_location, string $host ): array {
		$location = '' !== $key_location ? $key_location : trailingslashit( home_url( '/' ) ) . $key . '.txt';
		return array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => $location,
			'urlList'     => array( $url ),
		);
	}

	/**
	 * Validate an IndexNow key.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_valid_key( string $key ): bool {
		return Utils::is_valid_index_key( $key );
	}

	/**
	 * Schedule an index job exactly N seconds after publishing.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param int    $item_id     Item ID.
	 * @param string $permalink   Published URL.
	 * @param int    $delay       Delay in seconds.
	 * @return int Job ID.
	 */
	public static function schedule( int $campaign_id, int $item_id, string $permalink, int $delay ): int {
		$settings = get_option( 'lpgen_settings', array() );
		if ( empty( $settings['index_enabled'] ) ) {
			return 0;
		}
		$delay = Utils::index_delay( $settings );
		$when  = time() + $delay;
		$job_id = Database::insert_job(
			array(
				'campaign_id' => $campaign_id,
				'item_id'     => $item_id,
				'kind'        => 'index',
				'payload'     => array( 'permalink' => $permalink, 'delay' => $delay ),
				'run_at'      => gmdate( 'Y-m-d H:i:s', $when ),
			)
		);
		if ( $job_id > 0 ) {
			wp_schedule_single_event( $when, 'lpgen_run_job' );
		}
		return $job_id;
	}

	/**
	 * Execute an index job.
	 *
	 * @param array $job Job row.
	 * @return array<string,mixed> Results.
	 */
	public static function run( array $job ): array {
		$settings  = get_option( 'lpgen_settings', array() );
		$payload   = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		$permalink = is_array( $payload ) ? (string) ( $payload['permalink'] ?? '' ) : '';
		$campaign  = (int) ( $job['campaign_id'] ?? 0 );
		$job_id    = (int) ( $job['id'] ?? 0 );

		if ( '' === $permalink || false === wp_http_validate_url( $permalink ) ) {
			Database::log( 'error', 'Index job skipped: invalid permalink', $campaign, $job_id );
			return array( 'ok' => false, 'reason' => 'invalid_permalink' );
		}

		$results = array(
			'url'   => $permalink,
			'push'  => array( 'ok' => false, 'code' => 0 ),
			'now'   => array( 'ok' => false, 'code' => 0 ),
		);

		if ( ! empty( $settings['push_enabled'] ) ) {
			$endpoints = Utils::parse_csv( (string) ( $settings['rss_endpoints'] ?? '' ) );
			if ( empty( $endpoints ) ) {
				$endpoints = self::RSS_ENDPOINTS;
			}
			$results['push']['providers'] = array();
			foreach ( $endpoints as $endpoint ) {
				if ( false === wp_http_validate_url( $endpoint ) ) {
					continue;
				}
				$response = wp_remote_post(
					$endpoint,
					array(
						'timeout' => 15,
						'body'    => self::build_push_body( get_feed_link( 'rss2' ) ),
					)
				);
				$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
				$ok   = $code >= 200 && $code < 300;
				$results['push']['providers'][ $endpoint ] = array( 'ok' => $ok, 'code' => $code );
				$results['push']['ok'] = $results['push']['ok'] || $ok;
				$results['push']['code'] = $code;
				Database::log(
					$ok ? 'info' : 'warning',
					sprintf( 'RSS/WebSub %s returned HTTP %d', wp_parse_url( $endpoint, PHP_URL_HOST ), $code ),
					$campaign,
					$job_id,
					array( 'url' => $permalink, 'endpoint' => $endpoint )
				);
			}
		}

		$key = (string) ( $settings['indexnow_key'] ?? '' );
		if ( self::is_valid_key( $key ) ) {
			$endpoints = Utils::parse_csv( (string) ( $settings['indexnow_endpoints'] ?? self::INDEXNOW_ENDPOINT ) );
			if ( empty( $endpoints ) ) {
				$endpoints = array( self::INDEXNOW_ENDPOINT );
			}
			$payload = self::build_indexnow_payload(
				$permalink,
				$key,
				(string) ( $settings['indexnow_key_location'] ?? '' ),
				(string) wp_parse_url( home_url(), PHP_URL_HOST )
			);
			foreach ( $endpoints as $endpoint ) {
				if ( false === wp_http_validate_url( $endpoint ) ) {
					continue;
				}
				$response = wp_remote_post(
					$endpoint,
					array(
						'timeout' => 15,
						'headers' => array( 'Content-Type' => 'application/json' ),
						'body'    => wp_json_encode( $payload ),
					)
				);
				$code      = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
				$ok        = 200 === $code || 202 === $code;
				$results['now'] = array(
					'ok'       => $ok,
					'code'     => $code,
					'endpoint' => $endpoint,
				);
				Database::log(
					$ok ? 'info' : 'warning',
					sprintf( 'IndexNow %s returned HTTP %d', wp_parse_url( $endpoint, PHP_URL_HOST ), $code ),
					$campaign,
					$job_id,
					array( 'url' => $permalink )
				);
			}
		} else {
			Database::log( 'warning', 'IndexNow skipped: key belum dikonfigurasi', $campaign, $job_id );
		}

		$results['ok'] = $results['push']['ok'] || $results['now']['ok'];
		return $results;
	}

	/**
	 * Check whether the key file is already published and valid.
	 *
	 * @param string $key IndexNow key.
	 * @return bool
	 */
	public static function key_file_ready( string $key ): bool {
		if ( ! self::is_valid_key( $key ) ) {
			return false;
		}
		$path = ABSPATH . $key . '.txt';
		if ( ! is_file( $path ) ) {
			return false;
		}
		return trim( (string) file_get_contents( $path, false, null, 0, 256 ) ) === $key; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Ensure the key file exists without overwriting a different key file.
	 *
	 * @param string $key Validated key.
	 * @return array{ok:bool,message:string,created:bool}
	 */
	public static function ensure_key_file( string $key ): array {
		if ( ! self::is_valid_key( $key ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Format key tidak valid.', 'lp-generator' ),
				'created' => false,
			);
		}
		if ( self::key_file_ready( $key ) ) {
			return array(
				'ok'      => true,
				'message' => sprintf( __( 'File key sudah aktif: %s', 'lp-generator' ), $key . '.txt' ),
				'created' => false,
			);
		}
		$path = ABSPATH . $key . '.txt';
		if ( is_file( $path ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf( __( 'File %s sudah ada dengan key berbeda. Hapus/rename manual bila ingin memakai key ini.', 'lp-generator' ), $key . '.txt' ),
				'created' => false,
			);
		}
		$created = self::create_key_file( $key );
		return array(
			'ok'      => $created['ok'],
			'message' => $created['message'],
			'created' => $created['ok'],
		);
	}

	/**
	 * Create the IndexNow key file in ABSPATH.
	 *
	 * @param string $key Validated key.
	 * @return array{ok:bool,message:string}
	 */
	public static function create_key_file( string $key ): array {
		if ( ! self::is_valid_key( $key ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Format key tidak valid.', 'lp-generator' ),
			);
		}
		if ( ! is_writable( ABSPATH ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'ABSPATH tidak writable. Upload file key secara manual.', 'lp-generator' ),
			);
		}
		$path    = ABSPATH . $key . '.txt';
		$written = file_put_contents( $path, $key, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			return array(
				'ok'      => false,
				'message' => __( 'Gagal menulis file key.', 'lp-generator' ),
			);
		}
		return array(
			'ok'      => true,
			'message' => sprintf( __( 'File dibuat: %s', 'lp-generator' ), $key . '.txt' ),
		);
	}
}
