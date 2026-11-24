<?php
/**
 * Campaign meta box.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignUI
 */
final class CampaignUI {

	/**
	 * Register meta box.
	 */
	public static function register_meta_box(): void {
		add_meta_box(
			'lpgen_campaign_details',
			__( 'Detail Offer & Funnel', 'lp-generator' ),
			array( self::class, 'render' ),
			'lpgen_campaign',
			'normal',
			'high'
		);
	}

	/**
	 * Render meta box fields.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( 'lpgen_save_campaign', 'lpgen_campaign_nonce' );
		$values = array();
		foreach ( ContentRepository::CAMPAIGN_KEYS as $key ) {
			$values[ $key ] = ContentRepository::get_meta( $post->ID, $key, '' );
		}
		$defaults = array(
			'offer_type'        => 'product',
			'bofu_mode'         => 'new',
			'bofu_existing_id'  => 0,
			'topic_mode'        => 'auto',
			'manual_mofu_topics' => '',
			'manual_tofu_topics' => '',
			'mofu_count'        => 2,
			'tofu_count'        => 3,
			'language'          => 'id',
			'framework'         => 'aida',
			'lp_post_type'      => 'page',
			'lp_layout'        => 'canvas',
			'article_post_type' => 'post',
			'publish_status'    => 'draft',
			'index_enabled'     => 1,
			'index_delay'       => 10,
			'content_push_enabled' => 1,
			'content_push_until' => '',
			'internal_link_enabled' => 1,
			'internal_link_until' => '',
			'internal_link_target' => 'bofu',
			'phone'             => '',
			'brand'             => '',
			'city'              => '',
			'target_areas'      => '',
			'entity_enabled'    => 1,
			'entity_count'      => 8,
			'tag_max'           => 3,
			'tag_content_enabled' => 1,
			'tag_schedule_mode' => 'after_publish',
			'tag_schedule_delay' => 60,
			'tag_schedule_time' => '09:00',
			'auto_publish'      => 0,
			'schedule_mode'     => 'immediate',
			'schedule_interval' => 0,
			'schedule_start'    => '',
			'schedule_time'     => '09:00',
			'schedule_jitter'   => 0,
			'amazon_enabled'    => 0,
			'amazon_keyword'    => '',
			'image_enabled'     => 1,
			'image_source'      => 'auto',
			'image_upload_ids'  => '',
			'image_count'       => 0,
		);
		$values = array_merge( $defaults, array_filter( $values, static fn( $value ): bool => '' !== $value ) );
		$status = (string) ContentRepository::get_meta( $post->ID, 'status', 'draft' );
		$existing_pages = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$all_frameworks = Frameworks::all();
		?>
		<div class="lpgen-campaign-form">
			<!-- BAGIAN 1: DATA UTAMA -->
			<div class="lpgen-form-section">
				<h3><?php echo esc_html__( '1. Penawaran & Target BOFU', 'lp-generator' ); ?></h3>
				<table class="form-table lpgen-table">
					<tbody>
					<tr>
						<th><label for="lpgen_offer_name"><?php echo esc_html__( 'Nama Produk / Jasa / Offer', 'lp-generator' ); ?> *</label></th>
						<td>
							<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
								<input type="text" class="large-text" name="lpgen[offer_name]" id="lpgen_offer_name" value="<?php echo esc_attr( (string) $values['offer_name'] ); ?>" placeholder="Contoh: Jasa Pembuatan Website Solo / Madu Herbal Lambung" required style="flex:1;min-width:280px;" />
								<button type="button" class="button button-secondary" id="lpgen_btn_suggest" data-lpgen-action="suggest-campaign" title="<?php echo esc_attr__( 'Dapatkan gambaran dan ide otomatis dari AI berdasarkan nama produk/keyword yang Anda ketik.', 'lp-generator' ); ?>">
									<span class="dashicons dashicons-admin-generic" aria-hidden="true" style="vertical-align:text-bottom;font-size:16px;"></span>
									<?php echo esc_html__( '✨ Rekomendasi Ide AI', 'lp-generator' ); ?>
								</button>
							</div>
							<p class="description"><?php echo esc_html__( 'Ketik keyword/produk Anda, lalu klik "Rekomendasi Ide AI" untuk mendapatkan gambaran audiens, masalah, dan topik otomatis.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_lp_type"><?php echo esc_html__( 'Tipe Landing Page', 'lp-generator' ); ?> *</label></th>
						<td>
							<select name="lpgen[lp_type]" id="lpgen_lp_type" style="font-weight:600;min-width:240px;">
								<?php foreach ( LpTypeRegistry::all() as $type_key => $strategy ) : ?>
									<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( LpTypeRegistry::clean( $values['lp_type'] ?? '' ), $type_key ); ?>><?php echo esc_html( $strategy->label( LanguageService::clean( $values['language'] ?? 'id' ) ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Strategi konten, SEO, schema, CTA, dan pemeriksaan kualitas mengikuti tipe ini.', 'lp-generator' ); ?></p>
							<p><label for="lpgen_experiment_mode"><?php echo esc_html__( 'Mode eksperimen', 'lp-generator' ); ?> <select id="lpgen_experiment_mode" name="lpgen[experiment_mode]"><option value="" <?php selected( ExperimentMode::clean( $values['experiment_mode'] ?? '' ), '' ); ?>><?php echo esc_html__( 'Produksi', 'lp-generator' ); ?></option><?php foreach ( ExperimentMode::MODES as $mode ) : ?><option value="<?php echo esc_attr( $mode ); ?>" <?php selected( ExperimentMode::clean( $values['experiment_mode'] ?? '' ), $mode ); ?>><?php echo esc_html( str_replace( '_', ' ', $mode ) ); ?></option><?php endforeach; ?></select></label><br><span class="description"><?php echo esc_html__( 'Default untuk campaign baru: CONTENT_PREVIEW. Halaman eksperimen membrane noindex dan tidak masuk sitemap sampai dipromosikan.', 'lp-generator' ); ?></span></p>
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_language"><?php echo esc_html__( 'Bahasa Konten', 'lp-generator' ); ?> *</label></th>
						<td>
							<select name="lpgen[language]" id="lpgen_language" style="font-weight:600;min-width:220px;">
								<?php foreach ( LanguageService::SUPPORTED as $language ) : ?>
									<option value="<?php echo esc_attr( $language ); ?>" <?php selected( LanguageService::clean( $values['language'] ?? 'id' ), $language ); ?>><?php echo esc_html( LanguageService::label( $language, 'id' ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Pilih bahasa utama untuk Landing Page dan seluruh artikel pendukung. Daftar bahasa tidak diterjemahkan sebagian saja.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_offer_type"><?php echo esc_html__( 'Tipe Penawaran', 'lp-generator' ); ?></label></th>
						<td>
							<select name="lpgen[offer_type]" id="lpgen_offer_type">
								<?php foreach ( array( 'product' => __( 'Produk Fisik / Digital', 'lp-generator' ), 'service' => __( 'Jasa / Layanan', 'lp-generator' ), 'affiliate' => __( 'Afiliasi / Rekomendasi', 'lp-generator' ) ) as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['offer_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Target Landing Page BOFU', 'lp-generator' ); ?></th>
						<td>
							<fieldset>
								<label style="display:block;margin-bottom:6px;">
									<input type="radio" name="lpgen[bofu_mode]" value="new" <?php checked( 'new', $values['bofu_mode'] ); ?> class="lpgen-toggle-bofu" />
									<strong><?php echo esc_html__( 'Buat Landing Page BOFU Baru', 'lp-generator' ); ?></strong>
									<span class="description">— <?php echo esc_html__( 'AI akan membuat halaman sales landing page baru khusus untuk campaign ini.', 'lp-generator' ); ?></span>
								</label>
								<label style="display:block;margin-bottom:6px;">
									<input type="radio" name="lpgen[bofu_mode]" value="existing" <?php checked( 'existing', $values['bofu_mode'] ); ?> class="lpgen-toggle-bofu" />
									<strong><?php echo esc_html__( 'Pilih Halaman BOFU yang Sudah Ada (Rutin Update Konten)', 'lp-generator' ); ?></strong>
									<span class="description">— <?php echo esc_html__( 'Semua artikel TOFU/MOFU akan mengarahkan pembaca ke halaman yang sudah Anda buat sebelumnya.', 'lp-generator' ); ?></span>
								</label>
								<div id="lpgen_bofu_existing_box" style="margin-top:8px;<?php echo 'existing' === $values['bofu_mode'] ? '' : 'display:none;'; ?>">
									<label for="lpgen_bofu_existing_id"><strong><?php echo esc_html__( 'Pilih Halaman Target BOFU:', 'lp-generator' ); ?></strong></label><br />
									<select name="lpgen[bofu_existing_id]" id="lpgen_bofu_existing_id" style="max-width:500px;width:100%;">
										<option value="0"><?php echo esc_html__( '— Pilih Halaman / Post —', 'lp-generator' ); ?></option>
										<?php foreach ( $existing_pages as $page ) : ?>
											<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( (int) $values['bofu_existing_id'], (int) $page->ID ); ?>>
												<?php echo esc_html( sprintf( '%s (%s - %s)', get_the_title( $page ), $page->post_type, get_permalink( $page ) ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</fieldset>
							<p><label for="lpgen_bofu_path"><?php echo esc_html__( 'Path LP baru (opsional)', 'lp-generator' ); ?></label><br />
							<input class="regular-text" id="lpgen_bofu_path" name="lpgen[bofu_path]" value="<?php echo esc_attr( (string) ( $values['bofu_path'] ?? '' ) ); ?>" placeholder="jasa/website/solo" /></p>
							<p class="description"><?php echo esc_html__( 'Path bertingkat akan dibuatkan halaman induk otomatis saat publish. Isi konten induk di bawah agar halaman yang lebih umum tidak kosong. Untuk LP yang sudah terbit, pilih halaman di atas.', 'lp-generator' ); ?></p>
							<?php
							$path_parts = explode( '/', FunnelContent::clean_path( (string) ( $values['bofu_path'] ?? '' ) ) );
							array_pop( $path_parts );
							$cumulative = array();
							$parent_specs = LpParentPages::all( $values );
							foreach ( $path_parts as $part ) {
								if ( '' === $part ) { continue; }
								$cumulative[] = $part;
								$parent_path  = implode( '/', $cumulative );
								$spec         = isset( $parent_specs[ $parent_path ] ) ? $parent_specs[ $parent_path ] : LpParentPages::for_path( $values, $parent_path );
								?>
								<details style="margin-top:10px;border:1px solid #c3c4c7;border-radius:6px;padding:10px 12px;">
									<summary><strong><?php echo esc_html( sprintf( __( 'Konten halaman induk: /%s', 'lp-generator' ), $parent_path ) ); ?></strong></summary>
									<p><label><?php echo esc_html__( 'Judul', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[parent_pages][<?php echo esc_attr( $parent_path ); ?>][title]" value="<?php echo esc_attr( (string) $spec['title'] ); ?>" /></label></p>
									<p><label><?php echo esc_html__( 'Pengantar', 'lp-generator' ); ?><textarea class="large-text" rows="2" name="lpgen[parent_pages][<?php echo esc_attr( $parent_path ); ?>][intro]"><?php echo esc_textarea( (string) $spec['intro'] ); ?></textarea></label></p>
									<p><label><?php echo esc_html__( 'Isi halaman (2–3 paragraf)', 'lp-generator' ); ?><textarea class="large-text" rows="4" name="lpgen[parent_pages][<?php echo esc_attr( $parent_path ); ?>][body]"><?php echo esc_textarea( (string) $spec['body'] ); ?></textarea></label></p>
									<p><label><?php echo esc_html__( 'Teks tombol lanjutan', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[parent_pages][<?php echo esc_attr( $parent_path ); ?>][cta]" value="<?php echo esc_attr( (string) $spec['cta'] ); ?>" /></label></p>
									<p class="description"><?php echo esc_html__( 'Plugin otomatis menambahkan tautan ke sub-halaman berikutnya dan ke LP dengan anchor target keyword.', 'lp-generator' ); ?></p>
								</details>
								<?php
							}
							?>
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_offer_url"><?php echo esc_html__( 'URL CTA / WhatsApp / Checkout', 'lp-generator' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" name="lpgen[offer_url]" id="lpgen_offer_url" value="<?php echo esc_attr( (string) $values['offer_url'] ); ?>" placeholder="https://wa.me/62812... atau https://domain.com/beli" />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Kontak & alamat (Contact Us)', 'lp-generator' ); ?></th>
						<td>
							<p><label for="lpgen_phone"><?php echo esc_html__( 'Telepon / WhatsApp', 'lp-generator' ); ?> <input class="regular-text" id="lpgen_phone" name="lpgen[phone]" value="<?php echo esc_attr( (string) ( $values['phone'] ?? '' ) ); ?>" placeholder="0812..." /></label></p>
							<p><label for="lpgen_contact_email"><?php echo esc_html__( 'Email', 'lp-generator' ); ?> <input class="regular-text" type="email" id="lpgen_contact_email" name="lpgen[contact_email]" value="<?php echo esc_attr( (string) ( $values['contact_email'] ?? '' ) ); ?>" placeholder="halo@domain.com" /></label></p>
							<p><label for="lpgen_contact_address"><?php echo esc_html__( 'Alamat', 'lp-generator' ); ?><textarea class="large-text" rows="2" id="lpgen_contact_address" name="lpgen[contact_address]"><?php echo esc_textarea( (string) ( $values['contact_address'] ?? '' ) ); ?></textarea></label></p>
							<p><label for="lpgen_contact_hours"><?php echo esc_html__( 'Jam operasional', 'lp-generator' ); ?> <input class="regular-text" id="lpgen_contact_hours" name="lpgen[contact_hours]" value="<?php echo esc_attr( (string) ( $values['contact_hours'] ?? '' ) ); ?>" placeholder="Senin–Jumat 09.00–17.00 WIB" /></label></p>
							<p class="description"><?php echo esc_html__( 'Data ini hanya untuk campaign/wilayah ini dan tampil di footer Contact Us LP.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_target_areas"><?php echo esc_html__( 'Target Area / Kota (Opsional)', 'lp-generator' ); ?></label></th>
						<td>
							<p><label for="lpgen_city"><?php echo esc_html__( 'Kota utama LP', 'lp-generator' ); ?></label> <input id="lpgen_city" name="lpgen[city]" value="<?php echo esc_attr( (string) $values['city'] ); ?>" placeholder="Solo" /></p>
							<textarea class="large-text" rows="3" name="lpgen[target_areas]" id="lpgen_target_areas" placeholder="Solo&#10;Sukoharjo&#10;Karanganyar&#10;Klaten"><?php echo esc_textarea( (string) $values['target_areas'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Satu area per baris. AI akan membagi topik lokal secara alami dan semuanya tetap diarahkan ke BOFU campaign ini.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Data khusus tipe LP', 'lp-generator' ); ?></th>
						<td>
							<details style="border:1px solid #c3c4c7;border-radius:6px;padding:10px 12px;" <?php echo 'product' === LpTypeRegistry::clean( $values['lp_type'] ?? '' ) || 'amazon_affiliate' === LpTypeRegistry::clean( $values['lp_type'] ?? '' ) ? 'open' : ''; ?>>
								<summary><strong><?php echo esc_html__( 'Produk / Amazon Affiliate', 'lp-generator' ); ?></strong></summary>
								<p><label><?php echo esc_html__( 'Jenis produk', 'lp-generator' ); ?> <select name="lpgen[product_type]"><option value="physical" <?php selected( $values['product_type'] ?? '', 'physical' ); ?>>Physical</option><option value="digital" <?php selected( $values['product_type'] ?? '', 'digital' ); ?>>Digital</option><option value="saas" <?php selected( $values['product_type'] ?? '', 'saas' ); ?>>SaaS</option><option value="service" <?php selected( $values['product_type'] ?? '', 'service' ); ?>>Service</option></select></label></p>
								<p><label><?php echo esc_html__( 'Status produk', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[product_status]" value="<?php echo esc_attr( (string) ( $values['product_status'] ?? '' ) ); ?>" placeholder="available / preorder / unavailable" /></label></p>
								<p><label><?php echo esc_html__( 'Spesifikasi (satu per baris)', 'lp-generator' ); ?><textarea class="large-text" rows="3" name="lpgen[product_specs]"><?php echo esc_textarea( (string) ( $values['product_specs'] ?? '' ) ); ?></textarea></label></p>
								<p><label><?php echo esc_html__( 'Garansi / lisensi', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[product_warranty]" value="<?php echo esc_attr( (string) ( $values['product_warranty'] ?? '' ) ); ?>" /></label></p>
								<p><label><?php echo esc_html__( 'Kebijakan pengembalian', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[return_policy]" value="<?php echo esc_attr( (string) ( $values['return_policy'] ?? '' ) ); ?>" /></label></p>
								<hr />
								<p><label><?php echo esc_html__( 'Amazon ASIN (wajib untuk affiliate)', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[amazon_asin]" value="<?php echo esc_attr( (string) ( $values['amazon_asin'] ?? '' ) ); ?>" placeholder="B0..." /></label></p>
								<p><label><?php echo esc_html__( 'Marketplace Amazon', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[amazon_marketplace]" value="<?php echo esc_attr( (string) ( $values['amazon_marketplace'] ?? ( $values['amazon_region'] ?? '' ) ) ); ?>" placeholder="com / co.uk / de" /></label></p>
								<p><label><?php echo esc_html__( 'Amazon Associate Tag', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[amazon_affiliate_tag]" value="<?php echo esc_attr( (string) ( $values['amazon_affiliate_tag'] ?? ( $values['amazon_tag'] ?? '' ) ) ); ?>" /></label></p>
								<p><label><?php echo esc_html__( 'Sumber referensi / bukti', 'lp-generator' ); ?> <input class="regular-text" name="lpgen[amazon_source_reference]" value="<?php echo esc_attr( (string) ( $values['amazon_source_reference'] ?? '' ) ); ?>" placeholder="https://sumber-resmi..." /></label></p>
								<p><label><?php echo esc_html__( 'Analisis orisinal', 'lp-generator' ); ?><textarea class="large-text" rows="3" name="lpgen[amazon_original_analysis]"><?php echo esc_textarea( (string) ( $values['amazon_original_analysis'] ?? '' ) ); ?></textarea></label></p>
								<p class="description"><?php echo esc_html__( 'Tanpa ASIN, tag, dan sumber terverifikasi, LP affiliate tetap draft dan tidak dipublish.', 'lp-generator' ); ?></p>
							</details>
						</td>
					</tr>
					</tbody>
				</table>
			</div>

			<!-- BAGIAN 2: FRAMEWORK COPYWRITING -->
			<div class="lpgen-form-section" style="margin-top:20px;padding-top:16px;border-top:1px solid #dcdcde;">
				<h3><?php echo esc_html__( '2. Copywriting Framework', 'lp-generator' ); ?></h3>
				<table class="form-table lpgen-table">
					<tbody>
					<tr>
						<th><label for="lpgen_framework"><?php echo esc_html__( 'Formula Copywriting', 'lp-generator' ); ?></label></th>
						<td>
							<select name="lpgen[framework]" id="lpgen_framework" style="max-width:540px;width:100%;">
								<?php foreach ( Frameworks::for_select() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['framework'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button" data-lpgen-action="generate-framework" data-campaign="<?php echo esc_attr( (string) $post->ID ); ?>" style="margin-left:6px;"><?php echo esc_html__( 'Siapkan framework campaign', 'lp-generator' ); ?></button>
							
							<p class="description"><?php echo esc_html__( 'LP dibuat sebagai HTML responsif WordPress. AI menyiapkan rencana section khusus campaign mengikuti formula pilihan, atau merancang urutan sendiri pada mode Framework AI Khusus. Rencana disiapkan otomatis saat Mulai Generate.', 'lp-generator' ); ?></p>
							<p><label for="lpgen_lp_layout"><?php echo esc_html__( 'Tampilan LP:', 'lp-generator' ); ?></label> <select id="lpgen_lp_layout" name="lpgen[lp_layout]"><option value="canvas" <?php selected( $values['lp_layout'], 'canvas' ); ?>><?php echo esc_html__( 'Halaman LP penuh', 'lp-generator' ); ?></option><option value="theme" <?php selected( $values['lp_layout'], 'theme' ); ?>><?php echo esc_html__( 'Ikuti layout tema WordPress', 'lp-generator' ); ?></option></select></p>
							<p><label for="lpgen_lp_model"><?php echo esc_html__( 'Model desain LP:', 'lp-generator' ); ?></label> <select id="lpgen_lp_model" name="lpgen[lp_model]"><?php foreach ( LpTemplate::MODELS as $model_key => $model_label ) : ?><option value="<?php echo esc_attr( $model_key ); ?>" <?php selected( LpTemplate::clean( $values['lp_model'] ?? '' ), $model_key ); ?>><?php echo esc_html( $model_label ); ?></option><?php endforeach; ?></select><br><span class="description"><?php echo esc_html__( 'JSON konten AI dikonversi plugin ke model visual ini. Framework copywriting tetap mengatur urutan pesan.', 'lp-generator' ); ?></span></p>
							<p><label for="lpgen_article_theme"><?php echo esc_html__( 'Tema artikel pendukung:', 'lp-generator' ); ?></label> <select id="lpgen_article_theme" name="lpgen[article_theme]"><option value="editorial" <?php selected( $values['article_theme'] ?? 'editorial', 'editorial' ); ?>><?php echo esc_html__( 'Editorial', 'lp-generator' ); ?></option><option value="magazine" <?php selected( $values['article_theme'] ?? '', 'magazine' ); ?>><?php echo esc_html__( 'Magazine', 'lp-generator' ); ?></option><option value="minimal" <?php selected( $values['article_theme'] ?? '', 'minimal' ); ?>><?php echo esc_html__( 'Minimal', 'lp-generator' ); ?></option></select><br><span class="description"><?php echo esc_html__( 'Tema ini dipakai TOFU, MOFU, dan artikel tag campaign ini saja.', 'lp-generator' ); ?></span></p>
							<p><label for="lpgen_lp_target_keyword"><?php echo esc_html__( 'Target keyword LP (anchor internal link):', 'lp-generator' ); ?></label> <input class="regular-text" type="text" id="lpgen_lp_target_keyword" name="lpgen[lp_target_keyword]" value="<?php echo esc_attr( (string) ( $values['lp_target_keyword'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( trim( (string) ( $values['offer_name'] ?? '' ) . ' ' . (string) ( $values['city'] ?? '' ) ) ); ?>" /><br><span class="description"><?php echo esc_html__( 'Kosongkan untuk memakai Nama penawaran + Kota. Anchor ini dipakai semua artikel TOFU/MOFU/tag yang menuju LP.', 'lp-generator' ); ?></span></p>
							<div id="lpgen_framework_steps_preview" style="margin-top:10px;">
								<!-- Rendered via JS and PHP -->
								<?php
								$current_preset = Frameworks::get( (string) $values['framework'] );
								$ai_framework = Frameworks::campaign_plan( ContentRepository::get_campaign( (int) $post->ID ) );
								$display_steps = ( ! empty( $ai_framework['steps'] ) )
									? wp_list_pluck( $ai_framework['steps'], 'name' )
									: $current_preset['steps'];
								?>
								<div class="lpgen-steps-badges" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
									<span style="font-weight:600;font-size:12px;color:#50575e;"><?php echo esc_html__( 'Langkah Copywriting:', 'lp-generator' ); ?></span>
									<?php foreach ( $display_steps as $step_idx => $step_name ) : ?>
										<span class="lpgen-badge lpgen-badge--done" style="font-size:12px;padding:3px 8px;">
											<?php echo esc_html( sprintf( '%d. %s', $step_idx + 1, $step_name ) ); ?>
										</span>
									<?php endforeach; ?>
								</div>
							</div>
						</td>
					</tr>
					</tbody>
				</table>
			</div>

			<!-- BAGIAN 3: TOPIK KONTEN -->
			<div class="lpgen-form-section" style="margin-top:20px;padding-top:16px;border-top:1px solid #dcdcde;">
				<h3><?php echo esc_html__( '3. Topik Konten (TOFU & MOFU)', 'lp-generator' ); ?></h3>
				<table class="form-table lpgen-table">
					<tbody>
					<tr>
						<th><?php echo esc_html__( 'Cara Pembuatan Topik', 'lp-generator' ); ?></th>
						<td>
							<label style="margin-right:16px;">
								<input type="radio" name="lpgen[topic_mode]" value="auto" <?php checked( 'auto', $values['topic_mode'] ); ?> class="lpgen-toggle-topic" />
								<strong><?php echo esc_html__( 'Otomatis via AI', 'lp-generator' ); ?></strong>
							</label>
							<label>
								<input type="radio" name="lpgen[topic_mode]" value="manual" <?php checked( 'manual', $values['topic_mode'] ); ?> class="lpgen-toggle-topic" />
								<strong><?php echo esc_html__( 'Manual (Input Topik Sendiri)', 'lp-generator' ); ?></strong>
							</label>
						</td>
					</tr>
					<tr id="lpgen_topic_auto_row" style="<?php echo 'manual' === $values['topic_mode'] ? 'display:none;' : ''; ?>">
						<th><?php echo esc_html__( 'Jumlah Artikel AI', 'lp-generator' ); ?></th>
						<td>
							<label for="lpgen_mofu_count"><?php echo esc_html__( 'MOFU:', 'lp-generator' ); ?></label>
							<input type="number" min="1" max="10" name="lpgen[mofu_count]" id="lpgen_mofu_count" value="<?php echo esc_attr( (string) $values['mofu_count'] ); ?>" style="width:60px;" />
							<span style="margin:0 12px;">|</span>
							<label for="lpgen_tofu_count"><?php echo esc_html__( 'TOFU:', 'lp-generator' ); ?></label>
							<input type="number" min="1" max="10" name="lpgen[tofu_count]" id="lpgen_tofu_count" value="<?php echo esc_attr( (string) $values['tofu_count'] ); ?>" style="width:60px;" />
							<span class="description"><?php echo esc_html__( 'AI akan menentukan topik secara variatif sesuai area & persona.', 'lp-generator' ); ?></span>
						</td>
					</tr>
					<tr id="lpgen_topic_manual_row_mofu" style="<?php echo 'manual' === $values['topic_mode'] ? '' : 'display:none;' ?>">
						<th><label for="lpgen_manual_mofu_topics"><?php echo esc_html__( 'Topik / Judul MOFU (Manual)', 'lp-generator' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" name="lpgen[manual_mofu_topics]" id="lpgen_manual_mofu_topics" placeholder="Perbandingan Jasa Pembuatan Website Terbaik di Solo&#10;5 Hal Wajib Diperhatikan Sebelum Beli Landing Page"><?php echo esc_textarea( (string) $values['manual_mofu_topics'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Satu judul per baris. Konten perbandingan, evaluasi, dan solusi yang mengarah ke BOFU.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					<tr id="lpgen_topic_manual_row_tofu" style="<?php echo 'manual' === $values['topic_mode'] ? '' : 'display:none;' ?>">
						<th><label for="lpgen_manual_tofu_topics"><?php echo esc_html__( 'Topik / Judul TOFU (Manual)', 'lp-generator' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" name="lpgen[manual_tofu_topics]" id="lpgen_manual_tofu_topics" placeholder="Cara Meningkatkan Penjualan Bisnis Lokal di Era Digital&#10;Pentingnya Punya Website Resmi Bagi UMKM Solo"><?php echo esc_textarea( (string) $values['manual_tofu_topics'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Satu judul per baris. Konten edukasi & kesadaran masalah yang relevan.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_research_sources"><?php echo esc_html__( 'Sumber Riset (JSON)', 'lp-generator' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="6" name="lpgen[research_sources]" id="lpgen_research_sources" placeholder='{"sources":[{"url":"https://contoh.com/a","title":"Judul sumber","claims":[{"type":"price","text":"Kisaran biaya","as_of":"2026-10-12","exact_quote":"Kutipan persis dari sumber."}]}]}'><?php echo esc_textarea( (string) ( $values['research_sources'] ?? '' ) ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'JSON berisi sumber dan klaim yang boleh dipakai AI. Plugin tidak mencari web otomatis; tanggal pemeriksaan diisi dari pengaturan WordPress.', 'lp-generator' ); ?></p>
						</td>
					</tr>
					</tbody>
				</table>
			</div>

			<!-- BAGIAN 4: PENGATURAN LANJUTAN (COLLAPSIBLE) -->
			<div class="lpgen-form-section" style="margin-top:20px;padding-top:16px;border-top:1px solid #dcdcde;">
				<details style="background:#f6f7f7;padding:12px 16px;border:1px solid #c3c4c7;border-radius:6px;">
					<summary style="font-weight:600;font-size:14px;cursor:pointer;color:#2271b1;">
						⚙️ <?php echo esc_html__( 'Pengaturan Lanjutan (Persona, Jadwal, Internal Link, Gambar, Amazon)', 'lp-generator' ); ?>
					</summary>
					<div style="margin-top:14px;">
						<table class="form-table lpgen-table">
							<tbody>
							<tr>
								<th><label for="lpgen_audience"><?php echo esc_html__( 'Audiens Sasaran', 'lp-generator' ); ?></label></th>
								<td><textarea class="large-text" rows="2" name="lpgen[audience]" id="lpgen_audience"><?php echo esc_textarea( (string) $values['audience'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="lpgen_problem"><?php echo esc_html__( 'Masalah Utama', 'lp-generator' ); ?></label></th>
								<td><textarea class="large-text" rows="2" name="lpgen[problem]" id="lpgen_problem"><?php echo esc_textarea( (string) $values['problem'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="lpgen_promise"><?php echo esc_html__( 'Janji / Hasil', 'lp-generator' ); ?></label></th>
								<td><textarea class="large-text" rows="2" name="lpgen[promise]" id="lpgen_promise"><?php echo esc_textarea( (string) $values['promise'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="lpgen_benefits"><?php echo esc_html__( 'Benefit (satu per baris)', 'lp-generator' ); ?></label></th>
								<td><textarea class="large-text" rows="3" name="lpgen[benefits]" id="lpgen_benefits"><?php echo esc_textarea( (string) $values['benefits'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="lpgen_price"><?php echo esc_html__( 'Harga / Mulai Dari', 'lp-generator' ); ?></label></th>
								<td><input type="text" class="regular-text" name="lpgen[price]" id="lpgen_price" value="<?php echo esc_attr( (string) $values['price'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="lpgen_bonus"><?php echo esc_html__( 'Bonus (satu per baris)', 'lp-generator' ); ?></label></th>
								<td><textarea class="large-text" rows="2" name="lpgen[bonus]" id="lpgen_bonus"><?php echo esc_textarea( (string) $values['bonus'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="lpgen_guarantee"><?php echo esc_html__( 'Garansi', 'lp-generator' ); ?></label></th>
								<td><input type="text" class="regular-text" name="lpgen[guarantee]" id="lpgen_guarantee" value="<?php echo esc_attr( (string) $values['guarantee'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="lpgen_cta"><?php echo esc_html__( 'Teks Tombol CTA', 'lp-generator' ); ?></label></th>
								<td><input type="text" class="regular-text" name="lpgen[cta]" id="lpgen_cta" value="<?php echo esc_attr( (string) $values['cta'] ); ?>" placeholder="Pesan Sekarang" /></td>
							</tr>
							<tr>
								<th><label for="lpgen_voice"><?php echo esc_html__( 'Tone of Voice', 'lp-generator' ); ?></label></th>
								<td><input type="text" class="regular-text" name="lpgen[voice]" id="lpgen_voice" value="<?php echo esc_attr( (string) $values['voice'] ); ?>" placeholder="profesional, ramah, persuasif" /></td>
							</tr>
							<tr>
								<th><label for="lpgen_lp_post_type"><?php echo esc_html__( 'Post type LP', 'lp-generator' ); ?></label></th>
								<td>
									<select name="lpgen[lp_post_type]" id="lpgen_lp_post_type">
										<option value="page" <?php selected( $values['lp_post_type'], 'page' ); ?>>Page</option>
										<option value="post" <?php selected( $values['lp_post_type'], 'post' ); ?>>Post</option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="lpgen_article_post_type"><?php echo esc_html__( 'Post type artikel', 'lp-generator' ); ?></label></th>
								<td>
									<select name="lpgen[article_post_type]" id="lpgen_article_post_type">
										<option value="post" <?php selected( $values['article_post_type'], 'post' ); ?>>Post</option>
										<option value="page" <?php selected( $values['article_post_type'], 'page' ); ?>>Page</option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="lpgen_publish_status"><?php echo esc_html__( 'Status Publish', 'lp-generator' ); ?></label></th>
								<td>
									<select name="lpgen[publish_status]" id="lpgen_publish_status">
										<?php foreach ( array( 'publish', 'draft', 'pending' ) as $status_option ) : ?>
											<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $values['publish_status'], $status_option ); ?>><?php echo esc_html( ucfirst( $status_option ) ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="lpgen_content_push_enabled"><?php echo esc_html__( 'Push Konten Campaign', 'lp-generator' ); ?></label></th>
								<td>
									<input type="hidden" name="lpgen[content_push_enabled]" value="0" />
									<label><input type="checkbox" name="lpgen[content_push_enabled]" value="1" id="lpgen_content_push_enabled" <?php checked( 1, (int) $values['content_push_enabled'] ); ?> /> <?php echo esc_html__( 'Izinkan konten dipublish sesuai antrean', 'lp-generator' ); ?></label>
									<p><label for="lpgen_content_push_until"><?php echo esc_html__( 'Batas akhir push (kosong = tanpa batas):', 'lp-generator' ); ?></label> <input type="date" name="lpgen[content_push_until]" id="lpgen_content_push_until" value="<?php echo esc_attr( (string) $values['content_push_until'] ); ?>" /></p>
								</td>
							</tr>
							<tr>
								<th><label for="lpgen_internal_link_enabled"><?php echo esc_html__( 'Internal Link ke BOFU', 'lp-generator' ); ?></label></th>
								<td>
									<input type="hidden" name="lpgen[internal_link_enabled]" value="0" />
									<label><input type="checkbox" name="lpgen[internal_link_enabled]" value="1" id="lpgen_internal_link_enabled" <?php checked( 1, (int) $values['internal_link_enabled'] ); ?> /> <?php echo esc_html__( 'Semua artikel mengarahkan pembaca ke Landing Page BOFU', 'lp-generator' ); ?></label>
									<p>
										<label for="lpgen_internal_link_target"><?php echo esc_html__( 'Target link:', 'lp-generator' ); ?></label>
										<select name="lpgen[internal_link_target]" id="lpgen_internal_link_target">
											<option value="bofu" <?php selected( $values['internal_link_target'], 'bofu' ); ?>><?php echo esc_html__( 'Selalu BOFU (disarankan)', 'lp-generator' ); ?></option>
											<option value="next_stage" <?php selected( $values['internal_link_target'], 'next_stage' ); ?>><?php echo esc_html__( 'Link ke BOFU + link tambahan TOFU → MOFU', 'lp-generator' ); ?></option>
										</select>
									</p>
									<p><label for="lpgen_internal_link_until"><?php echo esc_html__( 'Internal link aktif sampai (kosong = tanpa batas):', 'lp-generator' ); ?></label> <input type="date" name="lpgen[internal_link_until]" id="lpgen_internal_link_until" value="<?php echo esc_attr( (string) $values['internal_link_until'] ); ?>" /></p>
								</td>
							</tr>
							<tr>
								<th><label for="lpgen_image_enabled"><?php echo esc_html__( 'Sisipkan Gambar', 'lp-generator' ); ?></label></th>
								<td>
									<input type="hidden" name="lpgen[image_enabled]" value="0" />
									<label><input type="checkbox" name="lpgen[image_enabled]" value="1" id="lpgen_image_enabled" <?php checked( 1, (int) $values['image_enabled'] ); ?> /> <?php echo esc_html__( 'Aktifkan gambar di konten', 'lp-generator' ); ?></label>
								</td>
							</tr>
							<tr>
								<th><label for="lpgen_image_source"><?php echo esc_html__( 'Engine Gambar', 'lp-generator' ); ?></label></th>
								<td>
									<select name="lpgen[image_source]" id="lpgen_image_source">
										<?php foreach ( array( 'auto' => __( 'Otomatis: upload → AI → Bing', 'lp-generator' ), 'upload' => __( 'Upload saja', 'lp-generator' ), 'ai' => __( 'AI 9Router saja', 'lp-generator' ), 'bing' => __( 'Bing Images saja', 'lp-generator' ) ) as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['image_source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th><?php echo esc_html__( 'Gambar AIDA per section', 'lp-generator' ); ?></th>
								<td><p class="description"><?php echo esc_html__( 'Untuk AIDA 1.5.1, pilih upload khusus tiap slot. Slot kosong pada mode upload dibiarkan kosong; mode auto dapat mencoba AI/Bing. Maksimal tiga slot, tanpa gambar pada Action.', 'lp-generator' ); ?></p>
								<?php foreach ( array( 'aida_hero_image_id' => 'Hero / Attention', 'aida_interest_image_id' => 'Kebutuhan / Interest', 'aida_desire_image_id' => 'Manfaat / Desire' ) as $slot_key => $slot_label ) : ?>
								<p><label for="lpgen_<?php echo esc_attr( $slot_key ); ?>"><?php echo esc_html( $slot_label ); ?></label> <input type="hidden" id="lpgen_<?php echo esc_attr( $slot_key ); ?>" name="lpgen[<?php echo esc_attr( $slot_key ); ?>]" value="<?php echo esc_attr( (string) ( $values[ $slot_key ] ?? 0 ) ); ?>" /> <span id="lpgen_<?php echo esc_attr( $slot_key ); ?>_label"><?php echo ! empty( $values[ $slot_key ] ) ? esc_html( get_the_title( (int) $values[ $slot_key ] ) ) : esc_html__( 'Belum dipilih', 'lp-generator' ); ?></span> <button type="button" class="button" data-lpgen-slot-picker="lpgen_<?php echo esc_attr( $slot_key ); ?>"><?php echo esc_html__( 'Pilih gambar', 'lp-generator' ); ?></button> <button type="button" class="button" data-lpgen-slot-clear="lpgen_<?php echo esc_attr( $slot_key ); ?>"><?php echo esc_html__( 'Kosongkan', 'lp-generator' ); ?></button></p>
								<?php endforeach; ?></td>
							</tr><tr>
								<th><label for="lpgen_amazon_enabled"><?php echo esc_html__( 'Amazon Affiliate', 'lp-generator' ); ?></label></th>
								<td>
									<label><input type="checkbox" name="lpgen[amazon_enabled]" value="1" id="lpgen_amazon_enabled" <?php checked( 1, (int) $values['amazon_enabled'] ); ?> /> <?php echo esc_html__( 'Aktifkan produk Amazon', 'lp-generator' ); ?></label>
									<input type="text" class="regular-text" name="lpgen[amazon_keyword]" id="lpgen_amazon_keyword" value="<?php echo esc_attr( (string) $values['amazon_keyword'] ); ?>" placeholder="Keyword Amazon (opsional)" style="margin-left:8px;" />
								</td>
							</tr>
							</tbody>
						</table>
					</div>
				</details>
			</div>

			<!-- ACTION BUTTONS -->
			<div class="lpgen-action-bar" style="margin-top:24px;padding:16px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
				<div style="display:flex;align-items:center;gap:10px;">
					<button type="button" class="button button-primary button-large" data-lpgen-action="start-campaign" data-campaign="<?php echo esc_attr( (string) $post->ID ); ?>" style="font-weight:700;">
						<?php echo esc_html__( '🚀 Mulai Generate Konten (Live)', 'lp-generator' ); ?>
					</button>
					<button type="button" class="button button-secondary" data-lpgen-action="run-now" data-campaign="<?php echo esc_attr( (string) $post->ID ); ?>">
						<?php echo esc_html__( 'Proses Antrean Sekarang', 'lp-generator' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=lpg-review&campaign_id=' . $post->ID ) ); ?>">
						<?php echo esc_html__( 'Lihat Review Konten', 'lp-generator' ); ?>
					</a>
				</div>
				<div>
					<?php echo esc_html__( 'Status:', 'lp-generator' ); ?>
					<span class="lpgen-badge lpgen-badge--<?php echo esc_attr( sanitize_key( $status ) ); ?>"><?php echo esc_html( $status ); ?></span>
				</div>
			</div>

			<div id="lpgen-live-runner" style="display:none;margin-top:16px;padding:16px;background:#1d2327;color:#fff;border-radius:8px;">
				<h4 style="margin:0 0 8px;color:#72aee6;"><?php echo esc_html__( 'Proses Pembuatan Konten Sedang Berjalan...', 'lp-generator' ); ?></h4>
				<div class="lpgen-progress" style="width:100%;height:14px;background:rgba(255,255,255,0.2);border-radius:7px;overflow:hidden;margin-bottom:8px;">
					<div class="lpgen-progress__bar" id="lpgen_runner_bar" style="width:5%;height:100%;background:#00a32a;transition:width 0.3s ease;"></div>
				</div>
				<div id="lpgen_runner_status" style="font-size:13px;color:#c3c4c7;"><?php echo esc_html__( 'Menyiapkan antrean job...', 'lp-generator' ); ?></div>
				<div id="lpgen_runner_countdown" style="font-size:12px;color:#ffd166;margin-top:4px;"></div>
				<ul id="lpgen_runner_details" style="margin:8px 0 0;padding-left:18px;font-size:12px;color:#c3c4c7;max-height:160px;overflow:auto;"></ul>
			</div>

			<div id="lpgen-action-result" aria-live="polite"></div>
		</div>

		<script>
		window.lpgenFrameworkData = <?php echo wp_json_encode( $all_frameworks ); ?>;
		</script>
		<?php
	}

	/**
	 * Save meta box values.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['lpgen_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lpgen_campaign_nonce'] ) ), 'lpgen_save_campaign' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'lpgen_manage' ) ) {
			return;
		}
		$raw = isset( $_POST['lpgen'] ) && is_array( $_POST['lpgen'] ) ? wp_unslash( $_POST['lpgen'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		self::save_meta_data( $post_id, $raw );
	}

	/**
	 * Sanitize and save campaign meta data array.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $raw     Raw input key-values.
	 */
	public static function save_meta_data( int $post_id, array $raw ): void {
		$data = array();
		foreach ( ContentRepository::CAMPAIGN_KEYS as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$value = $raw[ $key ];
			switch ( $key ) {
				case 'offer_type':
					$data[ $key ] = in_array( $value, array( 'product', 'service', 'affiliate' ), true ) ? $value : 'product';
					break;
				case 'language':
					$data[ $key ] = LanguageService::clean( $value );
					break;
				case 'lp_type':
					$data[ $key ] = LpTypeRegistry::clean( $value );
					break;
				case 'experiment_mode':
					$data[ $key ] = ExperimentMode::clean( $value );
					break;
				case 'product_type':
					$data[ $key ] = in_array( $value, array( 'physical', 'digital', 'saas', 'service' ), true ) ? $value : 'service';
					break;
				case 'product_status':
					$data[ $key ] = in_array( sanitize_key( (string) $value ), array( 'available', 'preorder', 'unavailable' ), true ) ? sanitize_key( (string) $value ) : 'available';
					break;
				case 'product_specs':
				case 'amazon_original_analysis':
					$data[ $key ] = Utils::clean_textarea( $value );
					break;
				case 'product_warranty':
				case 'return_policy':
				case 'amazon_asin':
				case 'amazon_marketplace':
				case 'amazon_affiliate_tag':
				case 'amazon_source_reference':
					$data[ $key ] = Utils::clean_text( $value );
					break;
				case 'lp_layout':
					$data[ $key ] = 'theme' === $value ? 'theme' : 'canvas';
					break;
				case 'lp_model':
					$data[ $key ] = LpTemplate::clean( $value );
					break;
				case 'lp_target_keyword':
					$data[ $key ] = Utils::truncate( Utils::clean_text( $value ), 100 );
					break;
				case 'lp_post_type':
				case 'article_post_type':
					$data[ $key ] = in_array( $value, array( 'post', 'page' ), true ) ? $value : 'post';
					break;
				case 'publish_status':
					$data[ $key ] = in_array( $value, array( 'publish', 'draft', 'pending' ), true ) ? $value : 'draft';
					break;
				case 'framework':
					$data[ $key ] = sanitize_key( (string) $value );
					break;
				case 'mofu_count':
				case 'tofu_count':
					$data[ $key ] = Utils::clamp_count( $value );
					break;
				case 'article_category':
					$data[ $key ] = absint( $value );
					break;
				case 'index_delay':
					$data[ $key ] = max( 0, min( 300, absint( $value ) ) );
					break;
				case 'content_push_enabled':
				case 'internal_link_enabled':
					$data[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'content_push_until':
				case 'internal_link_until':
					$data[ $key ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? (string) $value : '';
					break;
				case 'internal_link_target':
					$data[ $key ] = in_array( $value, array( 'bofu', 'next_stage' ), true ) ? $value : 'bofu';
					break;
				case 'index_enabled':
					$data[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'article_theme':
					$data[ $key ] = in_array( $value, array( 'editorial', 'magazine', 'minimal' ), true ) ? $value : 'editorial';
					break;
				case 'contact_email':
					$data[ $key ] = sanitize_email( (string) $value );
					break;
				case 'contact_address':
					$data[ $key ] = Utils::clean_textarea( $value );
					break;
				case 'contact_hours':
					$data[ $key ] = Utils::clean_text( $value );
					break;
				case 'offer_url':
					$clean_url = Utils::clean_url( $value );
					if ( '' === $clean_url && '' !== trim( (string) $value ) ) {
						// Keep the user's text visible so it can be corrected; report why LP will stop.
						$clean_url = Utils::clean_text( $value );
						Database::log( 'warning', 'URL CTA tidak valid dan disimpan apa adanya: ' . Utils::truncate( $clean_url, 120 ), $post_id );
					}
					$data[ $key ] = $clean_url;
					break;
				case 'bofu_mode':
					$data[ $key ] = in_array( $value, array( 'new', 'existing' ), true ) ? $value : 'new';
					break;
				case 'bofu_path':
					$data[ $key ] = FunnelContent::clean_path( (string) $value );
					break;
				case 'parent_pages':
					$data[ $key ] = wp_json_encode( LpParentPages::clean( $value ) );
					break;
				case 'bofu_existing_id':
					$data[ $key ] = absint( $value );
					break;
				case 'topic_mode':
					$data[ $key ] = in_array( $value, array( 'auto', 'manual' ), true ) ? $value : 'auto';
					break;
				case 'manual_mofu_topics':
				case 'manual_tofu_topics':
				case 'research_sources':
				case 'target_areas':
				case 'benefits':
				case 'bonus':
					$data[ $key ] = Utils::clean_textarea( $value );
					break;
				case 'entity_enabled':
				case 'tag_content_enabled':
				case 'auto_publish':
				case 'amazon_enabled':
					$data[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'entity_count':
					$data[ $key ] = max( 8, min( 12, absint( $value ) ) );
					break;
				case 'tag_max':
					$data[ $key ] = max( 1, min( 3, absint( $value ) ) );
					break;
				case 'tag_schedule_delay':
				case 'schedule_interval':
					$data[ $key ] = max( 0, min( 10080, absint( $value ) ) );
					break;
				case 'schedule_jitter':
					$data[ $key ] = max( 0, min( 60, absint( $value ) ) );
					break;
				case 'tag_schedule_mode':
					$data[ $key ] = Utils::clean_tag_schedule_mode( $value );
					break;
				case 'schedule_mode':
					$data[ $key ] = Utils::clean_schedule_mode( $value );
					break;
				case 'tag_schedule_time':
				case 'schedule_time':
					$data[ $key ] = Utils::clean_time( $value );
					break;
				case 'schedule_start':
					$data[ $key ] = Utils::clean_datetime_local( $value );
					break;
				case 'phone':
				case 'brand':
				case 'city':
				case 'amazon_keyword':
					$data[ $key ] = Utils::clean_text( $value );
					break;
				case 'image_enabled':
					$data[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'image_source':
					$data[ $key ] = ImagePipeline::clean_source( $value );
					break;
				case 'image_count':
					$data[ $key ] = max( 0, min( 6, absint( $value ) ) );
					break;
				case 'aida_hero_image_id':
				case 'aida_interest_image_id':
				case 'aida_desire_image_id':
					$data[ $key ] = absint( $value );
					break;
				case 'image_upload_ids':
					$data[ $key ] = implode( ',', ImagePipeline::clean_upload_ids( $value ) );
					break;
				default:
					$data[ $key ] = Utils::clean_text( $value );
			}
		}
		if ( ! empty( $data['bofu_path'] ) && false !== strpos( (string) $data['bofu_path'], '/' ) && 'page' !== (string) ( $data['lp_post_type'] ?? '' ) ) {
			$data['lp_post_type'] = 'page';
			Database::log( 'warning', 'Path LP bertingkat terdeteksi; post type LP otomatis diubah ke Page.', $post_id );
		}
		ContentRepository::save_campaign_meta( $post_id, $data );
	}
}
