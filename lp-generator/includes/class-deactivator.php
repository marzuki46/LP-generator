<?php
/**
 * Deactivation routines.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
final class Deactivator {

	/**
	 * Run on deactivation. Data is intentionally preserved.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'lpgen_run_job' );
		wp_clear_scheduled_hook( 'lpgen_daily_cleanup' );
		delete_option( 'lpgen_job_lock' );

		$settings  = get_option( 'lpgen_settings', array() );
		$role_name = (string) ( $settings['allowed_role'] ?? 'administrator' );
		$roles     = array_unique( array( 'administrator', $role_name ) );
		foreach ( $roles as $name ) {
			$role = get_role( $name );
			if ( $role ) {
				$role->remove_cap( 'lpgen_manage' );
			}
		}
	}
}
