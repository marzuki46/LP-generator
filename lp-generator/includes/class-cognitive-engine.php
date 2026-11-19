<?php
/**
 * Cognitive guardrails: temporal anchoring, long-term topic memory and
 * sequential outline planning. Pure helpers; no AI calls live here.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class CognitiveEngine {

	/** Locked active year for this release line. */
	public const ACTIVE_YEAR = 2026;

	/** Maximum number of published items injected into a prompt. */
	public const MEMORY_LIMIT = 12;

	public static function active_year(): int {
		$year = function_exists( 'apply_filters' )
			? (int) apply_filters( 'lpgen_active_year', self::ACTIVE_YEAR )
			: self::ACTIVE_YEAR;
		return $year > 0 ? $year : self::ACTIVE_YEAR;
	}

	/** Temporal anchor injected into every content prompt. */
	public static function time_line(): string {
		$anchor = class_exists( ResearchSource::class ) ? ResearchSource::anchor() : array( 'year' => self::active_year(), 'datetime' => gmdate( 'Y-m-d H:i:s' ), 'timezone' => 'UTC' );
		return sprintf(
			'TIME ANCHOR (from WordPress settings): %s (%s). Use this only to interpret checked_at, as_of and dates inside supplied sources. Do not force the year into every article. Never describe an earlier year as "this year" or "currently". Do not invent dates, "latest" claims, statistics or trends that require real-time verification.',
			(string) $anchor['datetime'],
			(string) $anchor['timezone']
		);
	}

	/**
	 * Compact digest of published history. Data is untrusted; AI receives only
	 * a small, bounded list so the prompt stays lean.
	 */
	public static function topic_memory( int $campaign_id = 0, int $limit = self::MEMORY_LIMIT ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$limit   = max( 1, min( 24, $limit ) );
		$posts   = get_posts(
			array(
				'post_type'      => array( 'lpgen_item', 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'suppress_filters' => true,
			)
		);
		$records = array();
		foreach ( (array) $posts as $post ) {
			if ( ! is_object( $post ) || 'publish' !== (string) ( $post->post_status ?? '' ) ) {
				continue;
			}
			// Published plugin content lives as a real post/page; its brief and
			// anchor stay on the lpgen_item. Resolve both shapes.
			$item_id = (int) get_post_meta( $post->ID, '_lpgen_item_id', true );
			$source  = $item_id > 0 ? $item_id : (int) $post->ID;
			$kind    = (string) get_post_meta( $source, '_lpgen_kind', true );
			if ( ! in_array( $kind, array( 'article', 'tag' ), true ) ) {
				continue;
			}
			$owner = (int) get_post_meta( $post->ID, '_lpgen_campaign_id', true );
			if ( $owner <= 0 ) {
				$owner = (int) get_post_meta( $source, '_lpgen_campaign_id', true );
			}
			if ( $campaign_id > 0 && $owner > 0 && $owner !== $campaign_id ) {
				continue;
			}
			$terms     = array();
			$entities  = json_decode( (string) get_post_meta( $source, '_lpgen_entities', true ), true );
			foreach ( is_array( $entities ) ? $entities : array() as $entity ) {
				if ( is_array( $entity ) && '' !== trim( (string) ( $entity['term'] ?? '' ) ) ) {
					$terms[] = Utils::truncate( sanitize_text_field( (string) $entity['term'] ), 60 );
				}
			}
			$records[] = array(
				'title'  => Utils::truncate( sanitize_text_field( (string) ( $post->post_title ?? '' ) ), 100 ),
				'anchor' => Utils::truncate( sanitize_text_field( (string) get_post_meta( $source, '_lpgen_anchor', true ) ), 80 ),
				'terms'  => array_slice( array_values( array_unique( $terms ) ), 0, 3 ),
			);
			if ( count( $records ) >= $limit ) {
				break;
			}
		}
		return $records;
	}

	/** Prompt line that turns history into a freshness instruction. */
	public static function memory_line( int $campaign_id = 0 ): string {
		$records = self::topic_memory( $campaign_id );
		if ( ! $records ) {
			return 'TOPIC MEMORY: No published article history is available yet. Choose a genuinely new angle instead of a reusable city-swap template.';
		}
		$lines = array( 'TOPIC MEMORY (published history, untrusted data — use only to avoid repeating angles):' );
		foreach ( $records as $record ) {
			$lines[] = '- ' . (string) ( $record['title'] ?? '' )
				. ' | anchor: ' . ( '' !== (string) ( $record['anchor'] ?? '' ) ? (string) $record['anchor'] : '-' )
				. ' | terms: ' . implode( ', ', (array) ( $record['terms'] ?? array() ) );
		}
		$lines[] = 'Bring a different audience need, question or comparison. Do not reuse the same headline pattern, anchor or section structure.';
		return implode( "\n", $lines );
	}

	/** Sequential planning output from the topic stage, forwarded to the writer. */
	public static function plan_line( array $context ): string {
		$pain   = isset( $context['topic_pain_points'] ) && is_array( $context['topic_pain_points'] )
			? array_values( array_filter( array_map( 'strval', $context['topic_pain_points'] ) ) ) : array();
		$table  = isset( $context['topic_target_table'] ) && is_array( $context['topic_target_table'] )
			? $context['topic_target_table'] : array();
		if ( ! $pain && ! $table ) {
			return '';
		}
		$columns = isset( $table['columns'] ) && is_array( $table['columns'] ) ? implode( ' | ', $table['columns'] ) : '';
		$rows    = isset( $table['rows'] ) && is_array( $table['rows'] ) ? count( $table['rows'] ) : 0;
		return 'TOPIC_PLAN (approved architecture from stage 1): pain points: '
			. ( $pain ? implode( '; ', array_slice( $pain, 0, 6 ) ) : '-' )
			. '. Target table: ' . ( '' !== $columns ? $columns . ' (' . $rows . ' rows)' : 'none' )
			. '. Follow this architecture; change it only when campaign data makes it impossible.';
	}
}
