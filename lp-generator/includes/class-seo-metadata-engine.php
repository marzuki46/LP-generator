<?php
/** Validated SEO metadata; Yoast remains the head-output authority. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class SeoMetadataEngine {
 public static function build( array $data, array $campaign, array $context = array() ): array {
  $post_id   = absint( $context['post_id'] ?? ( $campaign['post_id'] ?? 0 ) );
  $language  = LanguageService::clean( (string) ( $campaign['language'] ?? 'id' ) );
  $title     = trim( (string) ( $data['seo_title'] ?? $data['title'] ?? '' ) );
  $desc      = trim( (string) ( $data['meta_description'] ?? '' ) );
  $h1        = trim( (string) ( $data['title'] ?? '' ) );
  $canonical = $post_id > 0 ? (string) get_permalink( $post_id ) : (string) ( $campaign['bofu_url'] ?? '' );
  $canonical = '' !== $canonical ? trailingslashit( $canonical ) : '';
  $noindex   = ! empty( $context['experiment'] ) || ! empty( $context['noindex'] ) || ExperimentMode::should_noindex( $campaign );
  $images    = is_array( $context['images'] ?? null ) ? $context['images'] : array();
  $first     = reset( $images );
  $alt       = is_array( $first ) ? (string) ( $first['alt'] ?? '' ) : '';
  if ( '' === $alt ) {
   $alt = trim( (string) ( $campaign['offer_name'] ?? '' ) . ' ' . (string) ( $campaign['city'] ?? '' ) );
  }

  $meta = array(
   'seo_title'   => Utils::truncate( $title, 60 ),
   'description' => Utils::truncate( $desc, 155 ),
   'h1'          => $h1,
   'slug'        => sanitize_title( (string) ( $context['slug'] ?? $data['title'] ?? '' ) ),
   'canonical'   => $canonical,
   'robots'      => $noindex ? 'noindex,follow' : 'index,follow,max-image-preview:large',
   'language'    => $language,
   'html_lang'   => LanguageService::html_lang( $language ),
   'og'          => array(
    'title' => Utils::truncate( $title, 90 ),
    'description' => Utils::truncate( $desc, 200 ),
    'url' => $canonical,
    'locale' => 'en' === $language ? 'en_US' : 'id_ID',
   ),
   'twitter'     => array( 'card' => 'summary_large_image', 'title' => $title, 'description' => $desc ),
   'image_alt'   => Utils::truncate( sanitize_text_field( $alt ), 125 ),
   'sitemap_eligible' => ! $noindex && 'publish' === (string) ( $context['post_status'] ?? 'publish' ),
   'warnings'    => array(),
   'blockers'    => array(),
  );
  if ( mb_strlen( $title ) > 60 ) { $meta['warnings'][] = 'seo_title: truncated to 60 chars'; }
  if ( mb_strlen( $desc ) > 155 ) { $meta['warnings'][] = 'description: truncated to 155 chars'; }
  if ( '' === $title ) { $meta['blockers'][] = 'seo_title: empty'; }
  if ( '' === $desc ) { $meta['blockers'][] = 'description: empty'; }
  if ( 1 !== preg_match_all( '/<h1\b/i', (string) ( $context['rendered_html'] ?? '' ) ) && '' !== (string) ( $context['rendered_html'] ?? '' ) ) {
   $meta['blockers'][] = 'h1: must be exactly one';
  }
  if ( '' !== $canonical && false !== wp_http_validate_url( $canonical ) ) {
   $home = parse_url( home_url(), PHP_URL_HOST );
   if ( strtolower( (string) parse_url( $canonical, PHP_URL_HOST ) ) !== strtolower( (string) $home ) ) {
    $meta['blockers'][] = 'canonical: external host';
   }
  }
  if ( $noindex && $meta['sitemap_eligible'] ) { $meta['blockers'][] = 'robots: noindex page must not be sitemap eligible'; }
  return $meta;
 }

 public static function store( int $post_id, array $meta ): void {
  if ( $post_id > 0 ) { update_post_meta( $post_id, '_lpgen_v2_seo_meta', wp_json_encode( $meta ) ); }
 }

 /** Yoast metadata inputs only; never outputs a second canonical or schema graph. */
 public static function apply_yoast( int $post_id, array $meta ): void {
  if ( $post_id <= 0 || ! defined( 'WPSEO_VERSION' ) ) { return; }
  update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $meta['seo_title'] ) );
  update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $meta['description'] ) );
  // Focus keyword remains owned by Publisher::apply_seo_meta() (content entity), not SEO title.
 }
}
