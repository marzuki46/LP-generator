<?php
/** Content and links for hierarchical LP parent pages. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LpParentPages {
 /** Sanitize the JSON/array input from campaign settings. */
 public static function clean( $raw ): array {
  $data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
  if ( ! is_array( $data ) ) { return array(); }
  $out = array();
  foreach ( $data as $path => $fields ) {
   $path = FunnelContent::clean_path( is_scalar( $path ) ? (string) $path : '' );
   if ( '' === $path || ! is_array( $fields ) ) { continue; }
   $out[ $path ] = array(
    'title' => Utils::truncate( sanitize_text_field( (string) ( $fields['title'] ?? '' ) ), 120 ),
    'intro' => Utils::truncate( sanitize_textarea_field( (string) ( $fields['intro'] ?? '' ) ), 500 ),
    'body'  => sanitize_textarea_field( (string) ( $fields['body'] ?? '' ) ),
    'cta'   => Utils::truncate( sanitize_text_field( (string) ( $fields['cta'] ?? '' ) ), 80 ),
   );
  }
  return $out;
 }

 public static function all( array $campaign ): array {
  return self::clean( $campaign['parent_pages'] ?? '' );
 }

 /** Get one parent spec with sensible defaults derived from the path. */
 public static function for_path( array $campaign, string $path ): array {
  $all     = self::all( $campaign );
  $path    = FunnelContent::clean_path( $path );
  $parts   = explode( '/', $path );
  $slug    = end( $parts ) ?: $path;
  $default = ucwords( str_replace( '-', ' ', $slug ) );
  $spec    = isset( $all[ $path ] ) ? $all[ $path ] : array();
  return array(
   'title' => '' !== ( $spec['title'] ?? '' ) ? (string) $spec['title'] : $default,
   'intro' => (string) ( $spec['intro'] ?? '' ),
   'body'  => (string) ( $spec['body'] ?? '' ),
   'cta'   => '' !== ( $spec['cta'] ?? '' ) ? (string) $spec['cta'] : (string) ( $campaign['cta'] ?? __( 'Lihat detail', 'lp-generator' ) ),
  );
 }

 /** Child LP campaigns under this parent path (one level deeper). */
 public static function regions( array $campaign, string $path ): array {
  $out      = array();
  $own_path = FunnelContent::clean_path( (string) ( $campaign['bofu_path'] ?? '' ) );
  if ( '' !== $own_path && str_starts_with( $own_path, $path . '/' ) && substr_count( $own_path, '/' ) === substr_count( $path, '/' ) + 1 ) {
   $out[] = array(
    'title' => (string) ( $campaign['offer_name'] ?? $campaign['campaign_title'] ?? $path ),
    'url'   => home_url( '/' . $own_path . '/' ),
   );
  }
  if ( ! function_exists( 'get_posts' ) ) { return $out; }
  $campaigns = get_posts( array( 'post_type' => 'lpgen_campaign', 'posts_per_page' => 50, 'orderby' => 'ID', 'order' => 'DESC' ) );
  foreach ( is_array( $campaigns ) ? $campaigns : array() as $post ) {
   $child = FunnelContent::clean_path( (string) get_post_meta( (int) $post->ID, '_lpgen_bofu_path', true ) );
   if ( '' === $child || ! str_starts_with( $child, $path . '/' ) || substr_count( $child, '/' ) !== substr_count( $path, '/' ) + 1 ) { continue; }
   $post_id = (int) get_post_meta( (int) $post->ID, '_lpgen_campaign_bofu_post', true );
   $link    = $post_id > 0 ? get_permalink( $post_id ) : '';
   $out[]   = array(
    'title' => (string) ( get_post_meta( (int) $post->ID, '_lpgen_offer_name', true ) ?: get_the_title( (int) $post->ID ) ),
    'url'   => '' !== (string) $link ? (string) $link : home_url( '/' . $child . '/' ),
   );
  }
  $seen = array();
  $clean = array();
  foreach ( $out as $row ) {
   $key = $row['url'];
   if ( isset( $seen[ $key ] ) ) { continue; }
   $seen[ $key ] = 1;
   $clean[] = $row;
  }
  return array_slice( $clean, 0, 12 );
 }

 /** Reader-facing Custom HTML block for one responsive parent page. */
 public static function blocks( array $campaign, string $path, string $next_path, string $lp_url, string $anchor ): string {
  $spec    = self::for_path( $campaign, $path );
  $model   = LpTemplate::clean( $campaign['lp_model'] ?? 'editorial' );
  $regions = self::regions( $campaign, $path );
  $html    = '<div class="lpg-lp lpg-lp--parent lpg-lpm lpg-lpm--' . esc_attr( $model ) . '" data-lp-model="' . esc_attr( $model ) . '" data-parent-path="' . esc_attr( $path ) . '">';
  $html   .= '<nav class="lpg-lpm__nav" aria-label="' . esc_attr__( 'Navigasi wilayah', 'lp-generator' ) . '"><span class="lpg-lpm__brand">' . esc_html( (string) ( $campaign['brand'] ?? $campaign['offer_name'] ?? '' ) ) . '</span><a href="' . esc_url( $lp_url ) . '">' . esc_html( $anchor ) . '</a></nav>';
  $html   .= '<header class="lpg-parent__hero"><p class="lpg-lp__eyebrow">' . esc_html( $path ) . '</p><h1>' . esc_html( $spec['title'] ) . '</h1>';
  if ( '' !== $spec['intro'] ) { $html .= '<p class="lpg-parent__intro">' . esc_html( $spec['intro'] ) . '</p>'; }
  $html   .= '</header>';
  $paragraphs = preg_split( '/\R\s*\R/u', trim( $spec['body'] ) ) ?: array();
  if ( $paragraphs ) {
   $html .= '<section class="lpg-parent__body">';
   foreach ( $paragraphs as $paragraph ) {
    if ( '' !== trim( $paragraph ) ) { $html .= '<p>' . esc_html( $paragraph ) . '</p>'; }
   }
   $html .= '</section>';
  }
  if ( $regions ) {
   $html .= '<section class="lpg-parent__menu"><h2>' . esc_html__( 'Pilih wilayah / layanan', 'lp-generator' ) . '</h2><div class="lpg-parent__grid">';
   foreach ( $regions as $region ) {
    $html .= '<a class="lpg-related__card" href="' . esc_url( $region['url'] ) . '"><span class="lpg-related__kicker">WILAYAH</span><span class="lpg-related__title">' . esc_html( $region['title'] ) . '</span><span class="lpg-related__link">' . esc_html__( 'Lihat halaman', 'lp-generator' ) . ' &rarr;</span></a>';
   }
   $html .= '</div></section>';
  }
  $html .= self::contact( $campaign );
  $html .= '</div>';
  return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
 }

 /** Contact Us block for this campaign only. */
 public static function contact( array $campaign ): string {
  $phone   = trim( (string) ( $campaign['phone'] ?? '' ) );
  $email   = trim( (string) ( $campaign['contact_email'] ?? '' ) );
  $address = trim( (string) ( $campaign['contact_address'] ?? '' ) );
  $hours   = trim( (string) ( $campaign['contact_hours'] ?? '' ) );
  $wa      = '' !== $phone ? 'https://wa.me/' . preg_replace( '/\D+/', '', $phone ) : '';
  $html    = '<section class="lpg-parent__contact" id="kontak"><h2>' . esc_html__( 'Hubungi kami', 'lp-generator' ) . '</h2><ul>';
  if ( '' !== $phone ) { $html .= '<li>' . esc_html__( 'Telepon / WhatsApp', 'lp-generator' ) . ': <a href="' . esc_url( $wa ) . '">' . esc_html( $phone ) . '</a></li>'; }
  if ( '' !== $email ) { $html .= '<li>' . esc_html__( 'Email', 'lp-generator' ) . ': <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></li>'; }
  if ( '' !== $address ) { $html .= '<li>' . esc_html__( 'Alamat', 'lp-generator' ) . ': ' . esc_html( $address ) . '</li>'; }
  if ( '' !== $hours ) { $html .= '<li>' . esc_html__( 'Jam operasional', 'lp-generator' ) . ': ' . esc_html( $hours ) . '</li>'; }
  $html .= '</ul></section>';
  return $html;
 }
}
