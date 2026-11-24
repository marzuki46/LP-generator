<?php
/** Doorway-abuse prevention for local service pages. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LocalValueGate {
 public static function check( array $brief, array $campaign, array $context = array() ): array {
  $missing = array();
  $checks = array(
   'service_covered' => $context['service_covered'] ?? ( '' !== (string) ( $campaign['city'] ?? '' ) ),
   'local_need' => $context['local_need'] ?? ( '' !== (string) ( $brief['audience'] ?? '' ) ),
   'local_examples' => $context['local_examples'] ?? ( '' !== (string) ( $campaign['verified_examples'] ?? '' ) ),
   'geographic_claims' => $context['geographic_claims'] ?? ( '' !== (string) ( $campaign['service_area_evidence'] ?? '' ) ),
   'architecture_position' => $context['architecture_position'] ?? ( '' !== (string) ( $campaign['bofu_path'] ?? '' ) ),
  );
  foreach ( $checks as $key => $ok ) {
   if ( empty( $ok ) ) { $missing[] = $key; }
  }
  $differentiation = absint( $context['local_differentiation'] ?? 50 );
  $decision = 'PASS';
  $recommendation = '';
  if ( $missing ) {
   $decision = 'REVIEW_REQUIRED';
  }
  if ( $differentiation < 40 ) {
   $decision = 'HOLD';
   $recommendation = 'area_hub';
  }
  if ( ! empty( $context['unverified_local_claims'] ) ) {
   $decision = 'HOLD';
   $missing[] = 'unverified_local_claims';
  }
  return array(
   'decision' => $decision,
   'missing' => array_values( array_unique( $missing ) ),
   'differentiation' => $differentiation,
   'recommendation' => $recommendation,
   'note' => 'Local facts must be supplied and verified; the gate does not invent them.',
  );
 }
}
