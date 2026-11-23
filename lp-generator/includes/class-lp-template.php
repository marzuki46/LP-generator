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
		return 'lpg-lpm lpg-lpm--' . self::model( $campaign );
	}

	public static function label( array $campaign ): string {
		return (string) ( self::MODELS[ self::model( $campaign ) ] ?? 'Editorial SaaS' );
	}

	/** Decorative shell before the existing semantic hero/sections. */
	public static function open( array $campaign, array $data ): string {
		$model   = self::model( $campaign );
		$brand   = trim( (string) ( $campaign['brand'] ?? '' ) );
		$city    = trim( (string) ( $campaign['city'] ?? '' ) );
		$eyebrow = '' !== $brand && '' !== $city ? $brand . ' · ' . $city : ( '' !== $brand ? $brand : $city );
		$html    = '<nav class="lpg-lpm__nav" aria-label="' . esc_attr__( 'Navigasi halaman', 'lp-generator' ) . '">';
		$html   .= '<span class="lpg-lpm__brand">' . LandingPage::text( $eyebrow ) . '</span>';
		$html   .= '<span class="lpg-lpm__model">' . LandingPage::text( self::label( $campaign ) ) . '</span>';
		$html   .= '</nav>';

		if ( 'split' === $model ) {
			$html .= '<aside class="lpg-lpm__rail" aria-label="' . esc_attr__( 'Ringkasan penawaran', 'lp-generator' ) . '">';
			$html .= '<p class="lpg-lpm__rail-label">' . LandingPage::text( $campaign['offer_name'] ?? '' ) . '</p>';
			if ( ! empty( $campaign['price'] ) ) {
				$html .= '<p class="lpg-lpm__rail-price">' . LandingPage::text( $campaign['price'] ) . '</p>';
			}
			$html .= '</aside>';
		}
		if ( 'pricing' === $model ) {
			$html .= '<div class="lpg-lpm__pricing-head"><p>' . esc_html__( 'PAKET & INVESTASI', 'lp-generator' ) . '</p></div>';
		}
		return $html;
	}

	/** Trust strip, sticky CTA and related-links slot after the JSON sections. */
	public static function close( array $campaign, array $data, string $button ): string {
		$html = '<section class="lpg-lpm__trust" aria-label="' . esc_attr__( 'Ringkasan penawaran', 'lp-generator' ) . '">';
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
		$html .= '<footer class="lpg-lpm__footer"><span>' . LandingPage::text( $campaign['offer_name'] ?? '' ) . '</span>'
			. '<span>' . LandingPage::text( $campaign['brand'] ?? '' ) . '</span></footer>';
		return $html;
	}
}
