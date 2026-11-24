<?php
/** Structured data registry; stable @id references, no duplicated graph nodes. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class SchemaRegistry {
 public static function graph( array $campaign, array $data, array $context = array() ): array {
  $type  = LpTypeRegistry::clean( $campaign['lp_type'] ?? '' );
  $url   = (string) ( $context['url'] ?? get_permalink( absint( $context['post_id'] ?? 0 ) ) );
  $org   = self::org_id();
  $nodes = array();

  $nodes[] = array(
   '@type' => 'WebPage',
   '@id' => $url . '#webpage',
   'url' => $url,
   'name' => (string) ( $data['seo_title'] ?? $data['title'] ?? '' ),
   'description' => (string) ( $data['meta_description'] ?? '' ),
   'inLanguage' => LanguageService::html_lang( (string) ( $campaign['language'] ?? 'id' ) ),
   'isPartOf' => array( '@id' => self::website_id() ),
  );

  if ( 'service' === $type ) {
   $service = array(
    '@type' => 'Service',
    '@id' => $url . '#service',
    'name' => (string) ( $campaign['offer_name'] ?? '' ),
    'description' => (string) ( $data['intro'] ?? $campaign['promise'] ?? '' ),
    'provider' => array( '@id' => $org ),
   );
   if ( ! empty( $campaign['city'] ) ) {
    $service['areaServed'] = array( '@type' => 'City', 'name' => (string) $campaign['city'] );
   }
   if ( ! empty( $campaign['price'] ) && 'available' === (string) ( $campaign['product_status'] ?? 'available' ) ) {
    $service['offers'] = array( '@type' => 'Offer', 'priceSpecification' => array( '@type' => 'PriceSpecification', 'description' => (string) $campaign['price'] ) );
   }
   $nodes[] = $service;
  }

  if ( 'product' === $type ) {
   $software = in_array( (string) ( $campaign['product_type'] ?? '' ), array( 'saas', 'digital' ), true );
   $node = array(
    '@type' => $software ? 'SoftwareApplication' : 'Product',
    '@id' => $url . '#product',
    'name' => (string) ( $campaign['offer_name'] ?? '' ),
    'description' => (string) ( $data['intro'] ?? $campaign['promise'] ?? '' ),
   );
   if ( ! empty( $campaign['price'] ) && in_array( (string) ( $campaign['product_status'] ?? '' ), array( '', 'available', 'preorder' ), true ) ) {
    $node['offers'] = array( '@type' => 'Offer', 'priceSpecification' => array( '@type' => 'PriceSpecification', 'description' => (string) $campaign['price'] ) );
   }
   $nodes[] = $node;
  }

  if ( 'amazon_affiliate' === $type ) {
   // No aggregateRating, Review rating, offers or merchant claims. Article is the safe base.
   $article = array(
    '@type' => 'Article',
    '@id' => $url . '#article',
    'headline' => (string) ( $data['title'] ?? '' ),
    'description' => (string) ( $data['meta_description'] ?? '' ),
    'author' => array( '@id' => $org ),
    'publisher' => array( '@id' => $org ),
    'inLanguage' => LanguageService::html_lang( (string) ( $campaign['language'] ?? 'id' ) ),
   );
   if ( ! empty( $campaign['amazon_source_reference'] ) ) {
    $article['citation'] = (string) $campaign['amazon_source_reference'];
   }
   $nodes[] = $article;
  }

  return self::dedupe( $nodes );
 }

 /** Inject into Yoast graph by @id, never duplicate Organization/WebSite. */
 public static function inject_yoast( $graph, $context = null ) {
  if ( ! is_array( $graph ) ) { return $graph; }
  if ( is_object( $context ) ) { $post_id = absint( $context->ID ?? 0 ); }
  elseif ( is_array( $context ) ) { $post_id = absint( $context['ID'] ?? 0 ); }
  else { $post_id = 0; }
  if ( $post_id <= 0 || ! (int) get_post_meta( $post_id, '_lpgen_item_id', true ) ) { return $graph; }
  $campaign_id = (int) get_post_meta( $post_id, '_lpgen_campaign_id', true );
  $campaign = ContentRepository::get_campaign( $campaign_id );
  if ( ! $campaign ) { return $graph; }
  $structured = json_decode( (string) get_post_meta( $post_id, '_lpgen_structured', true ), true );
  $structured = is_array( $structured ) ? $structured : array();
  $add = self::graph( $campaign, $structured, array( 'post_id' => $post_id, 'url' => (string) get_permalink( $post_id ) ) );
  $ids = array();
  foreach ( $graph as $node ) {
   if ( is_array( $node ) && ! empty( $node['@id'] ) ) { $ids[ (string) $node['@id'] ] = true; }
  }
  foreach ( $add as $node ) {
   if ( empty( $node['@id'] ) || ! isset( $ids[ (string) $node['@id'] ] ) ) { $graph[] = $node; }
  }
  return $graph;
 }

 /** Fallback only when no SEO plugin owns the graph. */
 public static function fallback_output( array $campaign, array $data, array $context = array() ): string {
  if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) { return ''; }
  $graph = self::graph( $campaign, $data, $context );
  return '<script type="application/ld+json" class="lpgen-schema">' . (string) wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
 }

 private static function dedupe( array $nodes ): array {
  $seen = array();
  $out = array();
  foreach ( $nodes as $node ) {
   $id = is_array( $node ) ? (string) ( $node['@id'] ?? '' ) : '';
   if ( '' !== $id && isset( $seen[ $id ] ) ) { continue; }
   if ( '' !== $id ) { $seen[ $id ] = true; }
   $out[] = $node;
  }
  return $out;
 }

 public static function maybe_output_fallback(): void {
  if ( ! is_singular( array( 'page', 'post' ) ) ) { return; }
  $post_id = (int) get_queried_object_id();
  if ( $post_id <= 0 || ! (int) get_post_meta( $post_id, '_lpgen_item_id', true ) ) { return; }
  $campaign = ContentRepository::get_campaign( (int) get_post_meta( $post_id, '_lpgen_campaign_id', true ) );
  if ( ! $campaign ) { return; }
  $data = json_decode( (string) get_post_meta( $post_id, '_lpgen_structured', true ), true );
  echo self::fallback_output( $campaign, is_array( $data ) ? $data : array(), array( 'post_id' => $post_id, 'url' => (string) get_permalink( $post_id ) ) );
 }

 private static function org_id(): string { return home_url( '/#organization' ); }
 private static function website_id(): string { return home_url( '/#website' ); }
}
