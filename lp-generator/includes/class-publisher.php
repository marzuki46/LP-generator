<?php
/**
 * Turns generated JSON into Gutenberg blocks and publishes WP posts/pages.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Publisher
 */
final class Publisher {

	/**
	 * Build a CTA block. Pure except for WP escaping helpers.
	 *
	 * @param string $url       Destination URL.
	 * @param string $text      Button text.
	 * @param bool   $affiliate Add sponsored nofollow + new tab.
	 * @param string $anchor    Optional visible anchor text override.
	 * @return string
	 */
	public static function build_cta_block( string $url, string $text, bool $affiliate = false, string $anchor = '' ): string {
		$label = '' !== $anchor ? $anchor : $text;
		$rel   = $affiliate ? ' rel="sponsored nofollow" target="_blank"' : '';
		$safe_url  = esc_url( $url );
		$safe_text = esc_html( $label );
		return "<!-- wp:buttons -->\n"
			. '<div class="wp-block-buttons"><!-- wp:button -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link" href="' . $safe_url . '"' . $rel . '>' . $safe_text . '</a></div>'
			. "<!-- /wp:button --></div>\n<!-- /wp:buttons -->";
	}

	/**
	 * Wrap text in a paragraph block.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function paragraph_block( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>" . wp_kses_post( $text ) . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * Wrap text in a heading block.
	 *
	 * @param string $text  Heading.
	 * @param int    $level Level 2–4.
	 * @return string
	 */
	public static function heading_block( string $text, int $level = 2 ): string {
		$level = max( 2, min( 4, $level ) );
		return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">" . esc_html( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
	}

	/**
	 * Build a list block from bullets.
	 *
	 * @param string[] $bullets Bullets.
	 * @return string
	 */
	public static function list_block( array $bullets ): string {
		$bullets = array_filter( array_map( 'strval', $bullets ), static fn( $b ): bool => '' !== trim( $b ) );
		if ( empty( $bullets ) ) {
			return '';
		}
		$html = "<!-- wp:list -->\n<ul>";
		foreach ( $bullets as $bullet ) {
			$html .= '<li>' . wp_kses_post( $bullet ) . '</li>';
		}
		return $html . "</ul>\n<!-- /wp:list -->";
	}

	/**
	 * Build a comparison table block for affiliate offers.
	 *
	 * @param string[] $columns Columns.
	 * @param array<int,array<int,string>> $rows Rows.
	 * @return string
	 */
	public static function table_block( array $columns, array $rows ): string {
		if ( empty( $columns ) ) {
			return '';
		}
		$html = "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr>";
		foreach ( $columns as $column ) {
			$html .= '<th>' . esc_html( (string) $column ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $row as $cell ) {
				$html .= '<td>' . wp_kses_post( (string) $cell ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . "</tbody></table></figure>\n<!-- /wp:table -->";
	}

	/**
	 * Build disclosure paragraph for affiliate offers.
	 *
	 * @return string
	 */
	public static function disclosure_block(): string {
		return self::paragraph_block(
			'<strong>' . esc_html__( 'Disclosure:', 'lp-generator' ) . '</strong> '
			. esc_html__( 'Halaman ini mengandung tautan afiliasi. Kami dapat menerima komisi tanpa biaya tambahan bagi Anda. Harga dapat berubah; silakan cek halaman resmi.', 'lp-generator' )
		);
	}

	/**
	 * Insert a contextual internal link into a CTA paragraph.
	 *
	 * @param string $paragraph CTA paragraph from AI.
	 * @param string $url       Target permalink.
	 * @param string $anchor    Anchor suggestion.
	 * @return string
	 */
	public static function linkify_cta( string $paragraph, string $url, string $anchor ): string {
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html( $anchor ) . '</a>';
		if ( '' !== $anchor ) {
			$position = mb_stripos( $paragraph, $anchor );
			if ( false !== $position ) {
				$linked = mb_substr( $paragraph, 0, $position ) . $link . mb_substr( $paragraph, $position + mb_strlen( $anchor ) );
				return wp_kses_post( $linked );
			}
		}
		return wp_kses_post( $paragraph . ' ' . $link );
	}

	/**
	 * Convert a structured AI result into Gutenberg blocks.
	 *
	 * @param array  $structured Decoded AI JSON.
	 * @param string $kind       lp|topic|article.
	 * @param array  $campaign   Campaign context.
	 * @param array  $images     Attached image records for a gallery.
	 * @return string
	 */
	public static function build_blocks( array $structured, string $kind, array $campaign, array $images = array() ): string {
		if ( 'topic' === $kind ) {
			$out = array( self::heading_block( (string) ( $structured['title'] ?? '' ) ) );
			$out[] = self::paragraph_block( (string) ( $structured['angle'] ?? '' ) );
			$out[] = self::list_block( (array) ( $structured['outline'] ?? array() ) );
			return implode( "\n\n", array_filter( $out ) );
		}

		$blocks = array();
		if ( isset( $structured['intro'] ) ) {
			$blocks[] = self::paragraph_block( (string) $structured['intro'] );
		}
		if ( 'affiliate' === (string) ( $campaign['offer_type'] ?? '' ) ) {
			$blocks[] = self::disclosure_block();
			if ( ! empty( $campaign['amazon_products'] ) && is_array( $campaign['amazon_products'] ) ) {
				$blocks[] = Amazon::products_block( $campaign['amazon_products'], $campaign );
			}
		}
		$positioned      = array();
		$fallback_images = array();
		foreach ( $images as $image ) {
			$hint = is_array( $image ) ? sanitize_key( (string) ( $image['section_hint'] ?? '' ) ) : '';
			if ( '' !== $hint ) {
				$positioned[ $hint ][] = $image;
			} else {
				$fallback_images[] = $image;
			}
		}
		if ( ! empty( $fallback_images ) ) {
			$gallery = ImagePipeline::gallery_block( $fallback_images );
			if ( '' !== $gallery ) {
				$blocks[] = $gallery;
			}
		}
		$sections = isset( $structured['sections'] ) && is_array( $structured['sections'] ) ? $structured['sections'] : array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$heading = (string) ( $section['heading'] ?? '' );
			if ( '' !== $heading ) {
				$blocks[] = self::heading_block( $heading, 2 );
			}
			if ( isset( $section['body'] ) ) {
				$blocks[] = self::paragraph_block( (string) $section['body'] );
			}
			if ( isset( $section['bullets'] ) && is_array( $section['bullets'] ) ) {
				$blocks[] = self::list_block( $section['bullets'] );
			}
			$hint = sanitize_key( (string) ( $section['hint'] ?? '' ) );
			if ( '' === $hint ) {
				$hint = sanitize_title( $heading );
			}
			if ( '' !== $hint && isset( $positioned[ $hint ] ) ) {
				$gallery = ImagePipeline::gallery_block( $positioned[ $hint ] );
				if ( '' !== $gallery ) {
					$blocks[] = $gallery;
				}
				unset( $positioned[ $hint ] );
			}
		}
		foreach ( $positioned as $group ) {
			$gallery = ImagePipeline::gallery_block( $group );
			if ( '' !== $gallery ) {
				$blocks[] = $gallery;
			}
		}
		$faq = isset( $structured['faq'] ) && is_array( $structured['faq'] ) ? $structured['faq'] : array();
		if ( ! empty( $faq ) ) {
			$blocks[] = self::heading_block( __( 'Pertanyaan Umum', 'lp-generator' ), 2 );
			foreach ( $faq as $pair ) {
				if ( ! is_array( $pair ) ) {
					continue;
				}
				$blocks[] = self::heading_block( (string) ( $pair['q'] ?? '' ), 3 );
				$blocks[] = self::paragraph_block( (string) ( $pair['a'] ?? '' ) );
			}
		}
		if ( isset( $structured['cta_paragraph'] ) ) {
			$blocks[] = self::paragraph_block( (string) $structured['cta_paragraph'] );
		}
		$cta_text = (string) ( $structured['cta_text'] ?? $campaign['cta'] ?? '' );
		$cta_url  = (string) ( $structured['cta_url'] ?? $campaign['offer_url'] ?? '' );
		if ( '' !== $cta_text && '' !== $cta_url ) {
			$blocks[] = self::build_cta_block( $cta_url, $cta_text, 'affiliate' === ( $campaign['offer_type'] ?? '' ) );
		}
		return implode( "\n\n", array_filter( $blocks ) );
	}

	/**
	 * Publish one generated item.
	 *
	 * @param int    $item_id      Item ID.
	 * @param array  $campaign     Campaign context.
	 * @param string $target_url   Internal link target (may be empty for LP).
	 * @return int Published post ID, 0 on failure.
	 */
	public static function publish_item( int $item_id, array $campaign, string $target_url = '' ): int {
		$item = ContentRepository::get_item( $item_id );
		if ( empty( $item ) || 'topic' === $item['kind'] ) {
			return 0;
		}
		$structured = json_decode( (string) ( $item['structured'] ?? '' ), true );
		if ( ! is_array( $structured ) ) {
			$structured = array( 'title' => $item['title'] );
		}

		$images = ContentRepository::item_images( $item_id );
		$blocks = self::build_blocks( $structured, (string) $item['kind'], $campaign, $images );
		if ( 'article' === $item['kind'] && '' !== $target_url ) {
			$anchor = (string) ( $item['anchor'] ?? '' );
			if ( '' === $anchor ) {
				$anchor = __( 'lihat panduan lengkapnya', 'lp-generator' );
			}
			$link_block = self::paragraph_block( self::linkify_cta( '', $target_url, $anchor ) );
			$blocks    .= "\n\n" . $link_block;
		}

		$post_type = 'bofu' === $item['stage']
			? (string) ( $campaign['lp_post_type'] ?? 'page' )
			: (string) ( $campaign['article_post_type'] ?? 'post' );
		$post_type = in_array( $post_type, array( 'page', 'post' ), true ) ? $post_type : 'post';
		$status    = (string) ( $campaign['publish_status'] ?? 'publish' );
		$status    = in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'draft';

		$post_args = array(
			'post_type'    => $post_type,
			'post_status'  => $status,
			'post_title'   => sanitize_text_field( (string) $item['title'] ),
			'post_content' => wp_kses_post( $blocks ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $item['meta_description'] ?? '' ) ),
		);
		if ( 'post' === $post_type && absint( $campaign['article_category'] ?? 0 ) > 0 ) {
			$post_args['post_category'] = array( absint( $campaign['article_category'] ) );
		}
		$post_id = wp_insert_post( $post_args, true );
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		$post_id   = (int) $post_id;
		$permalink = get_permalink( $post_id );

		update_post_meta( $item_id, '_lpgen_post_id', $post_id );
		update_post_meta( $item_id, '_lpgen_permalink', $permalink );
		update_post_meta( $item_id, '_lpgen_status', 'published' );
		update_post_meta( $post_id, '_lpgen_item_id', $item_id );
		update_post_meta( $post_id, '_lpgen_campaign_id', absint( $campaign['campaign_id'] ?? 0 ) );

		$tags = ContentRepository::item_tags( $item_id );
		if ( ! empty( $tags ) && 'page' !== $post_type ) {
			wp_set_post_terms( $post_id, $tags, 'post_tag', true );
		}
		self::apply_seo_meta( $post_id, $item, $campaign );

		if ( 'publish' === $status && ! empty( $campaign['index_enabled'] ) ) {
			Indexer::schedule( absint( $campaign['campaign_id'] ?? 0 ), $item_id, $permalink, (int) ( $campaign['index_delay'] ?? 10 ) );
		}
		return $post_id;
	}

	/**
	 * Inject focus keyword, SEO title and meta description into Yoast meta keys.
	 *
	 * @param int   $post_id  Published post ID.
	 * @param array $item     Generated item.
	 * @param array $campaign Campaign context.
	 */
	public static function apply_seo_meta( int $post_id, array $item, array $campaign ): void {
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		if ( isset( $settings['yoast_enabled'] ) && empty( $settings['yoast_enabled'] ) ) {
			return;
		}

		$entities   = isset( $campaign['entities'] ) && is_array( $campaign['entities'] ) ? $campaign['entities'] : array();
		$first      = reset( $entities );
		$entity     = is_array( $first ) ? (string) ( $first['term'] ?? '' ) : (string) $first;
		$tag        = trim( (string) ( $item['tag'] ?? '' ) );
		$source     = (string) ( $settings['focus_keyword_source'] ?? 'entity' );
		$item_tags  = ContentRepository::item_tags( (int) ( $item['id'] ?? 0 ) );
		$keyword    = (string) ( $item['title'] ?? '' );
		if ( 'tag' === $source && '' !== $tag ) {
			$keyword = $tag;
		} elseif ( 'entity' === $source && '' !== $entity ) {
			$keyword = $entity;
		} elseif ( '' !== $tag ) {
			$keyword = $tag;
		}
		if ( '' === trim( $keyword ) && ! empty( $item_tags ) ) {
			$keyword = $item_tags[0];
		}
		$keyword = Utils::truncate( sanitize_text_field( $keyword ), 80 );

		$data = array(
			'keyword'     => $keyword,
			'title'       => (string) ( $item['title'] ?? '' ),
			'tag'         => '' !== $tag ? $tag : ( $item_tags[0] ?? '' ),
			'brand'       => (string) ( $campaign['brand'] ?? '' ) !== '' ? (string) $campaign['brand'] : get_bloginfo( 'name' ),
			'city'        => (string) ( $campaign['city'] ?? '' ),
			'phone'       => (string) ( $campaign['phone'] ?? '' ),
			'offer'       => (string) ( $campaign['offer_name'] ?? '' ),
			'promise'     => (string) ( $campaign['promise'] ?? '' ),
			'price'       => (string) ( $campaign['price'] ?? '' ),
			'stage'       => strtoupper( (string) ( $item['stage'] ?? '' ) ),
			'language'    => (string) ( $campaign['language'] ?? 'id' ),
		);

		$title_template = (string) ( $settings['yoast_title_template'] ?? '{{keyword}} | {{brand}}' );
		$desc_template  = (string) ( $settings['yoast_description_template'] ?? '{{keyword}} — {{promise}} Hubungi {{phone}} {{city}}.' );
		$seo_title      = '' !== $title_template
			? Utils::render_template( $title_template, $data, 60 )
			: (string) ( $item['seo_title'] ?? '' );
		$description    = '' !== $desc_template
			? Utils::render_template( $desc_template, $data, 155 )
			: (string) ( $item['meta_description'] ?? '' );
		if ( '' === trim( $seo_title ) ) {
			$seo_title = Utils::truncate( (string) ( $item['title'] ?? '' ), 60 );
		}
		if ( '' === trim( $description ) ) {
			$description = Utils::truncate( (string) ( $item['meta_description'] ?? '' ), 155 );
		}

		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
		update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $seo_title ) );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $description ) );
	}
}
