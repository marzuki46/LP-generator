<?php
/**
 * LangGraph-style state machine persisted on lpgen_item meta.
 * Nodes are existing Generator jobs; edges are explicit transitions.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class Orchestrator {

	public const STATES = array( 'new', 'research', 'outline', 'validated', 'writing', 'review' );

	private const EDGES = array(
		'new'       => array( 'research' ),
		'research'  => array( 'outline' ),
		'outline'   => array( 'validated', 'outline' ),
		'validated' => array( 'writing', 'outline' ),
		'writing'   => array( 'review' ),
		'review'    => array(),
	);

	public static function state( int $item_id ): array {
		$raw = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $item_id, '_lpgen_cognitive_state', true ) : '';
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array( 'stage' => 'new', 'history' => array() );
	}

	/** Idempotent transition: replaying the same edge does not duplicate history. */
	public static function transition( int $item_id, string $from, string $to, array $data = array() ): bool {
		if ( ! isset( self::EDGES[ $from ] ) || ! in_array( $to, self::EDGES[ $from ], true ) ) {
			return false;
		}
		$state   = self::state( $item_id );
		$history = is_array( $state['history'] ?? null ) ? $state['history'] : array();
		$last    = end( $history );
		if ( is_array( $last ) && ( $last['from'] ?? '' ) === $from && ( $last['to'] ?? '' ) === $to ) {
			return true;
		}
		$history[] = array(
			'from' => $from,
			'to'   => $to,
			'at'   => gmdate( 'c' ),
			'data' => Utils::truncate( (string) wp_json_encode( $data ), 300 ),
		);
		$next = array( 'stage' => $to, 'history' => array_slice( $history, -20 ) );
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $item_id, '_lpgen_cognitive_state', wp_json_encode( $next ) );
		}
		return true;
	}

	/** Conditional edge after outline validation. */
	public static function route_after_validate( bool $valid, int $attempts = 0, int $max_attempts = 2 ): string {
		if ( $valid ) {
			return 'write';
		}
		return $attempts < $max_attempts ? 'retry_outline' : 'stop';
	}

	/** Stage checkpoint for resume without repeating completed LLM calls. */
	public static function checkpoint( int $item_id, string $stage, array $data ): void {
		if ( ! function_exists( 'update_post_meta' ) || ! in_array( $stage, self::STATES, true ) ) {
			return;
		}
		update_post_meta( $item_id, '_lpgen_checkpoint_' . $stage, wp_json_encode( $data ) );
	}

	public static function resume( int $item_id, string $stage ): array {
		$raw = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $item_id, '_lpgen_checkpoint_' . $stage, true ) : '';
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
