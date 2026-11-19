<?php
/** Local funnel policy, entity validation and site-wide duplicate checks. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;

final class FunnelContent {
	public static function normalize( string $text ): string {
		$text = html_entity_decode( strip_tags( preg_replace( '/<!--.*?-->/s', ' ', $text ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', mb_strtolower( $text, 'UTF-8' ) ) );
	}

	public static function clean_path( string $path ): string {
		$parts = explode( '/', trim( $path, " /\t\r\n" ) );
		return implode( '/', array_filter( array_map( 'sanitize_title', $parts ) ) );
	}

	public static function entities( $raw ): array {
		$entities = array();
		foreach ( is_array( $raw ) ? $raw : array() as $entity ) {
			if ( ! is_array( $entity ) || ! is_string( $entity['term'] ?? null ) ) { continue; }
			$term = trim( sanitize_text_field( $entity['term'] ) );
			$key = self::normalize( $term );
			if ( '' === $key || mb_strlen( $term ) > 80 ) { continue; }
			$entities[ $key ] = array( 'term' => $term, 'type' => sanitize_key( (string) ( $entity['type'] ?? 'topic' ) ), 'notes' => sanitize_text_field( (string) ( $entity['notes'] ?? '' ) ) );
		}
		if ( count( $entities ) < 8 || count( $entities ) > 12 ) {
			throw new \RuntimeException( 'Konten membutuhkan 8–12 entitas keyword AI yang unik.' );
		}
		return array_values( $entities );
	}

	/** Five-word shingles detect copied passages despite changes of place names. */
	public static function similar( string $left, string $right ): bool {
		$a = self::normalize( $left ); $b = self::normalize( $right );
		if ( '' === $a || '' === $b ) { return false; }
		if ( $a === $b ) { return true; }
		$make = static function ( string $text ): array {
			$words = explode( ' ', $text ); $set = array();
			for ( $i = 0; $i <= count( $words ) - 5; ++$i ) {
				$set[ implode( ' ', array_slice( $words, $i, 5 ) ) ] = true;
			}
			return $set;
		};
		$x = $make( $a ); $y = $make( $b );
		$small = min( count( $x ), count( $y ) );
		return $small >= 35 && count( array_intersect_key( $x, $y ) ) / $small >= 0.65;
	}

	/** Scan all saved items and ordinary posts/pages, including other campaigns. */
	public static function corpus(): \Generator {
		$offset = 0;
		do {
			$posts = get_posts( array( 'post_type' => array( 'lpgen_item', 'post', 'page' ), 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => 100, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true ) );
			foreach ( $posts as $post ) { yield $post; }
			$offset += 100;
		} while ( count( $posts ) === 100 );
	}

	public static function titles( array $exclude = array() ): array {
		$titles = array();
		foreach ( self::corpus() as $post ) {
			if ( in_array( (int) $post->ID, $exclude, true ) || '' === trim( $post->post_content ) ) { continue; }
			$titles[] = $post->post_title;
			if ( count( $titles ) >= 150 ) { break; }
		}
		return array_values( array_unique( $titles ) );
	}

	public static function assert_unique( string $title, string $content, array $exclude = array(), bool $topic = false ): void {
		foreach ( TopicMemory::search( $title, 3 ) as $match ) {
			$memory_id = (int) ( $match['item_id'] ?? 0 );
			if ( ! empty( $match['duplicate_risk'] ) && ! in_array( $memory_id, $exclude, true ) ) {
				throw new \RuntimeException( sprintf( 'Topik terlalu mirip dengan memori #%d (%s). Pilih sudut pembahasan baru.', $memory_id, (string) $match['keyword'] ) );
			}
		}
		foreach ( self::corpus() as $post ) {
			if ( in_array( (int) $post->ID, $exclude, true ) || '' === trim( $post->post_content ) ) { continue; }
			$same_title = self::normalize( $title ) === self::normalize( $post->post_title );
			$is_topic = 'lpgen_item' === $post->post_type && 'topic' === get_post_meta( $post->ID, '_lpgen_kind', true );
			if ( $same_title || ( ! $topic && ! $is_topic && self::similar( $content, $post->post_content ) ) ) {
				throw new \RuntimeException( sprintf( 'Konten duplikat/terlalu mirip dengan konten #%d. Buat sudut pembahasan baru.', $post->ID ) );
			}
		}
	}

	public static function validate_content( array $data, string $kind ): void {
		if ( ! is_string( $data['title'] ?? null ) || '' === trim( $data['title'] ) ) { throw new \RuntimeException( 'Judul AI kosong/tidak valid.' ); }
		if ( 'topic' === $kind ) { return; }
		if ( preg_match( '/<(?:a|img|iframe|script)\b/i', (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) ) ) { throw new \RuntimeException( 'AI harus mengirim teks, bukan HTML link/gambar atau script. URL dan gambar ditempatkan oleh plugin.' ); }
		if ( ! is_array( $data['sections'] ?? null ) || count( $data['sections'] ) < 3 ) { throw new \RuntimeException( 'Konten AI belum memiliki pembahasan yang lengkap.' ); }
		foreach ( $data['sections'] as $section ) {
			if ( ! is_array( $section ) || ! is_string( $section['body'] ?? null ) || '' === trim( $section['body'] ) ) { throw new \RuntimeException( 'Section AI kosong/tidak valid.' ); }
		}
		if ( 'article' === $kind ) {
			$anchor = self::anchor( (string) ( $data['anchor_suggestion'] ?? '' ) );
			if ( ! is_string( $data['cta_paragraph'] ?? null ) || false === mb_stripos( $data['cta_paragraph'], $anchor ) ) { throw new \RuntimeException( 'Paragraf CTA AI harus memuat anchor keyword secara natural.' ); }
		}
	}

	public static function anchor( string $anchor ): string {
		$clean = trim( sanitize_text_field( $anchor ) );
		$words = preg_split( '/\s+/u', $clean );
		if ( $clean !== trim( $anchor ) || count( $words ) < 2 || count( $words ) > 10 || mb_strlen( $clean ) > 100 || in_array( self::normalize( $clean ), array( 'klik di sini', 'click here' ), true ) ) {
			throw new \RuntimeException( 'Anchor AI harus berupa keyword deskriptif 2–10 kata, tanpa HTML.' );
		}
		return $clean;
	}

	public static function exclusions( int $item_id ): array {
		return array_filter( array( $item_id, (int) get_post_meta( $item_id, '_lpgen_source_topic_id', true ), (int) get_post_meta( $item_id, '_lpgen_post_id', true ) ) );
	}

	public static function prepare( array $context, int $item_id ): array {
		$areas = array_values( array_filter( (array) ( $context['target_areas'] ?? array() ) ) );
		$context['content_area'] = $areas ? $areas[ (int) ( $context['item_index'] ?? 0 ) % count( $areas ) ] : (string) ( $context['city'] ?? '' );
		$context['topic_title'] = $context['topic_title'] ?? (string) ( $context['offer_name'] ?? '' );
		$context['existing_titles'] = self::titles( self::exclusions( $item_id ) );
		$stored = json_decode( (string) get_post_meta( $item_id, '_lpgen_entities', true ), true );
		if ( is_array( $stored ) ) {
			$context['entities'] = self::entities( $stored );
		} else {
			$result = AiClient::generate( Prompts::build_content_entities( $context ) );
			$context['entities'] = self::entities( $result['structured']['entities'] ?? array() );
			update_post_meta( $item_id, '_lpgen_entities', wp_json_encode( $context['entities'] ) );
			update_post_meta( $item_id, '_lpgen_entity_tokens', (int) $result['tokens'] );
			update_post_meta( $item_id, '_lpgen_entity_model', (string) $result['model'] );
		}
		return $context;
	}
}
