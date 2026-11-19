<?php
/**
 * Main plugin bootstrap class.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Register hooks.
	 */
	public function run(): void {
		add_action( 'init', array( ContentRepository::class, 'register_post_types' ) );
		add_action( 'lpgen_run_job', array( Generator::class, 'run_next' ) );
		add_action( 'lpgen_daily_cleanup', array( Database::class, 'prune_logs' ) );

		add_action( 'admin_menu', array( Admin::class, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( Admin::class, 'enqueue_assets' ) );
		Settings::hook();
		add_action( 'wp_head', array( SeoStandards::class, 'meta_description' ), 2 );
		add_filter( 'pre_get_document_title', array( SeoStandards::class, 'document_title' ) );
		add_filter( 'wp_kses_allowed_html', array( LandingPage::class, 'allow_table_focus' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( LandingPage::class, 'prepare_canvas_assets' ), PHP_INT_MAX );
		add_filter( 'template_include', array( LandingPage::class, 'template' ), 99 );
		add_action( 'enqueue_block_assets', array( LandingPage::class, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( LandingPage::class, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', array( CampaignUI::class, 'register_meta_box' ) );
		add_action( 'save_post_lpgen_campaign', array( CampaignUI::class, 'save_meta' ), 10, 2 );
		add_action( 'admin_notices', array( Admin::class, 'notices' ) );

		Ajax::register();
		Updater::hook();
	}
}
