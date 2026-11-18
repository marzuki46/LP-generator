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
			'article_post_type' => 'post',
			'publish_status'    => 'publish',
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
		$values = array_merge( $defaults, $values );
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
							<input type="text" class="large-text" name="lpgen[offer_name]" id="lpgen_offer_name" value="<?php echo esc_attr( (string) $values['offer_name'] ); ?>" placeholder="Contoh: Jasa Pembuatan Website Solo / Kursus SEO / Hosting X" required />
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
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_offer_url"><?php echo esc_html__( 'URL CTA / WhatsApp / Checkout', 'lp-generator' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" name="lpgen[offer_url]" id="lpgen_offer_url" value="<?php echo esc_attr( (string) $values['offer_url'] ); ?>" placeholder="https://wa.me/62812... atau https://domain.com/beli" />
						</td>
					</tr>
					<tr>
						<th><label for="lpgen_target_areas"><?php echo esc_html__( 'Target Area / Kota (Opsional)', 'lp-generator' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" name="lpgen[target_areas]" id="lpgen_target_areas" placeholder="Solo&#10;Sukoharjo&#10;Karanganyar&#10;Klaten"><?php echo esc_textarea( (string) $values['target_areas'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Satu area per baris. AI akan membagi topik lokal secara alami dan semuanya tetap diarahkan ke BOFU campaign ini.', 'lp-generator' ); ?></p>
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
							<button type="button" class="button" data-lpgen-action="generate-framework" data-campaign="<?php echo esc_attr( (string) $post->ID ); ?>" style="margin-left:6px;"><?php echo esc_html__( '⚡ Generate Framework AI', 'lp-generator' ); ?></button>
							
							<div id="lpgen_framework_steps_preview" style="margin-top:10px;">
								<!-- Rendered via JS and PHP -->
								<?php
								$current_preset = Frameworks::get( (string) $values['framework'] );
								$ai_framework = Frameworks::sanitize_generated( (string) ContentRepository::get_meta( $post->ID, 'ai_framework', '' ) );
								$display_steps = ( 'ai' === $values['framework'] && ! empty( $ai_framework['steps'] ) )
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
								<th><label for="lpgen_language"><?php echo esc_html__( 'Bahasa', 'lp-generator' ); ?></label></th>
								<td>
									<select name="lpgen[language]" id="lpgen_language">
										<option value="id" <?php selected( $values['language'], 'id' ); ?>><?php echo esc_html__( 'Bahasa Indonesia', 'lp-generator' ); ?></option>
										<option value="en" <?php selected( $values['language'], 'en' ); ?>><?php echo esc_html__( 'English', 'lp-generator' ); ?></option>
									</select>
								</td>
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
											<option value="next_stage" <?php selected( $values['internal_link_target'], 'next_stage' ); ?>><?php echo esc_html__( 'Tahap berikutnya (TOFU → MOFU → BOFU)', 'lp-generator' ); ?></option>
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
					$data[ $key ] = 'en' === $value ? 'en' : 'id';
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
				case 'offer_url':
					$data[ $key ] = Utils::clean_url( $value );
					break;
				case 'bofu_mode':
					$data[ $key ] = in_array( $value, array( 'new', 'existing' ), true ) ? $value : 'new';
					break;
				case 'bofu_existing_id':
					$data[ $key ] = absint( $value );
					break;
				case 'topic_mode':
					$data[ $key ] = in_array( $value, array( 'auto', 'manual' ), true ) ? $value : 'auto';
					break;
				case 'manual_mofu_topics':
				case 'manual_tofu_topics':
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
					$data[ $key ] = max( 3, min( 15, absint( $value ) ) );
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
				case 'image_upload_ids':
					$data[ $key ] = implode( ',', ImagePipeline::clean_upload_ids( $value ) );
					break;
				default:
					$data[ $key ] = Utils::clean_text( $value );
			}
		}
		ContentRepository::save_campaign_meta( $post_id, $data );
	}
}
