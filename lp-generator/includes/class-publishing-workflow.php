<?php
/** Explicit publishing state machine with audit history. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class PublishingWorkflow {
 public const STATES = array(
  'DRAFT','RESEARCHED','GENERATED','VALIDATED','APPROVAL_PENDING','APPROVED',
  'SCHEDULED','PUBLISHED','MONITORED','REVISION_REQUIRED','REJECTED','FAILED','ARCHIVED',
 );

 private const EDGES = array(
  'DRAFT' => array( 'RESEARCHED', 'GENERATED', 'REJECTED', 'ARCHIVED' ),
  'RESEARCHED' => array( 'GENERATED', 'REVISION_REQUIRED', 'REJECTED' ),
  'GENERATED' => array( 'VALIDATED', 'REVISION_REQUIRED', 'REJECTED' ),
  'VALIDATED' => array( 'APPROVAL_PENDING', 'REVISION_REQUIRED', 'REJECTED' ),
  'APPROVAL_PENDING' => array( 'APPROVED', 'REVISION_REQUIRED', 'REJECTED' ),
  'APPROVED' => array( 'SCHEDULED', 'REVISION_REQUIRED', 'REJECTED' ),
  'SCHEDULED' => array( 'PUBLISHED', 'FAILED', 'REVISION_REQUIRED' ),
  'PUBLISHED' => array( 'MONITORED', 'ARCHIVED' ),
  'MONITORED' => array( 'ARCHIVED', 'REVISION_REQUIRED' ),
  'REVISION_REQUIRED' => array( 'RESEARCHED', 'GENERATED', 'REJECTED' ),
  'REJECTED' => array( 'ARCHIVED', 'DRAFT' ),
  'FAILED' => array( 'SCHEDULED', 'REVISION_REQUIRED', 'REJECTED' ),
  'ARCHIVED' => array(),
 );

 public static function state( int $item_id ): string {
  if ( $item_id <= 0 || ! function_exists( 'get_post_meta' ) ) { return 'DRAFT'; }
  $state = strtoupper( (string) get_post_meta( $item_id, '_lpgen_v2_workflow_state', true ) );
  return in_array( $state, self::STATES, true ) ? $state : 'DRAFT';
 }

 public static function history( int $item_id ): array {
  $raw = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $item_id, '_lpgen_v2_workflow_history', true ) : '';
  $decoded = json_decode( $raw, true );
  return is_array( $decoded ) ? $decoded : array();
 }

 public static function can( string $from, string $to ): bool {
  $from = strtoupper( $from );
  $to   = strtoupper( $to );
  return isset( self::EDGES[ $from ] ) && in_array( $to, self::EDGES[ $from ], true );
 }

 public static function transition( int $item_id, string $from, string $to, array $context = array() ): bool {
  if ( $item_id <= 0 || ! self::can( $from, $to ) ) { return false; }
  $history = self::history( $item_id );
  $last = end( $history );
  if ( is_array( $last ) && ( $last['from'] ?? '' ) === strtoupper( $from ) && ( $last['to'] ?? '' ) === strtoupper( $to ) ) {
   return true;
  }
  $history[] = array(
   'from' => strtoupper( $from ),
   'to' => strtoupper( $to ),
   'at' => gmdate( 'c' ),
   'context' => Utils::truncate( (string) wp_json_encode( $context ), 300 ),
  );
  update_post_meta( $item_id, '_lpgen_v2_workflow_state', strtoupper( $to ) );
  update_post_meta( $item_id, '_lpgen_v2_workflow_history', wp_json_encode( array_slice( $history, -40 ) ) );
  Database::log( 'info', sprintf( 'Workflow item #%d: %s → %s', $item_id, strtoupper( $from ), strtoupper( $to ) ), (int) ( $context['campaign_id'] ?? 0 ) );
  return true;
 }

 /** Walk the shortest valid path to a target state, recording each step. */
 public static function advance_to( int $item_id, string $target, array $context = array() ): bool {
  $target = strtoupper( $target );
  if ( ! in_array( $target, self::STATES, true ) ) { return false; }
  $state = self::state( $item_id );
  if ( $state === $target ) { return true; }
  $queue = array( array( $state, array() ) );
  $seen  = array( $state => true );
  while ( $queue ) {
   $node = array_shift( $queue );
   $current = $node[0];
   $path = $node[1];
   foreach ( self::EDGES[ $current ] ?? array() as $next ) {
    if ( isset( $seen[ $next ] ) ) { continue; }
    $seen[ $next ] = true;
    $candidate = array_merge( $path, array( array( $current, $next ) ) );
    if ( $next === $target ) {
     foreach ( $candidate as $edge ) { self::transition( $item_id, $edge[0], $edge[1], $context ); }
     return true;
    }
    $queue[] = array( $next, $candidate );
   }
  }
  return false;
 }

 public static function init( int $item_id ): void {
  $raw = $item_id > 0 && function_exists( 'get_post_meta' ) ? (string) get_post_meta( $item_id, '_lpgen_v2_workflow_state', true ) : 'set';
  if ( '' !== $raw ) { return; }
  update_post_meta( $item_id, '_lpgen_v2_workflow_state', 'DRAFT' );
 }

 public static function mark_generated( int $item_id ): void {
  self::init( $item_id );
  $state = self::state( $item_id );
  if ( in_array( $state, array( 'DRAFT', 'RESEARCHED', 'REVISION_REQUIRED' ), true ) ) {
   self::transition( $item_id, $state, 'GENERATED' );
  }
 }
}
