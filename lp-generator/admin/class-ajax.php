<?php
/**
 * AJAX endpoints. Every handler verifies nonce AND capability.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ajax
 */
final class Ajax {

	/**
	 * Register all handlers.
	 */
	public static function register(): void {
		$actions = array(
			'start-campaign',
			'restart-campaign',
			'publish-campaign',
			'run-now',
			'process-queue',
			'set-status',
			'regenerate',
			'regenerate-image',
			'generate-tag-content',
			'generate-entities',
			'test-connection',
			'test-amazon',
			'generate-key',
			'generate-framework',
			'delete-log',
			'clear-logs',
			'create-key-file',
			'check-update',
			'suggest-campaign',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_lpgen_' . str_replace( '-', '_', $action ), array( self::class, 'handle' ) );
		}
	}

	/**
	 * Shared verification.
	 *
	 * @param string $action AJAX action suffix.
	 * @return bool
	 */
	private static function verify( string $action ): bool {
		if ( ! check_ajax_referer( 'lpgen_action', 'nonce', false ) ) {
			self::error( __( 'Nonce tidak valid.', 'lp-generator' ) );
			return false;
		}
		$capability = in_array( $action, array( 'test-connection', 'test-amazon', 'generate-key', 'clear-logs', 'create-key-file', 'check-update' ), true )
			? 'manage_options'
			: 'lpgen_manage';
		if ( ! current_user_can( $capability ) ) {
			self::error( __( 'Anda tidak memiliki izin.', 'lp-generator' ) );
			return false;
		}
		return true;
	}

	/**
	 * Dispatch the current AJAX action.
	 */
	public static function handle(): void {
		$action = isset( $_POST['action'] ) ? sanitize_key( (string) wp_unslash( $_POST['action'] ) ) : '';
		$action = 0 === strpos( $action, 'lpgen_' ) ? substr( $action, 6 ) : $action;
		$action = str_replace( '_', '-', $action );
		if ( ! self::verify( $action ) ) {
			return;
		}
		try {
		switch ( $action ) {
			case 'restart-campaign':
			case 'start-campaign':
				$campaign_id = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
				// Auto-save form payload if sent directly from the Campaign UI.
				if ( $campaign_id > 0 && isset( $_POST['lpgen'] ) && is_array( $_POST['lpgen'] ) ) {
					$raw = wp_unslash( $_POST['lpgen'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					CampaignUI::save_meta_data( $campaign_id, (array) $raw );
					if ( ! empty( $_POST['post_title'] ) ) {
						$raw_title = sanitize_text_field( (string) wp_unslash( $_POST['post_title'] ) );
						$current   = get_post_field( 'post_title', $campaign_id );
						if ( '' !== $raw_title && ( '' === $current || 'Auto Draft' === $current ) ) {
							wp_update_post( array( 'ID' => $campaign_id, 'post_title' => $raw_title ) );
						}
					}
				}
				$result = 'restart-campaign' === $action ? Generator::restart( $campaign_id ) : Generator::start( $campaign_id );
				break;
			case 'suggest-campaign':
				$raw_kw   = isset( $_POST['keyword'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['keyword'] ) ) : '';
				$raw_lang = isset( $_POST['language'] ) ? sanitize_key( (string) wp_unslash( $_POST['language'] ) ) : 'id';
				$result   = Generator::suggest_campaign( $raw_kw, $raw_lang );
				break;
			case 'publish-campaign':
				$result = Generator::start_publish( isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0 );
				break;
			case 'run-now':
				Generator::run_next();
				$result = array(
					'ok'      => true,
					'message' => __( 'Satu job telah dijalankan. Muat ulang untuk melihat progres.', 'lp-generator' ),
				);
				break;
			case 'process-queue':
				$campaign_id = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
				$result      = Generator::process_one( $campaign_id );
				break;
			case 'set-status':
				$item_id = isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0;
				$status  = isset( $_POST['status'] ) ? sanitize_key( (string) wp_unslash( $_POST['status'] ) ) : '';
				$allowed = array( 'approved', 'rejected', 'generated' );
				if ( 0 === $item_id || ! in_array( $status, $allowed, true ) ) {
					self::error( __( 'Data tidak valid.', 'lp-generator' ) );
					return;
				}
				ContentRepository::set_item_status( $item_id, $status );
				$result = array(
					'ok'      => true,
					'message' => sprintf( __( 'Item #%d → %s', 'lp-generator' ), $item_id, $status ),
				);
				break;
			case 'regenerate':
				$result = self::regenerate( isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0 );
				break;
			case 'regenerate-image':
				$result = Generator::regenerate_images( isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0 );
				break;
			case 'generate-tag-content':
				$raw_tag = isset( $_POST['tag'] ) ? wp_unslash( $_POST['tag'] ) : '';
				$result  = Generator::queue_tag_content(
					isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0,
					is_string( $raw_tag ) ? sanitize_text_field( $raw_tag ) : ''
				);
				break;
			case 'generate-entities':
				$result = Generator::queue_entities( isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0 );
				break;
			case 'generate-framework':
				$campaign_id = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
				if ( $campaign_id > 0 && isset( $_POST['lpgen'] ) && is_array( $_POST['lpgen'] ) ) { CampaignUI::save_meta_data( $campaign_id, (array) wp_unslash( $_POST['lpgen'] ) ); }
				$result = Generator::generate_framework( $campaign_id );
				break;
			case 'test-connection':
				$result = AiClient::test_connection();
				break;
			case 'test-amazon':
				$result = self::test_amazon();
				break;
			case 'generate-key':
				$key      = Utils::generate_index_key();
				$settings = get_option( 'lpgen_settings', array() );
				$settings = is_array( $settings ) ? $settings : array();
				$settings['indexnow_key'] = $key;
				update_option( 'lpgen_settings', $settings );
				$file     = Indexer::ensure_key_file( $key );
				$result   = array(
					'ok'      => $file['ok'],
					'message' => sprintf( __( 'Key baru: %s — %s', 'lp-generator' ), $key, $file['message'] ),
				);
				break;
			case 'delete-log':
				$deleted = Database::delete_log( isset( $_POST['log'] ) ? absint( $_POST['log'] ) : 0 );
				$result  = array(
					'ok'      => $deleted > 0,
					'message' => sprintf( __( '%d log dihapus.', 'lp-generator' ), $deleted ),
					'deleted' => $deleted,
				);
				break;
			case 'clear-logs':
				$args = array( 'all' => ! empty( $_POST['all'] ) );
				if ( ! empty( $_POST['campaign'] ) ) { $args['campaign_id'] = absint( $_POST['campaign'] ); }
				if ( ! empty( $_POST['level'] ) ) { $args['level'] = sanitize_key( (string) wp_unslash( $_POST['level'] ) ); }
				if ( ! empty( $_POST['older_than_days'] ) ) { $args['older_than_days'] = absint( $_POST['older_than_days'] ); }
				$deleted = Database::delete_logs( $args );
				$result = array(
					'ok'      => true,
					'message' => sprintf( __( '%d log dihapus.', 'lp-generator' ), $deleted ),
					'deleted' => $deleted,
				);
				break;
			case 'create-key-file':
				$settings = get_option( 'lpgen_settings', array() );
				$result   = Indexer::create_key_file( (string) ( $settings['indexnow_key'] ?? '' ) );
				break;
			case 'check-update':
				$result = Updater::check_for_updates();
				break;
			default:
				self::error( __( 'Aksi tidak dikenal.', 'lp-generator' ) );
				return;
		}
		} catch ( \Throwable $error ) {
			Database::log( 'error', 'Aksi admin gagal: ' . sanitize_key( $action ), isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0, 0, array( 'error_type' => get_class( $error ) ) );
			$result = array( 'ok' => false, 'code' => 'server_error', 'message' => __( 'Proses gagal di server. Periksa Activity Log. Jika antrean sudah terbentuk, gunakan Proses Antrean untuk melanjutkan.', 'lp-generator' ) );
		}
		wp_send_json( $result );
	}

	/**
	 * Re-queue one item for regeneration.
	 *
	 * @param int $item_id Item ID.
	 * @return array{ok:bool,message:string}
	 */
	private static function regenerate( int $item_id ): array {
		$item = ContentRepository::get_item( $item_id );
		if ( empty( $item ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Item tidak ditemukan.', 'lp-generator' ),
			);
		}
		$old_job = Database::last_job_for_item( $item_id );
		$payload = null !== $old_job ? json_decode( (string) $old_job['payload'], true ) : array();
		$payload = is_array( $payload ) ? $payload : array();
		$kind    = 'generate_' . (string) $item['kind'];
		if ( 'article' === $item['kind'] ) {
			$payload['topic_item_id'] = (int) ( $item['source_topic_id'] ?? 0 );
			$payload['stage'] = (string) $item['stage'];
			$payload['index'] = (int) $item['index'];
			$payload['target_stage'] = 'bofu';
			if ( 'tag' === $item['stage'] ) { $kind = 'generate_tag'; $payload['tag'] = (string) $item['tag']; }
		}
		delete_post_meta( $item_id, '_lpgen_entities' );
		if ( 'lp' === $item['kind'] && empty( ContentRepository::get_campaign( (int) $item['campaign_id'] )['framework_plan'] ) ) {
			Database::insert_job( array( 'campaign_id' => (int) $item['campaign_id'], 'kind' => 'generate_framework', 'payload' => array() ) );
		}

		Database::insert_job(
			array(
				'campaign_id' => (int) $item['campaign_id'],
				'item_id'     => $item_id,
				'kind'        => $kind,
				'payload'     => $payload,
			)
		);
		ContentRepository::set_item_status( $item_id, 'queued' );
		wp_schedule_single_event( time() + 1, 'lpgen_run_job' );
		return array(
			'ok'      => true,
			'message' => sprintf( __( 'Item #%d masuk antrean regenerate.', 'lp-generator' ), $item_id ),
		);
	}

	/**
	 * Test the Amazon/Bing scraper with a safe keyword.
	 *
	 * @return array{ok:bool,message:string}
	 */
	private static function test_amazon(): array {
		$settings = get_option( 'lpgen_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$keyword  = Utils::clean_text( isset( $_POST['keyword'] ) ? wp_unslash( $_POST['keyword'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' === $keyword ) {
			$keyword = 'wireless headphones';
		}
		$products = Amazon::fetch( $keyword, $settings );
		if ( empty( $products ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Tidak ada produk ditemukan. Cek koneksi, region, dan associate tag.', 'lp-generator' ),
			);
		}
		$titles = array();
		foreach ( array_slice( $products, 0, 3 ) as $product ) {
			$titles[] = Utils::truncate( (string) ( $product['title'] ?? '' ), 60 );
		}
		return array(
			'ok'      => true,
			'message' => sprintf( __( '%d produk ditemukan: %s', 'lp-generator' ), count( $products ), implode(' | ', $titles) ),
		);
	}

	/**
	 * Send an error JSON response.
	 *
	 * @param string $message Message.
	 */
	private static function error( string $message ): void {
		wp_send_json(
			array(
				'ok'      => false,
				'message' => $message,
			)
		);
	}
}

