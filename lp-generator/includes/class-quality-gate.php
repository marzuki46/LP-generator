<?php
/** Internal quality score (not a Google ranking score). @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class QualityGate {
 public const WEIGHTS = array(
  'intent_alignment' => 20,
  'unique_information' => 25,
  'useful_content' => 20,
  'factual_reliability' => 15,
  'technical_seo' => 10,
  'readability_ux' => 10,
 );

 public static function evaluate( array $context ): array {
  $campaign = $context['campaign'] ?? array();
  $data     = $context['data'] ?? array();
  $kind     = (string) ( $context['kind'] ?? 'lp' );
  $type     = LpTypeRegistry::clean( $campaign['lp_type'] ?? '' );
  $brief    = $context['brief'] ?? LpBriefGenerator::generate( $campaign );
  $scores   = array(
   'intent_alignment' => self::intent_score( $brief, $data ),
   'unique_information' => self::unique_score( $data, $context ),
   'useful_content' => self::useful_score( $data ),
   'factual_reliability' => self::fact_score( $context ),
   'technical_seo' => self::technical_score( $context ),
   'readability_ux' => self::readability_score( $data ),
  );
  $weighted = 0;
  foreach ( self::WEIGHTS as $key => $weight ) {
   $weighted += ( (int) ( $scores[ $key ] ?? 0 ) * $weight ) / 100;
  }
  $total = (int) round( $weighted );
  $blockers = array();
  $warnings = array();

  $seo = $context['seo'] ?? array();
  foreach ( is_array( $seo['blockers'] ?? null ) ? $seo['blockers'] : array() as $blocker ) {
   $blockers[] = 'seo: ' . $blocker;
  }
  $duplicate = $context['duplicate'] ?? array();
  if ( 'high' === ( $duplicate['risk'] ?? '' ) ) {
   $blockers[] = 'duplicate: high risk — ' . implode( '; ', (array) ( $duplicate['reasons'] ?? array() ) );
  }
  if ( 'service' === $type ) {
   $local = $context['local_gate'] ?? LocalValueGate::check( $brief, $campaign );
   if ( 'HOLD' === ( $local['decision'] ?? '' ) ) { $blockers[] = 'local_gate: HOLD'; }
   if ( 'REVIEW_REQUIRED' === ( $local['decision'] ?? '' ) ) { $warnings[] = 'local_gate: review required'; }
  }
  if ( 'amazon_affiliate' === $type ) {
   $affiliate = $context['affiliate_gate'] ?? AffiliateValueGate::check( $brief, $campaign, $data );
   if ( 'HOLD' === ( $affiliate['decision'] ?? '' ) ) { $blockers[] = 'affiliate_gate: HOLD'; }
  }
  if ( empty( $campaign['offer_url'] ) && 'lp' === $kind ) {
   $blockers[] = 'cta: missing offer_url';
  }

  $decision = 'HOLD';
  if ( ! $blockers ) {
   $decision = $total >= 85 ? 'ELIGIBLE_FOR_REVIEW' : ( $total >= 65 ? 'REVISION_REQUIRED' : 'HOLD' );
  }
  return array(
   'score' => $total,
   'scores' => $scores,
   'weights' => self::WEIGHTS,
   'decision' => $decision,
   'blockers' => array_values( array_unique( $blockers ) ),
   'warnings' => array_values( array_unique( $warnings ) ),
   'page_type' => $type,
   'kind' => $kind,
   'generated_at' => gmdate( 'Y-m-d H:i:s' ),
   'disclaimer' => 'Internal editorial signal only; not a Google ranking score.',
  );
 }

 public static function store( int $item_id, array $report ): void {
  if ( $item_id > 0 ) { update_post_meta( $item_id, '_lpgen_v2_quality', wp_json_encode( $report ) ); }
 }

 private static function intent_score( array $brief, array $data ): int {
  $score = empty( $brief['keyword'] ) ? 40 : 80;
  if ( ! empty( $data['title'] ) && 'transactional' === ( $brief['intent'] ?? '' ) ) { $score += 15; }
  return min( 100, $score );
 }

 private static function unique_score( array $data, array $context ): int {
  $duplicate = $context['duplicate'] ?? array();
  $risk = absint( $duplicate['score'] ?? 0 );
  return max( 0, 100 - $risk );
 }

 private static function useful_score( array $data ): int {
  $sections = is_array( $data['sections'] ?? null ) ? $data['sections'] : array();
  $score = count( $sections ) >= 4 ? 70 : 45;
  $faq = is_array( $data['faq'] ?? null ) ? count( $data['faq'] ) : 0;
  if ( $faq >= 3 ) { $score += 15; }
  if ( ! empty( $data['intro'] ) && mb_strlen( (string) $data['intro'] ) > 120 ) { $score += 15; }
  return min( 100, $score );
 }

 private static function fact_score( array $context ): int {
  $score = 80;
  if ( ! empty( $context['missing_evidence'] ) ) { $score -= 30; }
  if ( ! empty( $context['unverified_claims'] ) ) { $score -= 40; }
  return max( 0, $score );
 }

 private static function technical_score( array $context ): int {
  $seo = $context['seo'] ?? array();
  $score = 90;
  $score -= count( is_array( $seo['warnings'] ?? null ) ? $seo['warnings'] : array() ) * 5;
  $score -= count( is_array( $seo['blockers'] ?? null ) ? $seo['blockers'] : array() ) * 20;
  return max( 0, min( 100, $score ) );
 }

 private static function readability_score( array $data ): int {
  $score = 80;
  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( ! is_array( $section ) ) { continue; }
   $body = (string) ( $section['body'] ?? '' );
   if ( mb_strlen( $body ) < 120 ) { $score -= 10; }
  }
  return max( 0, min( 100, $score ) );
 }
}
