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
			'mofu_count'        => 2,
			'tofu_count'        => 3,
			'language'          => 'id',
			'framework'         => 'aida',
			'lp_post_type'      => 'page',
			'article_post_type' => 'post',
			'publish_status'    => 'publish',
			'index_enabled'     => 1,
			'index_delay'       => 10,
			'phone'             => '',
			'brand'             => '',
			'city'              => '',
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
		?>
		<table class="form-table lpgen-table">
			<tbody>
			<tr>
				<th><label for="lpgen_offer_type"><?php echo esc_html__( 'Tipe offer', 'lp-generator' ); ?></label></th>
				<td>
					<select name="lpgen[offer_type]" id="lpgen_offer_type">
						<?php foreach ( array( 'product' => __( 'Produk', 'lp-generator' ), 'service' => __( 'Jasa', 'lp-generator' ), 'affiliate' => __( 'Afiliasi', 'lp-generator' ) ) as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['offer_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="lpgen_offer_name"><?php echo esc_html__( 'Nama offer', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[offer_name]" id="lpgen_offer_name" value="<?php echo esc_attr( (string) $values['offer_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_offer_url"><?php echo esc_html__( 'URL offer / CTA', 'lp-generator' ); ?></label></th>
				<td><input type="url" class="regular-text" name="lpgen[offer_url]" id="lpgen_offer_url" value="<?php echo esc_attr( (string) $values['offer_url'] ); ?>" placeholder="https://..." /></td>
			</tr>
			<tr>
				<th><label for="lpgen_audience"><?php echo esc_html__( 'Audiens', 'lp-generator' ); ?></label></th>
				<td><textarea class="large-text" rows="2" name="lpgen[audience]" id="lpgen_audience"><?php echo esc_textarea( (string) $values['audience'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="lpgen_problem"><?php echo esc_html__( 'Masalah utama', 'lp-generator' ); ?></label></th>
				<td><textarea class="large-text" rows="2" name="lpgen[problem]" id="lpgen_problem"><?php echo esc_textarea( (string) $values['problem'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="lpgen_promise"><?php echo esc_html__( 'Janji / hasil', 'lp-generator' ); ?></label></th>
				<td><textarea class="large-text" rows="2" name="lpgen[promise]" id="lpgen_promise"><?php echo esc_textarea( (string) $values['promise'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="lpgen_benefits"><?php echo esc_html__( 'Benefit (satu per baris)', 'lp-generator' ); ?></label></th>
				<td><textarea class="large-text" rows="4" name="lpgen[benefits]" id="lpgen_benefits"><?php echo esc_textarea( (string) $values['benefits'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="lpgen_price"><?php echo esc_html__( 'Harga / start from', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[price]" id="lpgen_price" value="<?php echo esc_attr( (string) $values['price'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_bonus"><?php echo esc_html__( 'Bonus (satu per baris)', 'lp-generator' ); ?></label></th>
				<td><textarea class="large-text" rows="3" name="lpgen[bonus]" id="lpgen_bonus"><?php echo esc_textarea( (string) $values['bonus'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="lpgen_guarantee"><?php echo esc_html__( 'Garansi', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[guarantee]" id="lpgen_guarantee" value="<?php echo esc_attr( (string) $values['guarantee'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_cta"><?php echo esc_html__( 'Teks tombol CTA', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[cta]" id="lpgen_cta" value="<?php echo esc_attr( (string) $values['cta'] ); ?>" placeholder="Coba Sekarang" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_voice"><?php echo esc_html__( 'Tone of voice', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[voice]" id="lpgen_voice" value="<?php echo esc_attr( (string) $values['voice'] ); ?>" placeholder="professional, ramah, tegas" /></td>
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
				<th><label for="lpgen_framework"><?php echo esc_html__( 'Framework', 'lp-generator' ); ?></label></th>
				<td>
					<select name="lpgen[framework]" id="lpgen_framework">
						<?php foreach ( Frameworks::for_select() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['framework'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p>
						<button type="button" class="button" data-lpgen-action="generate-framework" data-campaign="<?php echo esc_attr( (string) $post->ID ); ?>"><?php echo esc_html__( 'Generate framework AI (langkah + gambar per section)', 'lp-generator' ); ?></button>
					</p>
					<?php
					$ai_framework = Frameworks::sanitize_generated( (string) ContentRepository::get_meta( $post->ID, 'ai_framework', '' ) );
					if ( ! empty( $ai_framework['steps'] ) ) :
						?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Framework AI aktif: %s', 'lp-generator' ), $ai_framework['label'] ) ); ?></p>
						<ul class="lpgen-framework-steps">
							<?php foreach ( $ai_framework['steps'] as $step ) : ?>
								<li><strong><?php echo esc_html( $step['name'] ); ?></strong> — <?php echo esc_html( $step['section_hint'] ); ?> / <?php echo esc_html( $step['image_role'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="lpgen_mofu_count"><?php echo esc_html__( 'Jumlah artikel MOFU', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="1" max="10" name="lpgen[mofu_count]" id="lpgen_mofu_count" value="<?php echo esc_attr( (string) $values['mofu_count'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_tofu_count"><?php echo esc_html__( 'Jumlah artikel TOFU', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="1" max="10" name="lpgen[tofu_count]" id="lpgen_tofu_count" value="<?php echo esc_attr( (string) $values['tofu_count'] ); ?>" /></td>
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
				<th><label for="lpgen_publish_status"><?php echo esc_html__( 'Status publish', 'lp-generator' ); ?></label></th>
				<td>
					<select name="lpgen[publish_status]" id="lpgen_publish_status">
						<?php foreach ( array( 'publish', 'draft', 'pending' ) as $status_option ) : ?>
							<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $values['publish_status'], $status_option ); ?>><?php echo esc_html( ucfirst( $status_option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="lpgen_index_enabled"><?php echo esc_html__( 'Submit indexer', 'lp-generator' ); ?></label></th>
				<td><label><input type="checkbox" name="lpgen[index_enabled]" value="1" id="lpgen_index_enabled" <?php checked( 1, (int) $values['index_enabled'] ); ?> /> <?php echo esc_html__( 'Aktif (jeda 10 detik setelah publish)', 'lp-generator' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="lpgen_index_delay"><?php echo esc_html__( 'Jeda index (detik)', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="0" max="300" name="lpgen[index_delay]" id="lpgen_index_delay" value="<?php echo esc_attr( (string) $values['index_delay'] ); ?>" /></td>
			</tr>
			<tr><th colspan="2"><h3><?php echo esc_html__( 'Data Campaign (placeholder SEO)', 'lp-generator' ); ?></h3></th></tr>
			<tr>
				<th><label for="lpgen_phone"><?php echo esc_html__( 'No HP / WhatsApp', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[phone]" id="lpgen_phone" value="<?php echo esc_attr( (string) $values['phone'] ); ?>" placeholder="0812..." /></td>
			</tr>
			<tr>
				<th><label for="lpgen_brand"><?php echo esc_html__( 'Brand / nama usaha', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[brand]" id="lpgen_brand" value="<?php echo esc_attr( (string) $values['brand'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_city"><?php echo esc_html__( 'Kota / area layanan', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[city]" id="lpgen_city" value="<?php echo esc_attr( (string) $values['city'] ); ?>" /></td>
			</tr>
			<tr><th colspan="2"><h3><?php echo esc_html__( 'Entitas Keyword & Tag', 'lp-generator' ); ?></h3></th></tr>
			<tr>
				<th><label for="lpgen_entity_enabled"><?php echo esc_html__( 'Generate entitas keyword', 'lp-generator' ); ?></label></th>
				<td><label><input type="checkbox" name="lpgen[entity_enabled]" value="1" id="lpgen_entity_enabled" <?php checked( 1, (int) $values['entity_enabled'] ); ?> /> <?php echo esc_html__( 'Aktif', 'lp-generator' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="lpgen_entity_count"><?php echo esc_html__( 'Jumlah entitas', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="3" max="15" name="lpgen[entity_count]" id="lpgen_entity_count" value="<?php echo esc_attr( (string) $values['entity_count'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_tag_max"><?php echo esc_html__( 'Maksimal tag per konten', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="1" max="3" name="lpgen[tag_max]" id="lpgen_tag_max" value="<?php echo esc_attr( (string) $values['tag_max'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_tag_content_enabled"><?php echo esc_html__( 'Artikel otomatis per tag', 'lp-generator' ); ?></label></th>
				<td><label><input type="checkbox" name="lpgen[tag_content_enabled]" value="1" id="lpgen_tag_content_enabled" <?php checked( 1, (int) $values['tag_content_enabled'] ); ?> /> <?php echo esc_html__( 'Aktif', 'lp-generator' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="lpgen_tag_schedule_mode"><?php echo esc_html__( 'Jadwal artikel tag', 'lp-generator' ); ?></label></th>
				<td>
					<select name="lpgen[tag_schedule_mode]" id="lpgen_tag_schedule_mode">
						<?php foreach ( array( 'after_publish' => __( 'Setelah publish', 'lp-generator' ), 'fixed' => __( 'Tanggal tertentu', 'lp-generator' ), 'daily' => __( 'Satu per hari', 'lp-generator' ) ) as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['tag_schedule_mode'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="lpgen_tag_schedule_delay"><?php echo esc_html__( 'Delay / jeda tag (menit)', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="0" max="10080" name="lpgen[tag_schedule_delay]" id="lpgen_tag_schedule_delay" value="<?php echo esc_attr( (string) $values['tag_schedule_delay'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_tag_schedule_time"><?php echo esc_html__( 'Jam artikel tag', 'lp-generator' ); ?></label></th>
				<td><input type="time" name="lpgen[tag_schedule_time]" id="lpgen_tag_schedule_time" value="<?php echo esc_attr( Utils::clean_time( $values['tag_schedule_time'] ) ); ?>" /></td>
			</tr>
			<tr><th colspan="2"><h3><?php echo esc_html__( 'Publish & Schedule', 'lp-generator' ); ?></h3></th></tr>
			<tr>
				<th><label for="lpgen_auto_publish"><?php echo esc_html__( 'Auto publish', 'lp-generator' ); ?></label></th>
				<td><label><input type="checkbox" name="lpgen[auto_publish]" value="1" id="lpgen_auto_publish" <?php checked( 1, (int) $values['auto_publish'] ); ?> /> <?php echo esc_html__( 'Lewati review (juga bisa diaktifkan global)', 'lp-generator' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="lpgen_schedule_mode"><?php echo esc_html__( 'Mode jadwal', 'lp-generator' ); ?></label></th>
				<td>
					<select name="lpgen[schedule_mode]" id="lpgen_schedule_mode">
						<?php foreach ( array( 'immediate' => __( 'Secepatnya', 'lp-generator' ), 'interval' => __( 'Jeda antar post', 'lp-generator' ), 'fixed' => __( 'Tanggal tertentu', 'lp-generator' ), 'daily' => __( 'Satu per hari', 'lp-generator' ) ) as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['schedule_mode'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="lpgen_schedule_interval"><?php echo esc_html__( 'Jeda antar post (menit)', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="0" max="10080" name="lpgen[schedule_interval]" id="lpgen_schedule_interval" value="<?php echo esc_attr( (string) $values['schedule_interval'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_schedule_start"><?php echo esc_html__( 'Mulai (YYYY-MM-DDTHH:MM)', 'lp-generator' ); ?></label></th>
				<td><input type="datetime-local" name="lpgen[schedule_start]" id="lpgen_schedule_start" value="<?php echo esc_attr( Utils::clean_datetime_local( $values['schedule_start'] ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_schedule_time"><?php echo esc_html__( 'Jam publish harian', 'lp-generator' ); ?></label></th>
				<td><input type="time" name="lpgen[schedule_time]" id="lpgen_schedule_time" value="<?php echo esc_attr( Utils::clean_time( $values['schedule_time'] ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="lpgen_schedule_jitter"><?php echo esc_html__( 'Jitter acak (menit)', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="0" max="60" name="lpgen[schedule_jitter]" id="lpgen_schedule_jitter" value="<?php echo esc_attr( (string) $values['schedule_jitter'] ); ?>" /></td>
			</tr>
			<tr><th colspan="2"><h3><?php echo esc_html__( 'Amazon Affiliate', 'lp-generator' ); ?></h3></th></tr>
			<tr>
				<th><label for="lpgen_amazon_enabled"><?php echo esc_html__( 'Pakai produk Amazon', 'lp-generator' ); ?></label></th>
				<td><label><input type="checkbox" name="lpgen[amazon_enabled]" value="1" id="lpgen_amazon_enabled" <?php checked( 1, (int) $values['amazon_enabled'] ); ?> /> <?php echo esc_html__( 'Aktif untuk campaign ini', 'lp-generator' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="lpgen_amazon_keyword"><?php echo esc_html__( 'Keyword produk Amazon', 'lp-generator' ); ?></label></th>
				<td><input type="text" class="regular-text" name="lpgen[amazon_keyword]" id="lpgen_amazon_keyword" value="<?php echo esc_attr( (string) $values['amazon_keyword'] ); ?>" placeholder="<?php echo esc_attr( (string) $values['offer_name'] ); ?>" /></td>
			</tr>
			<tr><th colspan="2"><h3><?php echo esc_html__( 'Gambar per Konten', 'lp-generator' ); ?></h3></th></tr>
			<tr>
				<th><label for="lpgen_image_enabled"><?php echo esc_html__( 'Sisipkan galeri gambar', 'lp-generator' ); ?></label></th>
				<td><input type="hidden" name="lpgen[image_enabled]" value="0" /><label><input type="checkbox" name="lpgen[image_enabled]" value="1" id="lpgen_image_enabled" <?php checked( 1, (int) $values['image_enabled'] ); ?> /> <?php echo esc_html__( 'Aktif untuk LP dan semua artikel', 'lp-generator' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="lpgen_image_source"><?php echo esc_html__( 'Engine gambar', 'lp-generator' ); ?></label></th>
				<td>
					<select name="lpgen[image_source]" id="lpgen_image_source">
						<?php foreach ( array( 'auto' => __( 'Otomatis: upload → AI → Bing', 'lp-generator' ), 'upload' => __( 'Upload saja', 'lp-generator' ), 'ai' => __( 'AI 9Router saja', 'lp-generator' ), 'bing' => __( 'Bing Images saja', 'lp-generator' ) ) as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['image_source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="lpgen_image_count"><?php echo esc_html__( 'Jumlah gambar per konten', 'lp-generator' ); ?></label></th>
				<td><input type="number" min="0" max="6" name="lpgen[image_count]" id="lpgen_image_count" value="<?php echo esc_attr( (string) $values['image_count'] ); ?>" /> <span class="description"><?php echo esc_html__( '0 = ikut setting global.', 'lp-generator' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="lpgen_image_upload_ids"><?php echo esc_html__( 'Gambar upload', 'lp-generator' ); ?></label></th>
				<td>
					<input type="hidden" name="lpgen[image_upload_ids]" id="lpgen_image_upload_ids" value="<?php echo esc_attr( (string) $values['image_upload_ids'] ); ?>" />
					<button type="button" class="button" data-lpgen-action="pick-images"><?php echo esc_html__( 'Pilih / upload dari Media Library', 'lp-generator' ); ?></button>
					<p class="description" id="lpgen_image_upload_info"><?php echo esc_html( sprintf( _n( '%d gambar dipilih', '%d gambar dipilih', count( ImagePipeline::clean_upload_ids( $values['image_upload_ids'] ) ), 'lp-generator' ), count( ImagePipeline::clean_upload_ids( $values['image_upload_ids'] ) ) ) ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>
		<p class="lpgen-status-line">
			<?php echo esc_html__( 'Status:', 'lp-generator' ); ?>
			<strong><?php echo esc_html( $status ); ?></strong>
		</p>
		<p>
			<button type="button" class="button button-primary" data-lpgen-action="start-campaign" data-campaign="<?php echo esc_attr( (string) $post->ID ); ?>">
				<?php echo esc_html__( 'Start generation', 'lp-generator' ); ?>
			</button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=lp-generator' ) ); ?>"><?php echo esc_html__( 'Lihat progress', 'lp-generator' ); ?></a>
		</p>
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
				case 'index_enabled':
					$data[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'offer_url':
					$data[ $key ] = Utils::clean_url( $value );
					break;
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
