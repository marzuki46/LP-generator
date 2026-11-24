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

		$settings = get_option( 'lpgen_settings', array() );
		if ( empty( $settings['ninerouter_url'] ) ) { return array( 'ok' => false, 'jobs' => 0, 'message' => __( 'Isi 9Router Base URL pada Settings → AI sebelum memulai generate.', 'lp-generator' ) ); }

		if ( 'existing' === $campaign['bofu_mode'] && '' === $campaign['bofu_url'] ) {
			return array( 'ok' => false, 'message' => __( 'Pilih halaman BOFU yang sudah terbit terlebih dahulu.', 'lp-generator' ), 'jobs' => 0 );
		}
		if ( Database::count_jobs( $campaign_id, 'running' ) > 0 ) {
			return array( 'ok' => false, 'message' => __( 'Tunggu job aktif selesai sebelum memulai ulang.', 'lp-generator' ), 'jobs' => 0 );
		}
		if ( 'manual' === $campaign['topic_mode'] ) {
			try {
				$exclude = array_map( static fn( array $item ): int => (int) $item['id'], ContentRepository::items( $campaign_id ) );
				$seen = array();
				foreach ( array_merge( $campaign['manual_mofu_topics'], $campaign['manual_tofu_topics'] ) as $title ) {
					$key = FunnelContent::normalize( (string) $title );
					if ( isset( $seen[ $key ] ) ) { throw new \RuntimeException( 'Topik manual tidak boleh berulang dalam satu campaign.' ); }
					$seen[ $key ] = true;
					FunnelContent::assert_unique( (string) $title, '', $exclude, true );
				}
			} catch ( \RuntimeException $e ) {
				return array( 'ok' => false, 'message' => $e->getMessage(), 'jobs' => 0 );
			}
		}
		Database::clear_pending_jobs( $campaign_id );
		ContentRepository::delete_items( $campaign_id );

		$items    = array();
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( empty( $campaign['framework_plan'] ) ) {
			$items[] = array( 'id' => 0, 'kind' => 'generate_framework', 'stage' => 'bofu', 'index' => 0 );
		}

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

		// 1. BOFU landing page (only if bofu_mode is 'new').
		$bofu_mode = (string) ( $campaign['bofu_mode'] ?? 'new' );
		if ( 'new' === $bofu_mode ) {
			$lp_id    = ContentRepository::create_item( $campaign_id, 'bofu', 'lp', 0 );
			$items[]  = array( 'id' => $lp_id, 'kind' => 'generate_lp', 'stage' => 'bofu', 'index' => 0 );
		}

		// 2. MOFU topics (manual or automatic).
		$topic_mode  = (string) ( $campaign['topic_mode'] ?? 'auto' );
		$mofu_topics = array();
		if ( 'manual' === $topic_mode && ! empty( $campaign['manual_mofu_topics'] ) ) {
			$manual_mofu = array_values( (array) $campaign['manual_mofu_topics'] );
			foreach ( $manual_mofu as $i => $title ) {
				$title = trim( (string) $title );
				if ( '' === $title ) {
					continue;
				}
				$id = ContentRepository::create_item( $campaign_id, 'mofu', 'topic', $i );
				ContentRepository::set_item_status( $id, 'approved' );
				wp_update_post( array( 'ID' => $id, 'post_title' => $title, 'post_content' => Publisher::paragraph_block( $title ) ) );
				update_post_meta( $id, '_lpgen_structured', wp_json_encode( array( 'title' => $title, 'angle' => $title ) ) );
				$mofu_topics[] = $id;
			}
		} else {
			for ( $i = 0; $i < (int) $campaign['mofu_count']; $i++ ) {
				$id            = ContentRepository::create_item( $campaign_id, 'mofu', 'topic', $i );
				$mofu_topics[] = $id;
				$items[]       = array( 'id' => $id, 'kind' => 'generate_topic', 'stage' => 'mofu', 'index' => $i );
			}
		}

		// 3. MOFU articles, each bound to its topic.
		foreach ( $mofu_topics as $i => $topic_id ) {
			$id      = ContentRepository::create_item( $campaign_id, 'mofu', 'article', $i );
			$items[] = array(
				'id'            => $id,
				'kind'          => 'generate_article',
				'stage'         => 'mofu',
				'index'         => $i,
				'topic_item_id' => $topic_id,
				'target_stage'  => 'bofu',
			);
		}

		// 4. TOFU topics (manual or automatic).
		$tofu_topics = array();
		if ( 'manual' === $topic_mode && ! empty( $campaign['manual_tofu_topics'] ) ) {
			$manual_tofu = array_values( (array) $campaign['manual_tofu_topics'] );
			foreach ( $manual_tofu as $i => $title ) {
				$title = trim( (string) $title );
				if ( '' === $title ) {
					continue;
				}
				$id = ContentRepository::create_item( $campaign_id, 'tofu', 'topic', $i );
				ContentRepository::set_item_status( $id, 'approved' );
				wp_update_post( array( 'ID' => $id, 'post_title' => $title, 'post_content' => Publisher::paragraph_block( $title ) ) );
				update_post_meta( $id, '_lpgen_structured', wp_json_encode( array( 'title' => $title, 'angle' => $title ) ) );
				$tofu_topics[] = $id;
			}
		} else {
			for ( $i = 0; $i < (int) $campaign['tofu_count']; $i++ ) {
				$id            = ContentRepository::create_item( $campaign_id, 'tofu', 'topic', $i );
				$tofu_topics[] = $id;
				$items[]       = array( 'id' => $id, 'kind' => 'generate_topic', 'stage' => 'tofu', 'index' => $i );
			}
		}

		// 5. TOFU articles.
		foreach ( $tofu_topics as $i => $topic_id ) {
			$id      = ContentRepository::create_item( $campaign_id, 'tofu', 'article', $i );
			$items[] = array(
				'id'            => $id,
				'kind'          => 'generate_article',
				'stage'         => 'tofu',
				'index'         => $i,
				'topic_item_id' => $topic_id,
				'target_stage'  => 'mofu',
			);
		}

		$gap_minutes = max( 0, min( 60, (int) ( $settings['content_gap_minutes'] ?? 2 ) ) );
		foreach ( $items as $sequence => $job ) {
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
					'run_at'      => gmdate( 'Y-m-d H:i:s', time() + ( $sequence * $gap_minutes * MINUTE_IN_SECONDS ) ),
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

	/** Clear every queued/running job and start one fresh generation run. */
	public static function restart( int $campaign_id ): array {
		if ( $campaign_id <= 0 ) {
			return array( 'ok' => false, 'message' => __( 'Campaign tidak valid.', 'lp-generator' ), 'jobs' => 0 );
		}
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			$table = Database::table( 'jobs' );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE campaign_id = %d AND status IN ('queued','running','failed')",
					$campaign_id
				)
			);
		}
		return self::start( $campaign_id );
	}

	/**
	 * Generate a complete, validated framework plan for this campaign in one AI call.
	 *
	 * The selected preset order is preserved. AI specializes its section goals,
	 * layouts and image roles to the offer; custom AI mode invents its own steps.
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
			$result = AiClient::generate( Prompts::build_framework_plan( $campaign ) );
			$generated = Frameworks::validate_plan( $result['structured'], $campaign );
			$label = $generated['label'];
			$steps = $generated['steps'];
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
	 * Enqueue publish jobs for approved items, BOFU → MOFU → TOFU.
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
		if ( 'new' === (string) ( $campaign['bofu_mode'] ?? 'new' )
			&& '' !== trim( (string) ( $campaign['bofu_path'] ?? '' ) )
			&& false !== strpos( (string) $campaign['bofu_path'], '/' )
			&& 'page' !== (string) ( $campaign['lp_post_type'] ?? '' ) ) {
			update_post_meta( $campaign_id, '_lpgen_lp_post_type', 'page' );
			$campaign['lp_post_type'] = 'page';
			Database::log( 'warning', 'Path LP bertingkat terdeteksi; post type LP otomatis diubah ke Page sebelum publish.', $campaign_id );
		}
		if ( ! self::content_push_active( $campaign ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Push konten campaign sedang nonaktif atau sudah melewati tanggal akhir.', 'lp-generator' ),
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
			Database::log( 'debug', sprintf( 'Job %s dimulai (attempt %d)', $job['kind'], (int) ( $job['attempts'] ?? 0 ) ), $campaign_id, $job_id );

			try {
				self::dispatch( $job );
				Database::update_job( $job_id, array( 'status' => 'done' ) );
				Database::log( 'debug', sprintf( 'Job %s selesai', $job['kind'] ), $campaign_id, $job_id );
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
			case 'generate_framework':
				$result = self::generate_framework( (int) $job['campaign_id'] );
				if ( ! $result['ok'] ) { throw new \RuntimeException( $result['message'] ); }
				break;
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
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$payload = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		$payload = is_array( $payload ) ? $payload : array();
		$stage   = (string) ( $payload['stage'] ?? $item['stage'] );
		$kind    = (string) $item['kind'];
		$context = $campaign;
		$context['stage']            = $stage;
		$context['item_index']       = (int) ( $payload['index'] ?? $item['index'] );
		$context['existing_titles'] = FunnelContent::titles( array( $item_id ) );
		$areas = (array) ( $campaign['target_areas'] ?? array() );
		$context['content_area'] = $areas ? $areas[ $context['item_index'] % count( $areas ) ] : (string) ( $campaign['city'] ?? '' );
		$context['target_stage'] = 'bofu';

		if ( 'article' === $kind ) {
			$topic_item_id = (int) ( $payload['topic_item_id'] ?? 0 );
			$topic         = $topic_item_id > 0 ? ContentRepository::get_item( $topic_item_id ) : array();
			$topic_json    = json_decode( (string) ( $topic['structured'] ?? '' ), true );
			$topic_json    = is_array( $topic_json ) ? $topic_json : array();
			if ( empty( $topic_json['title'] ) || ! in_array( (string) ( $topic['status'] ?? '' ), array( 'generated', 'approved', 'published' ), true ) ) {
				throw new \RuntimeException( 'Topik belum berhasil dibuat/disetujui; artikel ditahan.' );
			}
			update_post_meta( $item_id, '_lpgen_source_topic_id', $topic_item_id );
			$context['topic_title']       = (string) ( $topic['title'] ?? '' );
			$context['topic_angle']       = (string) ( $topic_json['angle'] ?? '' );
			$context['topic_outline']     = (array) ( $topic_json['outline'] ?? array() );
			$context['topic_pain_points'] = (array) ( $topic_json['pain_points'] ?? array() );
			$context['topic_target_table'] = (array) ( $topic_json['target_table'] ?? array() );
			$context = FunnelContent::prepare( $context, $item_id );
			$prompt = Prompts::build_article( $context );
		} elseif ( 'topic' === $kind ) {
			if ( 'new' === (string) ( Orchestrator::state( $item_id )['stage'] ?? '' ) ) {
				Orchestrator::transition( $item_id, 'new', 'research', array( 'topic' => $context['topic_title'] ?? '' ) );
			}
			$prompt = Prompts::build_topic( $context );
		} else {
			if ( empty( $campaign['framework_plan'] ) ) {
				Database::log( 'warning', 'Rencana framework tidak cocok dengan data campaign; regenerate otomatis sebelum LP.', $campaign_id );
				$regenerated = self::generate_framework( $campaign_id );
				$campaign    = ContentRepository::get_campaign( $campaign_id );
				foreach ( array( 'ai_framework', 'framework_plan', 'framework_slug', 'framework', 'framework_steps', 'framework_hints', 'framework_image_roles', 'framework_image_keywords' ) as $framework_key ) {
					$context[ $framework_key ] = $campaign[ $framework_key ] ?? '';
				}
				if ( empty( $campaign['framework_plan'] ) ) {
					throw new \RuntimeException( 'Framework campaign gagal disiapkan ulang: ' . ( $regenerated['message'] ?? 'data offer berubah' ) );
				}
			}
			$context = FunnelContent::prepare( $context, $item_id );
			$prompt = Prompts::build_lp( $context );
		}

		$schema = 'topic' === $kind ? StructuredOutput::topic_schema() : ( 'article' === $kind ? StructuredOutput::article_schema() : array() );
		if ( $schema ) {
			$result = StructuredOutput::generate_validated( $prompt, $schema, (array) ( $campaign['research_sources'] ?? array() ), 1 );
			if ( ! empty( $result['validation_errors'] ) ) {
				throw new \RuntimeException( 'Rencana AI tidak lolos validasi: ' . implode( '; ', $result['validation_errors'] ) );
			}
		} else {
			$result = AiClient::generate( $prompt );
		}
		if ( 'topic' !== $kind ) {
			$result['structured']['entities'] = $context['entities'];
			$result['structured'] = self::clean_ai_copy( $result['structured'], $kind, (array) ( $context['topic_target_table'] ?? array() ) );
		}
		if ( 'lp' === $kind ) { $result['structured'] = DesignContract::bind_content( $result['structured'], $campaign ); }
		FunnelContent::validate_content( $result['structured'], $kind );
		if ( 'topic' === $kind ) {
			if ( 'research' === (string) ( Orchestrator::state( $item_id )['stage'] ?? '' ) ) {
				Orchestrator::transition( $item_id, 'research', 'outline' );
			}
			Orchestrator::checkpoint( $item_id, 'outline', array_merge( $result['structured'], array( 'review_requested' => ! empty( $settings['outline_review_enabled'] ) ) ) );
			Orchestrator::transition( $item_id, 'outline', 'validated', array( 'attempts' => (int) ( $result['attempts'] ?? 1 ), 'review' => ! empty( $settings['outline_review_enabled'] ) ) );
			Database::log( 'info', sprintf( 'Outline #%d lolos validasi (%d attempt)', $item_id, (int) ( $result['attempts'] ?? 1 ) ), $campaign_id );
		}
		if ( 'article' === $kind && 'validated' === (string) ( Orchestrator::state( $item_id )['stage'] ?? '' ) ) {
			Orchestrator::transition( $item_id, 'validated', 'writing' );
		}
		if ( 'lp' === $kind ) { LandingPage::validate( $result['structured'], $campaign ); $result['structured']['framework_fingerprint'] = Frameworks::fingerprint( $campaign ); }
		$preview = Publisher::build_blocks( $result['structured'], $kind, $campaign );
		FunnelContent::assert_unique( (string) ( $result['structured']['title'] ?? '' ), $preview, FunnelContent::exclusions( $item_id ), 'topic' === $kind );
		if ( 'article' === $kind ) { FunnelContent::anchor( (string) ( $result['structured']['anchor_suggestion'] ?? '' ) ); }
		$context['content_tags'] = Utils::sanitize_tags(
			$result['structured']['tags'] ?? array(),
			max( 1, min( 3, (int) ( $campaign['tag_max'] ?? 3 ) ) )
		);
		$images = 'topic' === $kind
			? array()
			: ImagePipeline::prepare_for_item( $campaign_id, $item_id, $context );
		$blocks = Publisher::build_blocks( $result['structured'], $kind, $campaign, $images );
		ContentRepository::save_item_result( $item_id, $result['structured'], $result['model'], $result['tokens'], $blocks );
		self::save_lp_spec( $item_id, $result['structured'], $campaign, $context, 'topic' === $kind ? 'outline' : 'write' );
		if ( 'topic' === $kind ) { TopicMemory::add_from_item( $item_id ); }
		if ( 'writing' === (string) ( Orchestrator::state( $item_id )['stage'] ?? '' ) ) {
			Orchestrator::transition( $item_id, 'writing', 'review' );
		}
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
		if ( ! self::content_push_active( $campaign ) ) {
			Database::log( 'info', 'Publish dilewati: campaign melewati batas push konten', $campaign_id );
			return;
		}

		$target = '';
		if ( 'article' === $item['kind'] && self::internal_link_active( $campaign ) ) {
			$target = self::find_published_permalink( $campaign_id, 'bofu' );
			if ( '' === $target ) {
				$waits = absint( get_post_meta( $item_id, '_lpgen_bofu_wait', true ) );
				if ( $waits < 3 ) {
					$payload = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
					$payload = is_array( $payload ) ? $payload : array();
					update_post_meta( $item_id, '_lpgen_bofu_wait', $waits + 1 );
					Database::insert_job(
						array(
							'campaign_id' => $campaign_id,
							'item_id'     => $item_id,
							'kind'        => 'publish',
							'payload'     => $payload,
							'run_at'      => gmdate( 'Y-m-d H:i:s', time() + ( 10 * MINUTE_IN_SECONDS ) ),
						)
					);
					Database::log(
						'warning',
						sprintf( 'Artikel #%d ditunda 10 menit menunggu LP terbit (%d/3).', $item_id, $waits + 1 ),
						$campaign_id,
						(int) ( $job['id'] ?? 0 )
					);
					return;
				}
				throw new \RuntimeException( 'BOFU belum terbit. Artikel ditahan agar tidak kehilangan link ke LP tujuan.' );
			}
			delete_post_meta( $item_id, '_lpgen_bofu_wait' );
			if ( 'next_stage' === (string) ( $campaign['internal_link_target'] ?? '' ) && 'tofu' === $item['stage'] ) {
				$campaign['secondary_funnel_url'] = self::find_published_permalink( $campaign_id, 'mofu' );
			}
		}
		$post_id = Publisher::publish_item( $item_id, $campaign, $target );
		if ( 0 === $post_id ) {
			throw new \RuntimeException( 'wp_insert_post_failed' );
		}
		$permalink = (string) get_permalink( $post_id );
		Database::log( 'success', sprintf( 'Item %d published as post %d: %s', $item_id, $post_id, $permalink ), $campaign_id );
		if ( 'article' === (string) ( $item['kind'] ?? '' ) ) { TopicMemory::add_from_item( $item_id ); }
		if ( 'lp' === (string) ( $item['kind'] ?? '' ) ) {
			// BOFU finally exists: release every article/tag that was waiting for it.
			foreach ( ContentRepository::items( $campaign_id ) as $pending ) {
				$pending_id = (int) $pending['id'];
				if ( $pending_id === $item_id || 'approved' !== (string) $pending['status'] || absint( get_post_meta( $pending_id, '_lpgen_bofu_wait', true ) ) < 1 ) {
					continue;
				}
				$last = Database::last_job_for_item( $pending_id );
				if ( $last && 'publish' === (string) $last['kind'] && 'queued' === (string) $last['status'] ) {
					continue;
				}
				Database::insert_job(
					array(
						'campaign_id' => $campaign_id,
						'item_id'     => $pending_id,
						'kind'        => 'publish',
						'payload'     => array( 'stage' => (string) $pending['stage'], 'sequence' => 0 ),
						'run_at'      => gmdate( 'Y-m-d H:i:s', time() + 30 ),
					)
				);
				Database::log( 'info', sprintf( 'Artikel #%d dilanjutkan setelah LP terbit.', $pending_id ), $campaign_id );
			}
		}
	}

	/**
	 * Whether campaign content may still be published.
	 */
	private static function content_push_active( array $campaign ): bool {
		if ( array_key_exists( 'content_push_enabled', $campaign ) && empty( $campaign['content_push_enabled'] ) ) {
			return false;
		}
		$until = trim( (string) ( $campaign['content_push_until'] ?? '' ) );
		return '' === $until || $until >= current_time( 'Y-m-d' );
	}

	/**
	 * Whether article-to-BOFU internal linking is still enabled.
	 */
	private static function internal_link_active( array $campaign ): bool {
		if ( array_key_exists( 'internal_link_enabled', $campaign ) && empty( $campaign['internal_link_enabled'] ) ) {
			return false;
		}
		$until = trim( (string) ( $campaign['internal_link_until'] ?? '' ) );
		return '' === $until || $until >= current_time( 'Y-m-d' );
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

		$context['item_index'] = (int) $item['index'];
		$context['topic_title'] = $tag;
		$context = FunnelContent::prepare( $context, $item_id );
		$result = AiClient::generate( Prompts::build_tag_article( $context ) );
		$result['structured']['entities'] = $context['entities'];
		FunnelContent::validate_content( $result['structured'], 'article' );
		FunnelContent::anchor( (string) ( $result['structured']['anchor_suggestion'] ?? '' ) );
		FunnelContent::assert_unique( (string) $result['structured']['title'], Publisher::build_blocks( $result['structured'], 'article', $campaign ), FunnelContent::exclusions( $item_id ) );
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
	 * Find the first published permalink in a stage, or existing BOFU page if configured.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $stage       Stage.
	 * @return string
	 */
	private static function find_published_permalink( int $campaign_id, string $stage ): string {
		if ( 'bofu' === $stage ) {
			$campaign = ContentRepository::get_campaign( $campaign_id );
			if ( 'existing' === (string) ( $campaign['bofu_mode'] ?? 'new' ) && ! empty( $campaign['bofu_existing_id'] ) ) {
				$link = get_permalink( (int) $campaign['bofu_existing_id'] );
				if ( 'publish' === get_post_status( (int) $campaign['bofu_existing_id'] ) && ! empty( $link ) && false !== wp_http_validate_url( $link ) ) {
					return $link;
				}
			}
		}
		foreach ( ContentRepository::items( $campaign_id, $stage ) as $item ) {
			if ( 'published' === (string) $item['status'] && 'publish' === get_post_status( (int) $item['post_id'] ) && '' !== (string) $item['permalink'] ) {
				return (string) $item['permalink'];
			}
		}
		return '';
	}

	/** Replace placeholder/failed images for one item without regenerating the copy. */
	public static function regenerate_images( int $item_id ): array {
		$item = ContentRepository::get_item( $item_id );
		if ( empty( $item ) ) {
			return array( 'ok' => false, 'message' => __( 'Item tidak ditemukan.', 'lp-generator' ) );
		}
		$campaign_id = (int) ( $item['campaign_id'] ?? 0 );
		$campaign    = ContentRepository::get_campaign( $campaign_id );
		if ( empty( $campaign ) ) {
			return array( 'ok' => false, 'message' => __( 'Campaign tidak ditemukan.', 'lp-generator' ) );
		}
		$kept   = array();
		$specs  = json_decode( (string) get_post_meta( $item_id, '_lpgen_image_specs', true ), true );
		foreach ( is_array( $specs ) ? $specs : array() as $spec ) {
			$status = (string) ( $spec['status'] ?? '' );
			if ( in_array( $status, array( 'placeholder', 'failed' ), true ) ) {
				ImagePipeline::clear_keyword_failure( (string) ( $spec['keyword'] ?? '' ) );
				continue;
			}
		}
		foreach ( ContentRepository::item_images( $item_id ) as $image ) {
			if ( in_array( (string) ( $image['visual_status'] ?? '' ), array( 'placeholder', 'failed' ), true ) ) {
				continue;
			}
			$kept[] = $image;
		}
		ContentRepository::save_item_images( $item_id, $kept );
		$context          = $campaign;
		$context['stage'] = (string) ( $item['stage'] ?? 'mofu' );
		$context['topic_title'] = (string) ( $item['title'] ?? '' );
		$context['entities'] = json_decode( (string) ( $item['entities'] ?? '' ), true );
		$context['entities'] = is_array( $context['entities'] ) ? $context['entities'] : array();
		$images = ImagePipeline::prepare_for_item( $campaign_id, $item_id, $context );
		return array(
			'ok'      => true,
			'message' => sprintf( __( '%d gambar item #%d diperbarui.', 'lp-generator' ), count( $images ), $item_id ),
			'count'   => count( $images ),
		);
	}

	/** Persist a canonical, normalised spec and its warnings for one item. */
	private static function save_lp_spec( int $item_id, array $structured, array $campaign, array $context, string $step ): void {
		$spec = LpSpec::normalize( array_merge( $structured, array( 'entities' => $context['entities'] ?? array() ) ), $campaign, $item_id );
		update_post_meta( $item_id, '_lpgen_spec', wp_json_encode( $spec ) );
		$warnings = array();
		foreach ( $spec['sections'] as $section ) {
			if ( '' === trim( (string) $section['heading_goal'] ) ) {
				$warnings[] = 'authored=' . $section['id'] . '.heading; delivered=derived; disposition=heading derived from hint';
			}
			if ( '' === trim( (string) $section['image_keyword'] ) ) {
				$warnings[] = 'authored=' . $section['id'] . '.image_keyword; delivered=empty; disposition=image brief left to engine';
			}
		}
		if ( $warnings ) {
			LpWarnings::replace( $item_id, $step, $warnings );
		}
	}

	/** Strip framework labels and convert pipe tables into structured HTML-ready cells. */
	private static function clean_ai_copy( array $data, string $kind, array $planned_table = array() ): array {
		foreach ( array( 'title', 'intro', 'seo_title', 'meta_description', 'cta_text', 'cta_paragraph', 'anchor_suggestion' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$data[ $key ] = FunnelContent::strip_framework_labels( $data[ $key ] );
			}
		}
		$sections = isset( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : array();
		foreach ( $sections as $index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			foreach ( array( 'heading', 'body' ) as $key ) {
				if ( isset( $section[ $key ] ) && is_string( $section[ $key ] ) ) {
					$section[ $key ] = FunnelContent::strip_framework_labels( $section[ $key ] );
				}
			}
			if ( isset( $section['body'] ) && is_string( $section['body'] ) ) {
				$extracted = FunnelContent::extract_table( $section['body'] );
				$section['body'] = $extracted[0];
				if ( ! empty( $extracted[1] ) && empty( $section['table'] ) ) {
					$section['table'] = array( 'columns' => $extracted[1], 'rows' => $extracted[2] );
				}
			}
			$sections[ $index ] = $section;
		}
		if ( 'article' === $kind && ! empty( $planned_table['columns'] ) && is_array( $planned_table['rows'] ?? null ) ) {
			$attached = false;
			foreach ( $sections as $index => $section ) {
				if ( ! empty( $section['table'] ) ) {
					$attached = true;
					break;
				}
			}
			if ( ! $attached && $sections ) {
				$sections[0]['table'] = array(
					'columns' => array_values( array_map( 'strval', $planned_table['columns'] ) ),
					'rows'    => array_values( array_map( static function ( $row ): array { return is_array( $row ) ? array_values( array_map( 'strval', $row ) ) : array(); }, $planned_table['rows'] ) ),
				);
			}
		}
		$data['sections'] = $sections;
		return $data;
	}

	/** Anchor used by every support article: the campaign LP target keyword. */
	public static function internal_anchor( int $item_id, array $campaign ): string {
		$target = trim( (string) ( $campaign['lp_target_keyword'] ?? '' ) );
		if ( '' === $target ) {
			$target = trim( (string) ( $campaign['offer_name'] ?? '' ) . ' ' . ( string ) ( $campaign['city'] ?? '' ) );
		}
		$target = Utils::truncate( sanitize_text_field( $target ), 100 );
		$words  = preg_split( '/\s+/u', $target ) ?: array();
		if ( count( $words ) > 10 ) {
			$target = implode( ' ', array_slice( $words, 0, 10 ) );
		}
		try {
			return FunnelContent::anchor( $target );
		} catch ( \RuntimeException $e ) {
			return FunnelContent::anchor( (string) get_post_meta( $item_id, '_lpgen_anchor', true ) );
		}
	}

	/**
	 * Process one job in the queue (for real-time AJAX runner).
	 *
	 * @param int $campaign_id Optional campaign ID.
	 * @return array{ok:bool,done:bool,remaining:int,message:string,job_kind?:string,busy?:bool}
	 */
	public static function process_one( int $campaign_id = 0 ): array {
		if ( ! self::acquire_lock() ) {
			return array(
				'ok'        => false,
				'done'      => false,
				'busy'      => true,
				'remaining' => Database::count_jobs( $campaign_id, 'queued' ),
				'message'   => __( 'Worker sedang sibuk. Mencoba lagi...', 'lp-generator' ),
			);
		}

		try {
			$job = Database::get_next_job( $campaign_id > 0 ? $campaign_id : null );
			if ( null === $job ) {
				self::finalize_orphans();
				$snapshot = Database::queue_snapshot( $campaign_id );
				return array_merge( $snapshot, array(
					'ok' => $snapshot['total'] > 0,
					'waiting' => $snapshot['remaining'] > 0 || $snapshot['running'] > 0,
					'message' => $snapshot['total'] > 0 ? __( 'Menunggu jadwal antrean berikutnya atau antrean selesai.', 'lp-generator' ) : __( 'Belum ada antrean untuk campaign ini. Klik Generate terlebih dahulu.', 'lp-generator' ),
				) );
			}

			if ( ! Database::claim_job( (int) $job['id'] ) ) {
				return array(
					'ok'        => false,
					'done'      => false,
					'busy'      => true,
					'remaining' => Database::count_jobs( $campaign_id, 'queued' ),
					'failed' => Database::count_jobs( $campaign_id, 'failed' ),
					'message'   => __( 'Job telah diambil oleh proses lain.', 'lp-generator' ),
				);
			}

			$job_campaign = (int) $job['campaign_id'];
			$job_id       = (int) $job['id'];
			Database::log( 'debug', sprintf( 'Job %s dimulai via runner (attempt %d)', $job['kind'], (int) ( $job['attempts'] ?? 0 ) ), $job_campaign, $job_id );

			$outcome = 'done';
			try {
				self::dispatch( $job );
				Database::update_job( $job_id, array( 'status' => 'done' ) );
				Database::log( 'debug', sprintf( 'Job %s selesai', $job['kind'] ), $job_campaign, $job_id );
			} catch ( \Throwable $e ) {
				$outcome = 'retry';
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
				} else {
					$outcome = 'failed';
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
				}
				Database::log( 'error', 'Job gagal: ' . $message, $job_campaign, $job_id );
			}

			$remaining = Database::count_jobs( $job_campaign, 'queued' );
			if ( 0 === $remaining ) {
				self::finalize_orphans();
			}

			$snapshot = Database::queue_snapshot( $job_campaign );
			return array_merge( $snapshot, array(
				'ok' => true,
				'outcome' => $outcome,
				'job_kind' => (string) $job['kind'],
				'message' => 'done' === $outcome ? sprintf( __( 'Job %s selesai. Sisa: %d.', 'lp-generator' ), $job['kind'], $snapshot['remaining'] ) : __( 'Job belum berhasil. Antrean akan mencoba ulang sesuai batas retry; periksa Activity Log.', 'lp-generator' ),
			) );
		} finally {
			self::release_lock();
		}
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
			$next   = 'generating' === $status ? 'review' : ( 'publishing' === $status ? ( Database::count_jobs( $id, 'failed' ) > 0 ? 'failed' : 'published' ) : '' );
			if ( '' !== $next ) {
				update_post_meta( $id, '_lpgen_status', $next );
				Database::log( 'info', 'Campaign status → ' . $next, $id );
				if ( 'review' === $next ) {
					$campaign = ContentRepository::get_campaign( $id );
					if ( ! empty( $campaign ) && 0 === Database::count_jobs( $id, 'failed' ) && self::auto_publish_enabled( $campaign ) ) {
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

	/**
	 * Generate campaign suggestions based on keyword/offer name.
	 *
	 * @param string $keyword  Keyword or offer name.
	 * @param string $language Language (id|en).
	 * @return array{ok:bool,message:string,data?:array}
	 */
	public static function suggest_campaign( string $keyword, string $language = 'id' ): array {
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return array(
				'ok'      => false,
				'message' => __( 'Silakan ketik Nama Produk / Keyword terlebih dahulu untuk meminta rekomendasi AI.', 'lp-generator' ),
			);
		}
		$prompt = Prompts::build_campaign_suggestion( $keyword, $language );
		try {
			$result = AiClient::generate( $prompt );
			return array(
				'ok'      => true,
				'message' => __( 'Rekomendasi ide AI berhasil dibuat!', 'lp-generator' ),
				'data'    => $result['structured'],
			);
		} catch ( \RuntimeException $e ) {
			return array(
				'ok'      => false,
				'message' => sprintf( __( 'Gagal meminta rekomendasi AI: %s', 'lp-generator' ), $e->getMessage() ),
			);
		}
	}
}
