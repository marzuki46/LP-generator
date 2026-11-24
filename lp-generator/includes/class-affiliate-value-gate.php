<?php
/** Compliance and thin-content gate for Amazon affiliate LPs. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class AffiliateValueGate {
 public static function check( array $brief, array $campaign, array $context = array() ): array {
  $missing = array();
  foreach ( array( 'offer_name', 'amazon_marketplace', 'asin', 'affiliate_tag', 'source_reference', 'original_analysis' ) as $key ) {
   if ( '' === trim( (string) ( $brief[ $key ] ?? '' ) ) ) { $missing[] = $key; }
  }
  $marketplace = sanitize_key( (string) ( $brief['amazon_marketplace'] ?? '' ) );
  if ( '' !== $marketplace && ! in_array( $marketplace, Amazon::REGIONS, true ) ) {
   $missing[] = 'amazon_marketplace_invalid';
  }
  $analysis = (string) ( $brief['original_analysis'] ?? '' );
  if ( mb_strlen( trim( $analysis ) ) < 120 ) {
   $missing[] = 'original_analysis_too_short';
  }
  $flags = array();
  if ( preg_match( '/\b(i tested|saya sudah mencoba|hands[- ]on review)\b/i', $analysis ) && empty( $context['testing_evidence'] ) ) {
   $flags[] = 'unsupported_personal_testing_claim';
  }
  $json = (string) wp_json_encode( $context['data'] ?? array() );
  if ( stripos( $json, 'aggregateRating' ) !== false || stripos( $json, 'priceSpecification' ) !== false ) {
   $flags[] = 'unsupported_rating_or_offer_claim';
  }
  if ( ! empty( $context['disclosure_present'] ) === false && empty( $campaign['amazon_associate_disclosure'] ) ) {
   // Disclosure is rendered by the template; absence here is only a context warning.
   $flags[] = 'disclosure_context_missing';
  }
  $decision = ( $missing || in_array( 'unsupported_personal_testing_claim', $flags, true ) || in_array( 'unsupported_rating_or_offer_claim', $flags, true ) ) ? 'HOLD' : 'PASS';
  return array(
   'decision' => $decision,
   'missing' => array_values( array_unique( $missing ) ),
   'flags' => array_values( array_unique( $flags ) ),
   'note' => 'No price, availability, rating or hands-on claim may be fabricated.',
  );
 }
}
