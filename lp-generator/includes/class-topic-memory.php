<?php
/**
 * ADD-only cross-campaign topic memory with hybrid retrieval.
 * Semantic signal comes from 9Router embeddings; lexical overlap is fallback.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class TopicMemory {

	public const DUPLICATE_THRESHOLD = 0.72;

	/** In-memory override for tests; production reads the database table. */
	private static function rows(): array {
		if ( isset( $GLOBALS['lpg_topic_memory_rows'] ) && is_array( $GLOBALS['lpg_topic_memory_rows'] ) ) {
			return array_values( $GLOBALS['lpg_topic_memory_rows'] );
		}
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return array();
		}
		$table = Database::table( 'topic_memory' );
		$raw   = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
		return is_array( $raw ) ? array_map( static function ( $row ): array { return (array) $row; }, $raw ) : array();
	}

	/** Append-only: history must never be overwritten. */
	public static function add( array $record ): bool {
		$now  = gmdate( 'Y-m-d H:i:s' );
		$row  = array(
			'campaign_id' => absint( $record['campaign_id'] ?? 0 ),
			'item_id'     => absint( $record['item_id'] ?? 0 ),
			'keyword'     => Utils::truncate( sanitize_text_field( (string) ( $record['keyword'] ?? '' ) ), 200 ),
			'intent'      => Utils::truncate( sanitize_key( (string) ( $record['intent'] ?? '' ) ), 40 ),
			'summary'     => Utils::truncate( sanitize_text_field( (string) ( $record['summary'] ?? '' ) ), 500 ),
			'outline'     => (string) wp_json_encode( is_array( $record['outline'] ?? null ) ? $record['outline'] : array() ),
			'angle'       => Utils::truncate( sanitize_text_field( (string) ( $record['angle'] ?? '' ) ), 500 ),
			'entities'    => (string) wp_json_encode( is_array( $record['entities'] ?? null ) ? $record['entities'] : array() ),
			'url'         => esc_url_raw( (string) ( $record['url'] ?? '' ) ),
			'embedding'   => (string) wp_json_encode( is_array( $record['embedding'] ?? null ) ? $record['embedding'] : array() ),
			'created_at'  => $now,
			'checked_at'  => (string) ( $record['checked_at'] ?? $now ),
		);
		if ( isset( $GLOBALS['lpg_topic_memory_rows'] ) && is_array( $GLOBALS['lpg_topic_memory_rows'] ) ) {
			$row['id'] = count( $GLOBALS['lpg_topic_memory_rows'] ) + 1;
			$GLOBALS['lpg_topic_memory_rows'][] = $row;
			return true;
		}
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return false;
		}
		return false !== $wpdb->insert( Database::table( 'topic_memory' ), $row );
	}

	/** Capture a generated topic or published article into long-term memory. */
	public static function add_from_item( int $item_id ): bool {
		$item = function_exists( 'get_post_meta' ) ? ContentRepository::get_item( $item_id ) : array();
		if ( empty( $item ) ) {
			return false;
		}
		$structured = json_decode( (string) ( $item['structured'] ?? '' ), true );
		$structured = is_array( $structured ) ? $structured : array();
		if ( '' === trim( (string) ( $structured['title'] ?? '' ) ) ) {
			return false;
		}
		$entities = array();
		foreach ( is_array( $structured['entities'] ?? null ) ? $structured['entities'] : array() as $entity ) {
			if ( is_array( $entity ) && '' !== trim( (string) ( $entity['term'] ?? '' ) ) ) {
				$entities[] = (string) $entity['term'];
			}
		}
		return self::add(
			array(
				'campaign_id' => (int) ( $item['campaign_id'] ?? 0 ),
				'item_id'     => $item_id,
				'keyword'     => (string) ( $structured['title'] ?? '' ),
				'intent'      => (string) ( $structured['intent'] ?? '' ),
				'summary'     => (string) ( $structured['angle'] ?? ( $structured['intro'] ?? '' ) ),
				'outline'     => (array) ( $structured['outline'] ?? array() ),
				'angle'       => (string) ( $structured['funnel_link_angle'] ?? ( $structured['angle'] ?? '' ) ),
				'entities'    => $entities,
				'url'         => (string) get_post_meta( $item_id, '_lpgen_permalink', true ),
				'checked_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/** Hybrid retrieval: semantic + lexical + entity overlap. */
	public static function search( string $query, int $limit = 5 ): array {
		$rows     = self::rows();
		if ( ! $rows ) {
			return array();
		}
		$query_vec = self::embed_text( $query );
		$tokens    = self::tokens( $query );
		$anchor    = class_exists( ResearchSource::class ) ? ResearchSource::anchor() : array( 'year' => (int) gmdate( 'Y' ) );
		$scored    = array();
		foreach ( $rows as $row ) {
			$keyword = (string) ( $row['keyword'] ?? '' );
			$summary = (string) ( $row['summary'] ?? '' );
			$outline = self::decode( (string) ( $row['outline'] ?? '' ) );
			$entities = self::decode( (string) ( $row['entities'] ?? '' ) );
			$semantic = 0.0;
			if ( $query_vec ) {
				$row_vec = self::decode( (string) ( $row['embedding'] ?? '' ) );
				if ( is_array( $row_vec ) && $row_vec ) {
					$semantic = self::cosine( $query_vec, $row_vec );
				}
			}
			$lexical = self::overlap( $tokens, self::tokens( $keyword . ' ' . $summary . ' ' . implode( ' ', array_map( 'strval', $outline ) ) ) );
			$entity  = self::overlap( $tokens, array_map( 'strval', $entities ) );
			if ( $query_vec ) {
				$score = 0.55 * $semantic + 0.30 * $lexical + 0.15 * $entity;
			} else {
				$score = 0.75 * $lexical + 0.25 * $entity;
			}
			$year = (int) substr( (string) ( $row['checked_at'] ?? '' ), 0, 4 );
			if ( $year > 0 && $year < ( (int) $anchor['year'] - 1 ) ) {
				$score *= 0.8;
			}
			$scored[] = array(
				'item_id' => absint( $row['item_id'] ?? 0 ),
				'keyword' => $keyword,
				'url'     => (string) ( $row['url'] ?? '' ),
				'angle'   => (string) ( $row['angle'] ?? '' ),
				'score'   => round( $score, 4 ),
				'duplicate_risk' => $score >= self::DUPLICATE_THRESHOLD,
			);
		}
		usort( $scored, static function ( $a, $b ): int { return (int) ( ( $b['score'] * 10000 ) - ( $a['score'] * 10000 ) ); } );
		return array_slice( $scored, 0, max( 1, min( 10, $limit ) ) );
	}

	/** Prompt line that warns the writer about semantically close content. */
	public static function prompt_line( string $query ): string {
		$matches = self::search( $query, 4 );
		$risks   = array_values( array_filter( $matches, static fn( array $row ): bool => (bool) $row['duplicate_risk'] ) );
		if ( ! $risks ) {
			return 'SEMANTIC MEMORY: no close previous angle detected. Still avoid repeating the city-swap template.';
		}
		$lines = array( 'SEMANTIC MEMORY (possible repetition — change angle, not just wording):' );
		foreach ( $risks as $row ) {
			$lines[] = '- ' . (string) $row['keyword'] . ' | score ' . (string) $row['score']
				. ( '' !== (string) $row['url'] ? ' | ' . (string) $row['url'] : '' );
		}
		return implode( "\n", $lines );
	}

	private static function embed_text( string $text ): array {
		$vectors = AiClient::embed( array( $text ) );
		return isset( $vectors[0] ) && is_array( $vectors[0] ) ? $vectors[0] : array();
	}

	private static function tokens( string $text ): array {
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		preg_match_all( '/[\p{L}\p{N}]+/u', mb_strtolower( $text ), $matches );
		return array_values( array_unique( $matches[0] ?? array() ) );
	}

	private static function overlap( array $a, array $b ): float {
		if ( ! $a || ! $b ) {
			return 0.0;
		}
		$common = count( array_intersect( $a, $b ) );
		return $common / max( 1, min( count( $a ), count( $b ) ) );
	}

	private static function cosine( array $a, array $b ): float {
		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;
		$len = min( count( $a ), count( $b ) );
		for ( $i = 0; $i < $len; $i++ ) {
			$x = (float) $a[ $i ];
			$y = (float) $b[ $i ];
			$dot += $x * $y;
			$na  += $x * $x;
			$nb  += $y * $y;
		}
		if ( $na <= 0 || $nb <= 0 ) {
			return 0.0;
		}
		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}

	private static function decode( string $json ): array {
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
