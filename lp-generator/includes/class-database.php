<?php
/**
 * Database access layer for jobs and logs.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Database
 */
final class Database {

	/**
	 * Table name with prefix.
	 *
	 * @param string $suffix jobs|logs.
	 * @return string
	 */
	public static function table( string $suffix ): string {
		global $wpdb;
		return $wpdb->prefix . 'lpgen_' . $suffix;
	}

	/**
	 * Create or update tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$jobs    = self::table( 'jobs' );
		$logs    = self::table( 'logs' );

		$sql = "CREATE TABLE {$jobs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(32) NOT NULL,
			payload longtext,
			status varchar(16) NOT NULL DEFAULT 'queued',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			run_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_error text,
			PRIMARY KEY  (id),
			KEY campaign_status (campaign_id, status),
			KEY run_at_status (run_at, status)
		) {$charset};

		CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			level varchar(16) NOT NULL,
			message varchar(500) NOT NULL,
			context longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY job_id (job_id)
		) {$charset};";

		$memory = self::table( 'topic_memory' );
		$sql   .= "

		CREATE TABLE {$memory} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			keyword varchar(255) NOT NULL DEFAULT '',
			intent varchar(40) NOT NULL DEFAULT '',
			summary text NOT NULL,
			outline longtext NOT NULL,
			angle text NOT NULL,
			entities longtext NOT NULL,
			url varchar(500) NOT NULL DEFAULT '',
			embedding longtext NOT NULL,
			created_at datetime NOT NULL,
			checked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY item_id (item_id),
			KEY keyword_idx (keyword(191)),
			KEY checked_at (checked_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Run schema upgrades after a plugin update without waiting for reactivation. */
	public static function maybe_upgrade(): void {
		$version = (string) get_option( 'lpgen_db_version', '1' );
		if ( version_compare( $version, (string) LPG_DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'lpgen_db_version', LPG_DB_VERSION );
		}
	}

	/**
	 * Insert a job.
	 *
	 * @param array $args Job data.
	 * @return int Job ID.
	 */
	public static function insert_job( array $args ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$data = array(
			'campaign_id' => absint( $args['campaign_id'] ?? 0 ),
			'item_id'     => absint( $args['item_id'] ?? 0 ),
			'kind'        => sanitize_key( (string) ( $args['kind'] ?? '' ) ),
			'payload'     => isset( $args['payload'] ) ? wp_json_encode( $args['payload'] ) : '{}',
			'status'      => 'queued',
			'attempts'    => 0,
			'run_at'      => isset( $args['run_at'] ) ? (string) $args['run_at'] : $now,
			'created_at'  => $now,
			'updated_at'  => $now,
		);

		$format = array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );
		$inserted = $wpdb->insert( self::table( 'jobs' ), $data, $format );
		if ( false === $inserted || (int) $wpdb->insert_id <= 0 ) { throw new \RuntimeException( 'Antrean tidak dapat disimpan. Periksa tabel database plugin.' ); }
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the next runnable queued job, in strict creation order.
	 *
	 * @param int|null $campaign_id Optional campaign ID.
	 * @return array|null
	 */
	public static function get_next_job( ?int $campaign_id = null ): ?array {
		global $wpdb;
		$table = self::table( 'jobs' );
		if ( null !== $campaign_id && $campaign_id > 0 ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE campaign_id = %d AND status = %s AND run_at <= %s AND (kind = 'index' OR NOT EXISTS (SELECT 1 FROM {$table} earlier WHERE earlier.campaign_id = {$table}.campaign_id AND earlier.id < {$table}.id AND earlier.status IN ('queued','running') AND earlier.kind NOT IN ('index','generate_tag'))) ORDER BY id ASC LIMIT 1",
					$campaign_id,
					'queued',
					current_time( 'mysql', true )
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s AND run_at <= %s AND (kind = 'index' OR NOT EXISTS (SELECT 1 FROM {$table} earlier WHERE earlier.campaign_id = {$table}.campaign_id AND earlier.id < {$table}.id AND earlier.status IN ('queued','running') AND earlier.kind NOT IN ('index','generate_tag'))) ORDER BY id ASC LIMIT 1",
					'queued',
					current_time( 'mysql', true )
				),
				ARRAY_A
			);
		}
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Atomically claim a job.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public static function claim_job( int $job_id ): bool {
		global $wpdb;
		$updated = $wpdb->update(
			self::table( 'jobs' ),
			array(
				'status'     => 'running',
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $job_id,
				'status' => 'queued',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		return (int) $updated > 0;
	}

	/**
	 * Update whitelisted job columns.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $data   Data.
	 */
	public static function update_job( int $job_id, array $data ): void {
		global $wpdb;

		$allowed = array(
			'status'     => '%s',
			'attempts'   => '%d',
			'run_at'     => '%s',
			'last_error' => '%s',
			'updated_at' => '%s',
		);
		$values  = array();
		$formats = array();
		foreach ( $allowed as $column => $format ) {
			if ( array_key_exists( $column, $data ) ) {
				$values[ $column ] = $data[ $column ];
				$formats[]         = $format;
			}
		}
		if ( empty( $values ) ) {
			return;
		}
		$values['updated_at'] = current_time( 'mysql', true );
		$formats[]            = '%s';
		$wpdb->update( self::table( 'jobs' ), $values, array( 'id' => $job_id ), $formats, array( '%d' ) );
	}

	/**
	 * Count jobs.
	 *
	 * @param int         $campaign_id Campaign ID.
	 * @param string|null $status      Optional status.
	 * @return int
	 */
	public static function count_jobs( int $campaign_id, ?string $status = null ): int {
		global $wpdb;
		$table = self::table( 'jobs' );
		if ( null !== $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d AND status = %s", $campaign_id, $status )
			);
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d", $campaign_id ) );
	}

	/**
	 * List jobs for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<int,array>
	 */
	public static function jobs( int $campaign_id ): array {
		global $wpdb;
		$table = self::table( 'jobs' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY id ASC", $campaign_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get the most recent job for an item (used by regenerate).
	 *
	 * @param int $item_id Item ID.
	 * @return array|null
	 */
	public static function last_job_for_item( int $item_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table( 'jobs' ) . ' WHERE item_id = %d ORDER BY id DESC LIMIT 1',
				$item_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** A consistent response shared by cron-independent foreground workers and the dashboard. */
	public static function queue_snapshot( int $campaign_id ): array {
		$total = self::count_jobs( $campaign_id );
		$queued = self::count_jobs( $campaign_id, 'queued' );
		$running = self::count_jobs( $campaign_id, 'running' );
		return array(
			'total'       => $total,
			'done_count'  => self::count_jobs( $campaign_id, 'done' ),
			'remaining'   => $queued,
			'running'     => $running,
			'failed'      => self::count_jobs( $campaign_id, 'failed' ),
			'done'        => $total > 0 && 0 === $queued && 0 === $running,
			'status'      => (string) ContentRepository::get_meta( $campaign_id, 'status', 'draft' ),
			'next_run_at' => self::next_queued_time( $campaign_id ),
			'items'       => self::queue_details( $campaign_id ),
		);
	}

	/** Per-item queue detail for the live dashboard countdown. */
	public static function queue_details( int $campaign_id ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, item_id, kind, status, run_at, attempts, last_error FROM ' . self::table( 'jobs' ) . ' WHERE campaign_id = %d ORDER BY id ASC LIMIT 60',
				$campaign_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$row   = (array) $row;
			$title = (int) ( $row['item_id'] ?? 0 ) > 0 ? (string) get_the_title( (int) $row['item_id'] ) : '';
			$out[] = array(
				'job_id'  => absint( $row['id'] ?? 0 ),
				'item_id' => absint( $row['item_id'] ?? 0 ),
				'kind'    => (string) ( $row['kind'] ?? '' ),
				'status'  => (string) ( $row['status'] ?? '' ),
				'run_at'  => (string) ( $row['run_at'] ?? '' ),
				'attempts'=> absint( $row['attempts'] ?? 0 ),
				'title'   => Utils::truncate( $title, 80 ),
				'error'   => Utils::truncate( (string) ( $row['last_error'] ?? '' ), 120 ),
			);
		}
		return $out;
	}

	/**
	 * Earliest UTC timestamp among queued jobs.
	 *
	 * @return int Timestamp or 0 when the queue is empty.
	 */
	public static function next_queued_time( int $campaign_id = 0 ): int {
		global $wpdb;
		$table = self::table( 'jobs' );
		$sql = "SELECT MIN(run_at) FROM {$table} WHERE status = 'queued'";
		if ( $campaign_id > 0 ) { $sql .= $wpdb->prepare( ' AND campaign_id = %d', $campaign_id ); }
		$value = $wpdb->get_var( $sql );
		if ( empty( $value ) || ! is_string( $value ) ) {
			return 0;
		}
		$timestamp = strtotime( $value . ' UTC' );
		return false === $timestamp ? 0 : $timestamp;
	}

	/**
	 * Delete non-done jobs for a campaign (used on restart/reset).
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function clear_pending_jobs( int $campaign_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::table( 'jobs' ) . " WHERE campaign_id = %d AND status IN ('queued','running')",
				$campaign_id
			)
		);
	}

	/**
	 * Delete all jobs for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function delete_jobs( int $campaign_id ): void {
		global $wpdb;
		$wpdb->delete( self::table( 'jobs' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	}

	/**
	 * Append a log line. Secrets must never be passed in $context.
	 *
	 * @param string $level      Level.
	 * @param string $message    Message.
	 * @param int    $campaign_id Campaign ID.
	 * @param int    $job_id      Job ID.
	 * @param array  $context     Safe context.
	 */
	public static function log( string $level, string $message, int $campaign_id = 0, int $job_id = 0, array $context = array() ): void {
		global $wpdb;
		$wpdb->insert(
			self::table( 'logs' ),
			array(
				'campaign_id' => $campaign_id,
				'job_id'      => $job_id,
				'level'       => substr( sanitize_key( $level ), 0, 16 ),
				'message'     => substr( sanitize_text_field( $message ), 0, 500 ),
				'context'     => empty( $context ) ? null : wp_json_encode( $context ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent logs.
	 *
	 * @param int $campaign_id Campaign ID, 0 = all.
	 * @param int $limit       Limit.
	 * @return array<int,array>
	 */
	public static function logs( int $campaign_id = 0, int $limit = 100 ): array {
		global $wpdb;
		$table = self::table( 'logs' );
		$limit = max( 1, min( 500, $limit ) );
		if ( $campaign_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY id DESC LIMIT %d", $campaign_id, $limit ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
				ARRAY_A
			);
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Aggregate log counts for the dashboard cards.
	 *
	 * @return array<string,int>
	 */
	public static function log_stats(): array {
		global $wpdb;
		$table = self::table( 'logs' );
		$stats = array(
			'total'     => 0,
			'info'      => 0,
			'success'   => 0,
			'warning'   => 0,
			'error'     => 0,
			'campaigns' => 0,
		);
		$rows = $wpdb->get_results( "SELECT level, COUNT(*) AS total FROM {$table} GROUP BY level", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$level = sanitize_key( (string) ( $row['level'] ?? '' ) );
				$count = (int) ( $row['total'] ?? 0 );
				$stats['total'] += $count;
				$key = 'warn' === $level ? 'warning' : $level;
				if ( array_key_exists( $key, $stats ) ) {
					$stats[ $key ] += $count;
				}
			}
		}
		$stats['campaigns'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT campaign_id) FROM {$table} WHERE campaign_id > 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $stats;
	}

	/**
	 * Query logs with filters used by the interactive Logs screen.
	 *
	 * @param int    $campaign_id Campaign filter, 0 = all.
	 * @param string $level       Level filter.
	 * @param string $search      Text search.
	 * @param int    $limit       Row limit.
	 * @return array<int,array>
	 */
	public static function logs_filtered( int $campaign_id = 0, string $level = '', string $search = '', int $limit = 200 ): array {
		global $wpdb;
		$table  = self::table( 'logs' );
		$where  = array( '1=1' );
		$params = array();
		if ( $campaign_id > 0 ) {
			$where[]  = 'campaign_id = %d';
			$params[] = $campaign_id;
		}
		$allowed_levels = array( 'info', 'success', 'warn', 'warning', 'error', 'debug' );
		$level          = sanitize_key( $level );
		if ( in_array( $level, $allowed_levels, true ) ) {
			$where[]  = 'level = %s';
			$params[] = 'warn' === $level ? 'warn' : $level;
		}
		$search = trim( sanitize_text_field( $search ) );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(message LIKE %s OR context LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		$limit    = max( 1, min( 500, $limit ) );
		$params[] = $limit;
		$sql      = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete one log row.
	 *
	 * @param int $log_id Log ID.
	 * @return int Number of deleted rows.
	 */
	public static function delete_log( int $log_id ): int {
		global $wpdb;
		if ( $log_id <= 0 ) {
			return 0;
		}
		return (int) $wpdb->delete( self::table( 'logs' ), array( 'id' => $log_id ), array( '%d' ) );
	}

	/**
	 * Delete log rows using a guarded filter. An empty filter deletes nothing.
	 *
	 * @param array<string,mixed> $args Filter args: all, campaign_id, level, ids, older_than_days.
	 * @return int Number of deleted rows.
	 */
	public static function delete_logs( array $args ): int {
		global $wpdb;
		$table  = self::table( 'logs' );
		$where  = array();
		$params = array();
		if ( ! empty( $args['all'] ) ) {
			$where = array( '1=1' );
		}
		$campaign_id = absint( $args['campaign_id'] ?? 0 );
		if ( $campaign_id > 0 ) {
			$where[]  = 'campaign_id = %d';
			$params[] = $campaign_id;
		}
		$allowed = array( 'info', 'success', 'warn', 'warning', 'error', 'debug' );
		$level   = sanitize_key( (string) ( $args['level'] ?? '' ) );
		if ( in_array( $level, $allowed, true ) ) {
			$where[]  = 'level = %s';
			$params[] = $level;
		}
		$ids = isset( $args['ids'] ) && is_array( $args['ids'] ) ? array_values( array_filter( array_map( 'absint', $args['ids'] ) ) ) : array();
		if ( ! empty( $ids ) ) {
			$where[]  = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params   = array_merge( $params, $ids );
		}
		$days = absint( $args['older_than_days'] ?? 0 );
		if ( $days > 0 ) {
			$where[]  = 'created_at < %s';
			$params[] = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		}
		if ( empty( $where ) ) {
			return 0;
		}
		$sql = 'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->rows_affected;
	}

	/**
	 * Prune old logs.
	 *
	 * @param int $days Days to keep.
	 */
	public static function prune_logs( int $days = 90 ): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . self::table( 'logs' ) . ' WHERE created_at < %s', $cutoff )
		);
	}
}
