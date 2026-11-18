<?php
/**
 * Utility helpers. Pure helpers are kept WP-free so they can be unit tested.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Utils
 */
final class Utils {

	/**
	 * Mask a secret for display or logging.
	 *
	 * @param string $value Secret value.
	 * @return string
	 */
	public static function mask_secret( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$length = strlen( $value );
		if ( $length <= 8 ) {
			return str_repeat( '•', $length );
		}
		return substr( $value, 0, 3 ) . '…' . substr( $value, -2 );
	}

	/**
	 * Parse a comma/newline separated list into clean values.
	 *
	 * @param string $csv Comma separated values.
	 * @return string[]
	 */
	public static function parse_csv( string $csv ): array {
		$parts = preg_split( '/[\r\n,]+/', $csv ) ?: array();
		$out   = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * Parse model fallback chain.
	 *
	 * @param string $csv Comma separated model names/combos.
	 * @return string[]
	 */
	public static function parse_model_list( string $csv ): array {
		return array_slice( self::parse_csv( $csv ), 0, 10 );
	}

	/**
	 * Clamp the index delay between 0 and 300 seconds.
	 *
	 * @param array $settings Plugin settings.
	 * @return int
	 */
	public static function index_delay( array $settings ): int {
		$delay = isset( $settings['index_delay'] ) ? (int) $settings['index_delay'] : 10;
		return max( 0, min( 300, $delay ) );
	}

	/**
	 * Clamp a count between 1 and 10.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function clamp_count( $value ): int {
		return max( 1, min( 10, (int) $value ) );
	}

	/**
	 * Generate an IndexNow compatible key.
	 *
	 * @return string
	 */
	public static function generate_index_key(): string {
		return 'lpg' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Validate an IndexNow key. Prevents path traversal in key file names.
	 *
	 * @param string $key Key to validate.
	 * @return bool
	 */
	public static function is_valid_index_key( string $key ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/', $key );
	}

	/**
	 * Short JSON preview for logs. Never pass secrets here.
	 *
	 * @param mixed $data     Data to preview.
	 * @param int   $max_length Maximum characters.
	 * @return string
	 */
	public static function json_preview( $data, int $max_length = 200 ): string {
		$json = wp_json_encode( $data );
		if ( false === $json ) {
			return '';
		}
		if ( strlen( $json ) > $max_length ) {
			return substr( $json, 0, $max_length ) . '…';
		}
		return $json;
	}

	/**
	 * Sanitize a single-line input.
	 *
	 * @param mixed $raw Raw input.
	 * @return string
	 */
	public static function clean_text( $raw ): string {
		return sanitize_text_field( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	}

	/**
	 * Sanitize a multiline input.
	 *
	 * @param mixed $raw Raw input.
	 * @return string
	 */
	public static function clean_textarea( $raw ): string {
		return sanitize_textarea_field( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	}

	/**
	 * Sanitize a URL input.
	 *
	 * @param mixed $raw Raw input.
	 * @return string
	 */
	public static function clean_url( $raw ): string {
		$url = esc_url_raw( is_string( $raw ) ? wp_unslash( $raw ) : '' );
		return false === wp_http_validate_url( $url ) ? '' : $url;
	}

	/**
	 * Split a textarea into clean lines.
	 *
	 * @param string $text Textarea content.
	 * @return string[]
	 */
	public static function lines( string $text ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $text ) ?: array();
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Render a {{placeholder}} template with campaign data.
	 *
	 * Unknown placeholders are removed instead of leaking raw tokens.
	 *
	 * @param string $template   Template text.
	 * @param array  $data       Placeholder values.
	 * @param int    $max_length Optional truncation length.
	 * @return string
	 */
	public static function render_template( string $template, array $data, int $max_length = 0 ): string {
		$out = preg_replace_callback(
			'/\{\{([a-z0-9_]+)\}\}/i',
			static function ( array $matches ) use ( $data ): string {
				$key = strtolower( (string) $matches[1] );
				if ( ! array_key_exists( $key, $data ) || ! is_scalar( $data[ $key ] ) ) {
					return '';
				}
				return (string) $data[ $key ];
			},
			$template
		) ?? $template;
		$out = preg_replace( '/\s+/u', ' ', $out ) ?? $out;
		$out = preg_replace( '/\s*\|\s*\|+/', ' | ', $out ) ?? $out;
		$out = trim( $out, " \t\n\r\0\x0B|" );
		return $max_length > 0 ? self::truncate( $out, $max_length ) : $out;
	}

	/**
	 * Multibyte-safe truncation with an ellipsis.
	 *
	 * @param string $text Text.
	 * @param int    $max  Maximum visible length.
	 * @return string
	 */
	public static function truncate( string $text, int $max ): string {
		$text = trim( $text );
		if ( $max <= 0 ) {
			return $text;
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $length <= $max ) {
			return $text;
		}
		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, max( 0, $max - 1 ), 'UTF-8' ) : substr( $text, 0, max( 0, $max - 1 ) );
		return rtrim( $cut ) . '…';
	}

	/**
	 * Sanitize and de-duplicate AI suggested tags.
	 *
	 * @param mixed $raw Raw tags.
	 * @param int   $max Maximum tags (capped at 3).
	 * @return string[]
	 */
	public static function sanitize_tags( $raw, int $max = 3 ): array {
		$max   = max( 1, min( 3, $max ) );
		$items = is_array( $raw ) ? $raw : self::parse_csv( is_string( $raw ) ? $raw : '' );
		$out   = array();
		foreach ( $items as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$tag = sanitize_text_field( (string) $item );
			$tag = preg_replace( '/[^\p{L}\p{N}\s\-&+]/u', '', $tag ) ?? $tag;
			$tag = trim( preg_replace( '/\s+/u', ' ', $tag ) ?? $tag );
			if ( '' === $tag ) {
				continue;
			}
			$tag = self::truncate( $tag, 40 );
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $tag, 'UTF-8' ) : strtolower( $tag );
			if ( isset( $out[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = $tag;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return array_values( $out );
	}

	/**
	 * Validate a scheduling mode.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function clean_schedule_mode( $raw ): string {
		$mode = sanitize_key( is_string( $raw ) ? $raw : '' );
		return in_array( $mode, array( 'immediate', 'interval', 'fixed', 'daily' ), true ) ? $mode : 'immediate';
	}

	/**
	 * Validate a tag scheduling mode.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function clean_tag_schedule_mode( $raw ): string {
		$mode = sanitize_key( is_string( $raw ) ? $raw : '' );
		return in_array( $mode, array( 'after_publish', 'fixed', 'daily' ), true ) ? $mode : 'after_publish';
	}

	/**
	 * Validate HH:MM input.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function clean_time( $raw ): string {
		$time = sanitize_text_field( is_string( $raw ) ? wp_unslash( $raw ) : '' );
		return 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '09:00';
	}

	/**
	 * Validate a datetime-local input (YYYY-MM-DDTHH:MM).
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function clean_datetime_local( $raw ): string {
		$value = sanitize_text_field( is_string( $raw ) ? wp_unslash( $raw ) : '' );
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):[0-5]\d$/', $value, $matches ) ) {
			return '';
		}
		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
	}

	/**
	 * Compute the UTC timestamp for a queued post.
	 *
	 * @param array $settings Scheduling settings.
	 * @param int   $sequence Zero-based post sequence.
	 * @return int
	 */
	public static function schedule_timestamp( array $settings, int $sequence = 0 ): int {
		$mode     = self::clean_schedule_mode( $settings['schedule_mode'] ?? 'immediate' );
		$interval = max( 0, min( 10080, (int) ( $settings['schedule_interval'] ?? 0 ) ) ) * MINUTE_IN_SECONDS;
		$jitter   = max( 0, min( 60, (int) ( $settings['schedule_jitter'] ?? 0 ) ) ) * MINUTE_IN_SECONDS;
		$jitter   = $jitter > 0 && function_exists( 'wp_rand' ) ? wp_rand( 0, $jitter ) : 0;
		$now      = time();
		$sequence = max( 0, $sequence );

		switch ( $mode ) {
			case 'interval':
				return $now + ( $sequence * $interval ) + $jitter;
			case 'fixed':
				$start = self::clean_datetime_local( $settings['schedule_start'] ?? '' );
				$time  = self::clean_time( $settings['schedule_time'] ?? '09:00' );
				if ( '' === $start ) {
					$start = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
				}
				$base = self::local_to_gmt( str_replace( 'T', ' ', $start ) . ' ' . $time . ':00' );
				return max( $now, $base ) + ( $sequence * $interval ) + $jitter;
			case 'daily':
				$time = self::clean_time( $settings['schedule_time'] ?? '09:00' );
				$date = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
				$base = self::local_to_gmt( $date . ' ' . $time . ':00' );
				if ( $base <= $now ) {
					$base += DAY_IN_SECONDS;
				}
				return $base + ( $sequence * DAY_IN_SECONDS ) + $jitter;
			default:
				return $now + $sequence + $jitter;
		}
	}

	/**
	 * Convert a site-local date string to a UTC timestamp.
	 *
	 * @param string $local Local date/time.
	 * @return int
	 */
	private static function local_to_gmt( string $local ): int {
		if ( function_exists( 'get_gmt_from_date' ) ) {
			$gmt = get_gmt_from_date( $local, 'U' );
			if ( is_numeric( $gmt ) ) {
				return (int) $gmt;
			}
		}
		return (int) strtotime( $local . ' UTC' );
	}
}
