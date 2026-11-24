<?php
/** Canonical LP spec: deterministic, normalised, warning-producing. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LpSpec {
 public const FIXED_FIELDS = array(
  'campaign_id','item_id','offer_name','offer_url','offer_type','audience','problem',
  'promise','benefits','price','city','language','framework','framework_slug',
  'lp_model','lp_target_keyword','sections','sources','entities','created_at','checked_at',
 );

 /** Path to the JSON Schema (documentation + external consumers). */
 public static function schema_path(): string {
  return dirname( __DIR__ ) . '/schemas/lp-spec.schema.json';
 }

 /**
  * Build a canonical spec from AI output + campaign context.
  * Missing values are repaired with warnings; nothing throws for cosmetic gaps.
  */
 public static function normalize( array $raw, array $campaign, int $item_id = 0 ): array {
  $slug        = sanitize_key( (string) ( $campaign['framework_slug'] ?? 'aida' ) );
  $contract    = DesignContract::contract( $slug );
  $now         = gmdate( 'Y-m-d H:i:s' );
  $spec        = array();
  $spec['campaign_id'] = absint( $raw['campaign_id'] ?? ( $campaign['campaign_id'] ?? 0 ) );
  $spec['item_id']     = absint( $raw['item_id'] ?? $item_id );
  foreach ( array( 'offer_name','offer_url','offer_type','audience','problem','promise','price','city','language','lp_target_keyword' ) as $key ) {
   $value = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : (string) ( $campaign[ $key ] ?? '' );
   $spec[ $key ] = Utils::truncate( sanitize_text_field( $value ), 500 );
  }
  $benefits = isset( $raw['benefits'] ) ? $raw['benefits'] : ( $campaign['benefits'] ?? array() );
  $spec['benefits'] = self::string_list( is_array( $benefits ) ? $benefits : array() );
  $spec['framework']      = (string) ( $campaign['framework'] ?? Frameworks::get( $slug )['label'] );
  $spec['framework_slug'] = $slug;
  $spec['lp_model']       = LpTemplate::clean( $raw['lp_model'] ?? ( $campaign['lp_model'] ?? 'editorial' ) );
  $spec['entities']       = self::string_list( is_array( $raw['entities'] ?? null ) ? $raw['entities'] : array() );
  $spec['sources']        = is_array( $raw['sources'] ?? null ) ? array_values( $raw['sources'] ) : (array) ( $campaign['research_sources'] ?? array() );
  $spec['created_at'] = (string) ( $raw['created_at'] ?? $now );
  $spec['checked_at'] = (string) ( $raw['checked_at'] ?? $now );

  $ai_sections = isset( $raw['sections'] ) && is_array( $raw['sections'] ) ? array_values( $raw['sections'] ) : array();
  $sections = array();
  foreach ( $contract as $index => $slot ) {
   $ai = isset( $ai_sections[ $index ] ) && is_array( $ai_sections[ $index ] ) ? $ai_sections[ $index ] : array();
   $section = array(
    'id'            => (string) $slot['id'],
    'step'          => (int) ( $ai['step'] ?? ( $index + 1 ) ),
    'hint'          => (string) $slot['hint'],
    'layout'        => (string) $slot['layout'],
    'image_slot'    => (string) $slot['image_slot'],
    'role'          => (string) $slot['role'],
    'shape'         => (string) $slot['shape'],
    'heading_goal'  => Utils::truncate( sanitize_text_field( (string) ( $ai['heading'] ?? $ai['heading_goal'] ?? '' ) ), 200 ),
    'body'          => (string) ( $ai['body'] ?? '' ),
    'image_keyword' => Utils::truncate( sanitize_text_field( (string) ( $ai['image_keyword'] ?? '' ) ), 120 ),
    'table_needed'  => ! empty( $ai['table'] ) || ! empty( $ai['target_table'] ),
    'pain_points'   => self::string_list( is_array( $ai['pain_points'] ?? null ) ? $ai['pain_points'] : array() ),
    'cta_type'      => (string) ( $campaign['offer_type'] ?? 'service' ),
    'internal_link_target' => '' !== $spec['lp_target_keyword'] ? $spec['lp_target_keyword'] : trim( $spec['offer_name'] . ' ' . $spec['city'] ),
   );
   if ( '' === $section['heading_goal'] ) { $section['heading_goal'] = 'Jelaskan ' . $slot['hint'] . ' untuk ' . $spec['offer_name']; }
   $sections[] = $section;
  }
  $spec['sections'] = $sections;

  // Host-specific facts are preserved by design (additionalProperties: true).
  foreach ( $raw as $key => $value ) {
   if ( ! in_array( $key, self::FIXED_FIELDS, true ) && ! isset( $spec[ $key ] ) ) {
    $spec[ $key ] = is_scalar( $value ) ? Utils::truncate( sanitize_text_field( (string) $value ), 300 ) : $value;
   }
  }
  return $spec;
 }

 private static function string_list( array $values ): array {
  $out = array();
  foreach ( $values as $value ) {
   if ( is_array( $value ) ) { $value = (string) ( $value['term'] ?? $value['title'] ?? '' ); }
   $text = Utils::truncate( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 160 );
   if ( '' !== $text && ! in_array( $text, $out, true ) ) { $out[] = $text; }
  }
  return array_slice( $out, 0, 20 );
 }
}
