<?php
/**
 * Plugin settings page.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'lpgen_settings';

	/**
	 * Settings page slug.
	 */
	public const PAGE = 'lpg-settings';

	/**
	 * Field definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function fields(): array {
		return array(
			'ninerouter_url'        => array(
				'label'   => __( '9Router Base URL', 'lp-generator' ),
				'type'    => 'url',
				'section' => 'ai',
				'desc'    => __( 'Mis. http://localhost:3000 atau https://router.domain.com', 'lp-generator' ),
			),
			'ninerouter_key'        => array(
				'label'   => __( '9Router API Key', 'lp-generator' ),
				'type'    => 'password',
				'section' => 'ai',
				'desc'    => __( 'Disimpan ter-mask. Tidak pernah ditulis ke log.', 'lp-generator' ),
			),
			'api_format'            => array(
				'label'   => __( 'Format API', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'ai',
				'options' => array(
					'openai'    => __( 'OpenAI (/v1/chat/completions)', 'lp-generator' ),
					'anthropic' => __( 'Anthropic (/v1/messages)', 'lp-generator' ),
				),
			),
			'models'                => array(
				'label'   => __( 'Model / Combo Fallback', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'ai',
				'desc'    => __( 'Dipisah koma. Contoh: vip,mycodex,openai/gpt-4o-mini', 'lp-generator' ),
			),
			'timeout'               => array(
				'label'   => __( 'Timeout request (detik)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'ai',
				'min'     => 10,
				'max'     => 600,
			),
			'max_retries'           => array(
				'label'   => __( 'Retry per job', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'ai',
				'min'     => 1,
				'max'     => 5,
			),
			'temperature'           => array(
				'label'   => __( 'Temperature', 'lp-generator' ),
				'type'    => 'float',
				'section' => 'ai',
				'min'     => 0,
				'max'     => 1,
			),
			'index_enabled'         => array(
				'label'   => __( 'Aktifkan indexing', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'indexing',
			),
			'index_delay'           => array(
				'label'   => __( 'Jeda setelah publish (detik)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'indexing',
				'min'     => 0,
				'max'     => 300,
				'desc'    => __( 'Default 10 detik sesuai permintaan.', 'lp-generator' ),
			),
			'indexnow_key'          => array(
				'label'   => __( 'IndexNow Key', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'indexing',
				'desc'    => __( 'Dibuat otomatis saat aktivasi. Bisa di-regenerate lewat Alat bantu.', 'lp-generator' ),
			),
			'indexnow_key_location' => array(
				'label'   => __( 'IndexNow Key Location', 'lp-generator' ),
				'type'    => 'url',
				'section' => 'indexing',
				'desc'    => __( 'Kosongkan bila file key berada di root domain.', 'lp-generator' ),
			),
			'indexnow_endpoints'    => array(
				'label'   => __( 'IndexNow Endpoints', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'indexing',
				'desc'    => __( 'Dipisah koma. Default global endpoint sudah fan-out ke engine peserta.', 'lp-generator' ),
			),
			'rss_endpoints'         => array(
				'label'   => __( 'RSS/WebSub Providers', 'lp-generator' ),
				'type'    => 'textarea',
				'section' => 'indexing',
				'desc'    => __( 'Satu URL per baris. Default: Google WebSub dan Superfeedr. Plugin mengirim ping ke feed RSS; konten tetap berada di situs Anda.', 'lp-generator' ),
			),
			'push_enabled'          => array(
				'label'   => __( 'Google PuSH / WebSub', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'indexing',
				'desc'    => __( 'Default aktif. Hub endpoint dikunci oleh plugin.', 'lp-generator' ),
			),
			'push_hub'              => array(
				'label'   => __( 'WebSub Hub Endpoint', 'lp-generator' ),
				'type'    => 'locked',
				'section' => 'indexing',
				'desc'    => __( 'Terkunci oleh plugin agar tidak tersalah ubah.', 'lp-generator' ),
			),
			'custom_frameworks'     => array(
				'label'   => __( 'Custom Frameworks (JSON)', 'lp-generator' ),
				'type'    => 'textarea',
				'section' => 'frameworks',
				'desc'    => __( 'Objek JSON: {"nama":{"label":..,"steps":[..],"best_for":[..]}}', 'lp-generator' ),
			),
			'allowed_role'          => array(
				'label'   => __( 'Role pengelola', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'advanced',
				'desc'    => __( 'Role tambahan yang mendapat capability lpgen_manage.', 'lp-generator' ),
			),
			'preserve_data_on_uninstall' => array(
				'label'   => __( 'Simpan post saat uninstall', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'advanced',
			),

			'amazon_enabled'        => array(
				'label'   => __( 'Integrasi produk Amazon', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'amazon',
				'desc'    => __( 'Ambil produk & gambar via Bing Images site:amazon.{region}, seperti pola StupidPie v4.', 'lp-generator' ),
			),
			'amazon_region'         => array(
				'label'   => __( 'Region toko Amazon', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'amazon',
				'options' => array(
					'com'    => 'amazon.com (US)',
					'co.uk'  => 'amazon.co.uk (UK)',
					'de'     => 'amazon.de',
					'fr'     => 'amazon.fr',
					'it'     => 'amazon.it',
					'es'     => 'amazon.es',
					'ca'     => 'amazon.ca',
					'com.au' => 'amazon.com.au',
					'co.jp'  => 'amazon.co.jp',
					'in'     => 'amazon.in',
					'br'     => 'amazon.com.br',
					'mx'     => 'amazon.com.mx',
					'nl'     => 'amazon.nl',
					'se'     => 'amazon.se',
					'pl'     => 'amazon.pl',
					'sg'     => 'amazon.sg',
					'ae'     => 'amazon.ae',
					'com.tr' => 'amazon.com.tr',
				),
			),
			'amazon_tag'            => array(
				'label'   => __( 'Amazon Associate Tag', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'amazon',
				'desc'    => __( 'Tag afiliasi yang ditambahkan ke link pencarian produk.', 'lp-generator' ),
			),
			'amazon_products'       => array(
				'label'   => __( 'Jumlah produk per campaign', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'amazon',
				'min'     => 1,
				'max'     => 8,
			),
			'amazon_cache_hours'    => array(
				'label'   => __( 'Cache produk (jam)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'amazon',
				'min'     => 0,
				'max'     => 72,
			),
			'amazon_deeplink_template' => array(
				'label'   => __( 'Template deeplink afiliasi', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'amazon',
				'desc'    => __( 'Placeholder: {keyword}, {region}, {tag}.', 'lp-generator' ),
			),
			'amazon_image_proxy'    => array(
				'label'   => __( 'Proxikan gambar via i0.wp.com', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'amazon',
			),
			'amazon_merge_generic'  => array(
				'label'   => __( 'Tambah gambar umum bila hasil sedikit', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'amazon',
			),
			'amazon_timeout'        => array(
				'label'   => __( 'Timeout scrape (detik)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'amazon',
				'min'     => 5,
				'max'     => 30,
			),

			'entity_enabled'        => array(
				'label'   => __( 'Generate entitas keyword dari AI', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'content',
			),
			'entity_count'          => array(
				'label'   => __( 'Jumlah entitas keyword', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'content',
				'min'     => 3,
				'max'     => 15,
			),
			'tag_max'               => array(
				'label'   => __( 'Maksimal tag per konten', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'content',
				'min'     => 1,
				'max'     => 3,
			),
			'tag_content_enabled'   => array(
				'label'   => __( 'Buat artikel untuk setiap tag', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'content',
			),
			'tag_schedule_mode'     => array(
				'label'   => __( 'Jadwal konten tag', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'content',
				'options' => array(
					'after_publish' => __( 'Setelah publish (delay menit)', 'lp-generator' ),
					'fixed'         => __( 'Tanggal & jam tertentu', 'lp-generator' ),
					'daily'         => __( 'Satu per hari pada jam tertentu', 'lp-generator' ),
				),
			),
			'tag_schedule_delay'    => array(
				'label'   => __( 'Delay tag (menit)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'content',
				'min'     => 0,
				'max'     => 10080,
			),
			'tag_schedule_time'     => array(
				'label'   => __( 'Jam konten tag', 'lp-generator' ),
				'type'    => 'time',
				'section' => 'content',
			),

			'yoast_enabled'         => array(
				'label'   => __( 'Inject meta ke Yoast', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'seo',
			),
			'focus_keyword_source'  => array(
				'label'   => __( 'Sumber focus keyword', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'seo',
				'options' => array(
					'entity' => __( 'Entitas keyword AI', 'lp-generator' ),
					'title'  => __( 'Judul konten', 'lp-generator' ),
					'tag'    => __( 'Tag konten', 'lp-generator' ),
				),
			),
			'yoast_title_template'  => array(
				'label'   => __( 'Template meta title', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'seo',
				'desc'    => __( 'Placeholder: {{keyword}}, {{brand}}, {{city}}, {{phone}}, {{offer}}, {{promise}}, {{price}}, {{stage}}, {{title}}, {{tag}}.', 'lp-generator' ),
			),
			'yoast_description_template' => array(
				'label'   => __( 'Template meta description', 'lp-generator' ),
				'type'    => 'textarea',
				'section' => 'seo',
				'desc'    => __( 'Contoh jasa: {{keyword}} di {{city}} — {{promise}}. Hubungi {{phone}} sekarang.', 'lp-generator' ),
			),

			'auto_publish'          => array(
				'label'   => __( 'Auto publish tanpa Review', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'schedule',
				'desc'    => __( 'Item otomatis di-approve dan dipublish saat antrean generate selesai.', 'lp-generator' ),
			),
			'schedule_mode'         => array(
				'label'   => __( 'Mode jadwal publish', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'schedule',
				'options' => array(
					'immediate' => __( 'Secepatnya (berurutan)', 'lp-generator' ),
					'interval'  => __( 'Jeda antar post', 'lp-generator' ),
					'fixed'     => __( 'Mulai tanggal & jam tertentu', 'lp-generator' ),
					'daily'     => __( 'Satu post per hari', 'lp-generator' ),
				),
			),
			'schedule_interval'     => array(
				'label'   => __( 'Jeda antar post (menit)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'schedule',
				'min'     => 0,
				'max'     => 10080,
			),
			'schedule_start'        => array(
				'label'   => __( 'Mulai (YYYY-MM-DDTHH:MM)', 'lp-generator' ),
				'type'    => 'datetime',
				'section' => 'schedule',
			),
			'schedule_time'         => array(
				'label'   => __( 'Jam publish harian', 'lp-generator' ),
				'type'    => 'time',
				'section' => 'schedule',
			),
			'schedule_jitter'       => array(
				'label'   => __( 'Jitter acak (menit)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'schedule',
				'min'     => 0,
				'max'     => 60,
			),

			'image_enabled'         => array(
				'label'   => __( 'Tambahkan gambar ke setiap konten', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'images',
				'desc'    => __( 'Galeri dibuat di LP, artikel MOFU/TOFU, dan artikel tag.', 'lp-generator' ),
			),
			'image_source'          => array(
				'label'   => __( 'Engine gambar', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'images',
				'options' => array(
					'auto'   => __( 'Otomatis: upload → AI → Bing', 'lp-generator' ),
					'upload' => __( 'Upload Media Library saja', 'lp-generator' ),
					'ai'     => __( 'AI 9Router saja', 'lp-generator' ),
					'bing'   => __( 'Bing Images saja', 'lp-generator' ),
				),
			),
			'image_count'           => array(
				'label'   => __( 'Jumlah gambar per konten', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'images',
				'min'     => 1,
				'max'     => 6,
			),
			'image_max_kb'          => array(
				'label'   => __( 'Ukuran maksimal per gambar (KB)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'images',
				'min'     => 10,
				'max'     => 99,
				'desc'    => __( 'Hard limit di bawah 100 KB. Quality dan resolusi diturunkan otomatis.', 'lp-generator' ),
			),
			'image_max_width'       => array(
				'label'   => __( 'Lebar maksimal gambar (px)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'images',
				'min'     => 640,
				'max'     => 2560,
			),
			'image_ai_model'        => array(
				'label'   => __( 'Model image 9Router', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'images',
				'desc'    => __( 'Contoh: gemini/gemini-3-pro-image-preview atau openai/dall-e-3. Cek /v1/models/image.', 'lp-generator' ),
			),
			'image_ai_size'         => array(
				'label'   => __( 'Ukuran generate AI', 'lp-generator' ),
				'type'    => 'select',
				'section' => 'images',
				'options' => array(
					'1024x1024' => '1024x1024',
					'1536x1024' => '1536x1024',
					'1024x1536' => '1024x1536',
					'1792x1024' => '1792x1024',
					'1024x1792' => '1024x1792',
				),
			),
			'image_ai_timeout'      => array(
				'label'   => __( 'Timeout generate AI (detik)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'images',
				'min'     => 30,
				'max'     => 300,
			),
			'image_bing_timeout'    => array(
				'label'   => __( 'Timeout download Bing (detik)', 'lp-generator' ),
				'type'    => 'number',
				'section' => 'images',
				'min'     => 5,
				'max'     => 30,
			),
			'image_title_template'  => array(
				'label'   => __( 'Template title attachment', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'images',
				'desc'    => __( 'Placeholder: {{keyword}}, {{offer}}, {{brand}}, {{city}}, {{phone}}, {{tag}}.', 'lp-generator' ),
			),
			'image_alt_template'    => array(
				'label'   => __( 'Template alt text', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'images',
			),
			'image_caption_template' => array(
				'label'   => __( 'Template caption', 'lp-generator' ),
				'type'    => 'text',
				'section' => 'images',
			),
			'image_description_template' => array(
				'label'   => __( 'Template description', 'lp-generator' ),
				'type'    => 'textarea',
				'section' => 'images',
			),
			'image_iptc_enabled'    => array(
				'label'   => __( 'Tanam keyword ke IPTC', 'lp-generator' ),
				'type'    => 'checkbox',
				'section' => 'images',
				'desc'    => __( 'Best-effort: membutuhkan Imagick dengan dukungan WebP.', 'lp-generator' ),
			),
		);
	}

	/**
	 * Tab definitions for the settings screen.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function tabs(): array {
		return array(
			'ai'         => array(
				'label'       => __( 'AI', 'lp-generator' ),
				'description' => __( 'Koneksi, model, dan fallback 9Router.', 'lp-generator' ),
				'icon'        => 'dashicons-format-chat',
				'sections'    => array( 'ai' ),
			),
			'indexing'   => array(
				'label'       => __( 'Indexing', 'lp-generator' ),
				'description' => __( 'WebSub, IndexNow, dan file key otomatis.', 'lp-generator' ),
				'icon'        => 'dashicons-search',
				'sections'    => array( 'indexing' ),
			),
			'frameworks' => array(
				'label'       => __( 'Frameworks', 'lp-generator' ),
				'description' => __( 'Preset dan framework AI per campaign.', 'lp-generator' ),
				'icon'        => 'dashicons-layout',
				'sections'    => array( 'frameworks' ),
			),
			'content'    => array(
				'label'       => __( 'Konten & SEO', 'lp-generator' ),
				'description' => __( 'Batas teks, tag, dan placeholder artikel.', 'lp-generator' ),
				'icon'        => 'dashicons-edit',
				'sections'    => array( 'content', 'seo' ),
			),
			'amazon'     => array(
				'label'       => __( 'Amazon', 'lp-generator' ),
				'description' => __( 'Kredensial dan aturan produk afiliasi.', 'lp-generator' ),
				'icon'        => 'dashicons-cart',
				'sections'    => array( 'amazon' ),
			),
			'schedule'   => array(
				'label'       => __( 'Jadwal', 'lp-generator' ),
				'description' => __( 'Jeda artikel dan delay indeksasi.', 'lp-generator' ),
				'icon'        => 'dashicons-calendar-alt',
				'sections'    => array( 'schedule' ),
			),
			'images'     => array(
				'label'       => __( 'Gambar', 'lp-generator' ),
				'description' => __( 'Sumber gambar, WebP, dan metadata.', 'lp-generator' ),
				'icon'        => 'dashicons-images-alt2',
				'sections'    => array( 'images' ),
			),
			'advanced'   => array(
				'label'       => __( 'Advanced', 'lp-generator' ),
				'description' => __( 'Opsi teknis dan keamanan lanjutan.', 'lp-generator' ),
				'icon'        => 'dashicons-admin-tools',
				'sections'    => array( 'advanced' ),
			),
		);
	}

	/**
	 * Resolve the active settings tab from the request.
	 *
	 * @return string
	 */
	private static function active_tab(): string {
		$requested = isset( $_REQUEST['tab'] ) ? wp_unslash( $_REQUEST['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tab       = is_string( $requested ) ? sanitize_key( $requested ) : '';
		$tabs      = self::tabs();
		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * Sections shown on the active tab.
	 *
	 * @return string[]
	 */
	private static function active_sections(): array {
		$tabs = self::tabs();
		return (array) $tabs[ self::active_tab() ]['sections'];
	}

	/**
	 * Value of a field that must not be edited in the UI.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function locked_value( string $key ): string {
		return 'push_hub' === $key ? Indexer::PUSH_HUB : '';
	}

	/**
	 * Create missing automatic values and publish the IndexNow key file once.
	 */
	private static function ensure_runtime_defaults(): void {
		$settings = get_option( self::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$changed  = false;

		if ( empty( $settings['indexnow_key'] ) ) {
			$settings['indexnow_key'] = Utils::generate_index_key();
			$changed                  = true;
		}
		if ( ! array_key_exists( 'push_enabled', $settings ) ) {
			$settings['push_enabled'] = 1;
			$changed                  = true;
		}
		if ( (string) ( $settings['push_hub'] ?? '' ) !== Indexer::PUSH_HUB ) {
			$settings['push_hub'] = Indexer::PUSH_HUB;
			$changed              = true;
		}
		if ( empty( $settings['indexnow_endpoints'] ) ) {
			$settings['indexnow_endpoints'] = Indexer::INDEXNOW_ENDPOINT;
			$changed                        = true;
		}
		if ( empty( $settings['rss_endpoints'] ) ) {
			$settings['rss_endpoints'] = implode( "\n", Indexer::RSS_ENDPOINTS );
			$changed = true;
		}
		if ( $changed ) {
			update_option( self::OPTION, $settings );
		}

		$key       = (string) $settings['indexnow_key'];
		$check_key = 'lpgen_key_check_' . md5( $key );
		if ( '' !== $key && false === get_transient( $check_key ) ) {
			$result = Indexer::ensure_key_file( $key );
			set_transient( $check_key, 1, 5 * MINUTE_IN_SECONDS );
			if ( ! $result['ok'] ) {
				set_transient( 'lpgen_indexnow_notice', $result['message'], DAY_IN_SECONDS );
			}
		}
	}

	/**
	 * Register settings with WordPress.
	 */
	public static function register(): void {
		self::ensure_runtime_defaults();
		register_setting(
			self::OPTION,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => array(),
			)
		);

		$active   = self::active_sections();
		$sections = array(
			'ai'         => array(
				'title'    => __( 'AI — 9Router', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Konfigurasi koneksi dan fallback model.', 'lp-generator' ) . '</p>';
				},
			),
			'indexing'   => array(
				'title'    => __( 'Indexing', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'WebSub aktif dengan hub terkunci. IndexNow otomatis dibuat dan file key dipasang bila root writable.', 'lp-generator' ) . '</p>';
				},
			),
			'frameworks' => array(
				'title'    => __( 'Frameworks', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Preset bawaan: AIDA, PAS, BAB, FAB, QUEST, DAGMAR, DIPADA, IDEPA, ACCA, AISAS, 4P, SPIN. Framework AI dibuat per campaign.', 'lp-generator' ) . '</p>';
				},
			),
			'amazon'     => array(
				'title'    => __( 'Amazon Affiliate', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Produk & gambar diambil dari Bing Images dengan filter site:amazon.{region}.', 'lp-generator' ) . '</p>';
				},
			),
			'content'    => array(
				'title'    => __( 'Konten — Entitas & Tag', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Entitas keyword dihasilkan AI sekali per campaign. Tag maksimal 3 per konten.', 'lp-generator' ) . '</p>';
				},
			),
			'seo'        => array(
				'title'    => __( 'SEO — Yoast', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Focus keyword, meta title dan meta description ditulis ke meta Yoast.', 'lp-generator' ) . '</p>';
				},
			),
			'schedule'   => array(
				'title'    => __( 'Jadwal Publish', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Atur jeda antar post, tanggal mulai, atau satu post per hari.', 'lp-generator' ) . '</p>';
				},
			),
			'images'     => array(
				'title'    => __( 'Gambar per Konten', 'lp-generator' ),
				'callback' => static function (): void {
					echo '<p>' . esc_html__( 'Engine gambar: upload, AI 9Router, atau Bing Images. WebP <100 KB.', 'lp-generator' ) . '</p>';
				},
			),
			'advanced'   => array(
				'title'    => __( 'Advanced', 'lp-generator' ),
				'callback' => '__return_empty_string',
			),
		);
		foreach ( $sections as $id => $section ) {
			if ( ! in_array( $id, $active, true ) ) {
				continue;
			}
			add_settings_section( 'lpgen_' . $id, $section['title'], $section['callback'], self::PAGE );
		}

		foreach ( self::fields() as $key => $field ) {
			if ( ! in_array( $field['section'], $active, true ) ) {
				continue;
			}
			add_settings_field(
				$key,
				$field['label'],
				array( self::class, 'render_field' ),
				self::PAGE,
				'lpgen_' . $field['section'],
				array(
					'key'   => $key,
					'field' => $field,
				)
			);
		}
	}

	/**
	 * Register settings on admin_init.
	 */
	public static function hook(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$raw    = is_array( $input ) ? $input : array();
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$clean  = $current;
		$active = self::active_sections();
		foreach ( self::fields() as $key => $field ) {
			$option_keys  = array_keys( (array) ( $field['options'] ?? array() ) );
			$first_option = (string) ( $option_keys[0] ?? '' );
			$is_active    = in_array( $field['section'], $active, true );
			if ( 'locked' === $field['type'] ) {
				$clean[ $key ] = self::locked_value( $key );
				continue;
			}
			if ( ! array_key_exists( $key, $raw ) ) {
				if ( ! $is_active ) {
					continue;
				}
				if ( 'checkbox' === $field['type'] ) {
					$clean[ $key ] = 0;
				} elseif ( 'select' === $field['type'] ) {
					$clean[ $key ] = $first_option;
				} else {
					$clean[ $key ] = '';
				}
				continue;
			}
			$value = $raw[ $key ];
			if ( 'password' === $field['type'] && is_string( $value ) ) {
				$current = get_option( self::OPTION, array() );
				$current = is_array( $current ) ? $current : array();
				$stored  = (string) ( $current[ $key ] ?? '' );
				if ( '' !== $stored && Utils::mask_secret( $stored ) === $value ) {
					$value = $stored;
				}
			}
			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'number':
					$min = (int) ( $field['min'] ?? 0 );
					$max = (int) ( $field['max'] ?? 100 );
					$clean[ $key ] = max( $min, min( $max, absint( $value ) ) );
					break;
				case 'float':
					$clean[ $key ] = max( (float) ( $field['min'] ?? 0 ), min( (float) ( $field['max'] ?? 1 ), (float) $value ) );
					break;
				case 'url':
					$clean[ $key ] = Utils::clean_url( $value );
					break;
				case 'time':
					$clean[ $key ] = Utils::clean_time( $value );
					break;
				case 'datetime':
					$clean[ $key ] = Utils::clean_datetime_local( $value );
					break;
				case 'textarea':
					$clean[ $key ] = Utils::clean_textarea( $value );
					break;
				case 'select':
					$clean[ $key ] = array_key_exists( (string) $value, (array) ( $field['options'] ?? array() ) )
						? sanitize_key( (string) $value )
						: $first_option;
					break;
				case 'locked':
					$clean[ $key ] = self::locked_value( $key );
					break;
				default:
					$clean[ $key ] = Utils::clean_text( $value );
			}
		}
		$custom = (string) ( $clean['custom_frameworks'] ?? '' );
		if ( '' !== $custom ) {
			$decoded = json_decode( $custom, true );
			if ( ! is_array( $decoded ) ) {
				$clean['custom_frameworks'] = '';
				add_settings_error( self::OPTION, 'lpgen_bad_json', __( 'Custom frameworks bukan JSON valid; diabaikan.', 'lp-generator' ) );
			}
		}
		$key = (string) ( $clean['indexnow_key'] ?? '' );
		if ( ! Indexer::is_valid_key( $key ) ) {
			$clean['indexnow_key'] = Utils::generate_index_key();
			set_transient(
				'lpgen_indexnow_notice',
				__( 'IndexNow key dibuat otomatis karena key sebelumnya kosong/tidak valid.', 'lp-generator' ),
				DAY_IN_SECONDS
			);
		}
		$clean['push_hub'] = Indexer::PUSH_HUB;
		return $clean;
	}

	/**
	 * Render one field.
	 *
	 * @param array $args Field args.
	 */
	public static function render_field( array $args ): void {
		$key   = (string) $args['key'];
		$field = (array) $args['field'];
		$value = get_option( self::OPTION, array() );
		$value = is_array( $value ) ? $value : array();
		$value = $value[ $key ] ?? '';
		$type  = (string) $field['type'];
		$id    = 'lpgen_' . $key;
		$name  = self::OPTION . '[' . $key . ']';

		switch ( $type ) {
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( 1, (int) $value, false ) . ' /> '
					. esc_html__( 'Aktif', 'lp-generator' ) . '</label>';
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( (array) ( $field['options'] ?? array() ) as $option_key => $label ) {
					echo '<option value="' . esc_attr( (string) $option_key ) . '" ' . selected( (string) $value, (string) $option_key, false ) . '>' . esc_html( (string) $label ) . '</option>';
				}
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6" class="large-text code">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'password':
				echo '<input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( Utils::mask_secret( (string) $value ) ) . '" class="regular-text" autocomplete="off" />';
				break;
			case 'time':
				echo '<input type="time" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( Utils::clean_time( $value ) ) . '" />';
				break;
			case 'datetime':
				echo '<input type="datetime-local" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( Utils::clean_datetime_local( $value ) ) . '" />';
				break;
			case 'locked':
				echo '<code id="' . esc_attr( $id ) . '">' . esc_html( self::locked_value( $key ) ) . '</code>';
				echo '<p class="description">' . esc_html__( 'URL dikunci plugin; tidak ada input yang bisa diubah.', 'lp-generator' ) . '</p>';
				break;
			default:
				$input_type = in_array( $type, array( 'number', 'float' ), true ) ? 'number' : 'text';
				$extra      = '';
				if ( 'number' === $type || 'float' === $type ) {
					$extra = ' min="' . esc_attr( (string) ( $field['min'] ?? 0 ) ) . '" max="' . esc_attr( (string) ( $field['max'] ?? 100 ) ) . '" step="' . ( 'float' === $type ? 'any' : '1' ) . '"';
				}
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra composed from escaped attributes above.
				echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text"' . $extra . ' />';
		}
		if ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( (string) $field['desc'] ) . '</p>';
		}
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab      = self::active_tab();
		$tabs     = self::tabs();
		$settings = get_option( self::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$key      = (string) ( $settings['indexnow_key'] ?? '' );
		$notice   = get_transient( 'lpgen_indexnow_notice' );
		if ( false !== $notice ) {
			delete_transient( 'lpgen_indexnow_notice' );
		}
		?>
		<div class="wrap lpgen-wrap lpgen-settings">
			<header class="lpgen-settings-hero">
				<div class="lpgen-settings-hero__content">
					<span class="lpgen-eyebrow"><?php echo esc_html__( 'Campaign Automation', 'lp-generator' ); ?></span>
					<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
					<p><?php echo esc_html__( 'Atur mesin konten, indeksasi, dan gambar untuk semua campaign Anda.', 'lp-generator' ); ?></p>
				</div>
				<div class="lpgen-settings-hero__badges">
					<span class="lpgen-version">v<?php echo esc_html( LPG_VERSION ); ?></span>
					<span class="lpgen-hero-status"><span class="lpgen-hero-status__dot" aria-hidden="true"></span><?php echo esc_html__( 'Siap digunakan', 'lp-generator' ); ?></span>
				</div>
			</header>

			<?php settings_errors( self::OPTION ); ?>
			<?php if ( false !== $notice ) : ?>
				<p class="lpgen-panel lpgen-panel--notice"><?php echo esc_html( (string) $notice ); ?></p>
			<?php endif; ?>
			<nav class="nav-tab-wrapper wp-clearfix lpgen-tabs" aria-label="<?php echo esc_attr__( 'Settings LP Generator', 'lp-generator' ); ?>">
				<?php foreach ( $tabs as $slug => $definition ) : ?>
					<?php $is_active = $slug === $tab; ?>
					<a class="nav-tab lpgen-tab <?php echo $is_active ? 'nav-tab-active is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( (string) $definition['icon'] ); ?> lpgen-tab__icon" aria-hidden="true"></span>
						<span class="lpgen-tab__text">
							<span class="lpgen-tab__label"><?php echo esc_html( (string) $definition['label'] ); ?></span>
							<span class="lpgen-tab__desc"><?php echo esc_html( (string) $definition['description'] ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="lpgen-settings-grid">
				<div class="lpgen-settings-main">
					<section class="lpgen-settings-card" aria-labelledby="lpgen-current-tab-title">
						<header class="lpgen-settings-card__header">
							<span class="dashicons <?php echo esc_attr( (string) $tabs[ $tab ]['icon'] ); ?> lpgen-settings-card__icon" aria-hidden="true"></span>
							<div>
								<h2 id="lpgen-current-tab-title"><?php echo esc_html( (string) $tabs[ $tab ]['label'] ); ?></h2>
								<p><?php echo esc_html( (string) $tabs[ $tab ]['description'] ); ?></p>
							</div>
						</header>
						<div class="lpgen-settings-card__body">
							<?php if ( 'indexing' === $tab ) : ?>
								<p class="lpgen-panel lpgen-panel--notice">
									<?php echo esc_html( Indexer::key_file_ready( $key ) ? __( 'IndexNow key aktif dan file key sudah terpasang di root domain.', 'lp-generator' ) : __( 'IndexNow key belum aktif. Pastikan ABSPATH writable atau upload file key secara manual.', 'lp-generator' ) ); ?>
								</p>
							<?php endif; ?>
							<form action="options.php" method="post">
								<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
								<?php
								settings_fields( self::OPTION );
								do_settings_sections( self::PAGE );
								submit_button( __( 'Simpan Settings', 'lp-generator' ) );
								?>
							</form>
						</div>
					</section>
				</div>
				<aside class="lpgen-settings-side" aria-label="<?php echo esc_attr__( 'Alat bantu LP Generator', 'lp-generator' ); ?>">
					<section class="lpgen-settings-card lpgen-settings-card--side">
						<h2><?php echo esc_html__( 'Alat bantu', 'lp-generator' ); ?></h2>
						<p class="description"><?php echo esc_html__( 'Jalankan pengecekan cepat tanpa meninggalkan halaman ini.', 'lp-generator' ); ?></p>
						<div class="lpgen-tool-list">
							<button type="button" class="button" data-lpgen-action="test-connection"><?php echo esc_html__( 'Test koneksi 9Router', 'lp-generator' ); ?></button>
							<button type="button" class="button" data-lpgen-action="generate-key"><?php echo esc_html__( 'Regenerate IndexNow Key + file', 'lp-generator' ); ?></button>
							<button type="button" class="button" data-lpgen-action="test-amazon"><?php echo esc_html__( 'Test ambil produk Amazon', 'lp-generator' ); ?></button>
							<label class="screen-reader-text" for="lpgen_test_keyword"><?php echo esc_html__( 'Keyword uji Amazon', 'lp-generator' ); ?></label>
							<input type="text" id="lpgen_test_keyword" class="regular-text" value="wireless headphones" />
						</div>
						<div id="lpgen-tool-result" aria-live="polite"></div>
					</section>
					<section class="lpgen-settings-card lpgen-settings-card--side">
						<h2><?php echo esc_html__( 'Ringkasan', 'lp-generator' ); ?></h2>
						<ul class="lpgen-summary">
							<li><span><?php echo esc_html__( 'Tab aktif', 'lp-generator' ); ?></span><strong><?php echo esc_html( (string) $tabs[ $tab ]['label'] ); ?></strong></li>
							<li><span><?php echo esc_html__( 'IndexNow key', 'lp-generator' ); ?></span><strong><?php echo $key ? esc_html__( 'Aktif', 'lp-generator' ) : esc_html__( 'Belum ada', 'lp-generator' ); ?></strong></li>
							<li><span><?php echo esc_html__( 'WebSub', 'lp-generator' ); ?></span><strong><?php echo esc_html__( 'Terhubung', 'lp-generator' ); ?></strong></li>
						</ul>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}
}
