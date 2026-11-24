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
							<?php $experiment_label = ExperimentMode::admin_label( array( 'experiment_mode' => ContentRepository::get_meta( $id, 'experiment_mode', '' ) ) ); ?>
							<?php if ( '' !== $experiment_label ) : ?>
								<br><span class="lpgen-badge" style="background:#fef3c7;color:#92400e;font-weight:600;"><?php echo esc_html( $experiment_label ); ?></span>
							<?php endif; ?>
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
		<div class="wrap lpgen-wrap lpgen-review">
			<header class="lpgen-review-hero">
				<div class="lpgen-review-hero__main">
					<p class="lpgen-eyebrow"><?php echo esc_html__( 'Review & Publishing', 'lp-generator' ); ?></p>
					<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
					<p class="lpgen-review-hero__desc"><?php echo esc_html__( 'Periksa konten, kualitas, dan status workflow sebelum mempublish.', 'lp-generator' ); ?></p>
				</div>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lpgen-review-picker">
					<input type="hidden" name="page" value="lpg-review" />
					<label for="lpgen_review_campaign"><?php echo esc_html__( 'Campaign', 'lp-generator' ); ?></label>
					<div class="lpgen-review-picker__row">
						<select id="lpgen_review_campaign" name="campaign_id" onchange="this.form.submit()">
							<option value="0"><?php echo esc_html__( '— pilih campaign —', 'lp-generator' ); ?></option>
							<?php foreach ( $campaigns as $campaign ) : ?>
								<option value="<?php echo esc_attr( (string) $campaign->ID ); ?>" <?php selected( $campaign_id, (int) $campaign->ID ); ?>><?php echo esc_html( get_the_title( $campaign ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<noscript><button class="button"><?php echo esc_html__( 'Pilih', 'lp-generator' ); ?></button></noscript>
					</div>
				</form>
			</header>
			<?php if ( 0 === $campaign_id ) : ?>
				<div class="lpgen-review-empty">
					<span class="dashicons dashicons-feedback" aria-hidden="true"></span>
					<h2><?php echo esc_html__( 'Pilih campaign untuk mulai review', 'lp-generator' ); ?></h2>
					<p><?php echo esc_html__( 'Daftar item, kualitas, dan status publish akan tampil di sini.', 'lp-generator' ); ?></p>
				</div>
			<?php endif; ?>
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
				$counts   = array( 'total' => count( $items ), 'generated' => 0, 'approved' => 0, 'published' => 0, 'failed' => 0, 'quality_sum' => 0, 'quality_count' => 0 );
				foreach ( $items as $stat_item ) {
					$stat_status = (string) ( $stat_item['status'] ?? '' );
					if ( in_array( $stat_status, array( 'generated', 'review' ), true ) ) { ++$counts['generated']; }
					if ( 'approved' === $stat_status ) { ++$counts['approved']; }
					if ( 'published' === $stat_status ) { ++$counts['published']; }
					if ( in_array( $stat_status, array( 'failed', 'rejected' ), true ) ) { ++$counts['failed']; }
					$stat_quality = json_decode( (string) get_post_meta( (int) ( $stat_item['id'] ?? 0 ), '_lpgen_v2_quality', true ), true );
					if ( is_array( $stat_quality ) && isset( $stat_quality['score'] ) ) {
						$counts['quality_sum'] += (int) $stat_quality['score'];
						++$counts['quality_count'];
					}
				}
				$quality_avg = $counts['quality_count'] > 0 ? (int) round( $counts['quality_sum'] / $counts['quality_count'] ) : 0;
				?>
				<div class="lpgen-review-summary">
				<section class="lpgen-review-info">
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
				</section>
				<div class="lpgen-review-stats">
					<div class="lpgen-review-stat"><strong><?php echo esc_html( (string) $counts['total'] ); ?></strong><span><?php echo esc_html__( 'Total item', 'lp-generator' ); ?></span></div>
					<div class="lpgen-review-stat"><strong><?php echo esc_html( (string) $counts['generated'] ); ?></strong><span><?php echo esc_html__( 'Siap review', 'lp-generator' ); ?></span></div>
					<div class="lpgen-review-stat"><strong><?php echo esc_html( (string) $counts['approved'] ); ?></strong><span><?php echo esc_html__( 'Disetujui', 'lp-generator' ); ?></span></div>
					<div class="lpgen-review-stat"><strong><?php echo esc_html( (string) $counts['published'] ); ?></strong><span><?php echo esc_html__( 'Terbit', 'lp-generator' ); ?></span></div>
					<div class="lpgen-review-stat <?php echo $counts['failed'] > 0 ? 'lpgen-review-stat--alert' : ''; ?>"><strong><?php echo esc_html( (string) $counts['failed'] ); ?></strong><span><?php echo esc_html__( 'Perlu perhatian', 'lp-generator' ); ?></span></div>
					<div class="lpgen-review-stat"><strong><?php echo $counts['quality_count'] > 0 ? esc_html( (string) $quality_avg ) : '—'; ?></strong><span><?php echo esc_html__( 'Skor kualitas', 'lp-generator' ); ?></span></div>
				</div>
				</div>
				<?php
				$experiment_label = ExperimentMode::admin_label( $campaign );
				$promote_item_id  = 0;
				foreach ( $items as $candidate ) {
					if ( absint( $candidate['post_id'] ?? 0 ) > 0 ) { $promote_item_id = (int) $candidate['id']; break; }
				}
				?>
				<section class="lpgen-review-toolbar">
					<div class="lpgen-review-toolbar__meta">
						<span class="lpgen-review-toolbar__label"><?php echo esc_html__( 'Status campaign', 'lp-generator' ); ?></span>
						<span class="lpgen-badge lpgen-badge--<?php echo esc_attr( sanitize_key( $status ) ); ?>"><?php echo esc_html( $status ); ?></span>
						<?php if ( '' !== $experiment_label ) : ?>
							<span class="lpgen-badge lpgen-badge--experiment"><?php echo esc_html( $experiment_label ); ?></span>
							<span class="lpgen-review-toolbar__note"><?php echo esc_html__( 'Noindex & di luar sitemap sampai dipromosikan.', 'lp-generator' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="lpgen-review-toolbar__actions">
						<button type="button" class="button" data-lpgen-action="run-now" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php echo esc_html__( 'Proses Antrean', 'lp-generator' ); ?>
						</button>
						<?php if ( '' !== $experiment_label ) : ?>
							<button type="button" class="button button-secondary" data-lpgen-action="promote-experiment" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>" data-item="<?php echo esc_attr( (string) $promote_item_id ); ?>">
								<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
								<?php echo esc_html__( 'Promosikan', 'lp-generator' ); ?>
							</button>
						<?php endif; ?>
						<?php if ( in_array( $status, array( 'review', 'publishing', 'failed' ), true ) ) : ?>
							<button type="button" class="button button-primary" data-lpgen-action="publish-campaign" data-campaign="<?php echo esc_attr( (string) $campaign_id ); ?>">
								<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<?php echo esc_html( 'failed' === $status ? __( 'Coba Publish Ulang', 'lp-generator' ) : __( 'Publish & Index', 'lp-generator' ) ); ?>
							</button>
						<?php endif; ?>
					</div>
				</section>
				<p class="description lpgen-review-hint">
					<?php echo esc_html__( 'Topik (brief) adalah hasil tahap 1: judul, angle, outline, pain points, dan funnel angle. Artikel lengkap dibuat oleh job terpisah. Internal link baru muncul setelah BOFU dan artikel dipublish.', 'lp-generator' ); ?>
				</p>
				<section class="lpgen-review-list">
					<div class="lpgen-review-list__head">
						<h2><?php echo esc_html__( 'Item campaign', 'lp-generator' ); ?></h2>
						<span class="description"><?php echo esc_html__( 'Setiap baris bisa diperiksa, disetujui, atau dibuat ulang.', 'lp-generator' ); ?></span>
					</div>
					<div class="lpgen-review-table-wrap">
				<table class="widefat striped lpgen-table lpgen-review-table">
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
							$image_specs = json_decode( (string) get_post_meta( $item_id, '_lpgen_image_specs', true ), true );
							$has_placeholder = false;
							foreach ( is_array( $image_specs ) ? $image_specs : array() as $image_spec ) {
								if ( in_array( (string) ( $image_spec['status'] ?? '' ), array( 'placeholder', 'failed' ), true ) ) { $has_placeholder = true; break; }
							}
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Stage', 'lp-generator' ); ?>"><span class="lpgen-stage-chip"><?php echo esc_html( strtoupper( (string) $item['stage'] ) ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Jenis', 'lp-generator' ); ?>">
									<?php
									$kind_labels = array(
										'lp'      => __( 'Landing Page', 'lp-generator' ),
										'topic'   => __( 'Topik (brief)', 'lp-generator' ),
										'article' => __( 'Artikel', 'lp-generator' ),
										'tag'     => __( 'Artikel tag', 'lp-generator' ),
									);
									echo esc_html( $kind_labels[ (string) $item['kind'] ] ?? (string) $item['kind'] );
									?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Tag', 'lp-generator' ); ?>"><?php echo esc_html( implode( ', ', ContentRepository::item_tags( (int) $item['id'] ) ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Judul', 'lp-generator' ); ?>" class="lpgen-review-title-cell">
									<a class="lpgen-review-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a>
									<details><summary><?php echo esc_html__( 'Isi, SEO, anchor & entitas', 'lp-generator' ); ?></summary>
									<p><strong><?php echo esc_html__( 'SEO title:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) $item['seo_title'] ); ?><br />
									<strong><?php echo esc_html__( 'Deskripsi:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) $item['meta_description'] ); ?><br />
									<strong><?php echo esc_html__( 'Anchor ke BOFU:', 'lp-generator' ); ?></strong> <?php echo esc_html( (string) $item['anchor'] ); ?></p>
									<?php $item_entities = json_decode( (string) ( $item['entities'] ?? '' ), true ); ?>
									<p><strong><?php echo esc_html__( 'Entitas konten:', 'lp-generator' ); ?></strong> <?php echo esc_html( implode( ', ', array_column( is_array( $item_entities ) ? $item_entities : array(), 'term' ) ) ); ?></p>
									<div class="lpgen-review-content"><?php echo wp_kses_post( (string) $item['content'] ); ?></div>
									<?php if ( 'lp' === $item['kind'] ) : ?>
									<p><strong><?php echo esc_html__( 'Preview HTML dari data campaign tersimpan:', 'lp-generator' ); ?></strong></p>
									<iframe id="lpgen-preview-<?php echo esc_attr( (string) $item_id ); ?>" title="<?php echo esc_attr__( 'Preview landing page', 'lp-generator' ); ?>" sandbox="" loading="lazy" class="lpgen-review-preview" srcdoc="<?php echo esc_attr( LandingPage::preview_document( 'published' === $item_status && ! empty( $item['post_id'] ) ? (string) get_post_field( 'post_content', (int) $item['post_id'] ) : (string) $item['content'] ) ); ?>"></iframe>
									<p><button type="button" class="button" data-lpgen-preview-download="lpgen-preview-<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Unduh preview HTML', 'lp-generator' ); ?></button></p>
									<p><strong><?php echo esc_html__( 'HTML WordPress (salin dari sini atau edit pada item):', 'lp-generator' ); ?></strong></p>
									<textarea readonly rows="8" class="large-text code" aria-label="<?php echo esc_attr__( 'HTML landing page WordPress', 'lp-generator' ); ?>"><?php echo esc_html( (string) $item['content'] ); ?></textarea>
									<?php endif; ?>
									</details>
									<?php if ( '' !== $permalink ) : ?>
										<br /><a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Lihat publish →', 'lp-generator' ); ?></a>
									<?php endif; ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Model / Token', 'lp-generator' ); ?>">
									<span class="lpgen-review-model"><?php echo esc_html( (string) $item['model'] ); ?></span>
									<?php if ( (int) $item['tokens'] > 0 ) : ?>
										<br /><small><?php echo esc_html( (string) $item['tokens'] . ' tokens' ); ?></small>
									<?php endif; ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Status', 'lp-generator' ); ?>" class="lpgen-review-status-cell">
									<div class="lpgen-review-status-line"><span class="lpgen-badge lpgen-badge--<?php echo esc_attr( sanitize_key( $item_status ) ); ?>"><?php echo esc_html( $item_status ); ?></span></div>
									<div class="lpgen-review-meta-line"><span><?php echo esc_html__( 'Workflow', 'lp-generator' ); ?></span><strong><?php echo esc_html( PublishingWorkflow::state( $item_id ) ); ?></strong></div>
									<?php $quality_report = json_decode( (string) get_post_meta( $item_id, '_lpgen_v2_quality', true ), true ); ?>
									<?php if ( is_array( $quality_report ) ) : ?>
										<div class="lpgen-review-meta-line"><span><?php echo esc_html__( 'Quality', 'lp-generator' ); ?></span><strong><?php echo esc_html( sprintf( '%d · %s', (int) ( $quality_report['score'] ?? 0 ), (string) ( $quality_report['decision'] ?? '' ) ) ); ?></strong></div>
									<?php endif; ?>
									<?php if ( '' !== (string) $item['error'] ) : ?>
										<div class="lpgen-review-error"><?php echo esc_html( (string) $item['error'] ); ?></div>
									<?php endif; ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Aksi', 'lp-generator' ); ?>" class="lpgen-review-actions">
									<div class="lpgen-review-actions__group">
										<button type="button" class="button button-small button-primary" data-lpgen-action="set-status" data-status="approved" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Approve', 'lp-generator' ); ?></button>
										<button type="button" class="button button-small" data-lpgen-action="set-status" data-status="rejected" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Reject', 'lp-generator' ); ?></button>
									</div>
									<div class="lpgen-review-actions__group">
										<button type="button" class="button button-small" data-lpgen-action="regenerate" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Regenerate', 'lp-generator' ); ?></button>
										<?php if ( $has_placeholder ) : ?>
											<button type="button" class="button button-small" data-lpgen-action="regenerate-image" data-item="<?php echo esc_attr( (string) $item_id ); ?>"><?php echo esc_html__( 'Ganti gambar', 'lp-generator' ); ?></button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
					</div>
				</section>
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
			'debug'   => __( 'Debug (detail job)', 'lp-generator' ),
		);
		?>
		<div class="wrap lpgen-wrap lpgen-logs">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php echo esc_html( sprintf( __( 'Catatan progres campaign, generate, publish, gambar, dan indexing. Waktu ditampilkan dalam %s. Detail job per attempt ada di filter Debug dan disembunyikan secara default.', 'lp-generator' ), Utils::timezone_label() ) ); ?></p>
			<div class="lpgen-log-stats">
				<button type="button" class="lpgen-stat" data-level=""><strong><?php echo esc_html( (string) $stats['total'] ); ?></strong><span><?php echo esc_html__( 'Total log', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="info"><strong><?php echo esc_html( (string) $stats['info'] ); ?></strong><span><?php echo esc_html__( 'Info', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="success"><strong><?php echo esc_html( (string) $stats['success'] ); ?></strong><span><?php echo esc_html__( 'Success', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="warn"><strong><?php echo esc_html( (string) $stats['warning'] ); ?></strong><span><?php echo esc_html__( 'Warning', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="error"><strong><?php echo esc_html( (string) $stats['error'] ); ?></strong><span><?php echo esc_html__( 'Error', 'lp-generator' ); ?></span></button>
				<button type="button" class="lpgen-stat" data-level="debug"><strong><?php echo esc_html( (string) ( $stats['debug'] ?? 0 ) ); ?></strong><span><?php echo esc_html__( 'Debug', 'lp-generator' ); ?></span></button>
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
						<th><?php echo esc_html( sprintf( __( 'Waktu (%s)', 'lp-generator' ), Utils::timezone_label() ) ); ?></th>
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
								<td><time datetime="<?php echo esc_attr( (string) $log['created_at'] ); ?>"><?php echo esc_html( Utils::display_time( (string) $log['created_at'] ) ); ?></time></td>
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
