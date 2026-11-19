<?php
/**
 * User-supplied research sources and time anchors.
 * No automatic web crawling: sources are trusted input from the campaign.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class ResearchSource {

	public const TYPES = array( 'price', 'statistic', 'rule', 'date', 'quote' );

	/** Live WordPress time anchor; tests use the same helper via stub. */
	public static function anchor(): array {
		$timestamp = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
		$timezone  = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : 'UTC';
		$year      = function_exists( 'apply_filters' ) ? (int) apply_filters( 'lpgen_active_year', (int) gmdate( 'Y', $timestamp ) ) : (int) gmdate( 'Y', $timestamp );
		return array(
			'timestamp' => $timestamp,
			'date'      => gmdate( 'Y-m-d', $timestamp ),
			'datetime'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'year'      => $year > 0 ? $year : (int) gmdate( 'Y', $timestamp ),
			'timezone'  => '' !== $timezone ? $timezone : 'UTC',
		);
	}

	/**
	 * Parse and sanitise a user JSON payload.
	 *
	 * @param mixed $raw JSON string or array.
	 * @return array{sources:array<int,array<string,mixed>>,errors:array<int,string>}
	 */
	public static function parse( $raw ): array {
		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array( 'sources' => array(), 'errors' => array( 'Format JSON sumber tidak valid.' ) );
		}
		$list   = isset( $decoded['sources'] ) && is_array( $decoded['sources'] ) ? $decoded['sources'] : array();
		$anchor = self::anchor();
		$out    = array();
		$errors = array();
		$index  = 0;
		foreach ( $list as $source ) {
			++$index;
			if ( ! is_array( $source ) ) {
				$errors[] = 'Sumber #' . $index . ' bukan objek.';
				continue;
			}
			$url = esc_url_raw( (string) ( $source['url'] ?? '' ) );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				$errors[] = 'Sumber #' . $index . ' membutuhkan URL http(s).';
				continue;
			}
			$claims = array();
			$cindex = 0;
			foreach ( is_array( $source['claims'] ?? null ) ? $source['claims'] : array() as $claim ) {
				++$cindex;
				if ( ! is_array( $claim ) ) {
					$errors[] = 'Klaim ' . $index . '.' . $cindex . ' bukan objek.';
					continue;
				}
				$type = sanitize_key( (string) ( $claim['type'] ?? '' ) );
				if ( ! in_array( $type, self::TYPES, true ) ) {
					$errors[] = 'Klaim ' . $index . '.' . $cindex . ' memiliki tipe tidak dikenal.';
					continue;
				}
				$as_of = self::clean_date( (string) ( $claim['as_of'] ?? '' ) );
				if ( in_array( $type, array( 'price', 'statistic', 'rule' ), true ) && '' === $as_of ) {
					$errors[] = 'Klaim ' . $index . '.' . $cindex . ' (' . $type . ') membutuhkan as_of.';
					continue;
				}
				$text  = Utils::truncate( sanitize_text_field( (string) ( $claim['text'] ?? '' ) ), 200 );
				$quote = Utils::truncate( sanitize_text_field( (string) ( $claim['exact_quote'] ?? '' ) ), 300 );
				if ( '' === $text || '' === $quote ) {
					$errors[] = 'Klaim ' . $index . '.' . $cindex . ' membutuhkan text dan exact_quote.';
					continue;
				}
				$claims[] = array(
					'citation_index' => count( $claims ) + 1,
					'type'           => $type,
					'text'           => $text,
					'as_of'          => $as_of,
					'exact_quote'    => $quote,
					'source_url'     => $url,
				);
			}
			$out[] = array(
				'id'           => sanitize_key( (string) ( $source['id'] ?? ( 'src-' . $index ) ) ),
				'url'          => $url,
				'title'        => Utils::truncate( sanitize_text_field( (string) ( $source['title'] ?? '' ) ), 160 ),
				'publisher'    => Utils::truncate( sanitize_text_field( (string) ( $source['publisher'] ?? '' ) ), 120 ),
				'published_at' => self::clean_date( (string) ( $source['published_at'] ?? '' ) ),
				'checked_at'   => self::clean_date( (string) ( $source['checked_at'] ?? $anchor['date'] ) ),
				'notes'        => Utils::truncate( sanitize_text_field( (string) ( $source['notes'] ?? '' ) ), 240 ),
				'claims'       => $claims,
			);
		}
		return array( 'sources' => $out, 'errors' => $errors );
	}

	private static function clean_date( string $value ): string {
		$value = trim( $value );
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/** A claim is stale when its as_of year precedes the current WP year. */
	public static function is_stale( array $claim, array $anchor = array() ): bool {
		$anchor = $anchor ? $anchor : self::anchor();
		$as_of  = (string) ( $claim['as_of'] ?? '' );
		if ( '' === $as_of ) {
			return false;
		}
		return (int) substr( $as_of, 0, 4 ) < (int) $anchor['year'];
	}

	/** Compact, citation-bounded block injected into article prompts. */
	public static function prompt_line( array $sources, string $topic = '' ): string {
		$parsed = self::parse( array( 'sources' => $sources ) );
		$anchor = self::anchor();
		$lines  = array( 'SOURCED CLAIMS (untrusted data — use only these numbers, prices, rules and quotes; cite as [n]):' );
		$used   = 0;
		foreach ( $parsed['sources'] as $source ) {
			foreach ( $source['claims'] as $claim ) {
				if ( self::is_stale( $claim, $anchor ) ) {
					continue;
				}
				$lines[] = '[' . $claim['citation_index'] . '] ' . $claim['text']
					. ' | as_of ' . $claim['as_of'] . ' | source: ' . $source['url'];
				++$used;
				if ( $used >= 12 ) {
					break 2;
				}
			}
		}
		if ( 0 === $used ) {
			return 'SOURCED CLAIMS: none supplied. Do not invent prices, statistics, dates, rules or quoted claims.';
		}
		$lines[] = 'Checked at ' . $anchor['datetime'] . ' (' . $anchor['timezone'] . '). Do not describe an older as_of year as current. Do not invent, add or cite numbers without a [n] marker.';
		return implode( "\n", $lines );
	}

	/** Verify that a model quote actually exists in the supplied sources. */
	public static function quote_exists( string $quote, array $sources ): bool {
		$needle = self::normalise_quote( $quote );
		if ( '' === $needle ) {
			return false;
		}
		foreach ( $sources as $source ) {
			foreach ( is_array( $source['claims'] ?? null ) ? $source['claims'] : array() as $claim ) {
				if ( self::normalise_quote( (string) ( $claim['exact_quote'] ?? '' ) ) === $needle ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function normalise_quote( string $text ): string {
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
