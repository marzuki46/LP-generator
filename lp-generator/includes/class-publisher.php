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
		if ( 'lp' === $kind ) { return LandingPage::render( $structured, $campaign, $images ); }
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
		// Supporting articles use the first image as the featured thumbnail only;
		// galleries stay in generated content for LP/preview, not published articles.
		$inline_images   = 'article' !== $kind;
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
		if ( $inline_images && ! empty( $fallback_images ) ) {
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
			$table = is_array( $section['table'] ?? null ) ? $section['table'] : array();
			if ( ! empty( $table['columns'] ) && is_array( $table['columns'] ) && is_array( $table['rows'] ?? null ) ) {
				$width   = count( $table['columns'] );
				$rows    = array();
				foreach ( $table['rows'] as $row ) {
					if ( ! is_array( $row ) ) { continue; }
					$cells = array_slice( array_values( $row ), 0, $width );
					while ( count( $cells ) < $width ) { $cells[] = ''; }
					$rows[] = $cells;
				}
				if ( $rows ) {
					$blocks[] = self::table_block( array_values( array_map( 'strval', $table['columns'] ) ), $rows );
				}
			}
			$hint = sanitize_key( (string) ( $section['hint'] ?? '' ) );
			if ( '' === $hint ) {
				$hint = sanitize_title( $heading );
			}
			if ( $inline_images && '' !== $hint && isset( $positioned[ $hint ] ) ) {
				$gallery = ImagePipeline::gallery_block( $positioned[ $hint ] );
				if ( '' !== $gallery ) {
					$blocks[] = $gallery;
				}
				unset( $positioned[ $hint ] );
			}
		}
		foreach ( $inline_images ? $positioned : array() as $group ) {
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
		if ( 'lp' === $kind && '' !== $cta_text && '' !== $cta_url ) {
			$blocks[] = self::build_cta_block( $cta_url, $cta_text, 'affiliate' === ( $campaign['offer_type'] ?? '' ) );
		}
		$theme   = in_array( (string) ( $campaign['article_theme'] ?? '' ), array( 'editorial', 'magazine', 'minimal' ), true ) ? (string) $campaign['article_theme'] : 'editorial';
		$content = implode( "\n\n", array_filter( $blocks ) );
		return "<!-- wp:group {\"className\":\"lpg-article lpg-article--" . esc_attr( $theme ) . "\"} -->\n<div class=\"wp-block-group lpg-article lpg-article--" . esc_attr( $theme ) . "\">\n" . $content . "\n</div>\n<!-- /wp:group -->";
	}

	/**
	 * Publish one generated item.
	 *
	 * @param int    $item_id      Item ID.
	 * @param array  $campaign     Campaign context.
	 * @param string $target_url   Internal link target (may be empty for LP).
	 * @return int Published post ID, 0 on failure.
	 */
	/** Deterministic, varied placement so not every article links at the end. */
	public static function link_slot( int $item_id, string $anchor = '' ): string {
		$slots = array( 'top', 'middle', 'bottom' );
		$index = absint( crc32( $item_id . '|' . $anchor ) ) % 3;
		return $slots[ $index ];
	}

	/** Small 2-item related box (kicker + title + one-line excerpt). */
	public static function compact_related_box( int $item_id, array $campaign ): string {
		$campaign_id = (int) ( $campaign['campaign_id'] ?? 0 );
		if ( $campaign_id <= 0 || ! function_exists( 'get_post_meta' ) ) { return ''; }
		$items = ContentRepository::items( $campaign_id );
		$picks = array();
		foreach ( $items as $item ) {
			if ( (int) $item['id'] === $item_id || 'article' !== (string) $item['kind'] ) { continue; }
			$picks[] = $item;
		}
		$anchor = class_exists( Generator::class )
			? Generator::internal_anchor( $item_id, $campaign )
			: trim( (string) ( $campaign['offer_name'] ?? '' ) . ' ' . (string) ( $campaign['city'] ?? '' ) );
		$cards  = '';
		foreach ( array_slice( $picks, 0, 2 ) as $pick ) {
			$link = (string) ( $pick['permalink'] ?? '' );
			if ( '' === $link && ! empty( $pick['post_id'] ) ) { $link = (string) get_permalink( (int) $pick['post_id'] ); }
			if ( '' === $link ) { continue; }
			$kicker  = strtoupper( (string) ( $pick['stage'] ?? 'artikel' ) );
			$title   = (string) ( $pick['title'] ?? '' );
			$excerpt = Utils::truncate( sanitize_text_field( (string) ( $pick['meta_description'] ?? '' ) ), 110 );
			if ( '' === $excerpt ) { $excerpt = Utils::truncate( $title, 110 ); }
			$cards .= '<a class="lpg-inline-related__card" href="' . esc_url( $link ) . '">'
				. '<span class="lpg-inline-related__kicker">' . esc_html( $kicker ) . '</span>'
				. '<span class="lpg-inline-related__title">' . esc_html( $title ) . '</span>'
				. '<span class="lpg-inline-related__excerpt">' . esc_html( $excerpt ) . '</span>'
				. '</a>';
		}
		if ( '' === $cards ) { return ''; }
		return "<!-- wp:html -->\n<div class=\"lpg-inline-related\" data-lp-related=\"compact\" data-anchor=\"" . esc_attr( $anchor ) . "\">" . $cards . "</div>\n<!-- /wp:html -->";
	}

	/** Insert the BOFU link into a natural paragraph at a deterministic position. */
	public static function insert_funnel_link( string $blocks, string $url, string $anchor, string $fallback, string $slot = 'middle' ): string {
		SeoStandards::assert_internal( $url );
		preg_match_all( '#<p([^>]*)>(.*?)</p>#su', $blocks, $matches, PREG_OFFSET_CAPTURE );
		$paragraphs = isset( $matches[0] ) ? $matches[0] : array();
		$count      = count( $paragraphs );
		if ( 0 === $count ) {
			$sentence = '' !== trim( $fallback ) ? $fallback : __( 'Pelajari solusi yang sesuai dengan kebutuhan Anda:', 'lp-generator' );
			return $blocks . "\n\n" . self::paragraph_block( self::linkify_cta( strip_tags( $sentence ), $url, $anchor ) );
		}
		if ( 'top' === $slot ) {
			$want = 0;
		} elseif ( 'bottom' === $slot ) {
			$want = max( 0, $count - 2 );
		} else {
			$want = (int) floor( $count / 2 );
		}
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html( $anchor ) . '</a>';
		$order = array();
		for ( $step = 0; $step < $count; $step++ ) {
			$order[] = ( $want + $step ) % $count;
		}
		foreach ( $order as $index ) {
			$full    = $paragraphs[ $index ][0];
			$inner   = $matches[2][ $index ][0];
			if ( preg_match( '#<a\b#i', $inner ) ) { continue; }
			$parts   = preg_split( '/(<[^>]+>)/u', $inner, -1, PREG_SPLIT_DELIM_CAPTURE );
			$done    = false;
			foreach ( $parts as &$part ) {
				if ( $done || str_starts_with( (string) $part, '<' ) ) { continue; }
				$matched = preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $anchor, '/' ) . '(?![\p{L}\p{N}])/iu', (string) $part, $found, PREG_OFFSET_CAPTURE );
				if ( ! $matched ) { continue; }
				$pos   = mb_strlen( substr( (string) $part, 0, $found[0][1] ) );
				$part  = mb_substr( (string) $part, 0, $pos ) . $link . mb_substr( (string) $part, $pos + mb_strlen( $anchor ) );
				$done  = true;
			}
			unset( $part );
			if ( ! $done ) { continue; }
			$replacement = '<p' . $matches[1][ $index ][0] . '>' . implode( '', $parts ) . '</p>';
			$offset      = (int) $paragraphs[ $index ][1];
			$length      = strlen( $full );
			$blocks      = substr( $blocks, 0, $offset ) . $replacement . substr( $blocks, $offset + $length );
			return $blocks;
		}
		// Anchor absent from reader copy: insert a short natural bridge at the chosen position.
		$sentence = '' !== trim( $fallback ) ? strip_tags( $fallback ) : __( 'Pelajari solusi yang sesuai dengan kebutuhan Anda:', 'lp-generator' );
		$bridge   = self::paragraph_block( self::linkify_cta( $sentence, $url, $anchor ) );
		$insert_at = (int) $paragraphs[ min( $want, $count - 1 ) ][1];
		return substr( $blocks, 0, $insert_at ) . $bridge . "\n\n" . substr( $blocks, $insert_at );
	}

	public static function publish_item( int $item_id, array $campaign, string $target_url = '' ): int {
		$item = ContentRepository::get_item( $item_id );
		if ( empty( $item ) || 'topic' === $item['kind'] ) {
			return 0;
		}
		$structured = json_decode( (string) ( $item['structured'] ?? '' ), true );
		if ( ! is_array( $structured ) ) {
			$structured = array( 'title' => $item['title'] );
		}

		if ( 'lp' === $item['kind'] && ! empty( $item['lp_plan_fingerprint'] ) && ! hash_equals( (string) $item['lp_plan_fingerprint'], Frameworks::fingerprint( $campaign ) ) ) { throw new \RuntimeException( 'Data campaign berubah setelah LP dibuat. Generate ulang LP agar sesuai framework dan offer terbaru.' ); }
		$images = ContentRepository::item_images( $item_id );
		// The reviewed editor content is authoritative; do not rebuild it from AI JSON.
		$blocks = trim( (string) $item['content'] );
		if ( '' === $blocks ) { $blocks = self::build_blocks( $structured, (string) $item['kind'], $campaign, $images ); }
		FunnelContent::entities( json_decode( (string) ( $item['entities'] ?? '' ), true ) );
		FunnelContent::assert_unique( (string) $item['title'], $blocks, FunnelContent::exclusions( $item_id ) );
		if ( 'article' === $item['kind'] && '' !== $target_url ) {
			$anchor = class_exists( Generator::class )
				? Generator::internal_anchor( (int) $item['id'], $campaign )
				: FunnelContent::anchor( (string) ( $item['anchor'] ?? '' ) );
			$slot   = self::link_slot( $item_id, $anchor );
			$blocks = self::insert_funnel_link( $blocks, $target_url, $anchor, (string) ( $structured['cta_paragraph'] ?? '' ), $slot );
			$compact = self::compact_related_box( $item_id, $campaign );
			if ( '' !== $compact ) {
				// Place the compact box opposite the main funnel link for variety.
				$blocks = 'bottom' === $slot
					? $compact . "\n\n" . $blocks
					: $blocks . "\n\n" . $compact;
			}
			if ( ! empty( $campaign['secondary_funnel_url'] ) ) {
				SeoStandards::assert_internal( (string) $campaign['secondary_funnel_url'] );
				$blocks .= "\n\n" . self::paragraph_block( '<a href="' . esc_url( $campaign['secondary_funnel_url'] ) . '">' . esc_html__( 'Bandingkan pilihan dan pertimbangannya', 'lp-generator' ) . '</a>' );
			}
		}

		$post_type = 'bofu' === $item['stage']
			? (string) ( $campaign['lp_post_type'] ?? 'page' )
			: (string) ( $campaign['article_post_type'] ?? 'post' );
		$post_type = in_array( $post_type, array( 'page', 'post' ), true ) ? $post_type : 'post';
		if ( 'bofu' === $item['stage'] && '' !== trim( (string) ( $campaign['bofu_path'] ?? '' ) ) && 'page' !== $post_type ) {
			Database::log( 'warning', 'Path LP bertingkat memaksa post type Page; pengaturan campaign disesuaikan saat publish.', (int) ( $campaign['campaign_id'] ?? 0 ) );
			$post_type = 'page';
		}
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
		if ( 'bofu' === $item['stage'] && '' !== trim( (string) ( $campaign['bofu_path'] ?? '' ) ) ) {
			$path = FunnelContent::clean_path( (string) $campaign['bofu_path'] );
			if ( '' !== $path ) {
				$existing = get_page_by_path( $path, OBJECT, 'page' );
				if ( $existing && (int) $existing->ID !== (int) ( $item['post_id'] ?? 0 ) ) { throw new \RuntimeException( 'Path LP sudah digunakan. Pilih mode BOFU yang sudah ada.' ); }
				$parts = explode( '/', $path );
				$post_args['post_name'] = array_pop( $parts );
				$parent_id   = 0;
				$cumulative  = array();
				$chain       = array();
				foreach ( $parts as $part ) {
					$cumulative[] = $part;
					$chain[]      = implode( '/', $cumulative );
				}
				$chain[] = $path;
				$lp_url  = home_url( '/' . $path . '/' );
				$anchor  = class_exists( Generator::class )
					? Generator::internal_anchor( (int) ( $item['id'] ?? 0 ), $campaign )
					: trim( (string) ( $campaign['offer_name'] ?? '' ) . ' ' . (string) ( $campaign['city'] ?? '' ) );
				foreach ( $chain as $index => $parent_path ) {
					if ( $parent_path === $path ) { break; }
					$part         = basename( $parent_path );
					$next_path    = (string) ( $chain[ $index + 1 ] ?? '' );
					$spec         = LpParentPages::for_path( $campaign, $parent_path );
					$parent_args  = array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $spec['title'],
						'post_name'    => $part,
						'post_content' => wp_kses_post( LpParentPages::blocks( $campaign, $parent_path, $next_path, $lp_url, $anchor ) ),
						'post_excerpt' => sanitize_textarea_field( $spec['intro'] ),
						'post_parent'  => $parent_id,
					);
					$parent_args['meta_input'] = array( '_lpgen_canvas' => 1 );
					$parent = get_page_by_path( $parent_path, OBJECT, 'page' );
					if ( ! $parent ) {
						$parent_id = wp_insert_post( $parent_args, true );
						if ( is_wp_error( $parent_id ) || (int) $parent_id <= 0 ) {
							throw new \RuntimeException( 'Gagal membuat halaman induk LP: ' . $parent_path );
						}
						update_post_meta( (int) $parent_id, '_lpgen_parent_page', (int) ( $campaign['campaign_id'] ?? 0 ) );
						Database::log( 'info', sprintf( 'Halaman induk LP dibuat: /%s', $parent_path ), (int) ( $campaign['campaign_id'] ?? 0 ) );
						$parent_id = (int) $parent_id;
					} else {
						$parent_id = (int) $parent->ID;
						if ( (int) get_post_meta( $parent_id, '_lpgen_parent_page', true ) === (int) ( $campaign['campaign_id'] ?? 0 ) ) {
							$parent_args['ID'] = $parent_id;
							wp_update_post( $parent_args, true );
							Database::log( 'info', sprintf( 'Halaman induk LP diperbarui: /%s', $parent_path ), (int) ( $campaign['campaign_id'] ?? 0 ) );
						} elseif ( 'publish' !== (string) $parent->post_status ) {
							wp_update_post( array( 'ID' => $parent_id, 'post_status' => 'publish' ), true );
						}
					}
				}
				if ( $parent_id > 0 ) {
					$post_args['post_parent'] = $parent_id;
				}
			}
		}
		// Retrying or republishing the same item updates its destination, not a duplicate post.
		if ( ! empty( $item['post_id'] ) && get_post( (int) $item['post_id'] ) ) {
			$post_args['ID'] = (int) $item['post_id'];
		}
		$post_id = wp_insert_post( $post_args, true );
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		$post_id   = (int) $post_id;
		// Support articles: first verified image becomes the featured thumbnail,
		// so the blog/LP listing shows it without an inline gallery in the body.
		if ( 'article' === (string) ( $item['kind'] ?? '' ) && function_exists( 'set_post_thumbnail' ) ) {
			foreach ( $images as $candidate ) {
				$thumb_id = is_array( $candidate ) ? absint( $candidate['id'] ?? 0 ) : 0;
				if ( $thumb_id > 0 ) {
					set_post_thumbnail( $post_id, $thumb_id );
					break;
				}
			}
		}
		$permalink = get_permalink( $post_id );

		update_post_meta( $item_id, '_lpgen_post_id', $post_id );
		update_post_meta( $item_id, '_lpgen_permalink', $permalink );
		update_post_meta( $item_id, '_lpgen_status', 'published' );
		if ( 'lp' === $item['kind'] && false !== strpos( $blocks, 'lpg-lp' ) ) {
			update_post_meta( $post_id, '_lpgen_canvas', 'theme' === (string) ( $campaign['lp_layout'] ?? 'canvas' ) ? 0 : 1 );
		}
		update_post_meta( $post_id, '_lpgen_item_id', $item_id );
		update_post_meta( $post_id, '_lpgen_campaign_id', absint( $campaign['campaign_id'] ?? 0 ) );

		$tags = ContentRepository::item_tags( $item_id );
		if ( ! empty( $tags ) && 'page' !== $post_type ) {
			wp_set_post_terms( $post_id, $tags, 'post_tag', true );
		}
		self::apply_seo_meta( $post_id, $item, $campaign );
		if ( 'publish' === $status ) { SeoStandards::refresh_related( absint( $campaign['campaign_id'] ?? 0 ) ); }

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
		$yoast_enabled = ! isset( $settings['yoast_enabled'] ) || ! empty( $settings['yoast_enabled'] );

		$item_entities = json_decode( (string) ( $item['entities'] ?? '' ), true );
		$entities = is_array( $item_entities ) && $item_entities ? $item_entities : (array) ( $campaign['entities'] ?? array() );
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
		if ( 'template' !== (string) ( $settings['seo_meta_source'] ?? 'generated' ) ) {
			if ( '' !== trim( (string) ( $item['seo_title'] ?? '' ) ) ) { $seo_title = Utils::truncate( (string) $item['seo_title'], 60 ); }
			if ( '' !== trim( (string) ( $item['meta_description'] ?? '' ) ) ) { $description = Utils::truncate( (string) $item['meta_description'], 155 ); }
		}
		if ( '' === trim( $seo_title ) ) {
			$seo_title = Utils::truncate( (string) ( $item['title'] ?? '' ), 60 );
		}
		if ( '' === trim( $description ) ) {
			$description = Utils::truncate( (string) ( $item['meta_description'] ?? '' ), 155 );
		}

		update_post_meta( $post_id, '_lpgen_meta_description', sanitize_text_field( $description ) );
		update_post_meta( $post_id, '_lpgen_seo_title', sanitize_text_field( $seo_title ) );
		if ( ! $yoast_enabled ) { return; }
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
		update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $seo_title ) );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $description ) );
	}
}
