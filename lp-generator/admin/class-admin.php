<?php
/**
 * Admin menus, assets, notices.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
final class Admin {

	/**
	 * Register admin menu pages.
	 */
	public static function register_menus(): void {
		add_menu_page(
			__( 'LP Generator', 'lp-generator' ),
			__( 'LP Generator', 'lp-generator' ),
			'lpgen_manage',
			'lp-generator',
			array( ReviewUI::class, 'render_dashboard' ),
			'dashicons-feedback',
			30
		);
		add_submenu_page(
			'lp-generator',
			__( 'Dashboard', 'lp-generator' ),
			__( 'Dashboard', 'lp-generator' ),
			'lpgen_manage',
			'lp-generator',
			array( ReviewUI::class, 'render_dashboard' )
		);
		add_submenu_page(
			'lp-generator',
			__( 'Campaigns', 'lp-generator' ),
			__( 'Campaigns', 'lp-generator' ),
			'lpgen_manage',
			'edit.php?post_type=lpgen_campaign'
		);
		add_submenu_page(
			'lp-generator',
			__( 'Review Konten', 'lp-generator' ),
			__( 'Review Konten', 'lp-generator' ),
			'lpgen_manage',
			'lpg-review',
			array( ReviewUI::class, 'render_review' )
		);
		add_submenu_page(
			'lp-generator',
			__( 'Logs', 'lp-generator' ),
			__( 'Logs', 'lp-generator' ),
			'lpgen_manage',
			'lpg-logs',
			array( ReviewUI::class, 'render_logs' )
		);
		add_submenu_page(
			'lp-generator',
			__( 'Settings', 'lp-generator' ),
			__( 'Settings', 'lp-generator' ),
			'manage_options',
			'lpg-settings',
			array( Settings::class, 'render_page' )
		);
	}

	/**
	 * Enqueue plugin assets on our screens only.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_plugin_screen = false !== strpos( $hook, 'lp-generator' )
			|| false !== strpos( $hook, 'lpg-' )
			|| ( $screen && in_array( $screen->post_type, array( 'lpgen_campaign', 'lpgen_item' ), true ) );
		if ( ! $is_plugin_screen ) {
			return;
		}
		wp_enqueue_style( 'lpgen-admin', LPG_URL . 'admin/css/admin.css', array( 'dashicons' ), LPG_VERSION );
		wp_enqueue_script( 'lpgen-admin', LPG_URL . 'admin/js/admin.js', array( 'jquery' ), LPG_VERSION, true );
		wp_localize_script(
			'lpgen-admin',
			'lpgenAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lpgen_action' ),
				'i18n'    => array(
					'confirmStart'    => __( 'Mulai generate funnel ini? Konten lama akan diganti.', 'lp-generator' ),
					'confirmPublish'  => __( 'Publish semua item yang di-approve dan submit ke indexer?', 'lp-generator' ),
					'confirmDelete'   => __( 'Yakin?', 'lp-generator' ),
					'running'         => __( 'Memproses…', 'lp-generator' ),
				),
			)
		);
	}

	/**
	 * Admin notices for review-ready campaigns.
	 */
	public static function notices(): void {
		if ( ! current_user_can( 'lpgen_manage' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'lpgen_campaign' === $screen->post_type ) {
			return;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'lpgen_campaign',
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'meta_key'       => '_lpgen_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'review', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( empty( $query->posts ) ) {
			return;
		}
		foreach ( $query->posts as $post ) {
			$url = admin_url( 'admin.php?page=lpg-review&campaign_id=' . absint( $post->ID ) );
			echo '<div class="notice notice-info is-dismissible"><p>'
				. esc_html__( 'LP Generator: funnel siap direview —', 'lp-generator' )
				. ' <a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></p></div>';
		}
	}
}
