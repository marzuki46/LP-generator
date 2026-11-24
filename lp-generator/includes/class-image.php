<?php
/**
 * Image pipeline for generated content.
 *
 * Engines: existing Media Library uploads, 9Router image generation, and
 * Bing Images discovery (the same HTML pattern used by the Amazon module).
 * Every downloaded/generated image is converted to WebP below 100 KB and
 * attached to the Media Library with keyword-derived WordPress metadata and
 * best-effort IPTC keywords.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class ImagePipeline
 */
final class ImagePipeline {

	/**
	 * Allowed image sources. `auto` uses upload → AI → Bing.
	 */
	public const SOURCES = array( 'auto', 'upload', 'ai', 'bing' );

	/** Trim long AI-generated briefs to a stable subject for image engines. */
	public static function short_keyword( string $keyword, int $max = 72 ): string {
		$keyword = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $keyword ) ) ?? '' );
		$keyword = rtrim( $keyword, " \t\n\r\0\x0B.,;:!?" );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $keyword ) <= $max ) {
			return $keyword;
		}
		if ( function_exists( 'mb_substr' ) ) {
			$short = mb_substr( $keyword, 0, $max );
		} else {
			$short = substr( $keyword, 0, $max );
		}
		$space = function_exists( 'mb_strrpos' ) ? mb_strrpos( $short, ' ' ) : strrpos( $short, ' ' );
		if ( false !== $space && $space > 24 ) {
			$short = mb_substr( $short, 0, $space );
		}
		return rtrim( $short, " \t\n\r\0\x0B.,;:!?" );
	}

	/** Cache key for a failed image brief. */
	private static function failure_key( string $keyword ): string {
		return 'lpgen_imgfail_' . md5( self::short_keyword( $keyword ) );
	}

	public static function keyword_failed( string $keyword ): bool {
		return function_exists( 'get_transient' ) && false !== get_transient( self::failure_key( $keyword ) );
	}

	public static function mark_keyword_failed( string $keyword ): void {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::failure_key( $keyword ), 1, self::FAILURE_TTL );
		}
	}

	public static function clear_keyword_failure( string $keyword ): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::failure_key( $keyword ) );
		}
	}

	/** Pick the first keyword that has not failed recently. */
	public static function usable_keyword( string $preferred, array $fallbacks = array() ): string {
		$preferred = self::short_keyword( $preferred );
		if ( '' !== $preferred && ! self::keyword_failed( $preferred ) ) {
			return $preferred;
		}
		foreach ( $fallbacks as $fallback ) {
			$candidate = self::short_keyword( is_scalar( $fallback ) ? (string) $fallback : '' );
			if ( '' !== $candidate && ! self::keyword_failed( $candidate ) ) {
				return $candidate;
			}
		}
		return '' !== $preferred ? $preferred : self::short_keyword( (string) ( $fallbacks[0] ?? 'website' ) );
	}

	/**
	 * Default image model for 9Router. Discover available ids via /v1/models/image.
	 */
	public const DEFAULT_AI_MODEL = 'gemini/gemini-3-pro-image-preview';

	/**
	 * Supported AI image sizes.
	 */
	public const AI_SIZES = array( '1024x1024', '1536x1024', '1024x1536', '1792x1024', '1024x1792' );

	/**
	 * Hard download ceiling to avoid memory exhaustion.
	 */
	public const MAX_DOWNLOAD_BYTES = 10485760;

	/** Cache window for a keyword that failed on every engine. */
	public const FAILURE_TTL = 21600;

	/**
	 * Normalize an image source.
	 *
	 * @param mixed $raw Raw source value.
	 * @return string
	 */
	public static function clean_source( $raw ): string {
		$source = sanitize_key( is_string( $raw ) ? $raw : '' );
		return in_array( $source, self::SOURCES, true ) ? $source : 'auto';
	}

	/**
	 * Normalize an AI image size.
	 *
	 * @param mixed $raw Raw size value.
	 * @return string
	 */
	public static function clean_ai_size( $raw ): string {
		$size = sanitize_key( is_string( $raw ) ? $raw : '' );
		return in_array( $size, self::AI_SIZES, true ) ? $size : '1024x1024';
	}

	/**
	 * Maximum output file size in bytes. Always below 100 KB.
	 *
	 * @param array $settings Plugin settings.
	 * @return int
	 */
	public static function max_bytes( array $settings ): int {
		$kb = max( 10, min( 300, (int) ( $settings['image_max_kb'] ?? 180 ) ) );
		return $kb * 1024;
	}

	/**
	 * Number of images per published item.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $campaign Campaign context.
	 * @return int
	 */
	public static function image_count( array $settings, array $campaign = array() ): int {
		$count = (int) ( $campaign['image_count'] ?? 0 );
		if ( $count <= 0 ) {
			$count = (int) ( $settings['image_count'] ?? 2 );
		}
		return max( 1, min( 6, $count ) );
	}

	/**
	 * Whether image generation is active.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $campaign Campaign context.
	 * @return bool
	 */
	public static function enabled( array $settings, array $campaign = array() ): bool {
		if ( array_key_exists( 'image_enabled', $campaign ) ) {
			return ! empty( $campaign['image_enabled'] );
		}
		return ! isset( $settings['image_enabled'] ) || ! empty( $settings['image_enabled'] );
	}

	/**
	 * Sanitize a CSV list of attachment IDs.
	 *
	 * @param mixed $raw Raw IDs.
	 * @return int[]
	 */
	public static function clean_upload_ids( $raw ): array {
		$parts = is_array( $raw ) ? $raw : Utils::parse_csv( is_string( $raw ) ? $raw : '' );
		$out   = array();
		foreach ( $parts as $part ) {
			$id = absint( $part );
			if ( $id > 0 && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Derive clean image keywords from campaign and item context.
	 *
	 * @param array $context Item/campaign context.
	 * @return string[]
	 */
	public static function keywords( array $context ): array {
		$candidates = array();
		$entities   = isset( $context['entities'] ) && is_array( $context['entities'] ) ? $context['entities'] : array();
		$first      = reset( $entities );
		if ( is_array( $first ) ) {
			$candidates[] = (string) ( $first['term'] ?? '' );
		} elseif ( is_string( $first ) ) {
			$candidates[] = $first;
		}
		$candidates[] = (string) ( $context['tag'] ?? '' );
		$candidates[] = (string) ( $context['title'] ?? '' );
		$candidates[] = (string) ( $context['offer_name'] ?? '' );
		$candidates[] = (string) ( $context['campaign_title'] ?? '' );

		$tags = array();
		foreach ( array( 'content_tags', 'tags' ) as $key ) {
			if ( isset( $context[ $key ] ) && is_array( $context[ $key ] ) ) {
				$tags = array_merge( $tags, $context[ $key ] );
			}
		}
		foreach ( $tags as $tag ) {
			$candidates[] = is_scalar( $tag ) ? (string) $tag : '';
		}

		$out  = array();
		$seen = array();
		foreach ( $candidates as $candidate ) {
			$term = trim( sanitize_text_field( $candidate ) );
			if ( '' === $term ) {
				continue;
			}
			$term = Utils::truncate( $term, 60 );
			$key  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = 1;
			$out[]        = $term;
		}
		if ( empty( $out ) ) {
			$out[] = __( 'offer', 'lp-generator' );
		}
		return array_slice( $out, 0, 6 );
	}

	/**
	 * Resolve the engine order for one campaign.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $campaign Campaign context.
	 * @return string[]
	 */
	public static function source_order( array $settings, array $campaign ): array {
		$source = self::clean_source( $campaign['image_source'] ?? ( $settings['image_source'] ?? 'auto' ) );
		return 'auto' === $source ? array( 'upload', 'ai', 'bing' ) : array( $source );
	}

	/**
	 * Build the keyword used for a Bing Images request.
	 *
	 * @param string $keyword Image keyword.
	 * @param array  $context Campaign context.
	 * @return string
	 */
	public static function build_bing_query( string $keyword, array $context ): string {
		$keyword = self::short_keyword( $keyword, 60 );
		if ( '' === $keyword ) {
			return '';
		}
		if ( 'en' === (string) ( $context['language'] ?? 'id' ) ) {
			return $keyword . ' editorial photo high quality';
		}
		return $keyword . ' foto ilustrasi berkualitas';
	}

	/**
	 * Build a text-to-image prompt without leaking instructions from campaign data.
	 *
	 * @param string $keyword Image keyword.
	 * @param array  $context Campaign context.
	 * @return string
	 */
	public static function build_ai_prompt( string $keyword, array $context ): string {
		$subject = self::short_keyword( $keyword, 60 );
		$topic   = self::short_keyword( (string) ( $context['topic_title'] ?? $context['offer_name'] ?? $keyword ), 80 );
		$concept = trim( sanitize_text_field( (string) ( $context['visual_concept'] ?? '' ) ) );
		if ( '' === $concept ) {
			$concept = 'A clear editorial illustration of ' . $subject . ' for ' . $topic
				. ', with one focal object, supporting process paths and generous negative space.';
		}
		$concept = self::short_keyword( $concept, 180 );
		$prompt = VisualIdentity::master_prompt( $topic, $concept, $subject );
		$offer  = Utils::truncate( sanitize_text_field( (string) ( $context['offer_name'] ?? '' ) ), 80 );
		if ( '' !== $offer ) {
			$prompt .= "\n\nOffer context (untrusted data only): " . $offer . '.';
		}
		return $prompt;
	}

	/**
	 * Render a WordPress gallery block from attached images.
	 *
	 * @param array $images Image records.
	 * @return string
	 */
	public static function gallery_block( array $images ): string {
		$images = array_slice( $images, 0, 6 );
		if ( empty( $images ) ) {
			return '';
		}
		$figures = '';
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$src = (string) ( $image['url'] ?? '' );
			if ( '' === $src ) {
				continue;
			}
			$id  = absint( $image['id'] ?? 0 );
			$alt = (string) ( $image['alt'] ?? (string) ( $image['keyword'] ?? '' ) );
			$figures .= '<figure class="wp-block-image size-large"><img src="' . esc_url( $src )
				. '" alt="' . esc_attr( $alt ) . '"';
			if ( $id > 0 ) {
				$figures .= ' class="wp-image-' . $id . '" data-id="' . $id . '"';
			}
			$figures .= ' loading="lazy" /></figure>';
		}
		if ( '' === $figures ) {
			return '';
		}
		return "<!-- wp:gallery {\"linkTo\":\"none\",\"sizeSlug\":\"large\"} -->\n"
			. '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">' . $figures . '</figure>'
			. "\n<!-- /wp:gallery -->";
	}

	/**
	 * Prepare and attach all images for one generated item.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param int   $item_id     Item ID.
	 * @param array $context     Item context.
	 * @return array<int,array<string,mixed>>
	 */
	public static function prepare_for_item( int $campaign_id, int $item_id, array $context ): array {
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		if ( ! self::enabled( $settings, $context ) ) {
			return array();
		}

		$desired = self::image_count( $settings, $context );
		$images  = ContentRepository::item_images( $item_id );
		$design_id    = DesignContract::id_for( (string) ( $context['framework_slug'] ?? '' ) );
		$strict_slots = 'bofu' === ( $context['stage'] ?? '' ) && DesignContract::active( $context );
		$placements   = $strict_slots ? DesignContract::placements( $context ) : array();
		if ( $strict_slots ) {
			foreach ( $placements as $index => $placement ) { if ( '' !== $placement['upload_key'] && ! empty( $context[ $placement['upload_key'] ] ) ) { $desired = max( $desired, $index + 1 ); } }
			$desired = min( $desired, count( $placements ) );
			$images = array_values( array_filter( $images, static fn( array $image ): bool => $design_id === ( $image['design_id'] ?? '' ) && (int) ( $image['campaign_id'] ?? 0 ) === $campaign_id && ( $image['campaign_fingerprint'] ?? '' ) === Frameworks::fingerprint( $context ) ) );
			DesignContract::validate_images( $images, $context );
		}
		if ( count( $images ) >= $desired ) {
			return array_slice( $images, 0, $desired );
		}

		$keywords    = self::keywords( $context );
		$fw_keywords = isset( $context['framework_image_keywords'] ) && is_array( $context['framework_image_keywords'] )
			? array_values( array_filter( array_map( 'strval', $context['framework_image_keywords'] ) ) ) : array();
		$fw_roles    = isset( $context['framework_image_roles'] ) && is_array( $context['framework_image_roles'] )
			? array_values( array_filter( array_map( 'strval', $context['framework_image_roles'] ) ) ) : array();
		$fw_hints    = isset( $context['framework_hints'] ) && is_array( $context['framework_hints'] )
			? array_values( array_filter( array_map( 'strval', $context['framework_hints'] ) ) ) : array();
		$order        = self::source_order( $settings, $context );
		$visual_slug  = sanitize_title( (string) ( $context['topic_title'] ?? $context['offer_name'] ?? ( $keywords[0] ?? 'lpgen-image' ) ) );
		$visual_slug  = '' !== $visual_slug ? $visual_slug : 'lpgen-image';
		$visual_path  = VisualIdentity::filename( $visual_slug, 'hero' );
		$base         = VisualIdentity::basename( $visual_slug, 'hero' );

		for ( $i = $strict_slots ? 0 : count( $images ); $i < $desired; $i++ ) {
			if ( $strict_slots && in_array( $placements[ $i ]['image_slot'], array_column( $images, 'slot_id' ), true ) ) { continue; }
			if ( $strict_slots && '' === trim( $placements[ $i ]['keyword'] ) ) { Database::log( 'warning', 'Slot gambar dilewati karena brief kosong: ' . $placements[ $i ]['image_slot'], $campaign_id ); continue; }
			$raw_keyword = $strict_slots ? $placements[ $i ]['keyword'] : ( $fw_keywords[ $i ] ?? $keywords[ $i % count( $keywords ) ] );
			$keyword     = self::usable_keyword( (string) $raw_keyword, $keywords );
			if ( '' === $keyword ) {
				Database::log( 'warning', sprintf( 'Semua keyword gambar baru saja gagal; memakai placeholder untuk item %d.', $item_id ), $campaign_id );
				$keyword = self::short_keyword( (string) $raw_keyword ) ?: 'website';
			}
			$role    = $strict_slots ? $placements[ $i ]['role'] : ( $fw_roles[ $i ] ?? '' );
			$hint    = $strict_slots ? $placements[ $i ]['hint'] : ( $fw_hints[ $i ] ?? '' );
			$found   = null;
			$source_used = '';
			foreach ( $order as $source ) {
				$candidate = self::fetch( $source, $keyword, $i, $settings, $context );
				if ( is_array( $candidate ) && ! empty( $candidate['file'] ) && is_file( (string) $candidate['file'] ) ) {
					$found = $candidate;
					$source_used = (string) $source;
					break;
				}
			}
			if ( null !== $found ) {
				Database::log( 'info', sprintf( 'Image %d untuk item %d dari engine %s (hint: %s)', $i + 1, $item_id, $source_used, '' !== $hint ? $hint : 'auto' ), $campaign_id );
			}
			if ( null === $found ) {
				self::mark_keyword_failed( $keyword );
				Database::log( 'warning', sprintf( 'Image fetch failed for keyword "%s"; using local placeholder', self::short_keyword( $keyword ) ), $campaign_id );
				$placeholder = self::placeholder_file( $keyword, $context );
				if ( '' === $placeholder ) {
					continue;
				}
				$found       = array(
					'file'      => $placeholder,
					'temporary' => true,
					'source'    => 'placeholder',
					'alt'       => self::short_keyword( $keyword ),
				);
				$source_used = 'placeholder';
			}

			$meta = self::metadata( $keyword, $settings, $context );
			$meta['source'] = $source_used;
			$meta['visual_status'] = 'placeholder' === $source_used ? 'placeholder' : 'generated';
			if ( ! empty( $found['alt'] ) ) { $meta['alt'] = sanitize_text_field( (string) $found['alt'] ); }
			$meta['visual_path']  = $visual_path;
			$meta['visual_style'] = VisualIdentity::STYLE;
			$enforce_ratio = in_array( $source_used, array( 'ai', 'bing' ), true );
			$webp = self::to_webp( (string) $found['file'], $base . '-' . ( $i + 1 ), $settings, $meta, $enforce_ratio );
			if ( is_object( $webp ) && method_exists( $webp, 'get_error_message' ) ) {
				Database::log( 'warning', 'WebP conversion failed: ' . $webp->get_error_message(), $campaign_id );
				self::cleanup( $found );
				continue;
			}
			$record = self::attach( (string) $webp, $meta, $role, $hint );
			self::cleanup( $found );
			if ( ! empty( $record ) ) {
				if ( $strict_slots ) {
					$record['slot_id'] = $placements[ $i ]['image_slot'];
					$record['section_id'] = $placements[ $i ]['id'];
					$record['design_id'] = $design_id;
					$record['campaign_id'] = $campaign_id;
					$record['campaign_fingerprint'] = Frameworks::fingerprint( $context );
				}
				$images[] = $record;
			}
		}

		ContentRepository::save_item_images( $item_id, $images );
		$specs = array();
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) { continue; }
			$status = (string) ( $image['visual_status'] ?? 'generated' );
			$specs[] = array(
				'slot_id'      => sanitize_key( (string) ( $image['slot_id'] ?? '' ) ),
				'id'           => absint( $image['id'] ?? 0 ),
				'source'       => sanitize_key( (string) ( $image['source'] ?? '' ) ),
				'status'       => 'placeholder' === $status ? 'placeholder' : 'completed',
				'keyword'      => sanitize_text_field( (string) ( $image['keyword'] ?? '' ) ),
				'visual_path'  => sanitize_text_field( (string) ( $image['visual_path'] ?? '' ) ),
				'checked_at'   => gmdate( 'Y-m-d H:i:s' ),
			);
		}
		update_post_meta( $item_id, '_lpgen_image_specs', wp_json_encode( $specs ) );
		if ( ! empty( $images ) ) {
			Database::log( 'info', sprintf( 'Prepared %d keyword images for item %d', count( $images ), $item_id ), $campaign_id );
		}
		return $images;
	}

	/**
	 * Fetch one source image.
	 *
	 * @param string $source   upload|ai|bing.
	 * @param string $keyword  Image keyword.
	 * @param int    $index    Zero-based image index.
	 * @param array  $settings Plugin settings.
	 * @param array  $context  Campaign context.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function fetch( string $source, string $keyword, int $index, array $settings, array $context ) {
		switch ( $source ) {
			case 'upload':
				return self::fetch_upload( $index, $settings, $context );
			case 'ai':
				return self::fetch_ai( $keyword, $settings, $context );
			case 'bing':
				return self::fetch_bing( $keyword, $settings, $context );
			default:
				return new \WP_Error( 'lpgen_unknown_image_source', 'unknown_image_source' );
		}
	}

	/**
	 * Use an already uploaded Media Library image.
	 *
	 * @param int   $index    Zero-based image index.
	 * @param array $settings Plugin settings.
	 * @param array $context  Campaign context.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function fetch_upload( int $index, array $settings, array $context ) {
		$ids = self::clean_upload_ids( $context['image_upload_ids'] ?? ( $settings['image_upload_ids'] ?? array() ) );
		if ( empty( $ids ) && !( 'bofu' === ( $context['stage'] ?? '' ) && DesignContract::active( $context ) ) ) {
			return new \WP_Error( 'lpgen_no_upload', 'no_uploaded_images' );
		}
		$strict = 'bofu' === ( $context['stage'] ?? '' ) && DesignContract::active( $context );
		if ( $strict ) {
			$placements = DesignContract::placements( $context );
			$key        = (string) ( $placements[ $index ]['upload_key'] ?? '' );
			$id         = '' !== $key ? absint( $context[ $key ] ?? 0 ) : 0;
			if ( ! $id ) { return new \WP_Error( 'lpgen_slot_upload_missing', 'Belum ada upload untuk slot ' . ( $placements[ $index ]['id'] ?? '' ) . ' ini.' ); }
		} else { $id = $ids[ $index % count( $ids ) ]; }
		$file = get_attached_file( $id );
		if ( ! is_string( $file ) || '' === $file || ! is_file( $file ) ) {
			return new \WP_Error( 'lpgen_upload_missing', 'uploaded_file_missing' );
		}
		$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $file ) : '';
		if ( '' === (string) $mime ) {
			$info = @getimagesize( $file );
			$mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		}
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return new \WP_Error( 'lpgen_upload_type', 'unsupported_upload_type' );
		}
		return array(
			'file'      => $file,
			'temporary' => false,
			'source'    => 'upload',
			'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Generate one image through 9Router /v1/images/generations.
	 *
	 * @param string $keyword  Image keyword.
	 * @param array  $settings Plugin settings.
	 * @param array  $context  Campaign context.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function fetch_ai( string $keyword, array $settings, array $context ) {
		$base = rtrim( trim( (string) ( $settings['ninerouter_url'] ?? '' ) ), '/' );
		if ( '' === $base ) {
			return new \WP_Error( 'lpgen_ai_no_router', '9router_url_missing' );
		}
		$url  = $base . '/v1/images/generations?response_format=binary';
		$body = array(
			'model'  => Utils::clean_text( $settings['image_ai_model'] ?? self::DEFAULT_AI_MODEL ) ?: self::DEFAULT_AI_MODEL,
			'prompt' => self::build_ai_prompt( $keyword, $context ),
			'n'      => 1,
			'size'   => self::clean_ai_size( $settings['image_ai_size'] ?? '1536x1024' ),
		);
		$key     = trim( (string) ( $settings['ninerouter_key'] ?? '' ) );
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		$timeout = max( 30, min( 300, (int) ( $settings['image_ai_timeout'] ?? 120 ) ) );
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'lpgen_ai_request_failed', $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'lpgen_ai_bad_status', 'ai_http_' . $code );
		}
		$raw  = (string) wp_remote_retrieve_body( $response );
		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( '' === trim( $raw ) ) {
			return new \WP_Error( 'lpgen_ai_empty', 'empty_ai_response' );
		}
		if ( false !== strpos( $type, 'json' ) || '{' === substr( ltrim( $raw ), 0, 1 ) ) {
			$json = json_decode( $raw, true );
			$item = isset( $json['data'][0] ) && is_array( $json['data'][0] ) ? $json['data'][0] : array();
			if ( ! empty( $item['b64_json'] ) ) {
				$bytes = base64_decode( (string) $item['b64_json'], true );
				if ( false === $bytes ) {
					return new \WP_Error( 'lpgen_ai_bad_base64', 'invalid_b64_json' );
				}
				$file = self::write_temp( $bytes );
				return false === $file ? new \WP_Error( 'lpgen_temp_failed', 'temp_write_failed' ) : array(
					'file'      => $file,
					'temporary' => true,
					'source'    => 'ai',
				);
			}
			$image_url = (string) ( $item['url'] ?? '' );
			if ( '' === $image_url ) {
				return new \WP_Error( 'lpgen_ai_no_image', 'ai_response_has_no_image' );
			}
			return self::download( $image_url, $settings, $timeout, 'ai' );
		}
		$file = self::write_temp( $raw );
		return false === $file ? new \WP_Error( 'lpgen_temp_failed', 'temp_write_failed' ) : array(
			'file'      => $file,
			'temporary' => true,
			'source'    => 'ai',
		);
	}

	/**
	 * Discover one image through Bing Images.
	 *
	 * @param string $keyword  Image keyword.
	 * @param array  $settings Plugin settings.
	 * @param array  $context  Campaign context.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function fetch_bing( string $keyword, array $settings, array $context ) {
		$query = self::build_bing_query( $keyword, $context );
		if ( '' === $query ) {
			return new \WP_Error( 'lpgen_bing_query', 'empty_bing_query' );
		}
		$url      = Amazon::build_search_url( $query );
		$timeout  = max( 5, min( 30, (int) ( $settings['image_bing_timeout'] ?? ( $settings['amazon_timeout'] ?? 15 ) ) ) );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; LPG/1.2; +' . home_url( '/' ) . ')',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'lpgen_bing_search_failed', 'bing_search_failed' );
		}
		$html        = (string) wp_remote_retrieve_body( $response );
		$candidates  = Amazon::parse_images( $html );
		if ( empty( $candidates ) ) {
			return new \WP_Error( 'lpgen_bing_empty', 'bing_no_images' );
		}
		foreach ( array_slice( $candidates, 0, 8 ) as $candidate ) {
			$image_url = (string) ( $candidate['image'] ?? '' );
			if ( ! self::safe_image_url( $image_url ) ) {
				continue;
			}
			$result = self::download( $image_url, $settings, $timeout, 'bing' );
			if ( is_array( $result ) ) {
				return $result;
			}
		}
		return new \WP_Error( 'lpgen_bing_download_failed', 'bing_download_failed' );
	}

	/**
	 * Download a remote image with SSRF and size protections.
	 *
	 * @param string $url      Image URL.
	 * @param array  $settings Plugin settings.
	 * @param int    $timeout  Timeout in seconds.
	 * @param string $source   Source label.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function download( string $url, array $settings, int $timeout, string $source ) {
		if ( ! self::safe_image_url( $url ) ) {
			return new \WP_Error( 'lpgen_unsafe_image_url', 'unsafe_image_url' );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => max( 5, min( 30, $timeout ) ),
				'limit_response_size' => self::MAX_DOWNLOAD_BYTES,
				'headers'             => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; LPG/1.2; +' . home_url( '/' ) . ')',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'lpgen_image_download_failed', 'image_download_failed' );
		}
		$bytes = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $bytes ) < 512 || strlen( $bytes ) > self::MAX_DOWNLOAD_BYTES ) {
			return new \WP_Error( 'lpgen_image_size_invalid', 'image_size_invalid' );
		}
		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( '' !== $type && false === strpos( $type, 'image/' ) ) {
			return new \WP_Error( 'lpgen_image_content_type', 'not_an_image' );
		}
		$file = self::write_temp( $bytes );
		return false === $file ? new \WP_Error( 'lpgen_temp_failed', 'temp_write_failed' ) : array(
			'file'      => $file,
			'temporary' => true,
			'source'    => $source,
		);
	}

	/**
	 * Validate that a URL is http(s) and does not point at local/private hosts.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	public static function safe_image_url( string $url ): bool {
		if ( 1 !== preg_match( '#^https?://#i', $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		if ( false === wp_http_validate_url( $url ) ) {
			return false;
		}
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host || 'localhost' === $host || preg_match( '#\.(local|internal|test)$#', $host ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		$ip = gethostbyname( $host );
		if ( $ip !== $host && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return true;
	}

	/**
	 * Write bytes to a unique temporary file.
	 *
	 * @param string $bytes Raw bytes.
	 * @return string|false
	 */
	/** Brand-consistent local WebP placeholder when every engine fails. */
	public static function placeholder_file( string $keyword, array $context = array() ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagewebp' ) || ! function_exists( 'imagefill' ) ) {
			return '';
		}
		$width  = 1280;
		$height = 720;
		$image  = imagecreatetruecolor( $width, $height );
		if ( false === $image ) {
			return '';
		}
		$navy  = self::allocate_color( $image, VisualIdentity::BACKGROUND );
		$orange = self::allocate_color( $image, VisualIdentity::ACCENT );
		$gold   = self::allocate_color( $image, VisualIdentity::GOLD );
		imagefill( $image, 0, 0, $navy );
		// Abstract editorial composition: focal block right, supporting lines left. No text.
		imagefilledrectangle( $image, 754, 104, 1186, 616, $orange );
		imagefilledellipse( $image, 970, 360, 268, 268, $gold );
		imagefilledrectangle( $image, 88, 512, 660, 528, $orange );
		imagefilledrectangle( $image, 88, 560, 470, 573, $gold );
		imagefilledrectangle( $image, 88, 600, 560, 610, $gold );
		ob_start();
		$ok = imagewebp( $image, null, 82 );
		$bytes = ob_get_clean();
		imagedestroy( $image );
		if ( false === $ok || ! is_string( $bytes ) || '' === $bytes ) {
			return '';
		}
		return self::write_temp( $bytes );
	}

	private static function allocate_color( $image, string $hex ) {
		$hex = ltrim( $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );
		$color = imagecolorallocate( $image, (int) $r, (int) $g, (int) $b );
		return false === $color ? imagecolorallocate( $image, 255, 255, 255 ) : $color;
	}

	private static function write_temp( string $bytes ) {
		$file = tempnam( sys_get_temp_dir(), 'lpgen-' );
		if ( false === $file || false === file_put_contents( $file, $bytes ) ) {
			return false;
		}
		return $file;
	}

	/**
	 * Convert an image to WebP under the configured size limit.
	 *
	 * @param string $source   Source file.
	 * @param string $basename Safe basename.
	 * @param array  $settings Plugin settings.
	 * @param array  $meta     Resolved attachment metadata.
	 * @return string|\WP_Error
	 */
	private static function to_webp( string $source, string $basename, array $settings, array $meta = array(), bool $enforce_ratio = false ) {
		$upload = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array();
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return new \WP_Error( 'lpgen_upload_dir', 'upload_dir_unavailable' );
		}
		$subdir = gmdate( 'Y/m' );
		$dir    = trailingslashit( $upload['basedir'] ) . $subdir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'lpgen_upload_mkdir', 'cannot_create_upload_dir' );
		}
		$limit     = self::max_bytes( $settings );
		$max_width = max( 640, min( 2560, (int) ( $settings['image_max_width'] ?? 1280 ) ) );
		$quality   = 90;
		for ( $round = 0; $round < 12; $round++ ) {
			$path = self::convert_attempt( $source, $dir, $basename, $max_width, $quality, $enforce_ratio );
			if ( is_string( $path ) ) {
				if ( ! empty( $settings['image_iptc_enabled'] ) && ! empty( $meta['keyword'] ) ) {
					self::inject_iptc( $path, (string) $meta['keyword'], (string) ( $meta['caption'] ?? '' ) );
				}
				$bytes = is_file( $path ) ? (int) filesize( $path ) : $limit + 1;
				if ( $bytes <= $limit ) {
					return $path;
				}
				@unlink( $path );
			}
			if ( $quality > 40 ) {
				$quality -= 10;
			} else {
				$max_width = max( 320, (int) round( $max_width * 0.85 ) );
				$quality   = 80;
			}
		}
		return new \WP_Error( 'lpgen_webp_failed', 'cannot_encode_webp_under_limit' );
	}

	/**
	 * One WebP encoding attempt using WP_Image_Editor, with GD/Imagick fallback.
	 *
	 * @param string $source    Source file.
	 * @param string $dir       Destination directory.
	 * @param string $basename  Safe basename.
	 * @param int    $max_width Maximum width.
	 * @param int    $quality   Encoding quality.
	 * @return string|\WP_Error
	 */
	private static function convert_attempt( string $source, string $dir, string $basename, int $max_width, int $quality, bool $enforce_ratio = false ) {
		$name = sanitize_file_name( $basename . '-w' . $max_width . '-q' . $quality . '-' . wp_generate_password( 6, false, false ) . '.webp' );
		$name = wp_unique_filename( $dir, $name );
		$path = $dir . '/' . $name;

		if ( $enforce_ratio && self::convert_direct( $source, $path, $max_width, $quality, true ) ) {
			return $path;
		}

		$editor = wp_get_image_editor( $source );
		if ( ! is_wp_error( $editor ) ) {
			$size = $editor->get_size();
			if ( ! is_wp_error( $size ) ) {
				$width  = (int) ( $size['width'] ?? 0 );
				$height = (int) ( $size['height'] ?? 0 );
				if ( $width > 0 && $height > 0 ) {
					if ( $enforce_ratio ) {
						$crop = VisualIdentity::crop_size( $width, $height, $max_width );
						$editor->resize( $crop['target_w'], $crop['target_h'], true );
					} elseif ( $width > $max_width ) {
						$height = max( 1, (int) round( $height * $max_width / $width ) );
						$editor->resize( $max_width, $height, true );
					}
				}
				$editor->set_quality( $quality );
				$saved = $editor->save( $path, 'image/webp' );
				if ( ! is_wp_error( $saved ) ) {
					$file = is_array( $saved ) && ! empty( $saved['path'] ) ? (string) $saved['path'] : $path;
					return is_file( $file ) ? $file : new \WP_Error( 'lpgen_webp_save', 'saved_file_missing' );
				}
			}
		}

		$direct = self::convert_direct( $source, $path, $max_width, $quality, $enforce_ratio );
		return $direct ? $path : new \WP_Error( 'lpgen_webp_unsupported', 'webp_encoder_unavailable' );
	}

	/**
	 * Direct Imagick/GD encoder fallback.
	 *
	 * @param string $source    Source file.
	 * @param string $path      Destination path.
	 * @param int    $max_width Maximum width.
	 * @param int    $quality   Encoding quality.
	 * @return bool
	 */
	private static function convert_direct( string $source, string $path, int $max_width, int $quality, bool $enforce_ratio = false ): bool {
		$info   = @getimagesize( $source );
		$width  = is_array( $info ) ? (int) ( $info[0] ?? 0 ) : 0;
		$height = is_array( $info ) ? (int) ( $info[1] ?? 0 ) : 0;
		if ( $width <= 0 || $height <= 0 ) {
			return false;
		}
		if ( $enforce_ratio ) {
			$crop        = VisualIdentity::crop_size( $width, $height, $max_width );
			$target_width  = (int) $crop['target_w'];
			$target_height = (int) $crop['target_h'];
		} else {
			$target_width  = min( $width, $max_width );
			$target_height = max( 1, (int) round( $height * $target_width / $width ) );
		}

		if ( class_exists( 'Imagick' ) ) {
			try {
				$image = new \Imagick( $source );
				if ( $enforce_ratio ) {
					$image->cropThumbnailImage( $target_width, $target_height );
					$image->modulateImage( 100, 105, 100 );
					$image->contrastImage( 5 );
				} else {
					$image->resizeImage( $target_width, $target_height, \Imagick::FILTER_LANCZOS, 1 );
				}
				$image->setImageFormat( 'webp' );
				$image->setImageCompressionQuality( $quality );
				$ok = $image->writeImage( $path );
				$image->clear();
				return (bool) $ok;
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		if ( ! function_exists( 'imagewebp' ) || ! function_exists( 'imagescale' ) ) {
			return false;
		}
		$mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		switch ( $mime ) {
			case 'image/jpeg':
				$image = function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $source ) : false;
				break;
			case 'image/png':
				$image = function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $source ) : false;
				break;
			case 'image/webp':
				$image = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $source ) : false;
				break;
			case 'image/gif':
				$image = function_exists( 'imagecreatefromgif' ) ? @imagecreatefromgif( $source ) : false;
				break;
			default:
				$image = false;
		}
		if ( false === $image ) {
			return false;
		}
		if ( function_exists( 'imagepalettetotruecolor' ) ) {
			imagepalettetotruecolor( $image );
		}
		if ( $enforce_ratio && function_exists( 'imagecrop' ) ) {
			$crop = VisualIdentity::crop_size( $width, $height, $max_width );
			$cut  = imagecrop(
				$image,
				array(
					'x'      => (int) $crop['x'],
					'y'      => (int) $crop['y'],
					'width'  => (int) $crop['width'],
					'height' => (int) $crop['height'],
				)
			);
			if ( false !== $cut ) {
				imagedestroy( $image );
				$image = $cut;
			}
		}
		$scaled = imagescale( $image, $target_width, $target_height );
		if ( false === $scaled ) {
			imagedestroy( $image );
			return false;
		}
		if ( $enforce_ratio && function_exists( 'imagefilter' ) ) {
			imagefilter( $scaled, IMG_FILTER_CONTRAST, -5 );
			imagefilter( $scaled, IMG_FILTER_COLORIZE, 0, 0, 8, 12 );
		}
		imagealphablending( $scaled, false );
		imagesavealpha( $scaled, true );
		$ok = imagewebp( $scaled, $path, $quality );
		imagedestroy( $scaled );
		imagedestroy( $image );
		return (bool) $ok;
	}

	/**
	 * Resolve keyword-based metadata for one image.
	 *
	 * @param string $keyword  Focus keyword.
	 * @param array  $settings Plugin settings.
	 * @param array  $context  Campaign context.
	 * @return array<string,string>
	 */
	private static function metadata( string $keyword, array $settings, array $context ): array {
		$tag  = trim( (string) ( $context['tag'] ?? '' ) );
		$tags = isset( $context['content_tags'] ) && is_array( $context['content_tags'] ) ? $context['content_tags'] : array();
		$data = array(
			'keyword' => $keyword,
			'title'   => (string) ( $context['title'] ?? '' ),
			'tag'     => '' !== $tag ? $tag : (string) ( $tags[0] ?? '' ),
			'brand'   => (string) ( $context['brand'] ?? '' ),
			'city'    => (string) ( $context['city'] ?? '' ),
			'phone'   => (string) ( $context['phone'] ?? '' ),
			'offer'   => (string) ( $context['offer_name'] ?? '' ),
			'promise' => (string) ( $context['promise'] ?? '' ),
		);
		$title = self::template( (string) ( $settings['image_title_template'] ?? '{{keyword}}' ), $data, 80 );
		$alt   = self::template( (string) ( $settings['image_alt_template'] ?? '{{keyword}}' ), $data, 120 );
		if ( '' === $title ) {
			$title = $keyword;
		}
		if ( '' === $alt ) {
			$alt = $keyword;
		}
		return array(
			'keyword'     => $keyword,
			'title'       => $title,
			'alt'         => $alt,
			'caption'     => self::template( (string) ( $settings['image_caption_template'] ?? '{{keyword}} — {{offer}}' ), $data, 155 ),
			'description' => self::template( (string) ( $settings['image_description_template'] ?? '{{keyword}}: {{promise}} {{city}}' ), $data, 250 ),
		);
	}

	/**
	 * Attach the WebP file with keyword-derived metadata.
	 *
	 * @param string $file WebP file path.
	 * @param array  $meta Resolved attachment metadata.
	 * @param string $role Image role from generated framework.
	 * @param string $hint Section hint from generated framework.
	 * @return array<string,mixed>
	 */
	private static function attach( string $file, array $meta, string $role = '', string $hint = '' ): array {
		$keyword = (string) ( $meta['keyword'] ?? '' );
		$id      = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/webp',
				'post_title'     => (string) ( $meta['title'] ?? $keyword ),
				'post_excerpt'   => (string) ( $meta['caption'] ?? '' ),
				'post_content'   => (string) ( $meta['description'] ?? '' ),
				'post_status'    => 'inherit',
			),
			$file,
			0,
			true
		);
		if ( is_wp_error( $id ) || (int) $id <= 0 ) {
			return array();
		}
		$id  = (int) $id;
		$url = (string) wp_get_attachment_url( $id );
		$image_file = get_attached_file( $id );
		$metadata_file = is_string( $image_file ) && '' !== $image_file ? $image_file : $file;
		$image_php = ABSPATH . 'wp-admin/includes/image.php';
		if ( is_file( $image_php ) ) {
			require_once $image_php; // phpcs:ignore WPCompatibility.CheckFileExistence.NonWP
		}
		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			$attachment_meta = wp_generate_attachment_metadata( $id, $metadata_file );
			if ( is_array( $attachment_meta ) ) {
				update_post_meta( $id, '_wp_attachment_metadata', $attachment_meta );
			}
		}
		update_post_meta( $id, '_wp_attachment_image_alt', (string) ( $meta['alt'] ?? $keyword ) );
		update_post_meta( $id, '_lpgen_image_keyword', sanitize_text_field( $keyword ) );
		if ( ! empty( $meta['visual_path'] ) ) {
			update_post_meta( $id, '_lpgen_visual_path', sanitize_text_field( (string) $meta['visual_path'] ) );
		}
		if ( ! empty( $meta['visual_style'] ) ) {
			update_post_meta( $id, '_lpgen_visual_style', sanitize_text_field( (string) $meta['visual_style'] ) );
		}
		if ( ! empty( $meta['visual_status'] ) ) {
			update_post_meta( $id, '_lpgen_visual_status', sanitize_key( (string) $meta['visual_status'] ) );
		}

		return array(
			'id'           => $id,
			'width' => absint( $attachment_meta['width'] ?? 0 ),
			'height' => absint( $attachment_meta['height'] ?? 0 ),
			'url'          => $url,
			'alt'          => (string) ( $meta['alt'] ?? $keyword ),
			'keyword'      => $keyword,
			'role'         => sanitize_key( $role ),
			'section_hint' => sanitize_key( $hint ),
			'visual_path'   => (string) ( $meta['visual_path'] ?? '' ),
			'visual_style'  => (string) ( $meta['visual_style'] ?? '' ),
			'visual_status' => (string) ( $meta['visual_status'] ?? 'generated' ),
			'source'        => (string) ( $meta['source'] ?? '' ),
		);
	}

	/**
	 * Render a metadata template and trim leftover separators.
	 *
	 * @param string $template Template.
	 * @param array  $data     Placeholder values.
	 * @param int    $max      Maximum length.
	 * @return string
	 */
	private static function template( string $template, array $data, int $max ): string {
		$out = Utils::render_template( $template, $data, $max );
		return trim( $out, " \t\n\r\0\x0B—-|" );
	}

	/**
	 * Best-effort IPTC keyword injection. Requires Imagick with WebP support.
	 *
	 * @param string $file    WebP file.
	 * @param string $keyword Focus keyword.
	 * @param string $caption Caption text.
	 * @return bool
	 */
	private static function inject_iptc( string $file, string $keyword, string $caption ): bool {
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}
		try {
			$image = new \Imagick( $file );
			$image->setImageProperty( 'Iptc.Application2.ObjectName', Utils::truncate( $keyword, 64 ) );
			$image->setImageProperty( 'Iptc.Application2.Keywords', Utils::truncate( $keyword, 64 ) );
			if ( '' !== $caption ) {
				$image->setImageProperty( 'Iptc.Application2.Caption', Utils::truncate( $caption, 200 ) );
			}
			$ok = $image->writeImage( $file );
			$image->clear();
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Remove a temporary source file after conversion.
	 *
	 * @param array $candidate Fetch result.
	 */
	private static function cleanup( array $candidate ): void {
		if ( ! empty( $candidate['temporary'] ) && ! empty( $candidate['file'] ) && is_file( (string) $candidate['file'] ) ) {
			@unlink( (string) $candidate['file'] );
		}
	}
}