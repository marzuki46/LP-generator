<?php
/** Contextual internal-link ranking based on the content graph. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class InternalLinkEngine {
 public static function nodes( int $campaign_id ): array {
  $nodes = array();
  foreach ( ContentRepository::items( $campaign_id ) as $item ) {
   $nodes[] = array(
    'id' => (int) $item['id'],
    'type' => 'page',
    'kind' => (string) ( $item['kind'] ?? '' ),
    'stage' => (string) ( $item['stage'] ?? '' ),
    'status' => (string) ( $item['status'] ?? '' ),
    'title' => (string) ( $item['title'] ?? '' ),
    'url' => (string) ( $item['permalink'] ?? '' ),
    'campaign_id' => $campaign_id,
   );
  }
  if ( function_exists( 'get_posts' ) ) {
   foreach ( get_posts( array( 'post_type' => 'lpgen_campaign', 'posts_per_page' => 50 ) ) as $post ) {
    $nodes[] = array(
     'id' => (int) $post->ID,
     'type' => 'campaign',
     'path' => FunnelContent::clean_path( (string) get_post_meta( (int) $post->ID, '_lpgen_bofu_path', true ) ),
     'city' => (string) get_post_meta( (int) $post->ID, '_lpgen_city', true ),
     'title' => get_the_title( (int) $post->ID ),
     'campaign_id' => (int) $post->ID,
    );
   }
  }
  return $nodes;
 }

 public static function edges( int $campaign_id ): array {
  $campaign = ContentRepository::get_campaign( $campaign_id );
  $path = FunnelContent::clean_path( (string) ( $campaign['bofu_path'] ?? '' ) );
  $edges = array();
  if ( '' !== $path ) {
   $parts = explode( '/', $path );
   array_pop( $parts );
   $cumulative = array();
   foreach ( $parts as $part ) {
    $cumulative[] = $part;
    $edges[] = array( 'from' => $path, 'to' => implode( '/', $cumulative ), 'type' => 'PARENT_HUB' );
   }
  }
  foreach ( self::nodes( $campaign_id ) as $node ) {
   if ( 'page' !== $node['type'] || 'lp' === $node['kind'] ) { continue; }
   $edges[] = array( 'from' => $node['id'], 'to' => $campaign_id, 'type' => in_array( $node['stage'], array( 'tofu', 'mofu' ), true ) ? 'SUPPORTING_GUIDE' : 'RELATED_GUIDE' );
  }
  return $edges;
 }

 public static function suggest( int $campaign_id, array $context = array(), int $limit = 6 ): array {
  $campaign = ContentRepository::get_campaign( $campaign_id );
  $type     = LpTypeRegistry::clean( $campaign['lp_type'] ?? '' );
  $priority = LpTypeRegistry::get( $type )->link_strategy();
  $city     = strtolower( (string) ( $context['city'] ?? ( $campaign['city'] ?? '' ) ) );
  $language = LanguageService::clean( (string) ( $context['language'] ?? ( $campaign['language'] ?? 'id' ) ) );
  $out = array();
  foreach ( ContentRepository::items( $campaign_id ) as $item ) {
   $status = (string) ( $item['status'] ?? '' );
   if ( ! in_array( $status, array( 'approved', 'published' ), true ) || 'topic' === (string) ( $item['kind'] ?? '' ) ) { continue; }
   if ( (int) ( $item['id'] ?? 0 ) === (int) ( $context['exclude_item_id'] ?? 0 ) ) { continue; }
   $url = (string) ( $item['permalink'] ?? '' );
   if ( '' === $url && ! empty( $item['post_id'] ) ) { $url = (string) get_permalink( (int) $item['post_id'] ); }
   if ( '' === $url ) { continue; }
   $stage = (string) ( $item['stage'] ?? '' );
   $reason = ( 'lp' === ( $item['kind'] ?? '' ) ) ? 'PARENT_HUB' : ( in_array( $stage, array( 'tofu', 'mofu' ), true ) ? 'SUPPORTING_GUIDE' : 'RELATED_GUIDE' );
   $score = 5;
   $index = array_search( $reason, $priority, true );
   if ( false !== $index ) { $score += ( count( $priority ) - $index ); }
   if ( '' !== $city && false !== mb_stripos( (string) ( $item['title'] ?? '' ), $city ) ) { $score += 3; }
   if ( 'en' === $language && preg_match( '/\b(the|how|why|best)\b/i', (string) ( $item['title'] ?? '' ) ) ) { $score += 1; }
   $out[] = array(
    'id' => (int) $item['id'],
    'title' => (string) ( $item['title'] ?? '' ),
    'url' => $url,
    'stage' => $stage,
    'reason' => $reason,
    'score' => $score,
   );
  }
  usort( $out, static function ( $a, $b ): int { return (int) $b['score'] <=> (int) $a['score']; } );
  return array_slice( $out, 0, max( 1, min( 12, $limit ) ) );
 }

 public static function suggest_for_lp( int $campaign_id ): array {
  return self::suggest( $campaign_id, array( 'exclude_item_id' => 0 ), 6 );
 }
}
