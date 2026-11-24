<?php
/** Language system for ID/EN landing pages. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LanguageService {
 public const SUPPORTED = array( 'id', 'en' );

 public static function clean( $value ): string {
  $lang = strtolower( sanitize_key( (string) $value ) );
  return in_array( $lang, self::SUPPORTED, true ) ? $lang : 'id';
 }

 public static function html_lang( string $language ): string {
  return 'en' === self::clean( $language ) ? 'en' : 'id';
 }

 public static function label( string $language, string $in = 'id' ): string {
  $language = self::clean( $language );
  $in       = self::clean( $in );
  $labels   = array(
   'id' => array( 'id' => 'Bahasa Indonesia', 'en' => 'Indonesian' ),
   'en' => array( 'id' => 'Bahasa Inggris', 'en' => 'English' ),
  );
  return (string) ( $labels[ $language ][ $in ] ?? $language );
 }

 /** URL prefix planned for new pages. Existing URLs are never rewritten. */
 public static function slug_prefix( string $language ): string {
  return self::clean( $language );
 }

 /** Example planned path only; no redirect or canonical change is applied. */
 public static function planned_path( string $language, string $path, string $lp_type = 'service' ): string {
  $path = FunnelContent::clean_path( $path );
  if ( '' === $path ) { return ''; }
  $prefix = 'en' === self::clean( $language ) ? 'en' : 'id';
  $segments = explode( '/', $path );
  $first = array_shift( $segments );
  $map = array(
   'en' => array( 'jasa' => 'services', 'produk' => 'products', 'review' => 'reviews' ),
  );
  if ( 'en' === $prefix && isset( $map['en'][ $first ] ) ) {
   $first = $map['en'][ $first ];
  }
  return '/' . $prefix . '/' . trim( implode( '/', array_merge( array( $first ), $segments ) ), '/' ) . '/';
 }

 /** Hreflang pairs only for language versions that actually exist. */
 public static function hreflang_plan( array $existing ): array {
  $out = array();
  foreach ( $existing as $language => $url ) {
   $lang = self::clean( (string) $language );
   $url  = (string) $url;
   if ( '' === $url ) { continue; }
   $out[ $lang ] = $url;
  }
  if ( isset( $out['id'], $out['en'] ) ) {
   $out['x-default'] = $out['id'];
  }
  return $out;
 }

 /** Localized UI strings for headings, breadcrumbs, CTAs, FAQ and validation. */
 public static function strings( string $language ): array {
  if ( 'en' === self::clean( $language ) ) {
   return array(
    'nav' => 'Navigation',
    'breadcrumb' => 'Breadcrumb',
    'home' => 'Home',
    'related' => 'Related guides',
    'related_kicker' => 'Guide',
    'learn_more' => 'Learn more before deciding',
    'contact' => 'Contact us',
    'service_area' => 'Service area',
    'nav_page' => 'Page navigation',
    'price_order' => 'Price and order',
    'starting_from' => 'STARTING FROM',
    'offer_summary' => 'Offer summary',
    'packages_investment' => 'PACKAGES & INVESTMENT',
    'disclosure_label' => 'Disclosure:',
    'check_amazon' => 'Check availability on Amazon',
    'quick_recommendations' => 'Quick recommendations',
    'chat_whatsapp' => 'Chat on WhatsApp',
    'product_available' => 'Available',
    'product_preorder' => 'Preorder',
    'product_unavailable' => 'Unavailable',
    'amazon_disclosure' => 'As an Amazon Associate I earn from qualifying purchases.',
    'affiliate_disclosure' => 'This page contains affiliate links. We may earn a commission at no additional cost to you. Prices may change; check Amazon for current availability.',
    'faq' => 'Frequently asked questions',
    'cta_primary' => 'Get started',
    'cta_order' => 'Order now',
    'read_guide' => 'Read guide',
    'validation_required' => 'This field is required.',
    'validation_no_source' => 'A source is required for this claim.',
   );
  }
  return array(
   'nav' => 'Navigasi',
   'breadcrumb' => 'Breadcrumb',
   'home' => 'Beranda',
   'related' => 'Panduan terkait',
   'related_kicker' => 'Panduan',
   'learn_more' => 'Pelajari sebelum menentukan pilihan',
   'contact' => 'Hubungi kami',
   'service_area' => 'Area layanan',
   'nav_page' => 'Navigasi halaman',
   'price_order' => 'Harga dan pemesanan',
   'starting_from' => 'MULAI DARI',
   'offer_summary' => 'Ringkasan penawaran',
   'packages_investment' => 'PAKET & INVESTASI',
   'disclosure_label' => 'Disclosure:',
   'check_amazon' => 'Cek ketersediaan di Amazon',
   'quick_recommendations' => 'Rekomendasi singkat',
   'chat_whatsapp' => 'Chat WhatsApp',
   'product_available' => 'Tersedia',
   'product_preorder' => 'Pre-order',
   'product_unavailable' => 'Tidak tersedia',
   'amazon_disclosure' => 'Sebagai Amazon Associate, kami mendapat komisi dari pembelian yang memenuhi syarat.',
   'affiliate_disclosure' => 'Halaman ini memuat tautan afiliasi. Kami dapat menerima komisi tanpa biaya tambahan untuk Anda. Harga dapat berubah; periksa ketersediaan terkini di Amazon.',
   'faq' => 'Pertanyaan yang sering diajukan',
   'cta_primary' => 'Mulai sekarang',
   'cta_order' => 'Pesan sekarang',
   'read_guide' => 'Baca panduan',
   'validation_required' => 'Field ini wajib diisi.',
   'validation_no_source' => 'Klaim ini memerlukan sumber.',
  );
 }
}
