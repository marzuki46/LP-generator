<?php
/** Experiment/staging mode and controlled promotion. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class ExperimentMode {
 public const MODES = array(
  'CONTENT_PREVIEW',
  'SEO_VALIDATION',
  'SCHEMA_VALIDATION',
  'INTERNAL_LINK_TEST',
  'MULTILINGUAL_TEST',
  'PERFORMANCE_TEST',
  'PUBLISH_READY',
 );

 public static function clean( $value ): string {
  $mode = strtoupper( sanitize_key( (string) $value ) );
  return in_array( $mode, self::MODES, true ) ? $mode : '';
 }

 public static function is_experiment( array $campaign ): bool {
  return '' !== self::clean( $campaign['experiment_mode'] ?? '' );
 }

 public static function should_noindex( array $campaign ): bool {
  return self::is_experiment( $campaign );
 }

 public static function sitemap_eligible( array $campaign ): bool {
  return ! self::is_experiment( $campaign );
 }

 public static function admin_label( array $campaign ): string {
  $mode = self::clean( $campaign['experiment_mode'] ?? '' );
  return '' === $mode ? '' : 'Experiment · ' . str_replace( '_', ' ', $mode );
 }

 /** Apply noindex to a generated post without touching production pages. */
 public static function apply_noindex( int $post_id, array $campaign ): void {
  if ( $post_id <= 0 ) { return; }
  $noindex = self::should_noindex( $campaign );
  update_post_meta( $post_id, '_lpgen_noindex', $noindex ? 1 : 0 );
  if ( defined( 'WPSEO_VERSION' ) ) {
   update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $noindex ? 1 : 0 );
  }
 }

 /** Fallback robots tag only when no SEO plugin owns head output. */
 public static function robots_meta( array $campaign ): string {
  if ( ! self::should_noindex( $campaign ) ) { return ''; }
  if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
   return '';
  }
  return '<meta name="robots" content="noindex,follow" />' . "\n";
 }

 public static function maybe_output_robots(): void {
  if ( ! function_exists( 'is_singular' ) || ! is_singular() ) { return; }
  $post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
  if ( $post_id <= 0 ) { return; }
  $campaign_id = (int) get_post_meta( $post_id, '_lpgen_campaign_id', true );
  if ( $campaign_id <= 0 ) { return; }
  $campaign = ContentRepository::get_campaign( $campaign_id );
  if ( ! $campaign ) { return; }
  echo self::robots_meta( $campaign ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed safe string.
 }

 public static function promotion_checks( int $campaign_id, int $item_id ): array {
  $campaign = ContentRepository::get_campaign( $campaign_id );
  $item     = ContentRepository::get_item( $item_id );
  if ( ! $campaign || ! $item ) {
   return array( 'decision' => 'FAIL', 'checks' => array( 'context' => 'FAIL: campaign/item missing' ) );
  }
  $structured = json_decode( (string) ( $item['structured'] ?? '' ), true );
  $structured = is_array( $structured ) ? $structured : array();
  $quality    = json_decode( (string) get_post_meta( $item_id, '_lpgen_v2_quality', true ), true );
  $quality    = is_array( $quality ) ? $quality : array();
  $seo        = json_decode( (string) get_post_meta( $item_id, '_lpgen_v2_seo_meta', true ), true );
  $seo        = is_array( $seo ) ? $seo : array();
  $checks     = array(
   'quality' => empty( $quality ) ? 'NOT TESTED' : ( 'HOLD' === ( $quality['decision'] ?? '' ) ? 'FAIL' : ( 'ELIGIBLE_FOR_REVIEW' === ( $quality['decision'] ?? '' ) ? 'PASS' : 'WARNING' ) ),
   'seo' => empty( $seo['blockers'] ) ? 'PASS' : 'FAIL',
   'schema' => 'NOT TESTED',
   'internal_links' => ! empty( $item['content'] ) ? 'PASS' : 'WARNING',
   'multilingual' => 'NOT TESTED',
   'performance' => 'NOT TESTED',
   'accessibility' => ! empty( $structured['title'] ) ? 'PASS' : 'WARNING',
   'security' => '' !== (string) ( $campaign['offer_url'] ?? '' ) ? 'PASS' : 'FAIL',
   'approval' => 'approved' === (string) ( $item['status'] ?? '' ) ? 'PASS' : 'WARNING',
  );
  $fail = in_array( 'FAIL', $checks, true );
  return array(
   'decision' => $fail ? 'FAIL' : ( in_array( 'NOT TESTED', $checks, true ) || in_array( 'WARNING', $checks, true ) ? 'WARNING' : 'PASS' ),
   'checks' => $checks,
   'quality_score' => (int) ( $quality['score'] ?? 0 ),
  );
 }

 /** Promote only by clearing experiment mode; never auto-publishes. */
 public static function promote( int $campaign_id, int $item_id ): array {
  $checks = self::promotion_checks( $campaign_id, $item_id );
  if ( 'FAIL' === $checks['decision'] ) {
   return array( 'ok' => false, 'message' => __( 'Promosi ditolak: periksa quality/SEO/approval.', 'lp-generator' ), 'checks' => $checks );
  }
  update_post_meta( $campaign_id, '_lpgen_experiment_mode', '' );
  if ( $item_id > 0 ) { self::apply_noindex( $item_id, array( 'experiment_mode' => '' ) ); }
  return array( 'ok' => true, 'message' => __( 'Mode eksperimen dinonaktifkan. Publish tetap dilakukan manual setelah review.', 'lp-generator' ), 'checks' => $checks );
 }
}
