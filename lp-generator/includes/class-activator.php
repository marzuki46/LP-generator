<?php
/**
 * Activation routines.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Run on activation.
	 */
	public static function activate(): void {
		global $wp_version;

		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( LPG_FILE );
			wp_die(
				esc_html__( 'LP Generator membutuhkan PHP 8.1 atau lebih tinggi.', 'lp-generator' ),
				esc_html__( 'Aktivasi gagal', 'lp-generator' ),
				array( 'back_link' => true )
			);
		}
		if ( version_compare( (string) $wp_version, '6.4', '<' ) ) {
			deactivate_plugins( LPG_FILE );
			wp_die(
				esc_html__( 'LP Generator membutuhkan WordPress 6.4 atau lebih tinggi.', 'lp-generator' ),
				esc_html__( 'Aktivasi gagal', 'lp-generator' ),
				array( 'back_link' => true )
			);
		}

		Database::create_tables();
		update_option( 'lpgen_db_version', LPG_DB_VERSION );

		if ( false === get_option( 'lpgen_settings' ) ) {
			add_option(
				'lpgen_settings',
				array(
					'ninerouter_url'         => '',
					'ninerouter_key'         => '',
					'api_format'             => 'openai',
					'models'                 => 'vip,mycodex,openai/gpt-4o-mini',
					'timeout'                => 120,
					'max_retries'            => 2,
					'temperature'            => 0.7,
					'index_enabled'          => 1,
					'indexnow_key'           => $index_key,
					'indexnow_key_location'  => '',
					'indexnow_endpoints'     => Indexer::INDEXNOW_ENDPOINT,
					'rss_endpoints'          => implode( "\n", Indexer::RSS_ENDPOINTS ),
					'push_enabled'           => 1,
					'push_hub'               => Indexer::PUSH_HUB,
					'index_delay'            => 10,
					'custom_frameworks'      => '',
					'preserve_data_on_uninstall' => 1,
					'allowed_role'           => 'administrator',
					'amazon_enabled'         => 0,
					'amazon_region'          => 'com',
					'amazon_tag'             => '',
					'amazon_products'        => 3,
					'amazon_cache_hours'     => 24,
					'amazon_deeplink_template' => Amazon::DEFAULT_DEEPLINK,
					'amazon_image_proxy'     => 1,
					'amazon_merge_generic'   => 1,
					'amazon_timeout'         => 15,
					'entity_enabled'         => 1,
					'entity_count'           => 8,
					'tag_max'                => 3,
					'tag_content_enabled'    => 1,
					'tag_schedule_mode'      => 'after_publish',
					'tag_schedule_delay'     => 60,
					'tag_schedule_time'      => '09:00',
					'yoast_enabled'          => 1,
					'focus_keyword_source'   => 'entity',
					'yoast_title_template'   => '{{keyword}} | {{brand}}',
					'yoast_description_template' => '{{keyword}} — {{promise}} Hubungi {{phone}} {{city}}.',
					'auto_publish'           => 0,
					'schedule_mode'          => 'immediate',
					'schedule_interval'      => 0,
					'schedule_start'         => '',
					'schedule_time'          => '09:00',
					'schedule_jitter'        => 0,
					'image_enabled'          => 1,
					'image_source'           => 'auto',
					'image_count'            => 2,
					'image_max_kb'           => 95,
					'image_max_width'        => 1280,
					'image_ai_model'         => ImagePipeline::DEFAULT_AI_MODEL,
					'image_ai_size'          => '1024x1024',
					'image_ai_timeout'       => 120,
					'image_bing_timeout'     => 15,
					'image_title_template'   => '{{keyword}}',
					'image_alt_template'     => '{{keyword}}',
					'image_caption_template' => '{{keyword}} — {{offer}}',
					'image_description_template' => '{{keyword}}: {{promise}} {{city}}',
					'image_iptc_enabled'     => 1,
				)
			);
		}

		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'lpgen_manage' );
		}
		$settings   = get_option( 'lpgen_settings', array() );
		$role_name  = (string) ( $settings['allowed_role'] ?? 'administrator' );
		if ( 'administrator' !== $role_name ) {
			$extra = get_role( $role_name );
			if ( $extra ) {
				$extra->add_cap( 'lpgen_manage' );
			}
		}

		if ( ! wp_next_scheduled( 'lpgen_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lpgen_daily_cleanup' );
		}
	}
}
