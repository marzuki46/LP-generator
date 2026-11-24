<?php
/** Extensible landing-page type strategies. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

interface LpTypeStrategy {
 public function key(): string;
 public function label( string $language = 'id' ): string;
 public function brief( array $context ): array;
 public function validate_brief( array $brief ): array;
 public function seo_rules(): array;
 public function schema_types(): array;
 public function section_schema(): array;
 public function link_strategy(): array;
 public function cta_behavior(): array;
 public function quality_checks(): array;
 public function publishing_requirements(): array;
 public function analytics_config(): array;
}

abstract class AbstractLpTypeStrategy implements LpTypeStrategy {
 protected string $key;
 protected array $labels;
 protected array $definition;

 public function __construct( string $key, array $labels, array $definition ) {
  $this->key = $key;
  $this->labels = $labels;
  $this->definition = $definition;
 }

 public function key(): string { return $this->key; }

 public function label( string $language = 'id' ): string {
  return (string) ( $this->labels[ LanguageService::clean( $language ) ] ?? $this->labels['id'] );
 }

 /** Map legacy campaign fields into the type brief. */
 protected function resolve( string $field, array $context ) {
  switch ( $field ) {
   case 'contact_method':
    return $context['contact_method'] ?? ( $context['phone'] ?? ( $context['contact_email'] ?? ( $context['offer_url'] ?? '' ) ) );
   case 'product_type':
    return $context['product_type'] ?? ( $context['offer_type'] ?? '' );
   case 'description':
    return $context['description'] ?? ( $context['angle'] ?? ( $context['promise'] ?? '' ) );
   case 'target_customer':
    return $context['target_customer'] ?? ( $context['audience'] ?? '' );
   case 'amazon_marketplace':
    return $context['amazon_marketplace'] ?? ( $context['amazon_region'] ?? '' );
   case 'affiliate_tag':
    return $context['affiliate_tag'] ?? ( $context['amazon_tag'] ?? '' );
   case 'source_reference':
    if ( ! empty( $context['source_reference'] ) ) { return $context['source_reference']; }
    $sources = json_decode( (string) ( $context['research_sources'] ?? '' ), true );
    if ( is_array( $sources ) && $sources ) {
     $first = reset( $sources );
     return (string) ( $first['url'] ?? '' );
    }
    return '';
   case 'original_analysis':
    return $context['original_analysis'] ?? ( $context['angle'] ?? ( $context['promise'] ?? '' ) );
   default:
    return $context[ $field ] ?? '';
  }
 }

 public function brief( array $context ): array {
  $brief = array();
  foreach ( (array) ( $this->definition['required_fields'] ?? array() ) as $field ) {
   $brief[ $field ] = $this->resolve( $field, $context );
  }
  $brief['lp_type'] = $this->key;
  $brief['language'] = LanguageService::clean( $context['language'] ?? 'id' );
  $brief['sections'] = array_values( (array) ( $this->definition['sections'] ?? array() ) );
  return $brief;
 }

 public function validate_brief( array $brief ): array {
  $errors = array();
  foreach ( (array) ( $this->definition['required_fields'] ?? array() ) as $field ) {
   if ( '' === trim( (string) ( $brief[ $field ] ?? '' ) ) ) {
    $errors[] = $field . ': required';
   }
  }
  if ( empty( $brief['sections'] ) || ! is_array( $brief['sections'] ) ) {
   $errors[] = 'sections: required';
  }
  return $errors;
 }

 public function seo_rules(): array { return (array) ( $this->definition['seo_rules'] ?? array() ); }
 public function schema_types(): array { return (array) ( $this->definition['schema_types'] ?? array() ); }
 public function section_schema(): array { return (array) ( $this->definition['sections'] ?? array() ); }
 public function link_strategy(): array { return (array) ( $this->definition['link_strategy'] ?? array() ); }
 public function cta_behavior(): array { return (array) ( $this->definition['cta_behavior'] ?? array() ); }
 public function quality_checks(): array { return (array) ( $this->definition['quality_checks'] ?? array() ); }
 public function publishing_requirements(): array { return (array) ( $this->definition['publishing_requirements'] ?? array() ); }
 public function analytics_config(): array { return (array) ( $this->definition['analytics'] ?? array() ); }
}

final class ServiceLpType extends AbstractLpTypeStrategy {
 public function __construct() {
  parent::__construct(
   'service',
   array( 'id' => 'Landing Page Jasa', 'en' => 'Service Landing Page' ),
   array(
    'required_fields' => array( 'offer_name', 'audience', 'city', 'promise', 'contact_method' ),
    'sections' => array( 'hero', 'overview', 'problems', 'deliverables', 'benefits', 'portfolio', 'process', 'pricing', 'service_area', 'faq', 'contact' ),
    'seo_rules' => array( 'self_canonical' => true, 'one_h1' => true, 'local_intent' => true ),
    'schema_types' => array( 'Service', 'BreadcrumbList' ),
    'link_strategy' => array( 'parent_service_hub', 'related_service', 'service_area_hub', 'portfolio', 'supporting_guide', 'contact' ),
    'cta_behavior' => array( 'primary' => 'consultation', 'secondary' => 'phone_or_whatsapp' ),
    'quality_checks' => array( 'local_value_gate', 'doorway_prevention', 'verified_business_data' ),
    'publishing_requirements' => array( 'approved', 'noindex_off_only_if_production' ),
    'analytics' => array( 'event_prefix' => 'service_lp' ),
   )
  );
 }
}

final class ProductLpType extends AbstractLpTypeStrategy {
 public function __construct() {
  parent::__construct(
   'product',
   array( 'id' => 'Landing Page Produk', 'en' => 'Product Landing Page' ),
   array(
    'required_fields' => array( 'offer_name', 'product_type', 'description', 'target_customer', 'problem' ),
    'sections' => array( 'hero', 'what_it_does', 'who_for', 'problems', 'features', 'demo', 'specifications', 'pricing', 'setup_support', 'comparison', 'faq', 'purchase' ),
    'seo_rules' => array( 'self_canonical' => true, 'one_h1' => true, 'merchant_eligibility' => true ),
    'schema_types' => array( 'Product', 'SoftwareApplication' ),
    'link_strategy' => array( 'product_category', 'documentation', 'tutorial', 'complementary_product', 'comparison', 'support' ),
    'cta_behavior' => array( 'primary' => 'checkout_or_contact', 'secondary' => 'documentation' ),
    'quality_checks' => array( 'no_fake_reviews', 'no_fake_scarcity', 'availability_supported' ),
    'publishing_requirements' => array( 'approved', 'verified_product_data' ),
    'analytics' => array( 'event_prefix' => 'product_lp' ),
   )
  );
 }
}

final class AmazonAffiliateLpType extends AbstractLpTypeStrategy {
 public function __construct() {
  parent::__construct(
   'amazon_affiliate',
   array( 'id' => 'Landing Page Afiliasi Amazon', 'en' => 'Amazon Affiliate Landing Page' ),
   array(
    'required_fields' => array( 'offer_name', 'amazon_marketplace', 'asin', 'affiliate_tag', 'source_reference', 'original_analysis' ),
    'sections' => array( 'hero', 'quick_recommendations', 'selection_method', 'comparison_table', 'individual_analysis', 'pros_cons', 'specifications', 'which_fits', 'alternatives', 'buying_guide', 'faq', 'final_recommendation' ),
    'seo_rules' => array( 'self_canonical' => true, 'one_h1' => true, 'affiliate_disclosure' => true ),
    'schema_types' => array( 'Article', 'Review' ),
    'link_strategy' => array( 'buying_guide_hub', 'original_analysis', 'comparison_guide', 'use_case_guide', 'alternatives' ),
    'cta_behavior' => array( 'primary' => 'check_availability_on_amazon', 'disclosure' => 'As an Amazon Associate I earn from qualifying purchases.' ),
    'quality_checks' => array( 'affiliate_value_gate', 'no_fake_ratings', 'no_scraped_reviews', 'authorized_content_only' ),
    'publishing_requirements' => array( 'approved', 'marketplace_checklist', 'verified_asin_and_tag' ),
    'analytics' => array( 'event_prefix' => 'affiliate_lp', 'track_outbound' => true ),
   )
  );
 }
}

final class LpTypeRegistry {
 public static function all(): array {
  return array(
   'service' => new ServiceLpType(),
   'product' => new ProductLpType(),
   'amazon_affiliate' => new AmazonAffiliateLpType(),
  );
 }

 public static function clean( $value ): string {
  $type = sanitize_key( (string) $value );
  return isset( self::all()[ $type ] ) ? $type : 'service';
 }

 public static function from_offer_type( string $offer_type ): string {
  return match ( sanitize_key( $offer_type ) ) {
   'affiliate' => 'amazon_affiliate',
   'product' => 'product',
   default => 'service',
  };
 }

 public static function get( string $type ): LpTypeStrategy {
  $all = self::all();
  return $all[ self::clean( $type ) ];
 }
}
