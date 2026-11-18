<?php
/**
 * Sequential job orchestrator.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Generator
 */
final class Generator {

	/**
	 * Start (or restart) generation for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array{ok:bool,message:string,jobs:int}
	 */
	public static function start( int $campaign_id ): array {
		$campaign = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Campaign tidak ditemukan.', 'lp-generator' ),
				'jobs'    => 0,
			);
		}

		Database::clear_pending_jobs( $campaign_id );
		ContentRepository::delete_items( $campaign_id );

		$items    = array();
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		// 0a. Optional Amazon product discovery for affiliate campaigns.
		if ( 'affiliate' === (string) $campaign['offer_type'] && ( ! empty( $settings['amazon_enabled'] ) || ! empty( $campaign['amazon_enabled'] ) ) ) {
			$items[] = array(
				'id'    => 0,
				'kind'  => 'amazon_products',
				'stage' => 'bofu',
				'index' => 0,
			);
		}

		// 0b. Optional AI keyword entity discovery.
		if ( ! empty( $campaign['entity_enabled'] ) ) {
			$items[] = array(
				'id'    => 0,
				'kind'  => 'generate_entities',
				'stage' => 'bofu',
				'index' => 0,
			);
		}

		// 1. BOFU landing page.
		$lp_id    = ContentRepository::create_item( $campaign_id, 'bofu', 'lp', 0 );
		$items[]  = array( 'id' => $lp_id, 'kind' => 'generate_lp', 'stage' => 'bofu', 'index' => 0 );

		// 2. MOFU topics.
		$mofu_topics = array();
		for ( $i = 0; $i < (int) $campaign['mofu_count']; $i++ ) {
			$id            = ContentRepository::create_item( $campaign_id, 'mofu', 'topic', $i );
			$mofu_topics[] = $id;
			$items[]       = array( 'id' => $id, 'kind' => 'generate_topic', 'stage' => 'mofu', 'index' => $i );
		}

		// 3. MOFU articles, each bound to its topic.
		for ( $i = 0; $i < (int) $campaign['mofu_count']; $i++ ) {
			$id      = ContentRepository::create_item( $campaign_id, 'mofu', 'article', $i );
			$items[] = array(
				'id'            => $id,
				'kind'          => 'generate_article',
				'stage'         => 'mofu',
				'index'         => $i,
				'topic_item_id' => $mofu_topics[ $i ] ?? 0,
				'target_stage'  => 'bofu',
			);
		}

		// 4. TOFU topics.
		$tofu_topics = array();
		for ( $i = 0; $i < (int) $campaign['tofu_count']; $i++ ) {
			$id            = ContentRepository::create_item( $campaign_id, 'tofu', 'topic', $i );
			$tofu_topics[] = $id;
			$items[]       = array( 'id' => $id, 'kind' => 'generate_topic', 'stage' => 'tofu', 'index' => $i );
		}

		// 5. TOFU articles.
		for ( $i = 0; $i < (int) $campaign['tofu_count']; $i++ ) {
			$id      = ContentRepository::create_item( $campaign_id, 'tofu', 'article', $i );
			$items[] = array(
				'id'            => $id,
				'kind'          => 'generate_article',
				'stage'         => 'tofu',
				'index'         => $i,
				'topic_item_id' => $tofu_topics[ $i ] ?? 0,
				'target_stage'  => 'mofu',
			);
		}

		foreach ( $items as $job ) {
			Database::insert_job(
				array(
					'campaign_id' => $campaign_id,
					'item_id'     => (int) $job['id'],
					'kind'        => (string) $job['kind'],
					'payload'     => array(
						'stage'         => (string) $job['stage'],
						'index'         => (int) $job['index'],
						'topic_item_id' => (int) ( $job['topic_item_id'] ?? 0 ),
						'target_stage'  => (string) ( $job['target_stage'] ?? '' ),
					),
				)
			);
		}

		update_post_meta( $campaign_id, '_lpgen_status', 'generating' );
		wp_schedule_single_event( time() + 1, 'lpgen_run_job' );
		Database::log( 'info', sprintf( 'Generation started with %d jobs', count( $items ) ), $campaign_id );

		return array(
			'ok'      => true,
			'message' => sprintf( __( '%d job antrean dibuat.', 'lp-generator' ), count( $items ) ),
			'jobs'    => count( $items ),
		);
	}

	/**
	 * Generate a custom framework for one campaign, step by step, with AI.
	 *
	 * The first call proposes ordered step names. Each step is then refined in
	 * its own call so the AI can choose a section hint and an image role. The
	 * result is stored in campaign meta and used by every content prompt.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array{ok:bool,message:string,steps:int}
	 */
	public static function generate_framework( int $campaign_id ): array {
		$campaign = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Campaign tidak ditemukan.', 'lp-generator' ),
				'steps'   => 0,
			);
		}
		$lock_key = 'lpgen_framework_' . $campaign_id;
		if ( false !== get_transient( $lock_key ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Generate framework masih berjalan. Tunggu beberapa detik.', 'lp-generator' ),
				'steps'   => 0,
			);
		}
		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );
		try {
			$plan    = AiClient::generate( Prompts::build_framework_plan( $campaign ) );
			$names   = Frameworks::clean_step_names( $plan['structured']['step_names'] ?? array() );
			if ( count( $names ) < 4 ) {
				$names = Frameworks::clean_step_names( Frameworks::presets()['aida']['steps'] );
			}
			$label   = Utils::truncate( sanitize_text_field( (string) ( $plan['structured']['label'] ?? '' ) ), 60 );
			if ( '' === $label ) {
				$label = 'AI Framework';
			}
			$steps = array();
			foreach ( array_values( $names ) as $index => $name ) {
				try {
					$detail = AiClient::generate( Prompts::build_framework_step( $campaign, $name, (int) $index ) );
					$steps[] = Frameworks::sanitize_step( $detail['structured'], $name, (int) $index );
				} catch ( \Throwable $e ) {
					$steps[] = Frameworks::sanitize_step( array(), $name, (int) $index );
				}
			}
			$generated = array(
				'label'        => $label,
				'steps'        => $steps,
				'generated_at' => current_time( 'mysql' ),
			);
			ContentRepository::save_campaign_meta( $campaign_id, array( 'ai_framework' => wp_json_encode( $generated ) ) );
			Database::log( 'info', sprintf( 'AI framework generated: %s (%d steps)', $label, count( $steps ) ), $campaign_id );
			return array(
				'ok'      => true,
				'message' => sprintf( __( 'Framework \"%s\" dibuat dengan %d langkah.', 'lp-generator' ), $label, count( $steps ) ),
				'steps'   => count( $steps ),
			);
		} catch ( \Throwable $e ) {
			Database::log( 'error', 'AI framework generation failed: ' . $e->getMessage(), $campaign_id );
			return array(
				'ok'      => false,
				'message' => __( 'Gagal membuat framework AI. Cek koneksi 9Router dan log.', 'lp-generator' ),
				'steps'   => 0,
			);
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Enqueue publish jobs for approved items, BOFU â†’ MOFU â†’ TOFU.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array{ok:bool,message:string,jobs:int}
	 */
	public static function start_publish( int $campaign_id ): array {
		$campaign = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Campaign tidak ditemukan.', 'lp-generator' ),
				'jobs'    => 0,
			);
		}
		$items = ContentRepository::items( $campaign_id );
		if ( self::auto_publish_enabled( $campaign ) ) {
			foreach ( $items as $item ) {
				if ( 'generated' === (string) $item['status'] ) {
					ContentRepository::set_item_status( (int) $item['id'], 'approved' );
				}
			}
			$items = ContentRepository::items( $campaign_id );
		}
		$approved = array_filter(
			$items,
			static fn( array $item ): bool => 'approved' === (string) $item['status'] && 'topic' !== $item['kind']
		);
		if ( empty( $approved ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Tidak ada item yang di-approve.', 'lp-generator' ),
				'jobs'    => 0,
			);
		}
		$order = array( 'bofu' => 0, 'mofu' => 1, 'tofu' => 2, 'tag' => 3 );
		usort(
			$approved,
			static function ( array $a, array $b ) use ( $order ): int {
				$stage_cmp = ( $order[ $a['stage'] ] ?? 9 ) <=> ( $order[ $b['stage'] ] ?? 9 );
				return 0 !== $stage_cmp ? $stage_cmp : ( (int) $a['index'] <=> (int) $b['index'] );
			}
		);

		Database::clear_pending_jobs( $campaign_id );
		$sequence = 0;
		foreach ( $approved as $item ) {
			Database::insert_job(
				array(
					'campaign_id' => $campaign_id,
					'item_id'     => (int) $item['id'],
					'kind'        => 'publish',
					'payload'     => array(
						'stage'    => (string) $item['stage'],
						'sequence' => $sequence,
					),
					'run_at'      => gmdate( 'Y-m-d H:i:s', Utils::schedule_timestamp( $campaign, $sequence ) ),
				)
			);
			++$sequence;
		}
		$tag_jobs = self::enqueue_tag_content_jobs( $campaign_id, $campaign );

		update_post_meta( $campaign_id, '_lpgen_status', 'publishing' );
		wp_schedule_single_event( time() + 1, 'lpgen_run_job' );
		Database::log( 'info', sprintf( 'Publish started with %d items and %d tag jobs', count( $approved ), $tag_jobs ), $campaign_id );

		return array(
			'ok'      => true,
			'message' => sprintf( __( '%d item akan dipublish, %d konten tag antre.', 'lp-generator' ), count( $approved ), $tag_jobs ),
			'jobs'    => count( $approved ) + $tag_jobs,
		);
	}

	/**
	 * Run the next queued job. Called by WP-Cron and the manual "Run now" button.
	 */
	public static function run_next(): void {
		if ( ! self::acquire_lock() ) {
			wp_schedule_single_event( time() + 5, 'lpgen_run_job' );
			return;
		}

		try {
			$job = Database::get_next_job();
			if ( null === $job ) {
				self::finalize_orphans();
				// Keep the cron chain alive for jobs scheduled in the future.
				$next = Database::next_queued_time();
				if ( $next > 0 && function_exists( 'wp_next_scheduled' ) && false === wp_next_scheduled( 'lpgen_run_job' ) ) {
					wp_schedule_single_event( max( time() + 1, $next ), 'lpgen_run_job' );
				}
				return;
			}
			if ( ! Database::claim_job( (int) $job['id'] ) ) {
				return;
			}

			$campaign_id = (int) $job['campaign_id'];
			$job_id      = (int) $job['id'];
			Database::log( 'info', sprintf( 'Job %s dimulai (attempt %d)', $job['kind'], (int) ( $job['attempts'] ?? 0 ) ), $campaign_id, $job_id );

			try {
				self::dispatch( $job );
				Database::update_job( $job_id, array( 'status' => 'done' ) );
				Database::log( 'info', sprintf( 'Job %s selesai', $job['kind'] ), $campaign_id, $job_id );
			} catch ( \Throwable $e ) {
				$settings = get_option( 'lpgen_settings', array() );
				$max      = max( 1, min( 5, (int) ( $settings['max_retries'] ?? 2 ) ) );
				$attempts = (int) ( $job['attempts'] ?? 0 ) + 1;
				$message  = sanitize_text_field( $e->getMessage() );
				if ( $attempts <= $max ) {
					Database::update_job(
						$job_id,
						array(
							'status'     => 'queued',
							'attempts'   => $attempts,
							'run_at'     => gmdate( 'Y-m-d H:i:s', time() + ( 15 * $attempts ) ),
							'last_error' => $message,
						)
					);
					Database::log( 'warning', 'Job retry ' . $attempts . ': ' . $message, $campaign_id, $job_id );
				} else {
					Database::update_job(
						$job_id,
						array(
							'status'     => 'failed',
							'attempts'   => $attempts,
							'last_error' => $message,
						)
					);
					if ( (int) ( $job['item_id'] ?? 0 ) > 0 ) {
						ContentRepository::set_item_status( (int) $job['item_id'], 'failed' );
						update_post_meta( (int) $job['item_id'], '_lpgen_error', $message );
					}
					Database::log( 'error', 'Job gagal: ' . $message, $campaign_id, $job_id );
				}
			}

			wp_schedule_single_event( time() + 1, 'lpgen_run_job' );
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Dispatch by job kind.
	 *
	 * @param array $job Job row.
	 */
	private static function dispatch( array $job ): void {
		switch ( (string) $job['kind'] ) {
			case 'generate_lp':
			case 'generate_topic':
			case 'generate_article':
				self::generate_item( $job );
				break;
			case 'amazon_products':
				Amazon::prepare_campaign( (int) $job['campaign_id'] );
				break;
			case 'generate_entities':
				self::generate_entities( $job );
				break;
			case 'generate_tag':
				self::generate_tag_item( $job );
				break;
			case 'publish':
				self::publish_job( $job );
				break;
			case 'index':
				Indexer::run( $job );
				break;
			default:
				throw new \RuntimeException( 'unknown_job_kind' );
		}
	}

	/**
	 * Generate one item with one AI request.
	 *
	 * @param array $job Job row.
	 */
	private static function generate_item( array $job ): void {
		$campaign_id = (int) $job['campaign_id'];
		$item_id     = (int) $job['item_id'];
		$campaign    = ContentRepository::get_campaign( $campaign_id );
		$item        = ContentRepository::get_item( $item_id );
		if ( empty( $campaign ) || empty( $item ) ) {
			throw new \RuntimeException( 'campaign_or_item_missing' );
		}

		$payload = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		$payload = is_array( $payload ) ? $payload : array();
		$stage   = (string) ( $payload['stage'] ?? $item['stage'] );
		$kind    = (string) $item['kind'];
		$context = $campaign;
		$context['stage']            = $stage;
		$context['item_index']       = (int) ( $payload['index'] ?? $item['index'] );
		$context['existing_titles']  = self::existing_titles( $campaign_id );
		$context['target_stage']     = (string) ( $payload['target_stage'] ?? $item['target_stage'] );

		if ( 'article' === $kind ) {
			$topic_item_id = (int) ( $payload['topic_item_id'] ?? 0 );
			$topic         = $topic_item_id > 0 ? ContentRepository::get_item( $topic_item_id ) : array();
			$topic_json    = json_decode( (string) ( $topic['structured'] ?? '' ), true );
			$topic_json    = is_array( $topic_json ) ? $topic_json : array();
			$context['topic_title']  = (string) ( $topic['title'] ?? '' );
			$context['topic_angle']  = (string) ( $topic_json['angle'] ?? '' );
			$context['topic_outline'] = (array) ( $topic_json['outline'] ?? array() );
			$prompt = Prompts::build_article( $context );
		} elseif ( 'topic' === $kind ) {
			$prompt = Prompts::build_topic( $context );
		} else {
			$prompt = Prompts::build_lp( $context );
		}

		$result = AiClient::generate( $prompt );
		$context['content_tags'] = Utils::sanitize_tags(
			$result['structured']['tags'] ?? array(),
			max( 1, min( 3, (int) ( $campaign['tag_max'] ?? 3 ) ) )
		);
		$images = 'topic' === $kind
			? array()
			: ImagePipeline::prepare_for_item( $campaign_id, $item_id, $context );
		$blocks = Publisher::build_blocks( $result['structured'], $kind, $campaign, $images );
		ContentRepository::save_item_result( $item_id, $result['structured'], $result['model'], $result['tokens'], $blocks );
		Database::log(
			'info',
			sprintf( 'Item %d generated with %s (%d tokens)', $item_id, $result['model'], $result['tokens'] ),
			$campaign_id
		);
	}

	/**
	 * Publish one item and resolve its internal link target.
	 *
	 * @param array $job Job row.
	 */
	private static function publish_job( array $job ): void {
		$campaign_id = (int) $job['campaign_id'];
		$item_id     = (int) $job['item_id'];
		$campaign    = ContentRepository::get_campaign( $campaign_id );
		$item        = ContentRepository::get_item( $item_id );
		if ( empty( $campaign ) || empty( $item ) ) {
			throw new \RuntimeException( 'campaign_or_item_missing' );
		}

		$target = '';
		if ( 'article' === $item['kind'] ) {
			$target = 'bofu' === $item['target_stage']
				? self::find_published_permalink( $campaign_id, 'bofu' )
				: self::find_published_permalink( $campaign_id, 'mofu' );
			if ( '' === $target ) {
				$target = self::find_published_permalink( $campaign_id, 'bofu' );
			}
		}
		$post_id = Publisher::publish_item( $item_id, $campaign, $target );
		if ( 0 === $post_id ) {
			throw new \RuntimeException( 'wp_insert_post_failed' );
		}
		$permalink = (string) get_permalink( $post_id );
		Database::log( 'success', sprintf( 'Item %d published as post %d: %s', $item_id, $post_id, $permalink ), $campaign_id );
	}

	/**
	 * Generate keyword entities for a campaign in one AI job.
	 *
	 * @param array $job Job row.
	 */
	private static function generate_entities( array $job ): void {
		$campaign_id = (int) $job['campaign_id'];
		$campaign    = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			throw new \RuntimeException( 'campaign_missing' );
		}
		$result   = AiClient::generate( Prompts::build_entities( $campaign ) );
		$entities = isset( $result['structured']['entities'] ) && is_array( $result['structured']['entities'] )
			? $result['structured']['entities']
			: array();
		$count = ContentRepository::save_entities( $campaign_id, $entities );
		Database::log( 'info', sprintf( 'Generated %d keyword entities with %s', $count, $result['model'] ), $campaign_id );
	}

	/**
	 * Queue one AI article per campaign tag.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign context.
	 * @return int Number of queued tag jobs.
	 */
	private static function enqueue_tag_content_jobs( int $campaign_id, array $campaign ): int {
		if ( empty( $campaign['tag_content_enabled'] ) ) {
			return 0;
		}
		$max  = max( 1, min( 3, (int) ( $campaign['tag_max'] ?? 3 ) ) );
		$tags = ContentRepository::collect_tags( $campaign_id, $max );
		if ( empty( $tags ) ) {
			return 0;
		}
		$queued = 0;
		foreach ( $tags as $index => $tag ) {
			if ( ContentRepository::find_tag_item( $campaign_id, (string) $tag ) > 0 ) {
				continue;
			}
			$item_id = ContentRepository::create_item( $campaign_id, 'tag', 'article', (int) $index, $tag );
			if ( $item_id <= 0 ) {
				continue;
			}
			$generate_at = self::tag_generation_timestamp( $campaign, (int) $index );
			$publish_at  = $generate_at + max( 10, min( 3600, (int) ( $campaign['index_delay'] ?? 10 ) ) ) + $index;
			Database::insert_job(
				array(
					'campaign_id' => $campaign_id,
					'item_id'     => $item_id,
					'kind'        => 'generate_tag',
					'payload'     => array(
						'tag'          => $tag,
						'stage'        => 'tag',
						'target_stage' => 'bofu',
						'sequence'     => (int) $index,
						'publish_at'   => $publish_at,
					),
					'run_at'      => gmdate( 'Y-m-d H:i:s', $generate_at ),
				)
			);
			++$queued;
		}
		return $queued;
	}

	/**
	 * Generate one tag article, then queue it for scheduled publishing.
	 *
	 * @param array $job Job row.
	 */
	private static function generate_tag_item( array $job ): void {
		$campaign_id = (int) $job['campaign_id'];
		$item_id     = (int) $job['item_id'];
		$campaign    = ContentRepository::get_campaign( $campaign_id );
		$item        = ContentRepository::get_item( $item_id );
		if ( empty( $campaign ) || empty( $item ) ) {
			throw new \RuntimeException( 'campaign_or_item_missing' );
		}
		$payload = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		$payload = is_array( $payload ) ? $payload : array();
		$tag     = trim( sanitize_text_field( (string) ( $payload['tag'] ?? $item['tag'] ?? '' ) ) );
		if ( '' === $tag ) {
			throw new \RuntimeException( 'missing_tag' );
		}

		$context = $campaign;
		$context['tag']             = $tag;
		$context['stage']           = 'tag';
		$context['target_stage']    = 'bofu';
		$context['existing_titles'] = self::existing_titles( $campaign_id );

		$result = AiClient::generate( Prompts::build_tag_article( $context ) );
		$context['content_tags'] = Utils::sanitize_tags(
			$result['structured']['tags'] ?? array(),
			max( 1, min( 3, (int) ( $campaign['tag_max'] ?? 3 ) ) )
		);
		$images = ImagePipeline::prepare_for_item( $campaign_id, $item_id, $context );
		$blocks = Publisher::build_blocks( $result['structured'], 'article', $campaign, $images );
		ContentRepository::save_item_result( $item_id, $result['structured'], $result['model'], $result['tokens'], $blocks );
		ContentRepository::set_item_status( $item_id, 'approved' );

		$publish_at = (int) ( $payload['publish_at'] ?? 0 );
		if ( $publish_at <= time() ) {
			$publish_at = time() + 30;
		}
		Database::insert_job(
			array(
				'campaign_id' => $campaign_id,
				'item_id'     => $item_id,
				'kind'        => 'publish',
				'payload'     => array(
					'stage'    => 'tag',
					'sequence' => (int) ( $payload['sequence'] ?? 0 ),
				),
				'run_at'      => gmdate( 'Y-m-d H:i:s', $publish_at ),
			)
		);
		Database::log( 'info', sprintf( 'Tag article generated and scheduled for "%s"', $tag ), $campaign_id );
	}

	/**
	 * Manually queue entity regeneration.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array{ok:bool,message:string}
	 */
	public static function queue_entities( int $campaign_id ): array {
		$campaign = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Campaign tidak ditemukan.', 'lp-generator' ),
			);
		}
		Database::insert_job(
			array(
				'campaign_id' => $campaign_id,
				'kind'        => 'generate_entities',
				'payload'     => array(),
			)
		);
		wp_schedule_single_event( time() + 1, 'lpgen_run_job' );
		return array(
			'ok'      => true,
			'message' => __( 'Generate entitas keyword masuk antrean.', 'lp-generator' ),
		);
	}

	/**
	 * Manually queue a tag article with the campaign's tag schedule.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $tag         Tag/entity.
	 * @return array{ok:bool,message:string}
	 */
	public static function queue_tag_content( int $campaign_id, string $tag ): array {
		$campaign = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Campaign tidak ditemukan.', 'lp-generator' ),
			);
		}
		$tag = trim( sanitize_text_field( $tag ) );
		if ( '' === $tag ) {
			return array(
				'ok'      => false,
				'message' => __( 'Tag tidak valid.', 'lp-generator' ),
			);
		}
		if ( ContentRepository::find_tag_item( $campaign_id, $tag ) > 0 ) {
			return array(
				'ok'      => false,
				'message' => sprintf( __( 'Konten untuk tag "%s" sudah ada.', 'lp-generator' ), $tag ),
			);
		}
		$index       = ContentRepository::tag_item_count( $campaign_id );
		$item_id     = ContentRepository::create_item( $campaign_id, 'tag', 'article', $index, $tag );
		$generate_at = self::tag_generation_timestamp( $campaign, $index );
		$publish_at  = $generate_at + max( 10, min( 3600, (int) ( $campaign['index_delay'] ?? 10 ) ) ) + $index;
		Database::insert_job(
			array(
				'campaign_id' => $campaign_id,
				'item_id'     => $item_id,
				'kind'        => 'generate_tag',
				'payload'     => array(
					'tag'          => $tag,
					'stage'        => 'tag',
					'target_stage' => 'bofu',
					'sequence'     => $index,
					'publish_at'   => $publish_at,
				),
				'run_at'      => gmdate( 'Y-m-d H:i:s', $generate_at ),
			)
		);
		wp_schedule_single_event( time() + 1, 'lpgen_run_job' );
		return array(
			'ok'      => true,
			'message' => sprintf( __( 'Konten tag "%s" dijadwalkan.', 'lp-generator' ), $tag ),
		);
	}

	/**
	 * Compute when a tag article should be generated.
	 *
	 * @param array $campaign Campaign context.
	 * @param int   $sequence Zero-based tag sequence.
	 * @return int UTC timestamp.
	 */
	private static function tag_generation_timestamp( array $campaign, int $sequence ): int {
		$mode  = Utils::clean_tag_schedule_mode( $campaign['tag_schedule_mode'] ?? 'after_publish' );
		$delay = max( 0, min( 10080, (int) ( $campaign['tag_schedule_delay'] ?? 60 ) ) );
		if ( 'after_publish' === $mode ) {
			return time() + ( ( $sequence + 1 ) * $delay * MINUTE_IN_SECONDS );
		}
		$schedule = array(
			'schedule_mode'     => $mode,
			'schedule_time'     => (string) ( $campaign['tag_schedule_time'] ?? '09:00' ),
			'schedule_start'    => gmdate( 'Y-m-d\TH:i' ),
			'schedule_interval' => $delay,
			'schedule_jitter'   => 0,
		);
		return Utils::schedule_timestamp( $schedule, $sequence );
	}

	/**
	 * Whether publishing should skip the manual review step.
	 *
	 * @param array $campaign Campaign context.
	 * @return bool
	 */
	private static function auto_publish_enabled( array $campaign ): bool {
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		return ! empty( $campaign['auto_publish'] ) || ! empty( $settings['auto_publish'] );
	}

	/**
	 * Find the first published permalink in a stage.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $stage       Stage.
	 * @return string
	 */
	private static function find_published_permalink( int $campaign_id, string $stage ): string {
		foreach ( ContentRepository::items( $campaign_id, $stage ) as $item ) {
			if ( 'published' === (string) $item['status'] && '' !== (string) $item['permalink'] ) {
				return (string) $item['permalink'];
			}
		}
		return '';
	}

	/**
	 * Titles already generated in a campaign, for de-duplication prompts.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string[]
	 */
	private static function existing_titles( int $campaign_id ): array {
		$titles = array();
		foreach ( ContentRepository::items( $campaign_id ) as $item ) {
			if ( 'queued' !== (string) $item['status'] && '' !== (string) $item['title'] ) {
				$titles[] = (string) $item['title'];
			}
		}
		return $titles;
	}

	/**
	 * Finalize campaigns whose queue has drained.
	 */
	private static function finalize_orphans(): void {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID IN (
					SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ('generating','publishing')
				)",
				'lpgen_campaign',
				'_lpgen_status'
			)
		);
		foreach ( (array) $rows as $row ) {
			$id = (int) $row->ID;
			if ( Database::count_jobs( $id, 'queued' ) > 0 || Database::count_jobs( $id, 'running' ) > 0 ) {
				continue;
			}
			$status = (string) ContentRepository::get_meta( $id, 'status', '' );
			$next   = 'generating' === $status ? 'review' : ( 'publishing' === $status ? 'published' : '' );
			if ( '' !== $next ) {
				update_post_meta( $id, '_lpgen_status', $next );
				Database::log( 'info', 'Campaign status â†’ ' . $next, $id );
				if ( 'review' === $next ) {
					$campaign = ContentRepository::get_campaign( $id );
					if ( ! empty( $campaign ) && self::auto_publish_enabled( $campaign ) ) {
						$result = self::start_publish( $id );
						Database::log(
							$result['ok'] ? 'info' : 'warning',
							'Auto publish: ' . $result['message'],
							$id
						);
					}
				}
			}
		}
	}

	/**
	 * Acquire a simple lock so only one job runs at a time.
	 *
	 * @return bool
	 */
	private static function acquire_lock(): bool {
		// add_option() is atomic, unlike get/set_transient; safe as a single-job lock.
		return (bool) add_option( 'lpgen_job_lock', gmdate( 'Y-m-d H:i:s' ), '', 'no' );
	}

	/**
	 * Release the job lock.
	 */
	private static function release_lock(): void {
		delete_option( 'lpgen_job_lock' );
	}
}