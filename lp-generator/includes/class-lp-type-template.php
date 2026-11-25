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
  $aida_service = ( 'service' === $type && 'aida' === sanitize_key( (string) ( $campaign['framework_slug'] ?? 'aida' ) ) );
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
   . ( $aida_service ? ' lpg-lpt--aida-service' : '' )
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
  $html .= '</div>';
  if ( $aida_service ) {
   $html .= self::hero_aside( $campaign, $strings, $hero_images );
  } else {
   $html .= LandingPage::images( $hero_images, true );
  }
  $html .= '</header>';

  if ( 'product' === $type ) {
   $html .= self::product_banner( $campaign );
  }
  if ( $affiliate ) {
   $html .= self::affiliate_quick( $data, $strings );
  }

  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( ! is_array( $section ) ) { continue; }
   $html .= self::section( $section, $slotted, $button, $type, $aida_service );
  }

  if ( $aida_service ) {
   $html .= self::support_cards( $data, $campaign, $strings );
   $html .= self::price_card( $campaign, $strings, $button );
  }

  if ( 'service' === $type ) {
   $html .= self::service_contact( $campaign );
  }

  $faq = is_array( $data['faq'] ?? null ) ? $data['faq'] : array();
  if ( $faq ) {
   $html .= '<section id="aida-faq" class="lpg-lpt__faq"><h2>' . esc_html( LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) )['faq'] ) . '</h2>';
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

 private static function hero_aside( array $campaign, array $strings, array $hero_images ): string {
  $benefits = array_values( array_filter( array_map( 'trim', (array) ( $campaign['benefits'] ?? array() ) ) ) );
  if ( ! $benefits ) {
   $benefits = array( $strings['needs'], $strings['solution'], $strings['benefits_label'] );
  }
  $html = '<aside class="lpg-lpt__hero-aside" aria-label="' . esc_attr( $strings['benefits_label'] ) . '">';
  if ( $hero_images ) {
   $html .= LandingPage::images( array_slice( $hero_images, 0, 1 ), true );
  }
  $html .= '<div class="lpg-lpt__hero-aside-card"><p class="lpg-lpt__kicker">' . esc_html( $strings['benefits_label'] ) . '</p><ul>';
  foreach ( array_slice( $benefits, 0, 3 ) as $benefit ) {
   $html .= '<li>' . LandingPage::text( (string) $benefit ) . '</li>';
  }
  $html .= '</ul>';
  if ( '' !== trim( (string) ( $campaign['promise'] ?? '' ) ) ) {
   $html .= '<p class="lpg-lpt__hero-aside-note">' . LandingPage::text( (string) $campaign['promise'] ) . '</p>';
  }
  return $html . '</div></aside>';
 }

 private static function price_card( array $campaign, array $strings, string $button ): string {
  $price = trim( (string) ( $campaign['price'] ?? '' ) );
  $tiers = is_array( $campaign['price_tiers'] ?? null ) ? $campaign['price_tiers'] : array();
  if ( ! $tiers && '' !== $price ) {
   $tiers = array( array( 'name' => (string) ( $campaign['offer_name'] ?? '' ), 'price' => $price, 'description' => '', 'features' => array(), 'badge' => '' ) );
  }
  if ( ! $tiers ) { return ''; }
  $html = '<section id="aida-price" class="lpg-lpt__price-card lpg-lpt__price-card--tiers">'
   . '<div class="lpg-lpt__price-head"><p class="lpg-lp__eyebrow">' . esc_html( $strings['pricing'] ) . '</p>'
   . '<h2>' . LandingPage::text( (string) ( $campaign['promise'] ?? $campaign['offer_name'] ?? '' ) ) . '</h2>'
   . '<p class="lpg-lpt__price-sub">' . esc_html( $strings['from_label'] ) . '</p></div>'
   . '<div class="lpg-lpt__price-grid">';
  foreach ( array_slice( $tiers, 0, 4 ) as $tier ) {
   $name = (string) ( $tier['name'] ?? '' );
   $cost = (string) ( $tier['price'] ?? '' );
   if ( '' === $name && '' === $cost ) { continue; }
   $featured = '' !== trim( (string) ( $tier['badge'] ?? '' ) );
   $html .= '<article class="lpg-lpt__price-tier' . ( $featured ? ' lpg-lpt__price-tier--featured' : '' ) . '">';
   if ( $featured ) { $html .= '<span class="lpg-lpt__price-badge">' . LandingPage::text( (string) $tier['badge'] ) . '</span>'; }
   if ( '' !== $name ) { $html .= '<h3>' . LandingPage::text( $name ) . '</h3>'; }
   if ( '' !== $cost ) { $html .= '<p class="lpg-lpt__price-value">' . LandingPage::text( $cost ) . '</p>'; }
   if ( '' !== trim( (string) ( $tier['description'] ?? '' ) ) ) { $html .= '<p class="lpg-lpt__price-desc">' . LandingPage::text( (string) $tier['description'] ) . '</p>'; }
   $features = is_array( $tier['features'] ?? null ) ? $tier['features'] : array();
   if ( $features ) {
    $html .= '<ul class="lpg-lpt__price-features">';
    foreach ( array_slice( $features, 0, 8 ) as $feature ) { $html .= '<li>' . LandingPage::text( (string) $feature ) . '</li>'; }
    $html .= '</ul>';
   }
   if ( '' !== $button ) { $html .= '<div class="lpg-lpt__price-cta">' . $button . '</div>'; }
   $html .= '</article>';
  }
  $html .= '</div>';
  foreach ( array( 'guarantee' => $strings['guarantee'], 'bonus' => $strings['bonus'] ) as $key => $label ) {
   if ( ! empty( $campaign[ $key ] ) ) {
    $html .= '<p class="lpg-lpt__price-meta"><strong>' . esc_html( $label ) . ':</strong> ' . LandingPage::text( (string) $campaign[ $key ] ) . '</p>';
   }
  }
  return $html . '</section>';
 }

 private static function support_cards( array $data, array $campaign, array $strings ): string {
  $cards = array();
  foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
   if ( ! is_array( $section ) || empty( $section['heading'] ) ) { continue; }
   $body = (string) ( $section['body'] ?? '' );
   $cards[] = array( 'title' => (string) $section['heading'], 'body' => Utils::truncate( wp_strip_all_tags( $body ), 180 ) );
   if ( count( $cards ) >= 3 ) { break; }
  }
  if ( ! $cards ) {
   foreach ( array_slice( (array) ( $campaign['benefits'] ?? array() ), 0, 3 ) as $benefit ) {
    $cards[] = array( 'title' => (string) $benefit, 'body' => (string) ( $campaign['promise'] ?? '' ) );
   }
  }
  if ( ! $cards ) { return ''; }
  $html = '<section class="lpg-lpt__support" aria-label="' . esc_attr( $strings['benefits_label'] ) . '"><div class="lpg-lpt__support-head">'
   . '<p class="lpg-lp__eyebrow">' . esc_html( $strings['benefits_label'] ) . '</p>'
   . '<h2>' . esc_html( $strings['solution'] ) . '</h2></div><div class="lpg-lpt__support-grid">';
  foreach ( $cards as $index => $card ) {
   $html .= '<article class="lpg-lpt__support-card"><span class="lpg-lpt__support-num">' . esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>'
    . '<h3>' . LandingPage::text( (string) $card['title'] ) . '</h3>'
    . '<p>' . LandingPage::text( (string) $card['body'] ) . '</p></article>';
  }
  return $html . '</div></section>';
 }

 private static function section( array $section, array &$slotted, string $button, string $type, bool $aida_service = false ): string {
  $slot       = sanitize_key( (string) ( $section['image_slot'] ?? '' ) );
  $section_id = sanitize_key( (string) ( $section['section_id'] ?? '' ) );
  $html = '<section class="lpg-lpt__section lpg-lpt__section--' . esc_attr( sanitize_key( (string) ( $section['layout'] ?? 'story' ) ) ) . '"'
   . ' data-step="' . absint( $section['step'] ?? 0 ) . '"'
   . ( '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '" data-section-id="' . esc_attr( $section_id ) . '"' : '' )
   . ( '' !== $slot ? ' data-image-slot="' . esc_attr( $slot ) . '"' : '' ) . '>';
  $step    = absint( $section['step'] ?? 0 );
  $content = '<div class="lpg-lpt__copy">'
   . ( $aida_service ? '<span class="lpg-lpt__section-eyebrow">' . esc_html( str_pad( (string) $step, 2, '0', STR_PAD_LEFT ) ) . '</span>' : '' )
   . '<h2>' . LandingPage::text( $section['heading'] ?? '' ) . '</h2>' . LandingPage::paragraphs( $section['body'] ?? '' ) . '</div>';
  $cards = is_array( $section['cards'] ?? null ) ? $section['cards'] : array();
  if ( $cards ) {
   $tag = 'process' === (string) ( $section['layout'] ?? '' ) ? 'ol' : 'ul';
   $content .= '<' . $tag . ' class="lpg-lpt__cards">';
   foreach ( array_slice( $cards, 0, 12 ) as $card ) {
    if ( is_array( $card ) ) {
     $content .= '<li><h3>' . LandingPage::text( $card['title'] ?? '' ) . '</h3>' . LandingPage::paragraphs( $card['body'] ?? '' ) . '</li>';
    }
   }
   $content .= '</' . $tag . '>';
  }
  $bullets = is_array( $section['bullets'] ?? null ) ? $section['bullets'] : array();
  if ( $bullets ) {
   $content .= '<ul class="lpg-lp__points">';
   foreach ( array_slice( $bullets, 0, 16 ) as $bullet ) { $content .= '<li>' . LandingPage::text( $bullet ) . '</li>'; }
   $content .= '</ul>';
  }
  $table = is_array( $section['table'] ?? null ) ? $section['table'] : array();
  if ( ! empty( $table['columns'] ) && is_array( $table['rows'] ?? null ) ) {
   $content .= '<div class="lpg-lpt__table-wrap"><table><thead><tr>';
   foreach ( array_slice( $table['columns'], 0, 8 ) as $column ) { $content .= '<th scope="col">' . LandingPage::text( $column ) . '</th>'; }
   $content .= '</tr></thead><tbody>';
   foreach ( array_slice( $table['rows'], 0, 20 ) as $row ) {
    if ( ! is_array( $row ) ) { continue; }
    $content .= '<tr>';
    foreach ( array_slice( $row, 0, 8 ) as $cell ) { $content .= '<td>' . LandingPage::text( $cell ) . '</td>'; }
    $content .= '</tr>';
   }
   $content .= '</tbody></table></div>';
  }
  if ( 'offer' === (string) ( $section['layout'] ?? '' ) && '' !== $button ) {
   $content .= '<div class="lpg-lpt__section-cta">' . $button . '</div>';
  }
  $media = '';
  if ( '' !== $slot && isset( $slotted[ $slot ] ) ) {
   $media = LandingPage::images( array( $slotted[ $slot ] ) );
   unset( $slotted[ $slot ] );
  }
  if ( $aida_service ) {
   $html .= '<div class="lpg-lpt__section-grid"><div class="lpg-lpt__section-main">' . $content . '</div>'
    . '<div class="lpg-lpt__section-media">' . ( '' !== $media ? $media : '<span class="lpg-lpt__section-media-dot" aria-hidden="true"></span>' ) . '</div></div>';
  } else {
   $html .= $content . $media;
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
