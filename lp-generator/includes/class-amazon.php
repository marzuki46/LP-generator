<?php
/**
 * Amazon affiliate product discovery, following the pattern used by StupidPie v4:
 * Bing Images with a site:amazon.{region} filter, Photon-proxied images, and an
 * Amazon search deeplink carrying the associate tag. No PA-API credentials needed.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Amazon
 */
final class Amazon {

	/**
	 * Supported Amazon storefront regions.
	 */
	public const REGIONS = array(
		'com',
		'co.uk',
		'de',
		'fr',
		'it',
		'es',
		'ca',
		'com.au',
		'co.jp',
		'in',
		'br',
		'mx',
		'nl',
		'se',
		'pl',
		'sg',
		'ae',
		'com.tr',
	);

	/**
	 * Default affiliate deeplink template.
	 */
	public const DEFAULT_DEEPLINK = 'https://www.amazon.{region}/s/ref=nb_sb_noss_2?url=search-alias%3Daps&field-keywords={keyword}&tag={tag}';

	/**
	 * Normalize a region against an allowlist.
	 *
	 * @param string $region Raw region.
	 * @return string
	 */
	public static function normalize_region( string $region ): string {
		$region = strtolower( trim( $region ) );
		$region = preg_replace( '/[^a-z.]/', '', $region ) ?? '';
		return in_array( $region, self::REGIONS, true ) ? $region : 'com';
	}

	/**
	 * Build the Bing Images query used by the reference plugin.
	 *
	 * @param string $keyword Offer keyword.
	 * @param string $region  Amazon region.
	 * @return string
	 */
	public static function build_query( string $keyword, string $region ): string {
		$keyword = trim( preg_replace( '/\s+/u', ' ', $keyword ) ?? '' );
		if ( '' === $keyword ) {
			return '';
		}
		return $keyword . ' site:amazon.' . self::normalize_region( $region );
	}

	/**
	 * Build the Bing Images async endpoint URL.
	 *
	 * @param string $query Search query.
	 * @return string
	 */
	public static function build_search_url( string $query ): string {
		return 'https://www.bing.com/images/async?q=' . rawurlencode( $query ) . '&qft=' . rawurlencode( '+filterui:imagesize-medium' );
	}

	/**
	 * Parse Bing `a.iusc m="{json}"` nodes into product records.
	 *
	 * @param string $html Bing HTML.
	 * @return array<int,array<string,string>>
	 */
	public static function parse_images( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}
		preg_match_all( '/<a\b[^>]*\bclass\s*=\s*["\'][^"\']*\biusc\b[^"\']*["\'][^>]*>/i', $html, $tags );
		$out = array();
		foreach ( $tags[0] ?? array() as $tag ) {
			if ( 1 !== preg_match( '/\bm\s*=\s*["\']([^"\']+)["\']/i', $tag, $match ) ) {
				continue;
			}
			$json = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$url = (string) ( $data['murl'] ?? '' );
			if ( ! self::is_http_url( $url ) ) {
				continue;
			}
			$page  = (string) ( $data['purl'] ?? '' );
			$title = str_replace( array( "\u{e000}", "\u{e001}", ' ...' ), '', (string) ( $data['t'] ?? '' ) );
			$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? '' );
			if ( '' === $title ) {
				$title = ucwords( trim( preg_replace( '/[-_]+/', ' ', rawurldecode( basename( (string) parse_url( $url, PHP_URL_PATH ) ) ) ) ?? '' ) );
			}
			if ( '' === $title ) {
				continue;
			}
			$link    = self::is_http_url( $page ) ? $page : $url;
			$thumb   = (string) ( $data['turl'] ?? '' );
			$out[]   = array(
				'title'     => $title,
				'url'       => $link,
				'image'     => $url,
				'thumbnail' => self::is_http_url( $thumb ) ? $thumb : '',
				'domain'    => (string) parse_url( $link, PHP_URL_HOST ),
			);
		}
		return $out;
	}

	/**
	 * Proxy an image through Jetpack Photon, like the reference plugin does.
	 *
	 * @param string $url     Image URL.
	 * @param bool   $enabled Whether to proxy.
	 * @return string
	 */
	public static function proxy_image( string $url, bool $enabled ): string {
		if ( ! $enabled || ! self::is_http_url( $url ) ) {
			return $url;
		}
		$plain = preg_replace( '#^https?://#i', '', $url ) ?? $url;
		$plain = preg_replace( '#^i0\.wp\.com/#i', '', $plain ) ?? $plain;
		return 'https://i0.wp.com/' . $plain;
	}

	/**
	 * Build a tracked Amazon search link.
	 *
	 * @param string $keyword  Product title or search keyword.
	 * @param string $region   Storefront region.
	 * @param string $tag      Associate tag.
	 * @param string $template Optional custom template.
	 * @return string
	 */
	public static function affiliate_url( string $keyword, string $region, string $tag, string $template = '' ): string {
		$region   = self::normalize_region( $region );
		$template = '' !== $template ? $template : self::DEFAULT_DEEPLINK;
		$tag      = preg_replace( '/[^A-Za-z0-9\-]/', '', $tag ) ?? '';
		$url      = str_replace(
			array( '{keyword}', '{region}', '{tag}' ),
			array( rawurlencode( trim( $keyword ) ), $region, rawurlencode( $tag ) ),
			$template
		);
		return self::is_http_url( $url ) ? $url : 'https://www.amazon.' . $region . '/';
	}

	/**
	 * Fetch products for a keyword, with transient caching and generic fallback.
	 *
	 * @param string $keyword  Offer keyword.
	 * @param array  $settings Plugin settings.
	 * @return array<int,array<string,string>>
	 */
	public static function fetch( string $keyword, array $settings ): array {
		$region = self::normalize_region( (string) ( $settings['amazon_region'] ?? 'com' ) );
		$max    = max( 1, min( 8, (int) ( $settings['amazon_products'] ?? 3 ) ) );
		$query  = self::build_query( $keyword, $region );
		if ( '' === $query ) {
			return array();
		}

		$ttl       = max( 0, min( 72, (int) ( $settings['amazon_cache_hours'] ?? 24 ) ) );
		$cache_key = 'lpgen_amz_' . md5( $query . '|' . $max . '|' . ( empty( $settings['amazon_merge_generic'] ) ? '0' : '1' ) );
		if ( $ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return array_slice( $cached, 0, $max );
			}
		}

		$products = self::scrape( $keyword, $settings, $region, true );
		if ( ! empty( $settings['amazon_merge_generic'] ) && count( $products ) < $max ) {
			$products = array_merge( $products, self::scrape( $keyword, $settings, $region, false ) );
			$seen     = array();
			$unique   = array();
			foreach ( $products as $product ) {
				$key = md5( (string) ( $product['title'] ?? '' ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = 1;
				$unique[]     = $product;
			}
			$products = $unique;
		}

		$tag   = (string) ( $settings['amazon_tag'] ?? '' );
		$proxy = ! empty( $settings['amazon_image_proxy'] );
		$out   = array();
		foreach ( array_slice( $products, 0, $max ) as $product ) {
			$title = (string) ( $product['title'] ?? '' );
			$out[] = array(
				'title'         => $title,
				'url'           => (string) ( $product['url'] ?? '' ),
				'image'         => self::proxy_image( (string) ( $product['image'] ?? '' ), $proxy ),
				'thumbnail'     => self::proxy_image( (string) ( $product['thumbnail'] ?? '' ), $proxy ),
				'domain'        => (string) ( $product['domain'] ?? '' ),
				'affiliate_url' => self::affiliate_url( $title, $region, $tag, (string) ( $settings['amazon_deeplink_template'] ?? '' ) ),
			);
		}

		if ( $ttl > 0 && ! empty( $out ) ) {
			set_transient( $cache_key, $out, $ttl * HOUR_IN_SECONDS );
		}
		return $out;
	}

	/**
	 * Prepare and persist affiliate products for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int Product count.
	 */
	public static function prepare_campaign( int $campaign_id ): int {
		$campaign = ContentRepository::get_campaign( $campaign_id );
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		if ( empty( $campaign ) || 'affiliate' !== (string) $campaign['offer_type'] ) {
			return 0;
		}
		if ( empty( $settings['amazon_enabled'] ) && empty( $campaign['amazon_enabled'] ) ) {
			return 0;
		}
		$keyword = trim( (string) ( $campaign['amazon_keyword'] ?? '' ) );
		if ( '' === $keyword ) {
			$keyword = trim( (string) ( $campaign['offer_name'] ?? '' ) );
		}
		if ( '' === $keyword ) {
			$keyword = get_the_title( $campaign_id );
		}
		$products = self::fetch( $keyword, $settings );
		ContentRepository::save_amazon_products( $campaign_id, $products );
		Database::log( 'info', sprintf( 'Amazon products prepared: %d', count( $products ) ), $campaign_id );
		return count( $products );
	}

	/**
	 * Render product cards as a Gutenberg HTML block.
	 *
	 * @param array $products Products.
	 * @param array $campaign Campaign context.
	 * @return string
	 */
	public static function products_block( array $products, array $campaign ): string {
		$products = array_slice( $products, 0, 8 );
		if ( empty( $products ) ) {
			return '';
		}
		$heading = 'en' === (string) ( $campaign['language'] ?? 'id' )
			? __( 'Recommended products', 'lp-generator' )
			: __( 'Rekomendasi Produk', 'lp-generator' );
		$label = 'en' === (string) ( $campaign['language'] ?? 'id' )
			? __( 'Check price', 'lp-generator' )
			: __( 'Cek harga', 'lp-generator' );

		$html = '<h3>' . esc_html( $heading ) . '</h3><div class="lpgen-products">';
		foreach ( $products as $product ) {
			$title = (string) ( $product['title'] ?? '' );
			$image = (string) ( $product['image'] ?? '' );
			$link  = (string) ( $product['affiliate_url'] ?? '' );
			$html .= '<div class="lpgen-product">';
			if ( self::is_http_url( $image ) ) {
				$html .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" />';
			}
			$html .= '<h4>' . esc_html( $title ) . '</h4>';
			if ( self::is_http_url( $link ) ) {
				$html .= '<a href="' . esc_url( $link ) . '" rel="sponsored nofollow noopener" target="_blank">'
					. esc_html( $label ) . ' →</a>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';
		return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
	}

	/**
	 * Perform one Bing Images request.
	 *
	 * @param string $keyword    Offer keyword.
	 * @param array  $settings   Plugin settings.
	 * @param string $region     Amazon region.
	 * @param bool   $site_filter Add site:amazon.{region} filter.
	 * @return array<int,array<string,string>>
	 */
	private static function scrape( string $keyword, array $settings, string $region, bool $site_filter ): array {
		$search = $site_filter ? self::build_query( $keyword, $region ) : $keyword;
		$url    = self::build_search_url( $search );
		if ( false === wp_http_validate_url( $url ) ) {
			return array();
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => max( 5, min( 30, (int) ( $settings['amazon_timeout'] ?? 15 ) ) ),
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; LPG/1.1; +' . home_url( '/' ) . ')',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body || strlen( $body ) > 2000000 ) {
			return array();
		}
		$products = self::parse_images( $body );
		if ( ! $site_filter ) {
			return $products;
		}
		$amazon = array_values(
			array_filter(
				$products,
				static function ( array $product ) use ( $region ): bool {
					return false !== stripos( (string) ( $product['domain'] ?? '' ), 'amazon.' . $region )
						|| false !== stripos( (string) ( $product['domain'] ?? '' ), 'amazon' );
				}
			)
		);
		return ! empty( $amazon ) ? $amazon : $products;
	}

	/**
	 * Basic http(s) URL validation that also works in unit tests.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_http_url( string $url ): bool {
		return 1 === preg_match( '#^https?://#i', $url ) && false !== filter_var( $url, FILTER_VALIDATE_URL );
	}
}
