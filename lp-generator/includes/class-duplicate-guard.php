<?php
/** Duplicate / doorway risk detection across generated pages. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class DuplicateGuard {
 public static function check( array $context, array $data = array() ): array {
  $campaign_id = absint( $context['campaign_id'] ?? 0 );
  $title       = (string) ( $data['title'] ?? $context['title'] ?? '' );
  $slug        = sanitize_title( (string) ( $context['slug'] ?? $title ) );
  $canonical   = (string) ( $context['canonical'] ?? '' );
  $city        = strtolower( (string) ( $context['city'] ?? '' ) );
  $language    = LanguageService::clean( (string) ( $context['language'] ?? 'id' ) );
  $exclude     = absint( $context['exclude_item_id'] ?? 0 );
  $content     = (string) ( $context['content'] ?? self::content_string( $data ) );
  $reasons     = array();
  $score       = 0;

  foreach ( ContentRepository::items( $campaign_id ) as $item ) {
   $id = (int) ( $item['id'] ?? 0 );
   if ( $id === $exclude ) { continue; }
   $other_title = (string) ( $item['title'] ?? '' );
   if ( '' !== $title && FunnelContent::normalize( $title ) === FunnelContent::normalize( $other_title ) ) {
    $score += 60;
    $reasons[] = 'judul identik dengan item #' . $id;
   }
   $known_cities = array_values( array_filter( array_map( 'trim', (array) ( $context['known_cities'] ?? array( $city ) ) ) ) );
   if ( '' !== $city && self::city_swapped_title( $title, $other_title, $known_cities ) ) {
    $score += 35;
    $reasons[] = 'judul hanya mengganti kota dengan item #' . $id;
   }
   $other_content = (string) ( $item['content'] ?? '' );
   if ( '' !== $content && '' !== $other_content && FunnelContent::similar( $content, $other_content ) ) {
    $score += 40;
    $reasons[] = 'kemiripan isi tinggi dengan item #' . $id;
   }
   $headings = self::headings( $data );
   $other_headings = self::headings( json_decode( (string) ( $item['structured'] ?? '' ), true ) ?: array() );
   if ( $headings && $other_headings && count( array_intersect( $headings, $other_headings ) ) >= 2 ) {
    $score += 20;
    $reasons[] = 'pola heading berulang dengan item #' . $id;
   }
  }
  if ( '' !== $canonical ) {
   foreach ( ContentRepository::items( 0 ) as $item ) {
    if ( (int) ( $item['id'] ?? 0 ) === $exclude ) { continue; }
    $other = (string) get_post_meta( (int) ( $item['post_id'] ?? 0 ), '_lpgen_canonical', true );
    if ( '' !== $other && trailingslashit( $other ) === trailingslashit( $canonical ) ) {
     $score += 60;
     $reasons[] = 'canonical bentrok dengan item #' . (int) $item['id'];
     break;
    }
   }
  }
  foreach ( TopicMemory::search( $title, 3 ) as $match ) {
   if ( ! empty( $match['duplicate_risk'] ) && (int) ( $match['item_id'] ?? 0 ) !== $exclude ) {
    $score += 25;
    $reasons[] = 'memori semantik menandai kemiripan dengan #' . (int) $match['item_id'];
    break;
   }
  }
  $score = min( 100, $score );
  return array(
   'score' => $score,
   'risk' => $score >= 70 ? 'high' : ( $score >= 40 ? 'medium' : 'low' ),
   'reasons' => array_values( array_unique( $reasons ) ),
   'language' => $language,
  );
 }

 private static function content_string( array $data ): string {
  $parts = array();
  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( is_array( $section ) ) { $parts[] = (string) ( $section['heading'] ?? '' ) . ' ' . (string) ( $section['body'] ?? '' ); }
  }
  return implode( "\n\n", $parts );
 }

 private static function headings( array $data ): array {
  $out = array();
  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( is_array( $section ) && ! empty( $section['heading'] ) ) { $out[] = FunnelContent::normalize( (string) $section['heading'] ); }
  }
  return $out;
 }

 private static function city_swapped_title( string $left, string $right, array $cities ): bool {
  if ( ! $cities || '' === $left || '' === $right ) { return false; }
  $normalize = static function ( string $text ) use ( $cities ): string {
   foreach ( $cities as $city ) {
    if ( '' === (string) $city ) { continue; }
    $text = preg_replace( '/\b' . preg_quote( (string) $city, '/' ) . '\b/iu', ' CITY ', $text ) ?? $text;
   }
   return FunnelContent::normalize( $text );
  };
  return $normalize( $left ) === $normalize( $right ) && FunnelContent::normalize( $left ) !== FunnelContent::normalize( $right );
 }
}
