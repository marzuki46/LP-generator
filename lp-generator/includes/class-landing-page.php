<?php
/**
 * Safe semantic HTML landing pages for WordPress Custom HTML blocks.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class LandingPage {

	public static function sanitize_css( $raw ): string {
		if ( ! is_string( $raw ) ) { return ''; }
		return mb_substr( trim( wp_strip_all_tags( $raw ) ), 0, 50000 );
	}

	/** In canvas mode, remove the active theme's stylesheet, including parent/child CSS. */
	public static function prepare_canvas_assets(): void {
		if ( ! self::is_canvas() ) { return; }
		$styles = wp_styles();
		$theme_urls = array( trailingslashit( get_template_directory_uri() ), trailingslashit( get_stylesheet_directory_uri() ) );
		foreach ( (array) $styles->queue as $handle ) {
			$src = (string) ( $styles->registered[ $handle ]->src ?? '' );
			$src = preg_replace( '#^https?:#', '', $src );
			foreach ( $theme_urls as $theme_url ) {
				if ( '' !== $src && str_starts_with( $src, (string) preg_replace( '#^https?:#', '', $theme_url ) ) ) { wp_dequeue_style( $handle ); break; }
			}
		}
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}

	private static function is_canvas(): bool {
		return is_singular( array( 'page', 'post' ) ) && ! post_password_required() && 1 === (int) get_post_meta( get_queried_object_id(), '_lpgen_canvas', true );
	}

	public static function preview_document( string $content ): string {
		$css = (string) file_get_contents( LPG_PATH . 'public/css/landing-page.css' ) . "\n"
			. (string) file_get_contents( LPG_PATH . 'public/css/aida.css' ) . "\n"
			. (string) file_get_contents( LPG_PATH . 'public/css/lp-models.css' ) . "\n"
			. (string) file_get_contents( LPG_PATH . 'public/css/article-themes.css' ) . "\n"
			. (string) file_get_contents( LPG_PATH . 'public/css/lp-type-templates.css' );
		$settings = get_option( 'lpgen_settings', array() );
		$css .= "\n" . self::sanitize_css( is_array( $settings ) ? ( $settings['lp_custom_css'] ?? '' ) : '' );
		return '<!doctype html><html><head><base href="about:srcdoc"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><style>' . $css . '</style></head><body>' . wp_kses_post( $content ) . '</body></html>';
	}

	/** Passive tabindex enables keyboard scrolling; no active HTML is added to KSES. */
	public static function allow_table_focus( array $tags, string $context ): array {
		if ( 'post' === $context && isset( $tags['div'] ) ) { $tags['div']['tabindex'] = true;
			if ( isset( $tags['img'] ) ) { $tags['img']['fetchpriority'] = true; } }
		return $tags;
	}

	/** Optional canvas avoids duplicate theme titles and narrow article containers. */
	public static function template( string $template ): string {
		if ( self::is_canvas() ) {
			return LPG_PATH . 'templates/landing-page.php';
		}
		return $template;
	}

	/** Load the same scoped presentation on the frontend and in the block editor. */
	public static function enqueue_assets(): void {
		$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		if ( ! is_admin() && ( ! is_singular( array( 'page', 'post' ) ) || ( false === strpos( $content, 'lpg-lp' ) && false === strpos( $content, 'lpg-article' ) ) ) ) { return; }
		wp_enqueue_style( 'lpgen-landing-page', LPG_URL . 'public/css/landing-page.css', array(), LPG_VERSION );
		wp_enqueue_style( 'lpgen-aida', LPG_URL . 'public/css/aida.css', array( 'lpgen-landing-page' ), LPG_VERSION );
		wp_enqueue_style( 'lpgen-lp-models', LPG_URL . 'public/css/lp-models.css', array( 'lpgen-aida' ), LPG_VERSION );
		wp_enqueue_style( 'lpgen-article-themes', LPG_URL . 'public/css/article-themes.css', array( 'lpgen-lp-models' ), LPG_VERSION );
		wp_enqueue_style( 'lpgen-lp-type-templates', LPG_URL . 'public/css/lp-type-templates.css', array( 'lpgen-article-themes' ), LPG_VERSION );
		$settings = get_option( 'lpgen_settings', array() );
		$custom = self::sanitize_css( is_array( $settings ) ? ( $settings['lp_custom_css'] ?? '' ) : '' );
		if ( '' !== $custom ) { wp_add_inline_style( 'lpgen-aida', $custom ); }
	}

	public static function enqueue_admin_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( false !== strpos( $hook, 'lpg-' ) || ( $screen && in_array( $screen->post_type, array( 'lpgen_item', 'lpgen_campaign' ), true ) ) ) {
			self::enqueue_assets();
		}
	}

	/** Reject reordered, missing or mislabeled steps instead of publishing a generic LP. */
	public static function validate( array $data, array $campaign ): void {
		$plan = Frameworks::campaign_plan( $campaign );
		if ( empty( $plan['steps'] ) ) {
			throw new \RuntimeException( 'Rencana framework LP belum siap atau sudah berubah.' );
		}
		if ( empty( $campaign['offer_url'] ) || false === wp_http_validate_url( (string) $campaign['offer_url'] ) || ! is_string( $data['cta_text'] ?? null ) || '' === trim( $data['cta_text'] ) ) { throw new \RuntimeException( 'LP membutuhkan URL CTA campaign yang valid dan teks tombol.' ); }
		if ( DesignContract::active( $campaign ) ) { DesignContract::validate( $data, $campaign ); }
		$sections = $data['sections'] ?? null;
		if ( ! is_array( $sections ) || count( $sections ) !== count( $plan['steps'] ) ) {
			throw new \RuntimeException( 'Section LP harus lengkap sesuai seluruh langkah framework campaign.' );
		}
		foreach ( array_values( $sections ) as $index => $section ) {
			$step = $plan['steps'][ $index ];
			if ( ! is_array( $section ) || ( $section['step'] ?? null ) !== $index + 1 || ( $section['hint'] ?? '' ) !== $step['section_hint'] || ( $section['layout'] ?? '' ) !== $step['layout'] ) {
				throw new \RuntimeException( 'Urutan, hint atau layout section LP tidak sesuai framework campaign.' );
			}
			foreach ( array( 'heading', 'body' ) as $key ) {
				if ( ! is_string( $section[ $key ] ?? null ) || '' === trim( $section[ $key ] ) ) {
					throw new \RuntimeException( 'Heading dan pembahasan setiap section LP wajib terisi.' );
				}
			}
			$cards = $section['cards'] ?? array();
			if ( ! is_array( $cards ) ) { throw new \RuntimeException( 'Kartu LP harus berupa daftar.' ); }
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) || ! is_string( $card['title'] ?? null ) || ! is_string( $card['body'] ?? null ) ) {
					throw new \RuntimeException( 'Kartu LP memerlukan judul dan isi teks.' );
				}
			}
			$table = $section['table'] ?? array();
			if ( ! is_array( $table ) || ( ! empty( $table ) && ( ! is_array( $table['columns'] ?? null ) || ! is_array( $table['rows'] ?? null ) ) ) ) {
				throw new \RuntimeException( 'Tabel LP memerlukan kolom dan baris yang valid.' );
			}
			if ( ! empty( $table['columns'] ) ) {
				foreach ( $table['rows'] as $row ) {
					if ( ! is_array( $row ) || count( $row ) !== count( $table['columns'] ) ) { throw new \RuntimeException( 'Jumlah sel tabel LP harus sesuai kolomnya.' ); }
				}
			}
		}
		if ( ! is_string( $data['intro'] ?? null ) || '' === trim( $data['intro'] ) || ! is_array( $data['faq'] ?? null ) || count( $data['faq'] ) < 4 ) {
			throw new \RuntimeException( 'LP membutuhkan pengantar hero dan minimal empat FAQ.' );
		}
		foreach ( $data['faq'] as $pair ) {
			if ( ! is_array( $pair ) || ! is_string( $pair['q'] ?? null ) || ! is_string( $pair['a'] ?? null ) || '' === trim( $pair['q'] ) || '' === trim( $pair['a'] ) ) {
				throw new \RuntimeException( 'FAQ LP harus memiliki pertanyaan dan jawaban.' );
			}
		}
		if ( preg_match( '/\{(?:TESTIMONI|TESTIMONIAL|PORTFOLIO|PROOF)[^}]*\}/i', (string) wp_json_encode( $data ) ) ) {
			throw new \RuntimeException( 'Hapus placeholder bukti/testimoni sebelum LP diterbitkan.' );
		}
	}

	/** Only plain text enters markup: no AI-supplied HTML, event handlers or CSS. */
	public static function text( $value ): string {
		return esc_html( is_scalar( $value ) ? (string) $value : '' );
	}

	public static function paragraphs( $value ): string {
		if ( ! is_string( $value ) ) { return ''; }
		$paragraphs = preg_split( '/\R\s*\R/u', trim( $value ) );
		$html = '';
		foreach ( $paragraphs as $paragraph ) {
			if ( '' !== trim( $paragraph ) ) { $html .= '<p>' . self::text( $paragraph ) . '</p>'; }
		}
		return $html;
	}

	public static function button( string $url, string $label, bool $affiliate ): string {
		$url = esc_url( $url );
		if ( '' === $url || '' === trim( $label ) ) { return ''; }
		return '<a class="lpg-lp__button" href="' . $url . '"' . ( $affiliate ? ' rel="sponsored nofollow noopener" target="_blank"' : '' ) . '>' . self::text( $label ) . '<span aria-hidden="true"> &rarr;</span></a>';
	}

	public static function images( array $images, bool $hero = false ): string {
		if ( ! $images ) { return ''; }
		$html = '<div class="lpg-lp__gallery' . ( $hero ? ' lpg-lp__gallery--hero' : '' ) . '">';
		$first_image = true;
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) || empty( $image['url'] ) || '' === esc_url( (string) $image['url'] ) ) { continue; }
			$html .= '<figure><img src="' . esc_url( (string) $image['url'] ) . '" alt="' . esc_attr( (string) ( $image['alt'] ?? '' ) ) . '" class="wp-image-' . absint( $image['id'] ?? 0 ) . '" loading="' . ( $hero && $first_image ? 'eager' : 'lazy' ) . '" decoding="async"' . ( $hero && $first_image ? ' fetchpriority="high"' : '' ) . ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ? ' width="' . absint( $image['width'] ) . '" height="' . absint( $image['height'] ) . '"' : '' ) . ' /></figure>';
			$first_image = false;
		}
		return $html . '</div>';
	}

	public static function section( array $section, array $images, string $button ): string {
		$hint = sanitize_key( (string) ( $section['hint'] ?? '' ) );
		$layout = (string) ( $section['layout'] ?? Frameworks::layout_for_hint( $hint ) );
		$layout = in_array( $layout, Frameworks::LAYOUTS, true ) ? $layout : 'story';
		$section_id = sanitize_key( (string) ( $section['section_id'] ?? '' ) );
		$image_slot = sanitize_key( (string) ( $section['image_slot'] ?? '' ) );
		$html = '<section class="lpg-lp__section lpg-lp__section--' . esc_attr( $layout ) . '" data-step="' . absint( $section['step'] ?? 0 ) . '" data-hint="' . esc_attr( $hint ) . '"'
			. ( '' !== $section_id ? ' data-section-id="' . esc_attr( $section_id ) . '"' : '' )
			. ( '' !== $image_slot ? ' data-image-slot="' . esc_attr( $image_slot ) . '"' : '' ) . '>';
		$html .= '<div class="lpg-lp__section-copy"><h2>' . self::text( $section['heading'] ?? '' ) . '</h2>' . self::paragraphs( $section['body'] ?? '' ) . '</div>';
		$cards = is_array( $section['cards'] ?? null ) ? $section['cards'] : array();
		$bullets = is_array( $section['bullets'] ?? null ) ? $section['bullets'] : array();
		if ( $cards ) {
			$list_tag = 'process' === $layout ? 'ol' : 'ul';
			$html .= '<' . $list_tag . ' class="lpg-lp__cards">';
			foreach ( array_slice( $cards, 0, 12 ) as $card ) {
				if ( ! is_array( $card ) ) { continue; }
				$html .= '<li><h3>' . self::text( $card['title'] ?? '' ) . '</h3>' . self::paragraphs( $card['body'] ?? '' ) . '</li>';
			}
			$html .= '</' . $list_tag . '>';
		}
		if ( $bullets ) {
			$tag = 'process' === $layout && ! $cards ? 'ol' : 'ul';
			$html .= '<' . $tag . ' class="lpg-lp__points">';
			foreach ( array_slice( $bullets, 0, 16 ) as $bullet ) { $html .= '<li>' . self::text( $bullet ) . '</li>'; }
			$html .= '</' . $tag . '>';
		}
		$table = is_array( $section['table'] ?? null ) ? $section['table'] : array();
		if ( ! empty( $table['columns'] ) && is_array( $table['columns'] ) && is_array( $table['rows'] ?? null ) ) {
			$html .= '<div class="lpg-lp__table-wrap" tabindex="0" role="region" aria-label="' . esc_attr( (string) ( $section['heading'] ?? '' ) ) . '"><table><caption>' . self::text( $section['heading'] ?? '' ) . '</caption><thead><tr>';
			foreach ( array_slice( $table['columns'], 0, 8 ) as $column ) { $html .= '<th scope="col">' . self::text( $column ) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ( array_slice( $table['rows'], 0, 20 ) as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$html .= '<tr>';
				foreach ( array_slice( $row, 0, 8 ) as $cell ) { $html .= '<td>' . self::text( $cell ) . '</td>'; }
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';
		}
		$quote = $section['quote'] ?? array();
		if ( is_array( $quote ) && ! empty( $quote['text'] ) && ! empty( $quote['source'] ) ) {
			$html .= '<blockquote><p>' . self::text( $quote['text'] ) . '</p><cite>' . self::text( $quote['source'] ) . '</cite></blockquote>';
		}
		$html .= self::images( $images );
		if ( 'offer' === $layout ) { $html .= $button; }
		return $html . '</section>';
	}

	/** No style/script/embed tags: HTML survives WordPress post sanitization. */
	public static function render( array $data, array $campaign, array $images = array() ): string {
		if ( array_key_exists( 'lp_type', $campaign ) ) {
			return LpTypeTemplate::render( $data, $campaign, $images );
		}
		if ( DesignContract::AIDA === DesignContract::id_for( (string) ( $campaign['framework_slug'] ?? '' ) ) && DesignContract::active( $campaign ) ) { return AidaLayout::render( $data, $campaign, $images ); }
		$slug = sanitize_key( (string) ( $campaign['framework_slug'] ?? 'aida' ) );
		if ( DesignContract::active( $campaign ) ) { DesignContract::validate_images( $images, $campaign ); }
		$type = in_array( (string) ( $campaign['offer_type'] ?? '' ), array( 'product', 'service', 'affiliate' ), true ) ? $campaign['offer_type'] : 'service';
		$affiliate = 'affiliate' === $type;
		$url = (string) ( $campaign['offer_url'] ?? $data['cta_url'] ?? '' );
		$label = (string) ( $data['cta_text'] ?? $campaign['cta'] ?? '' );
		$button = self::button( $url, $label, $affiliate );
		$hero_images = array();
		$positioned  = array();
		$slotted     = array();
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) { continue; }
			$slot = sanitize_key( (string) ( $image['slot_id'] ?? '' ) );
			if ( '' !== $slot ) { $slotted[ $slot ] = $image; continue; }
			$hint = sanitize_key( (string) ( $image['section_hint'] ?? '' ) );
			if ( '' === $hint || 'hero' === $hint ) { $hero_images[] = $image; } else { $positioned[ $hint ][] = $image; }
		}
		$model = LpTemplate::model( $campaign );
		$html = '<div class="lpg-lp lpg-lp--' . esc_attr( $slug ) . ' lpg-lp--' . esc_attr( $type ) . ' ' . esc_attr( LpTemplate::root_class( $campaign ) ) . '" data-framework="' . esc_attr( $slug ) . '" data-lp-model="' . esc_attr( $model ) . '" data-lp-type="' . esc_attr( LpTemplate::type( $campaign ) ) . '">';
		$html .= LpTemplate::open( $campaign, $data );
		$html .= '<header class="lpg-lp__hero' . ( $hero_images ? ' lpg-lp__hero--split' : '' ) . '"><div class="lpg-lp__hero-copy">';
		$eyebrow = trim( implode( ' · ', array_filter( array( (string) ( $campaign['brand'] ?? '' ), (string) ( $campaign['city'] ?? '' ) ) ) ) );
		if ( '' !== $eyebrow ) { $html .= '<p class="lpg-lp__eyebrow">' . self::text( $eyebrow ) . '</p>'; }
		$html .= '<h1>' . self::text( $data['title'] ?? $campaign['offer_name'] ?? '' ) . '</h1>';
		if ( $affiliate ) {
			$html .= '<p class="lpg-lp__disclosure"><strong>' . esc_html__( 'Disclosure:', 'lp-generator' ) . '</strong> ' . ( 'en' === ( $campaign['language'] ?? 'id' ) ? 'This page contains affiliate links. We may earn a commission at no additional cost to you. Prices may change; check the official website.' : 'Halaman ini memuat tautan afiliasi. Kami dapat menerima komisi tanpa biaya tambahan untuk Anda. Harga dapat berubah; periksa situs resminya.' ) . '</p>';
		}
		$html .= '<div class="lpg-lp__intro">' . self::paragraphs( $data['intro'] ?? '' ) . '</div>' . $button;
		$html .= '</div>' . self::images( $hero_images, true ) . '</header>';
		foreach ( is_array( $data['sections'] ?? null ) ? $data['sections'] : array() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$hint = sanitize_key( (string) ( $section['hint'] ?? '' ) );
			$slot = sanitize_key( (string) ( $section['image_slot'] ?? '' ) );
			$section_images = '' !== $slot && isset( $slotted[ $slot ] ) ? array( $slotted[ $slot ] ) : ( $positioned[ $hint ] ?? array() );
			$html .= self::section( $section, $section_images, $button );
			unset( $positioned[ $hint ], $slotted[ $slot ] );
		}
		foreach ( $slotted as $group ) { $html .= self::images( array( $group ) ); }
		foreach ( $positioned as $group ) { $html .= self::images( $group ); }
		if ( $affiliate && ! empty( $campaign['amazon_products'] ) && is_array( $campaign['amazon_products'] ) ) {
			$html .= '<section class="lpg-lp__section">' . preg_replace( '/<!--.*?-->/s', '', Amazon::products_block( $campaign['amazon_products'], $campaign ) ) . '</section>';
		}
		$faq = is_array( $data['faq'] ?? null ) ? $data['faq'] : array();
		if ( $faq ) {
			$html .= '<section class="lpg-lp__faq"><h2>' . ( 'en' === ( $campaign['language'] ?? 'id' ) ? 'Frequently asked questions' : 'Pertanyaan yang sering diajukan' ) . '</h2>';
			foreach ( $faq as $pair ) {
				if ( is_array( $pair ) && ! empty( $pair['q'] ) && ! empty( $pair['a'] ) ) {
					$html .= '<details><summary>' . self::text( $pair['q'] ) . '</summary>' . self::paragraphs( $pair['a'] ) . '</details>';
				}
			}
			$html .= '</section>';
		}
		$html .= LpTemplate::close( $campaign, $data, $button );
		return "<!-- wp:html -->\n" . $html . "</div>\n<!-- /wp:html -->";
	}
}
