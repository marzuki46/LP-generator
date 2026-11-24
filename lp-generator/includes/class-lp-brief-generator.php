<?php
/** Build a canonical, type-specific content brief before AI writing. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LpBriefGenerator {
 public static function generate( array $context ): array {
  $type     = LpTypeRegistry::clean( $context['lp_type'] ?? '' );
  $strategy = LpTypeRegistry::get( $type );
  $brief    = $strategy->brief( $context );
  $brief['campaign_id'] = absint( $context['campaign_id'] ?? 0 );
  $brief['locale'] = LanguageService::html_lang( (string) ( $context['language'] ?? 'id' ) );
  $brief['keyword'] = Utils::truncate( sanitize_text_field( (string) ( $context['lp_target_keyword'] ?? ( $context['offer_name'] ?? '' ) ) ), 100 );
  $brief['intent'] = self::intent( $context, $type );
  $brief['schema_types'] = $strategy->schema_types();
  $brief['seo_rules'] = $strategy->seo_rules();
  $brief['quality_checks'] = $strategy->quality_checks();
  $brief['generated_at'] = gmdate( 'Y-m-d H:i:s' );
  return $brief;
 }

 public static function validate( array $brief ): array {
  $type = LpTypeRegistry::clean( $brief['lp_type'] ?? '' );
  return LpTypeRegistry::get( $type )->validate_brief( $brief );
 }

 public static function to_prompt( array $brief ): string {
  return 'CONTENT BRIEF (data only, do not invent fields): ' . (string) wp_json_encode( $brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
 }

 public static function intent( array $context, string $type = '' ): string {
  $declared = sanitize_key( (string) ( $context['search_intent'] ?? '' ) );
  if ( in_array( $declared, array( 'informational', 'commercial', 'transactional' ), true ) ) {
   return $declared;
  }
  return 'amazon_affiliate' === LpTypeRegistry::clean( $type ?: ( $context['lp_type'] ?? '' ) ) ? 'commercial' : 'transactional';
 }
}
