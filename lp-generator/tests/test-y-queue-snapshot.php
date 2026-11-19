<?php
/**
 * Regression for the foreground worker's queue snapshot.
 *
 * The runner must keep reporting queued jobs for the processed campaign
 * instead of treating one processed job as "all done".
 *
 * @package LPG
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/includes/class-database.php';

$GLOBALS['wpdb'] = new class {
	public string $prefix = 'wp_';
	public array $calls = array();
	private array $lastArgs = array();

	public function prepare( $sql, ...$args ) {
		$this->lastArgs = $args;
		$this->calls[]  = $sql;
		return $sql;
	}

	public function get_var( $sql ) {
		$sql = (string) $sql;
		if ( str_contains( $sql, 'MIN(run_at)' ) ) {
			return gmdate( 'Y-m-d H:i:s', time() + 60 );
		}
		if ( str_contains( $sql, 'COUNT(*)' ) ) {
			$campaign = str_contains( $sql, 'campaign_id = %d' ) ? (int) ( $this->lastArgs[0] ?? 0 ) : 0;
			$status   = $campaign > 0 ? (string) ( $this->lastArgs[1] ?? '' ) : (string) ( $this->lastArgs[0] ?? '' );
			$counts   = array(
				'42|'       => 6,
				'42|queued' => 3,
				'42|running'=> 1,
				'42|done'   => 2,
				'42|failed' => 0,
			);
			return (int) ( $counts[ $campaign . '|' . $status ] ?? 0 );
		}
		return 0;
	}
};

$snapshot = \LPG\Database::queue_snapshot( 42 );
lpg_assert_same( 6, $snapshot['total'], 'snapshot menghitung seluruh job campaign' );
lpg_assert_same( 3, $snapshot['remaining'], 'snapshot masih melihat antrean queued yang tersisa' );
lpg_assert_same( 1, $snapshot['running'], 'snapshot membedakan job berjalan' );
lpg_assert_same( 2, $snapshot['done_count'], 'snapshot menghitung job selesai' );
lpg_assert_same( false, $snapshot['done'], 'satu job yang diproses tidak dianggap akhir antrean' );
lpg_assert_true( $snapshot['next_run_at'] > time(), 'snapshot menyediakan jadwal antrean berikutnya' );
