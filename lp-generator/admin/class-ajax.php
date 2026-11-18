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
			'publish-campaign',
			'run-now',
			'set-status',
			'regenerate',
			'generate-tag-content',
			'generate-entities',
			'test-connection',
			'test-amazon',
			'generate-key',
			'generate-framework',
			'delete-log',
			'clear-logs',
			'create-key-file',
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
		$capability = in_array( $action, array( 'test-connection', 'test-amazon', 'generate-key', 'clear-logs', 'create-key-file' ), true )
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
		switch ( $action ) {
			case 'start-campaign':
				$result = Generator::start( isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0 );
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
				$result = Generator::generate_framework( isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0 );
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
				Database::log( 'info', sprintf( 'Log dibersihkan: %d baris', $deleted ), 0, 0, array( 'by' => get_current_user_id(), 'filter' => $args ) );
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
			default:
				self::error( __( 'Aksi tidak dikenal.', 'lp-generator' ) );
				return;
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

