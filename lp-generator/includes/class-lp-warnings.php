<?php
/** Machine-readable per-item warnings, adapted from Automattic warnings.json. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LpWarnings {
 public static function get( int $item_id ): array {
  if ( $item_id <= 0 || ! function_exists( 'get_post_meta' ) ) { return array(); }
  $raw = json_decode( (string) get_post_meta( $item_id, '_lpgen_warnings', true ), true );
  return is_array( $raw ) ? $raw : array();
 }

 /** Append one warning to a step, de-duplicated. */
 public static function add( int $item_id, string $step, string $message ): void {
  if ( $item_id <= 0 || '' === trim( $message ) || ! function_exists( 'update_post_meta' ) ) { return; }
  $step    = sanitize_key( $step );
  $all     = self::get( $item_id );
  $list    = isset( $all[ $step ] ) && is_array( $all[ $step ] ) ? $all[ $step ] : array();
  $message = self::line( $message );
  if ( ! in_array( $message, $list, true ) ) {
   $list[] = $message;
   $all[ $step ] = array_slice( $list, -20 );
   update_post_meta( $item_id, '_lpgen_warnings', wp_json_encode( $all ) );
  }
 }

 /** Replace all warnings for one step (deterministic passes own the whole set). */
 public static function replace( int $item_id, string $step, array $messages ): void {
  if ( $item_id <= 0 || ! function_exists( 'update_post_meta' ) ) { return; }
  $step = sanitize_key( $step );
  $all  = self::get( $item_id );
  $out  = array();
  foreach ( array_values( $messages ) as $message ) {
   $line = self::line( is_scalar( $message ) ? (string) $message : '' );
   if ( '' !== $line && ! in_array( $line, $out, true ) ) { $out[] = $line; }
  }
  $all[ $step ] = array_slice( $out, -20 );
  update_post_meta( $item_id, '_lpgen_warnings', wp_json_encode( $all ) );
 }

 /** authored=…; delivered=…; disposition=… bounded to 160 chars. */
 public static function line( string $message ): string {
  return Utils::truncate( trim( preg_replace( '/\s+/u', ' ', $message ) ?? '' ), 160 );
 }
}
