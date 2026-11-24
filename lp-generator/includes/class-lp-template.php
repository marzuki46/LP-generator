<?php
/**
 * Visual LP models. A model is a layout recipe, not a copywriting framework.
 * JSON copy enters one renderer; the selected model decides the visual shell.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class LpTemplate {

	public const MODELS = array(
		'editorial' => 'Editorial SaaS',
		'split'     => 'Split Proof',
		'pricing'   => 'Pricing Focus',
		'minimal'   => 'Minimal Direct',
	);

	public static function clean( $value ): string {
		$model = sanitize_key( (string) $value );
		return isset( self::MODELS[ $model ] ) ? $model : 'editorial';
	}

	public static function model( array $campaign ): string {
		return self::clean( $campaign['lp_model'] ?? 'editorial' );
	}

	/** Root classes shared by generic and AIDA renderers. */
	public static function root_class( array $campaign ): string {
		$type = LpTypeRegistry::clean( $campaign['lp_type'] ?? '' );
		return 'lpg-lpm lpg-lpm--' . self::model( $campaign ) . ' lpg-lpt lpg-lpt--' . $type;
	}

	public static function type( array $campaign ): string {
		return LpTypeRegistry::clean( $campaign['lp_type'] ?? '' );
	}

	public static function label( array $campaign ): string {
		return (string) ( self::MODELS[ self::model( $campaign ) ] ?? 'Editorial SaaS' );
	}

	/** Decorative shell before the existing semantic hero/sections. */
	public static function open( array $campaign, array $data ): string {
		$strings = LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) );
		$model   = self::model( $campaign );
		$brand   = trim( (string) ( $campaign['brand'] ?? '' ) );
		$city    = trim( (string) ( $campaign['city'] ?? '' ) );
		$eyebrow = '' !== $brand && '' !== $city ? $brand . ' · ' . $city : ( '' !== $brand ? $brand : $city );
		$html    = '<nav class="lpg-lpm__nav" aria-label="' . esc_attr( $strings['nav_page'] ) . '">';
		$html   .= '<span class="lpg-lpm__brand">' . LandingPage::text( $eyebrow ) . '</span>';
		$html   .= '</nav>';

		$price = trim( (string) ( $campaign['price'] ?? '' ) );
		if ( '' === $price ) {
			$price = (string) ( $campaign['language'] ?? 'id' ) === 'en' ? 'Tailored pricing' : 'Harga disesuaikan kebutuhan';
		}
		$order_label = 'en' === (string) ( $campaign['language'] ?? 'id' ) ? 'Order Now' : 'Pesan Sekarang';
		$order_url   = (string) ( $campaign['offer_url'] ?? '' );
		$affiliate   = 'affiliate' === (string) ( $campaign['offer_type'] ?? '' );
		$html .= '<section class="lpm-offerbar" aria-label="' . esc_attr( $strings['price_order'] ) . '">';
		$html .= '<div class="lpm-offerbar__price"><span>' . esc_html( $strings['starting_from'] ) . '</span><strong>' . LandingPage::text( $price ) . '</strong></div>';
		// Affiliate LP keeps its disclosure before the first CTA; the hero button owns that CTA.
		$html .= '<div class="lpm-offerbar__order">' . ( $affiliate ? '' : LandingPage::button( $order_url, $order_label, false ) ) . '</div>';
		$html .= '</section>';

		if ( 'split' === $model ) {
			$html .= '<aside class="lpg-lpm__rail" aria-label="' . esc_attr( $strings['offer_summary'] ) . '">';
			$html .= '<p class="lpg-lpm__rail-label">' . LandingPage::text( $campaign['offer_name'] ?? '' ) . '</p>';
			if ( ! empty( $campaign['price'] ) ) {
				$html .= '<p class="lpg-lpm__rail-price">' . LandingPage::text( $campaign['price'] ) . '</p>';
			}
			$html .= '</aside>';
		}
		if ( 'pricing' === $model ) {
			$html .= '<div class="lpg-lpm__pricing-head"><p>' . esc_html( $strings['packages_investment'] ) . '</p></div>';
		}
		return $html;
	}

	/** Trust strip, sticky CTA and related-links slot after the JSON sections. */
	public static function close( array $campaign, array $data, string $button ): string {
		$strings = LanguageService::strings( (string) ( $campaign['language'] ?? 'id' ) );
		$html = '<section class="lpg-lpm__trust" aria-label="' . esc_attr( $strings['offer_summary'] ) . '">';
		$items = array();
		foreach ( array( 'price', 'guarantee', 'cta' ) as $key ) {
			if ( ! empty( $campaign[ $key ] ) ) {
				$items[] = (string) $campaign[ $key ];
			}
		}
		foreach ( array_slice( (array) ( $campaign['benefits'] ?? array() ), 0, 3 ) as $benefit ) {
			$items[] = (string) $benefit;
		}
		foreach ( array_slice( $items, 0, 4 ) as $item ) {
			$html .= '<div class="lpg-lpm__trust-item">' . LandingPage::text( $item ) . '</div>';
		}
		$html .= '</section>';
		if ( '' !== $button ) {
			$html .= '<div class="lpg-lpm__sticky-cta">' . $button . '</div>';
		}
		$html .= '<!-- lpg-related:start --><!-- lpg-related:end -->';
		$city     = trim( (string) ( $campaign['city'] ?? '' ) );
		$areas    = $campaign['target_areas'] ?? array();
		$areas    = is_array( $areas ) ? $areas : Utils::lines( (string) $areas );
		$areas    = array_values( array_filter( array_map( 'trim', $areas ) ) );
		$location = $city;
		foreach ( array_slice( $areas, 0, 3 ) as $area ) {
			if ( '' === $location || false === mb_stripos( $location, (string) $area ) ) {
				$location = '' === $location ? (string) $area : $location . ', ' . (string) $area;
			}
		}
		$html .= '<footer class="lpg-lpm__footer"><span>' . LandingPage::text( $campaign['offer_name'] ?? '' ) . '</span>'
			. '<span>' . LandingPage::text( $campaign['brand'] ?? '' ) . '</span>'
			. ( '' !== $location ? '<span>' . esc_html( $strings['service_area'] ) . ': ' . LandingPage::text( $location ) . '</span>' : '' )
			. '</footer>';
		return $html;
	}
}
