<?php
/**
 * Campaign/item post type registration and persistence helpers.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContentRepository
 */
final class ContentRepository {

	/**
	 * Campaign meta keys.
	 */
	public const CAMPAIGN_KEYS = array(
		'offer_type',
		'offer_name',
		'offer_url',
		'audience',
		'problem',
		'promise',
		'benefits',
		'price',
		'bonus',
		'guarantee',
		'cta',
		'voice',
		'language',
		'framework',
		'mofu_count',
		'tofu_count',
		'lp_post_type',
		'article_post_type',
		'publish_status',
		'article_category',
		'index_enabled',
		'index_delay',
		'content_push_enabled',
		'content_push_until',
		'internal_link_enabled',
		'internal_link_until',
		'internal_link_target',
		'target_areas',
		'phone',
		'brand',
		'city',
		'entity_enabled',
		'entity_count',
		'tag_max',
		'tag_content_enabled',
		'tag_schedule_mode',
		'tag_schedule_delay',
		'tag_schedule_time',
		'auto_publish',
		'schedule_mode',
		'schedule_interval',
		'schedule_start',
		'schedule_time',
		'schedule_jitter',
		'amazon_enabled',
		'amazon_keyword',
		'image_enabled',
		'image_source',
		'image_upload_ids',
		'image_count',
		'ai_framework',
	);

	/**
	 * Item meta keys.
	 */
	public const ITEM_KEYS = array(
		'campaign_id',
		'stage',
		'kind',
		'index',
		'status',
		'framework',
		'structured',
		'seo_title',
		'meta_description',
		'target_stage',
		'anchor',
		'offer_url',
		'post_id',
		'permalink',
		'model',
		'tokens',
		'error',
		'tags',
		'tag',
		'images',
	);

	/**
	 * Register post types.
	 */
	public static function register_post_types(): void {
		register_post_type(
			'lpgen_campaign',
			array(
				'labels'       => array(
					'name'          => __( 'LP Campaigns', 'lp-generator' ),
					'singular_name' => __( 'LP Campaign', 'lp-generator' ),
					'add_new_item'  => __( 'Add New Campaign', 'lp-generator' ),
					'edit_item'     => __( 'Edit Campaign', 'lp-generator' ),
					'menu_name'     => __( 'Campaigns', 'lp-generator' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'lpgen_item',
			array(
				'labels'       => array(
					'name'          => __( 'Generated Items', 'lp-generator' ),
					'singular_name' => __( 'Generated Item', 'lp-generator' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'excerpt' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Build campaign context array from a campaign post.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<string,mixed>
	 */
	public static function get_campaign( int $campaign_id ): array {
		$post = get_post( $campaign_id );
		if ( ! $post || 'lpgen_campaign' !== $post->post_type ) {
			return array();
		}

		$context = array(
			'campaign_id' => $campaign_id,
			'campaign_title' => get_the_title( $campaign_id ),
			'status'      => self::get_meta( $campaign_id, 'status', 'draft' ),
		);
		foreach ( self::CAMPAIGN_KEYS as $key ) {
			$context[ $key ] = self::get_meta( $campaign_id, $key, '' );
		}

		$context['benefits'] = Utils::lines( (string) $context['benefits'] );
		$context['bonus']    = Utils::lines( (string) $context['bonus'] );
		$context['mofu_count'] = Utils::clamp_count( $context['mofu_count'] ?: 2 );
		$context['tofu_count'] = Utils::clamp_count( $context['tofu_count'] ?: 3 );
		$context['content_push_enabled'] = empty( $context['content_push_enabled'] ) ? 0 : 1;
		$context['internal_link_enabled'] = empty( $context['internal_link_enabled'] ) ? 0 : 1;
		$context['internal_link_target'] = in_array( (string) $context['internal_link_target'], array( 'bofu', 'next_stage' ), true ) ? (string) $context['internal_link_target'] : 'bofu';
		$context['target_areas'] = Utils::lines( (string) $context['target_areas'] );

		$framework          = Frameworks::get( (string) $context['framework'] );
		$context['framework']        = $framework['label'];
		$context['framework_steps']  = $framework['steps'];
		$generated = Frameworks::sanitize_generated( (string) ( $context['ai_framework'] ?? '' ) );
		if ( ! empty( $generated['steps'] ) ) {
			$context['framework']               = $generated['label'];
			$context['framework_steps']         = wp_list_pluck( $generated['steps'], 'name' );
			$context['framework_hints']         = wp_list_pluck( $generated['steps'], 'section_hint' );
			$context['framework_image_roles']   = wp_list_pluck( $generated['steps'], 'image_role' );
			$context['framework_image_keywords'] = wp_list_pluck( $generated['steps'], 'image_keyword' );
		}
		$context['entities']         = self::get_entities( $campaign_id );
		$context['amazon_products']  = self::get_amazon_products( $campaign_id );
		$context['tag_max']          = max( 1, min( 3, (int) ( $context['tag_max'] ?: 3 ) ) );
		$context['entity_count']     = max( 3, min( 15, (int) ( $context['entity_count'] ?: 8 ) ) );
		$context['image_source']     = ImagePipeline::clean_source( $context['image_source'] ?? 'auto' );
		$context['image_count']      = max( 0, min( 6, (int) ( $context['image_count'] ?? 0 ) ) );
		$context['image_upload_ids'] = implode( ',', ImagePipeline::clean_upload_ids( $context['image_upload_ids'] ?? '' ) );

		return $context;
	}

	/**
	 * Read a campaign/item meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta suffix.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_meta( int $post_id, string $key, $default = '' ) {
		$value = get_post_meta( $post_id, '_lpgen_' . $key, true );
		return '' === $value || null === $value ? $default : $value;
	}

	/**
	 * Save campaign meta from a sanitized input array.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $input   Sanitized values.
	 */
	public static function save_campaign_meta( int $post_id, array $input ): void {
		$defaults = array(
			'offer_type'       => 'product',
			'offer_name'       => '',
			'offer_url'        => '',
			'audience'         => '',
			'problem'          => '',
			'promise'          => '',
			'benefits'         => '',
			'price'            => '',
			'bonus'            => '',
			'guarantee'        => '',
			'cta'              => '',
			'voice'            => 'professional',
			'language'         => 'id',
			'framework'        => 'aida',
			'mofu_count'       => 2,
			'tofu_count'       => 3,
			'lp_post_type'     => 'page',
			'article_post_type' => 'post',
			'publish_status'   => 'publish',
			'article_category' => 0,
			'index_enabled'    => 1,
			'index_delay'      => 10,
			'phone'            => '',
			'brand'            => '',
			'city'             => '',
			'entity_enabled'   => 1,
			'entity_count'     => 8,
			'tag_max'          => 3,
			'tag_content_enabled' => 1,
			'tag_schedule_mode'   => 'after_publish',
			'tag_schedule_delay'  => 60,
			'tag_schedule_time'   => '09:00',
			'auto_publish'     => 0,
			'schedule_mode'    => 'immediate',
			'schedule_interval'=> 0,
			'schedule_start'   => '',
			'schedule_time'    => '09:00',
			'schedule_jitter'  => 0,
			'amazon_enabled'   => 0,
			'amazon_keyword'   => '',
			'image_enabled'    => 1,
			'image_source'     => 'auto',
			'image_upload_ids' => '',
			'image_count'      => 0,
			'ai_framework'     => '',
		);
		$values = array_merge( $defaults, array_intersect_key( $input, $defaults ) );

		foreach ( $values as $key => $value ) {
			update_post_meta( $post_id, '_lpgen_' . $key, $value );
		}
	}

	/**
	 * Create a generated item shell.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $stage       bofu|mofu|tofu.
	 * @param string $kind        lp|topic|article.
	 * @param int    $index       Zero-based sequence.
	 * @param string $tag         Optional tag for dedicated tag articles.
	 * @return int Item ID.
	 */
	public static function create_item( int $campaign_id, string $stage, string $kind, int $index, string $tag = '' ): int {
		$title = sprintf( '[%s %s #%d]', strtoupper( $stage ), strtoupper( $kind ), $index + 1 );
		$id    = wp_insert_post(
			array(
				'post_type'    => 'lpgen_item',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => '',
				'post_excerpt' => '',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		$meta = array(
			'campaign_id' => $campaign_id,
			'stage'       => $stage,
			'kind'        => $kind,
			'index'       => $index,
			'status'      => 'queued',
			'framework'   => (string) self::get_meta( $campaign_id, 'framework', 'aida' ),
			'offer_url'   => (string) self::get_meta( $campaign_id, 'offer_url', '' ),
			'target_stage' => in_array( $stage, array( 'mofu', 'tag' ), true ) ? 'bofu' : 'mofu',
			'language'    => (string) self::get_meta( $campaign_id, 'language', 'id' ),
			'tag'         => sanitize_text_field( $tag ),
			'tags'        => '' !== $tag ? wp_json_encode( array( sanitize_text_field( $tag ) ) ) : '',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_lpgen_' . $key, $value );
		}
		return (int) $id;
	}

	/**
	 * Store AI output into an item.
	 *
	 * @param int    $item_id    Item ID.
	 * @param array  $structured Decoded AI JSON.
	 * @param string $model      Model used.
	 * @param int    $tokens     Token count.
	 * @param string $blocks     Rendered Gutenberg blocks.
	 */
	public static function save_item_result( int $item_id, array $structured, string $model, int $tokens, string $blocks ): void {
		if ( isset( $structured['cta_url'] ) ) {
			$cta_url = esc_url_raw( (string) $structured['cta_url'] );
			$structured['cta_url'] = false === wp_http_validate_url( $cta_url ) ? '' : $cta_url;
		}
		$title = isset( $structured['title'] ) ? sanitize_text_field( (string) $structured['title'] ) : get_the_title( $item_id );
		$campaign_id = absint( self::get_meta( $item_id, 'campaign_id', 0 ) );
		$max_tags    = max( 1, min( 3, (int) ( self::get_meta( $campaign_id, 'tag_max', 3 ) ?: 3 ) ) );
		$tags        = Utils::sanitize_tags( $structured['tags'] ?? array(), $max_tags );
		$forced_tag  = trim( (string) self::get_meta( $item_id, 'tag', '' ) );
		if ( '' !== $forced_tag && ! in_array( $forced_tag, $tags, true ) ) {
			array_unshift( $tags, $forced_tag );
		}
		$tags = array_slice( $tags, 0, $max_tags );
		if ( ! empty( $tags ) ) {
			$structured['tags'] = $tags;
		}
		$topic = '';
		if ( 'topic' === self::get_meta( $item_id, 'kind' ) ) {
			$parts = array(
				(string) ( $structured['angle'] ?? '' ),
				'Intent: ' . (string) ( $structured['intent'] ?? '' ),
				'Outline: ' . implode( ' | ', (array) ( $structured['outline'] ?? array() ) ),
				'Funnel angle: ' . (string) ( $structured['funnel_link_angle'] ?? '' ),
			);
			$topic = implode( "\n", $parts );
		}
		wp_update_post(
			array(
				'ID'           => $item_id,
				'post_title'   => $title,
				'post_content' => wp_kses_post( $blocks ),
				'post_excerpt' => sanitize_textarea_field( $topic ),
			),
			true
		);
		update_post_meta( $item_id, '_lpgen_structured', wp_json_encode( $structured ) );
		update_post_meta( $item_id, '_lpgen_seo_title', sanitize_text_field( (string) ( $structured['seo_title'] ?? '' ) ) );
		update_post_meta( $item_id, '_lpgen_meta_description', sanitize_text_field( (string) ( $structured['meta_description'] ?? '' ) ) );
		update_post_meta( $item_id, '_lpgen_anchor', sanitize_text_field( (string) ( $structured['anchor_suggestion'] ?? '' ) ) );
		update_post_meta( $item_id, '_lpgen_tags', wp_json_encode( array_values( $tags ) ) );
		update_post_meta( $item_id, '_lpgen_model', sanitize_text_field( $model ) );
		update_post_meta( $item_id, '_lpgen_tokens', absint( $tokens ) );
		update_post_meta( $item_id, '_lpgen_status', 'generated' );
		delete_post_meta( $item_id, '_lpgen_error' );
	}

	/**
	 * Set item status.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $status  Status.
	 */
	public static function set_item_status( int $item_id, string $status ): void {
		update_post_meta( $item_id, '_lpgen_status', sanitize_key( $status ) );
	}

	/**
	 * Get item with meta.
	 *
	 * @param int $item_id Item ID.
	 * @return array<string,mixed>
	 */
	public static function get_item( int $item_id ): array {
		$post = get_post( $item_id );
		if ( ! $post || 'lpgen_item' !== $post->post_type ) {
			return array();
		}
		$item = array(
			'id'      => $item_id,
			'title'   => get_the_title( $item_id ),
			'content' => (string) $post->post_content,
			'excerpt' => (string) $post->post_excerpt,
		);
		foreach ( self::ITEM_KEYS as $key ) {
			$item[ $key ] = self::get_meta( $item_id, $key, '' );
		}
		return $item;
	}

	/**
	 * Query items.
	 *
	 * @param int         $campaign_id Campaign ID.
	 * @param string|null $stage       Optional stage.
	 * @param string|null $kind        Optional kind.
	 * @return array<int,array>
	 */
	public static function items( int $campaign_id, ?string $stage = null, ?string $kind = null ): array {
		$meta_query = array(
			array(
				'key'     => '_lpgen_campaign_id',
				'value'   => $campaign_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
		);
		if ( null !== $stage ) {
			$meta_query[] = array(
				'key'     => '_lpgen_stage',
				'value'   => sanitize_key( $stage ),
				'compare' => '=',
			);
		}
		if ( null !== $kind ) {
			$meta_query[] = array(
				'key'     => '_lpgen_kind',
				'value'   => sanitize_key( $kind ),
				'compare' => '=',
			);
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'lpgen_item',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$out = array();
		foreach ( $query->posts as $post ) {
			$out[] = self::get_item( (int) $post->ID );
		}
		return $out;
	}

	/**
	 * Find a topic item by stage and index.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $stage       mofu|tofu.
	 * @param int    $index       Zero-based index.
	 * @return int
	 */
	public static function find_topic_item( int $campaign_id, string $stage, int $index ): int {
		$items = self::items( $campaign_id, $stage, 'topic' );
		foreach ( $items as $item ) {
			if ( (int) $item['index'] === $index ) {
				return (int) $item['id'];
			}
		}
		return 0;
	}

	/**
	 * Read the AI-generated keyword entities for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<int,array<string,string>>
	 */
	public static function get_entities( int $campaign_id ): array {
		$raw = self::get_meta( $campaign_id, 'entities', '' );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $entity ) {
			if ( ! is_array( $entity ) ) {
				$entity = array( 'term' => (string) $entity );
			}
			$term = trim( sanitize_text_field( (string) ( $entity['term'] ?? '' ) ) );
			if ( '' === $term ) {
				continue;
			}
			$out[] = array(
				'term'   => Utils::truncate( $term, 60 ),
				'type'   => sanitize_key( (string) ( $entity['type'] ?? 'topic' ) ),
				'intent' => sanitize_key( (string) ( $entity['intent'] ?? 'informational' ) ),
				'notes'  => sanitize_text_field( (string) ( $entity['notes'] ?? '' ) ),
			);
		}
		return $out;
	}

	/**
	 * Persist keyword entities for a campaign.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $entities    Raw AI entities.
	 * @return int Number of stored entities.
	 */
	public static function save_entities( int $campaign_id, array $entities ): int {
		$list  = array();
		$seen  = array();
		$count = 0;
		foreach ( $entities as $entity ) {
			if ( ! is_array( $entity ) ) {
				continue;
			}
			$term = trim( sanitize_text_field( (string) ( $entity['term'] ?? '' ) ) );
			if ( '' === $term ) {
				continue;
			}
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = 1;
			$list[] = array(
				'term'   => Utils::truncate( $term, 60 ),
				'type'   => sanitize_key( (string) ( $entity['type'] ?? 'topic' ) ),
				'intent' => sanitize_key( (string) ( $entity['intent'] ?? 'informational' ) ),
				'notes'  => sanitize_text_field( (string) ( $entity['notes'] ?? '' ) ),
			);
			++$count;
			if ( $count >= 15 ) {
				break;
			}
		}
		update_post_meta( $campaign_id, '_lpgen_entities', wp_json_encode( $list ) );
		return count( $list );
	}

	/**
	 * Read cached Amazon products for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<int,array<string,string>>
	 */
	public static function get_amazon_products( int $campaign_id ): array {
		$raw  = self::get_meta( $campaign_id, 'amazon_products', '' );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		return is_array( $data ) ? array_slice( $data, 0, 8 ) : array();
	}

	/**
	 * Persist Amazon products for a campaign.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $products    Products.
	 */
	public static function save_amazon_products( int $campaign_id, array $products ): void {
		update_post_meta( $campaign_id, '_lpgen_amazon_products', wp_json_encode( array_slice( $products, 0, 8 ) ) );
	}

	/**
	 * Read sanitized tags attached to an item.
	 *
	 * @param int $item_id Item ID.
	 * @return string[]
	 */
	public static function item_tags( int $item_id ): array {
		$raw  = self::get_meta( $item_id, 'tags', '' );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		return is_array( $data ) ? Utils::sanitize_tags( $data, 3 ) : array();
	}

	/**
	 * Collect unique tags across a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $limit       Maximum tags.
	 * @return string[]
	 */
	public static function collect_tags( int $campaign_id, int $limit = 3 ): array {
		$out  = array();
		$seen = array();
		foreach ( self::items( $campaign_id ) as $item ) {
			if ( 'topic' === (string) $item['kind'] || 'rejected' === (string) $item['status'] ) {
				continue;
			}
			foreach ( self::item_tags( (int) $item['id'] ) as $tag ) {
				$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $tag, 'UTF-8' ) : strtolower( $tag );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = 1;
				$out[]        = $tag;
				if ( count( $out ) >= $limit ) {
					return $out;
				}
			}
		}
		return $out;
	}

	/**
	 * Find an existing tag article item for a tag.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $tag         Tag to look for.
	 * @return int Item ID or 0.
	 */
	public static function find_tag_item( int $campaign_id, string $tag ): int {
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $tag ), 'UTF-8' ) : strtolower( trim( $tag ) );
		if ( '' === $needle ) {
			return 0;
		}
		foreach ( self::items( $campaign_id ) as $item ) {
			if ( 'tag' !== (string) $item['stage'] ) {
				continue;
			}
			$existing = function_exists( 'mb_strtolower' )
				? mb_strtolower( trim( (string) $item['tag'] ), 'UTF-8' )
				: strtolower( trim( (string) $item['tag'] ) );
			if ( $existing === $needle ) {
				return (int) $item['id'];
			}
		}
		return 0;
	}

	/**
	 * Count dedicated tag article items for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	public static function tag_item_count( int $campaign_id ): int {
		$count = 0;
		foreach ( self::items( $campaign_id ) as $item ) {
			if ( 'tag' === (string) $item['stage'] ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Read sanitized image records attached to an item.
	 *
	 * @param int $item_id Item ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function item_images( int $item_id ): array {
		$raw  = self::get_meta( $item_id, 'images', '' );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $data, 0, 6 ) as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$id  = absint( $image['id'] ?? 0 );
			$url = esc_url_raw( (string) ( $image['url'] ?? '' ) );
			if ( $id <= 0 || '' === $url ) {
				continue;
			}
			$out[] = array(
				'id'           => $id,
				'url'          => $url,
				'alt'          => sanitize_text_field( (string) ( $image['alt'] ?? '' ) ),
				'keyword'      => sanitize_text_field( (string) ( $image['keyword'] ?? '' ) ),
				'role'         => sanitize_key( (string) ( $image['role'] ?? '' ) ),
				'section_hint' => sanitize_key( (string) ( $image['section_hint'] ?? '' ) ),
			);
		}
		return $out;
	}

	/**
	 * Persist image records for an item.
	 *
	 * @param int   $item_id Item ID.
	 * @param array $images  Image records.
	 */
	public static function save_item_images( int $item_id, array $images ): void {
		$clean = array();
		foreach ( array_slice( $images, 0, 6 ) as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$clean[] = array(
				'id'           => absint( $image['id'] ?? 0 ),
				'url'          => esc_url_raw( (string) ( $image['url'] ?? '' ) ),
				'alt'          => sanitize_text_field( (string) ( $image['alt'] ?? '' ) ),
				'keyword'      => sanitize_text_field( (string) ( $image['keyword'] ?? '' ) ),
				'role'         => sanitize_key( (string) ( $image['role'] ?? '' ) ),
				'section_hint' => sanitize_key( (string) ( $image['section_hint'] ?? '' ) ),
			);
		}
		update_post_meta( $item_id, '_lpgen_images', wp_json_encode( $clean ) );
	}

	/**
	 * Delete generated items for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function delete_items( int $campaign_id ): void {
		foreach ( self::items( $campaign_id ) as $item ) {
			wp_delete_post( (int) $item['id'], true );
		}
	}
}
