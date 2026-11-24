<?php
/** Visual templates per landing-page type. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class LpTypeTemplate {
 public static function render( array $data, array $campaign, array $images = array() ): string {
  if ( DesignContract::active( $campaign ) ) {
   DesignContract::validate_images( $images, $campaign );
  }
  $type     = LpTypeRegistry::clean( $campaign['lp_type'] ?? '' );
  $strings  = LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) );
  $model    = LpTemplate::model( $campaign );
  $affiliate = 'amazon_affiliate' === $type;
  $url      = (string) ( $campaign['offer_url'] ?? $data['cta_url'] ?? '' );
  $label    = (string) ( $data['cta_text'] ?? $campaign['cta'] ?? '' );
  if ( $affiliate ) {
   $label = (string) ( $campaign['amazon_cta_label'] ?? $strings['check_amazon'] );
  }
  $button = LandingPage::button( $url, $label, $affiliate );

  $slotted = array();
  foreach ( $images as $image ) {
   if ( is_array( $image ) && ! empty( $image['slot_id'] ) ) {
    $slotted[ sanitize_key( (string) $image['slot_id'] ) ] = $image;
   }
  }
  $hero_images = array_values( array_filter( $images, static fn( $image ): bool => is_array( $image ) && empty( $image['slot_id'] ) ) );

  $html = '<div class="lpg-lp lpg-lp--' . esc_attr( sanitize_key( (string) ( $campaign['framework_slug'] ?? 'aida' ) ) )
   . ' lpg-lpt lpg-lpt--' . esc_attr( $type )
   . ' lpg-lpm lpg-lpm--' . esc_attr( $model )
   . '" data-framework="' . esc_attr( sanitize_key( (string) ( $campaign['framework_slug'] ?? 'aida' ) ) )
   . '" data-lp-type="' . esc_attr( $type )
   . '" data-lp-model="' . esc_attr( $model ) . '"';
  if ( DesignContract::active( $campaign ) ) {
   $html .= ' data-design-id="' . esc_attr( DesignContract::id_for( (string) ( $campaign['framework_slug'] ?? '' ) ) ) . '"';
  }
  $html .= '>';
  $html .= LpTemplate::open( $campaign, $data );

  if ( $affiliate ) {
   $html .= '<div class="lpg-lpt__disclosure-top"><strong>' . esc_html( $strings['disclosure_label'] ) . '</strong> '
    . esc_html( $strings['amazon_disclosure'] ) . '</div>';
  }

  $html .= '<header class="lpg-lpt__hero">';
  $html .= '<div class="lpg-lpt__hero-copy"><p class="lpg-lp__eyebrow">' . LandingPage::text( $campaign['offer_name'] ?? '' ) . '</p>';
  $html .= '<h1>' . LandingPage::text( $data['title'] ?? '' ) . '</h1>';
  if ( $affiliate ) {
   $html .= '<p class="lpg-lp__disclosure">' . esc_html( $strings['affiliate_disclosure'] ) . '</p>';
  }
  $html .= '<div class="lpg-lp__intro">' . LandingPage::paragraphs( $data['intro'] ?? '' ) . '</div>' . $button;
  if ( 'service' === $type && ! empty( $campaign['phone'] ) ) {
   $html .= '<a class="lpg-lpt__secondary" href="https://wa.me/' . esc_attr( preg_replace( '/\D+/', '', (string) $campaign['phone'] ) ) . '">' . esc_html( $strings['chat_whatsapp'] ) . '</a>';
  }
  $html .= '</div>' . LandingPage::images( $hero_images, true ) . '</header>';

  if ( 'product' === $type ) {
   $html .= self::product_banner( $campaign );
  }
  if ( $affiliate ) {
   $html .= self::affiliate_quick( $data, $strings );
  }

  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( ! is_array( $section ) ) { continue; }
   $html .= self::section( $section, $slotted, $button, $type );
  }

  if ( 'service' === $type ) {
   $html .= self::service_contact( $campaign );
  }

  $faq = is_array( $data['faq'] ?? null ) ? $data['faq'] : array();
  if ( $faq ) {
   $html .= '<section class="lpg-lpt__faq"><h2>' . esc_html( LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) )['faq'] ) . '</h2>';
   foreach ( $faq as $pair ) {
    if ( is_array( $pair ) && ! empty( $pair['q'] ) && ! empty( $pair['a'] ) ) {
     $html .= '<details><summary>' . LandingPage::text( $pair['q'] ) . '</summary>' . LandingPage::paragraphs( $pair['a'] ) . '</details>';
    }
   }
   $html .= '</section>';
  }

  if ( '' !== $button ) {
   $html .= '<section class="lpg-lpt__final-cta"><h2>' . LandingPage::text( $campaign['promise'] ?? $data['title'] ?? '' ) . '</h2>' . $button . '</section>';
  }
  $html .= LpTemplate::close( $campaign, $data, $button );
  $html .= '</div>';
  return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
 }

 private static function section( array $section, array &$slotted, string $button, string $type ): string {
  $slot       = sanitize_key( (string) ( $section['image_slot'] ?? '' ) );
  $section_id = sanitize_key( (string) ( $section['section_id'] ?? '' ) );
  $html = '<section class="lpg-lpt__section lpg-lpt__section--' . esc_attr( sanitize_key( (string) ( $section['layout'] ?? 'story' ) ) ) . '"'
   . ' data-step="' . absint( $section['step'] ?? 0 ) . '"'
   . ( '' !== $section_id ? ' data-section-id="' . esc_attr( $section_id ) . '"' : '' )
   . ( '' !== $slot ? ' data-image-slot="' . esc_attr( $slot ) . '"' : '' ) . '>';
  $html .= '<div class="lpg-lpt__copy"><h2>' . LandingPage::text( $section['heading'] ?? '' ) . '</h2>' . LandingPage::paragraphs( $section['body'] ?? '' ) . '</div>';
  $cards = is_array( $section['cards'] ?? null ) ? $section['cards'] : array();
  if ( $cards ) {
   $tag = 'process' === (string) ( $section['layout'] ?? '' ) ? 'ol' : 'ul';
   $html .= '<' . $tag . ' class="lpg-lpt__cards">';
   foreach ( array_slice( $cards, 0, 12 ) as $card ) {
    if ( is_array( $card ) ) {
     $html .= '<li><h3>' . LandingPage::text( $card['title'] ?? '' ) . '</h3>' . LandingPage::paragraphs( $card['body'] ?? '' ) . '</li>';
    }
   }
   $html .= '</' . $tag . '>';
  }
  $bullets = is_array( $section['bullets'] ?? null ) ? $section['bullets'] : array();
  if ( $bullets ) {
   $html .= '<ul class="lpg-lp__points">';
   foreach ( array_slice( $bullets, 0, 16 ) as $bullet ) { $html .= '<li>' . LandingPage::text( $bullet ) . '</li>'; }
   $html .= '</ul>';
  }
  $table = is_array( $section['table'] ?? null ) ? $section['table'] : array();
  if ( ! empty( $table['columns'] ) && is_array( $table['rows'] ?? null ) ) {
   $html .= '<div class="lpg-lpt__table-wrap"><table><thead><tr>';
   foreach ( array_slice( $table['columns'], 0, 8 ) as $column ) { $html .= '<th scope="col">' . LandingPage::text( $column ) . '</th>'; }
   $html .= '</tr></thead><tbody>';
   foreach ( array_slice( $table['rows'], 0, 20 ) as $row ) {
    if ( ! is_array( $row ) ) { continue; }
    $html .= '<tr>';
    foreach ( array_slice( $row, 0, 8 ) as $cell ) { $html .= '<td>' . LandingPage::text( $cell ) . '</td>'; }
    $html .= '</tr>';
   }
   $html .= '</tbody></table></div>';
  }
  if ( '' !== $slot && isset( $slotted[ $slot ] ) ) {
   $html .= LandingPage::images( array( $slotted[ $slot ] ) );
   unset( $slotted[ $slot ] );
  }
  if ( 'offer' === (string) ( $section['layout'] ?? '' ) && '' !== $button ) {
   $html .= '<div class="lpg-lpt__section-cta">' . $button . '</div>';
  }
  return $html . '</section>';
 }

 private static function product_banner( array $campaign ): string {
  $status = (string) ( $campaign['product_status'] ?? 'available' );
  $specs  = Utils::lines( (string) ( $campaign['product_specs'] ?? '' ) );
  $html   = '<section class="lpg-lpt__product-banner"><div><p class="lpg-lpt__kicker">' . esc_html( ucfirst( (string) ( $campaign['product_type'] ?? 'product' ) ) ) . '</p>'
   . '<h2>' . LandingPage::text( $campaign['promise'] ?? '' ) . '</h2>';
  if ( ! empty( $campaign['price'] ) ) { $html .= '<p class="lpg-lp__price">' . LandingPage::text( $campaign['price'] ) . '</p>'; }
  $status_strings = LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) );
  $status_label   = $status_strings[ 'product_' . sanitize_key( $status ) ] ?? ucfirst( $status );
  $html .= '<p class="lpg-lpt__status">' . esc_html( $status_label ) . '</p></div>';
  if ( $specs ) {
   $html .= '<ul class="lpg-lpt__specs">';
   foreach ( array_slice( $specs, 0, 6 ) as $spec ) { $html .= '<li>' . LandingPage::text( $spec ) . '</li>'; }
   $html .= '</ul>';
  }
  return $html . '</section>';
 }

 private static function affiliate_quick( array $data, array $strings ): string {
  $cards = array();
  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( ! is_array( $section ) ) { continue; }
   foreach ( is_array( $section['cards'] ?? null ) ? $section['cards'] : array() as $card ) {
    if ( is_array( $card ) && ! empty( $card['title'] ) ) { $cards[] = $card; }
   }
  }
  if ( ! $cards ) { return ''; }
  $html = '<section class="lpg-lpt__quick"><h2>' . esc_html( $strings['quick_recommendations'] ) . '</h2><div class="lpg-lpt__quick-grid">';
  foreach ( array_slice( $cards, 0, 4 ) as $card ) {
   $html .= '<article><h3>' . LandingPage::text( $card['title'] ) . '</h3>' . LandingPage::paragraphs( $card['body'] ?? '' ) . '</article>';
  }
  return $html . '</div></section>';
 }

 private static function service_contact( array $campaign ): string {
  $html = '<section class="lpg-lpt__contact" id="kontak"><h2>' . esc_html( LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) )['contact'] ) . '</h2><ul>';
  if ( ! empty( $campaign['phone'] ) ) { $html .= '<li>' . LandingPage::text( $campaign['phone'] ) . '</li>'; }
  if ( ! empty( $campaign['contact_email'] ) ) { $html .= '<li>' . LandingPage::text( $campaign['contact_email'] ) . '</li>'; }
  if ( ! empty( $campaign['contact_address'] ) ) { $html .= '<li>' . LandingPage::text( $campaign['contact_address'] ) . '</li>'; }
  if ( ! empty( $campaign['contact_hours'] ) ) { $html .= '<li>' . LandingPage::text( $campaign['contact_hours'] ) . '</li>'; }
  return $html . '</ul></section>';
 }
}
