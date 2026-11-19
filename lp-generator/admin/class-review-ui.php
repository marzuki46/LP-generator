<?php
/**
 * Dashboard, review, and logs screens.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewUI
 */
final class ReviewUI {

	/**
	 * Dashboard: campaigns and queue progress.
	 */
	public static function render_dashboard(): void {
		if ( ! current_user_can( 'lpgen_manage' ) ) {
			return;
		}
		$campaigns = get_posts(
			array(
				'post_type'      => 'lpgen_campaign',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap lpgen-wrap">
			<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
				<h1 style="margin:0;"><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="lpgen-badge" style="background:#e0f2fe;color:#0369a1;font-weight:600;">v<?php echo esc_html( LPG_VERSION ); ?></span>
					<button type="button" class="button button-secondary" data-lpgen-action="check-update">
						<span class="dashicons dashicons-update" aria-hidden="true" style="vertical-align:text-bottom;font-size:16px;"></span>
						<?php echo esc_html__( 'Cek Update', 'lp-generator' ); ?>
					</button>
				</div>
			</div>
			<div id="lpgen-tool-result" aria-live="polite" style="margin-bottom:16px;"></div>
			<p><?php echo esc_html__( 'Ringkasan campaign dan antrean job. Generate berjalan otomatis berurutan; review dilakukan di akhir.', 'lp-generator' ); ?></p>
			<table class="widefat striped lpgen-table">
				<thead>
				<tr>
					<th><?php echo esc_html__( 'Campaign', 'lp-generator' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'lp-generator' ); ?></th>
					<th><?php echo esc_html__( 'Antrean', 'lp-generator' ); ?></th>
					<th><?php echo esc_html__( 'Progres', 'lp-generator' ); ?></th>
					<th><?php echo esc_html__( 'Aksi', 'lp-generator' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( empty( $campaigns ) ) : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'Belum ada campaign. Buat di menu Campaigns → Add New.', 'lp-generator' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<?php
						$id       = (int) $campaign->ID;
						$status   = (string) ContentRepository::get_meta( $id, 'status', 'draft' );
						$total    = Database::count_jobs( $id );
						$done     = Database::count_jobs( $id, 'done' );
						$failed   = Database::count_jobs( $id, 'failed' );
						$percent  = $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;
						$review_url = admin_url( 'admin.php?page=lpg-review&campaign_id=' . $id );
						$edit_url   = admin_url( 'post.php?post=' . $id . '&action=edit' );
						?>
						<tr data-lpgen-campaign="<?php echo esc_attr( (string) $id ); ?>">
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></td>
							<td data-lpgen-field="status"><span class="lpgen-badge lpgen-badge--<?php echo esc_attr( sanitize_key( $status ) ); ?>"><?php echo esc_html( 'draft' === $status ? __( 'Belum digenerate', 'lp-generator' ) : $status ); ?></span>
							<?php if ( '' === Utils::clean_url( (string) ContentRepository::get_meta( $id, 'offer_url', '' ) ) ) : ?>
								<br><small style="color:#b32d2e;"><?php echo esc_html__( 'URL CTA belum valid — isi di edit campaign.', 'lp-generator' ); ?></small>
							<?php endif; ?>
						</td>
							<td data-lpgen-field="queue">
								<?php
								printf(
									/* translators: 1: done, 2: total, 3: failed */
									esc_html__( '%1$d/%2$d selesai · %3$d gagal', 'lp-generator' ),
									(int) $done,
									(int) $total,
									(int) $failed
								);
								?>
							</td>
							<td>
								<div class="lpgen-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>" aria-valuemin="0" aria-valuemax="100">
									<div class="lpgen-progress__bar" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></div>
								</div>
								<small data-lpgen-field="percent"><?php echo esc_html( $percent . '%' ); ?></small>
							</td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $review_url ); ?>"><?php echo esc_html__( 'Review', 'lp-generator' ); ?></a>
								<button type="button" class="button button-small" data-lpgen-action="start-campaign" data-campaign="<?php echo esc_attr( (string) $id ); ?>"><?php echo esc_html__( 'Generate', 'lp-generator' ); ?></button>
								<button type="button" class="button button-small" data-lpgen-action="restart-campaign" data-campaign="<?php echo esc_attr( (string) $id ); ?>"><?php echo esc_html__( 'Mulai dari Awal', 'lp-generator' ); ?></button>
								<button type="button" class="button button-small" data-lpgen-action="run-now" data-campaign="<?php echo esc_attr( (string) $id ); ?>"><?php echo esc_html__( 'Proses Antrean', 'lp-generator' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
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
		<?php
	}

	/**
	 * Review screen for one campaign.
	 */
	public static function render_review(): void {
		if ( ! current_user_can( 'lpgen_manage' ) ) {
			return;
		}
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$campaigns   = get_posts(
			array(
				'post_type'      => 'lpgen_campaign',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap lpgen-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="lpg-review" />
				<select name="campaign_id" onchange="this.form.submit()">
					<option value="0"><?php echo esc_html__( '— pilih campaign —', 'lp-generator' ); ?></option>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<option value="<?php echo esc_attr( (string) $campaign->ID ); ?>" <?php selected( $campaign_id, (int) $campaign->ID ); ?>><?php echo esc_html( get_the_title( $campaign ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<noscript><button class="button"><?php echo esc_html__( 'Pilih', 'lp-generator' ); ?></button></noscript>
			</form>
			<?php if ( $campaign_id > 0 ) : ?>
				<?php
				$status   = (string) ContentRepository::get_meta( $campaign_id, 'status', 'draft' );
				$items    = ContentRepository::items( $campaign_id );
				$campaign = ContentRepository::get_campaign( $campaign_id );
				$entities = ContentRepository::get_entities( $campaign_id );
				$terms    = array();
				foreach ( $entities as $entity ) {
					$terms[] = (string) ( $entity['term'] ?? '' );
				}
				$tags     = ContentRepository::collect_tags( $campaign_id, 3 );
				$mode     = Utils::clean_schedule_mode( $campaign['schedule_mode'] ?? 'immediate' );
				?>
				<div class="lpgen-panel">
					<p><strong><?php echo esc_html__( 'LP tujuan:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) ( $campaign['bofu_url'] ?? '' ) ); ?></p>
					<p>
						<strong><?php echo esc_html__( 'Entitas keyword:', 'lp-generator' ); ?></strong>
						<?php echo esc_html( empty( $terms ) ? __( 'belum ada', 'lp-generator' ) : implode( ', ', $terms ) ); ?>
						<button type="button" class="button button-small" data-lpgen-action="generate-entities" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>"><?php echo esc_html__( 'Generate ulang', 'lp-generator' ); ?></button>
					</p>
					<p>
						<strong><?php echo esc_html__( 'Tag:', 'lp-generator' ); ?></strong>
						<?php if ( empty( $tags ) ) : ?>
							<?php echo esc_html__( 'belum ada', 'lp-generator' ); ?>
						<?php else : ?>
							<?php foreach ( $tags as $tag ) : ?>
								<code><?php echo esc_html( $tag ); ?></code>
								<button type="button" class="button button-small" data-lpgen-action="generate-tag-content" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>" data-tag="<?php echo esc_attr( $tag ); ?>"><?php echo esc_html__( 'Buat konten', 'lp-generator' ); ?></button>
							<?php endforeach; ?>
						<?php endif; ?>
					</p>
					<p>
						<strong><?php echo esc_html__( 'Jadwal publish:', 'lp-generator' ); ?></strong>
						<?php echo esc_html( sprintf( '%s · interval %d menit · delay index %d detik', $mode, (int) ( $campaign['schedule_interval'] ?? 0 ), (int) ( $campaign['index_delay'] ?? 10 ) ) ); ?>
					</p>
				</div>
				<p>
					<?php echo esc_html__( 'Status campaign:', 'lp-generator' ); ?>
					<strong><?php echo esc_html( $status ); ?></strong>
					<button type="button" class="button" data-lpgen-action="run-now" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>" style="margin-left:8px;">
						<?php echo esc_html__( '⚡ Proses Antrean Sekarang', 'lp-generator' ); ?>
					</button>
					<?php if ( 'review' === $status || 'publishing' === $status ) : ?>
						<button type="button" class="button button-primary" data-lpgen-action="publish-campaign" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>">
							<?php echo esc_html__( 'Publish & Index', 'lp-generator' ); ?>
						</button>
					<?php endif; ?>
				</p>
				<table class="widefat striped lpgen-table">
					<thead>
					<tr>
						<th><?php echo esc_html__( 'Stage', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Jenis', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Tag', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Judul', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Model / Token', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Aksi', 'lp-generator' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="7"><?php echo esc_html__( 'Belum ada item. Jalankan generate terlebih dahulu.', 'lp-generator' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$item_status = (string) $item['status'];
							$item_id     = (int) $item['id'];
							$permalink   = (string) $item['permalink'];
							$edit_url    = admin_url( 'post.php?post=' . $item_id . '&action=edit' );
							?>
							<tr>
								<td><?php echo esc_html( strtoupper( (string) $item['stage'] ) ); ?></td>
								<td><?php echo esc_html( (string) $item['kind'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', ContentRepository::item_tags( (int) $item['id'] ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a>
									<details><summary><?php echo esc_html__( 'Isi, SEO, anchor & entitas', 'lp-generator' ); ?></summary>
									<p><strong><?php echo esc_html__( 'SEO title:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) $item['seo_title'] ); ?><br />
									<strong><?php echo esc_html__( 'Deskripsi:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) $item['meta_description'] ); ?><br />
									<strong><?php echo esc_html__( 'Anchor ke BOFU:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) $item['anchor'] ); ?></p>
									<?php $item_entities = json_decode( (string) ( $item['entities'] ?? '' ), true ); ?>
									<p><strong><?php echo esc_html__( 'Entitas konten:', 'lp-generator' ); ?></strong> <?php echo esc_html( implode( ', ', array_column( is_array( $item_entities ) ? $item_entities : array(), 'term' ) ) ); ?></p>
									<div style="max-height:420px;overflow:auto;background:#fff;padding:12px;"><?php echo wp_kses_post( (string) $item['content'] ); ?></div>
									<?php if ( 'lp' === $item['kind'] ) : ?>
									<p><strong><?php echo esc_html__( 'Preview HTML dari data campaign tersimpan:', 'lp-generator' ); ?></strong></p>
									<iframe id="lpgen-preview-<?php echo esc_attr( (string) $item_id ); ?>" title="<?php echo esc_attr__( 'Preview landing page', 'lp-generator' ); ?>" sandbox="" loading="lazy" style="width:100%;height:720px;border:1px solid #ccd0d4;background:#fff;" srcdoc="<?php echo esc_attr( LandingPage::preview_document( 'published' === $item_status && ! empty( $item['post_id'] ) ? (string) get_post_field( 'post_content', (int) $item['post_id'] ) : (string) $item['content'] ) ); ?>"></iframe>
									<p><button type="button" class="button" data-lpgen-preview-download="lpgen-preview-<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Unduh preview HTML', 'lp-generator' ); ?></button></p>
									<p><strong><?php echo esc_html__( 'HTML WordPress (salin dari sini atau edit pada item):', 'lp-generator' ); ?></strong></p>
									<textarea readonly rows="8" class="large-text code" aria-label="<?php echo esc_attr__( 'HTML landing page WordPress', 'lp-generator' ); ?>"><?php echo esc_html( (string) $item['content'] ); ?></textarea>
									<?php endif; ?>
									</details>
									<?php if ( '' !== $permalink ) : ?>
										<br /><a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Lihat publish →', 'lp-generator' ); ?></a>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( (string) $item['model'] ); ?>
									<?php if ( (int) $item['tokens'] > 0 ) : ?>
										<br /><small><?php echo esc_html( (string) $item['tokens'] . ' tokens' ); ?></small>
									<?php endif; ?>
								</td>
								<td><span class="lpgen-badge lpgen-badge--<?php echo esc_attr( sanitize_key( $item_status ) ); ?>"><?php echo esc_html( $item_status ); ?></span>
									<?php if ( '' !== (string) $item['error'] ) : ?>
										<br /><small><?php echo esc_html( (string) $item['error'] ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-small" data-lpgen-action="set-status" data-status="approved" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Approve', 'lp-generator' ); ?></button>
									<button type="button" class="button button-small" data-lpgen-action="set-status" data-status="rejected" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Reject', 'lp-generator' ); ?></button>
									<button type="button" class="button button-small" data-lpgen-action="regenerate" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Regenerate', 'lp-generator' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<div id="lpgen-action-result" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Logs screen.
	 */
	public static function render_logs(): void {
		if ( ! current_user_can( 'lpgen_manage' ) ) {
			return;
		}
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$level       = isset( $_GET['level'] ) ? sanitize_key( (string) wp_unslash( $_GET['level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit       = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 200; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stats       = Database::log_stats();
		$logs        = Database::logs_filtered( $campaign_id, $level, $search, $limit );
		$campaigns   = get_posts(
			array(
				'post_type'      => 'lpgen_campaign',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$titles = array();
		foreach ( $campaigns as $campaign ) {
			$titles[ (int) $campaign->ID ] = get_the_title( $campaign );
		}
		$levels = array(
			''        => __( 'Semua level', 'lp-generator' ),
			'info'    => __( 'Info', 'lp-generator' ),
			'success' => __( 'Success', 'lp-generator' ),
			'warn'    => __( 'Warning', 'lp-generator' ),
			'error'   => __( 'Error', 'lp-generator' ),
		);
		?>
		<div class="wrap lpgen-wrap lpgen-logs">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Catatan progres campaign, generate, publish, gambar, dan indexing. Gunakan filter atau hapus log yang tidak diperlukan.', 'lp-generator' ); ?></p>
			<div class="lpgen-log-stats">
				<button type="button" class="lpgen-stat" data-level=""><strong><?php echo esc_html( (string) $stats['total'] ); ?></strong><span><?php echo esc_html__( 'Total log', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="info"><strong><?php echo esc_html( (string) $stats['info'] ); ?></strong><span><?php echo esc_html__( 'Info', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="success"><strong><?php echo esc_html( (string) $stats['success'] ); ?></strong><span><?php echo esc_html__( 'Success', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="warn"><strong><?php echo esc_html( (string) $stats['warning'] ); ?></strong><span><?php echo esc_html__( 'Warning', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="error"><strong><?php echo esc_html( (string) $stats['error'] ); ?></strong><span><?php echo esc_html__( 'Error', 'lp-generator' ); ?></span></button>
				<div class="lpgen-stat lpgen-stat--static"><strong><?php echo esc_html( (string) $stats['campaigns'] ); ?></strong><span><?php echo esc_html__( 'Campaign tercatat', 'lp-generator' ); ?></span></div>
			</div>
			<form method="get" action="" id="lpgen-log-filters" class="lpgen-log-filters">
				<input type="hidden" name="page" value="lpg-logs" />
				<label>
					<span><?php echo esc_html__( 'Campaign', 'lp-generator' ); ?></span>
					<select name="campaign_id">
						<option value="0"><?php echo esc_html__( 'Semua campaign', 'lp-generator' ); ?></option>
						<?php foreach ( $campaigns as $campaign ) : ?>
							<option value="<?php echo esc_attr( (string) $campaign->ID ); ?>" <?php selected( $campaign_id, (int) $campaign->ID ); ?>><?php echo esc_html( get_the_title( $campaign ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php echo esc_html__( 'Level', 'lp-generator' ); ?></span>
					<select name="level">
						<?php foreach ( $levels as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="lpgen-log-search">
					<span><?php echo esc_html__( 'Cari pesan', 'lp-generator' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="keyword, campaign, error…" />
				</label>
				<label>
					<span><?php echo esc_html__( 'Baris', 'lp-generator' ); ?></span>
					<select name="limit">
						<?php foreach ( array( 50, 100, 200, 500 ) as $option ) : ?>
							<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $limit, $option ); ?>><?php echo esc_html( (string) $option ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Terapkan', 'lp-generator' ); ?></button>
				<button type="button" class="button" data-lpgen-action="clear-logs"><?php echo esc_html__( 'Hapus sesuai filter', 'lp-generator' ); ?></button>
				<button type="button" class="button" data-lpgen-action="clear-logs" data-all="1"><?php echo esc_html__( 'Hapus semua log', 'lp-generator' ); ?></button>
				<label class="lpgen-log-auto"><input type="checkbox" id="lpgen-log-autorefresh" /> <?php echo esc_html__( 'Auto-refresh 15 detik', 'lp-generator' ); ?></label>
			</form>
			<div id="lpgen-action-result" aria-live="polite"></div>
			<p class="lpgen-log-count"><?php echo esc_html( sprintf( __( 'Menampilkan %d baris terbaru.', 'lp-generator' ), count( $logs ) ) ); ?></p>
			<div class="lpgen-log-table-wrap">
				<table class="widefat striped lpgen-table lpgen-log-table">
					<thead>
					<tr>
						<th class="lpgen-log-check"><input type="checkbox" id="lpgen-log-check-all" aria-label="<?php echo esc_attr__( 'Pilih semua log', 'lp-generator' ); ?>" /></th>
						<th><?php echo esc_html__( 'Waktu (UTC)', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Level', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Campaign', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Job', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Pesan progres', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Context', 'lp-generator' ); ?></th>
						<th><?php echo esc_html__( 'Aksi', 'lp-generator' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="8"><?php echo esc_html__( 'Belum ada log yang cocok.', 'lp-generator' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$log_id      = (int) ( $log['id'] ?? 0 );
							$log_level   = sanitize_key( (string) ( $log['level'] ?? 'info' ) );
							$log_campaign = (int) ( $log['campaign_id'] ?? 0 );
							$log_context = (string) ( $log['context'] ?? '' );
							?>
							<tr>
								<td class="lpgen-log-check"><input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( (string) $log_id ); ?>" /></td>
								<td><time datetime="<?php echo esc_attr( (string) $log['created_at'] ); ?>"><?php echo esc_html( (string) $log['created_at'] ); ?></time></td>
								<td><span class="lpgen-log-level lpgen-log-level--<?php echo esc_attr( $log_level ); ?>"><?php echo esc_html( $log_level ); ?></span></td>
								<td><?php echo $log_campaign > 0 ? esc_html( $titles[ $log_campaign ] ?? ( '#' . $log_campaign ) ) : '—'; ?></td>
								<td><?php echo esc_html( (string) ( $log['job_id'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $log['message'] ?? '' ) ); ?></td>
								<td><code title="<?php echo esc_attr( $log_context ); ?>"><?php echo esc_html( substr( $log_context, 0, 120 ) ); ?></code></td>
								<td>
									<button type="button" class="button-link" data-lpgen-action="delete-log" data-log="<?php echo esc_attr( (string) $log_id ); ?>"><?php echo esc_html__( 'Hapus', 'lp-generator' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
			<button type="button" class="button" data-lpgen-action="clear-logs" data-selected="1"><?php echo esc_html__( 'Hapus log terpilih', 'lp-generator' ); ?></button>
		</div>
		<?php
	}
}
