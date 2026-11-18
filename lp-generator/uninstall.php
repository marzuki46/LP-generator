<?php
/**
 * Uninstall cleanup. Published posts are preserved by default.
 *
 * @package LPG
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$lpgen_settings = get_option( 'lpgen_settings', array() );
$lpgen_preserve = isset( $lpgen_settings['preserve_data_on_uninstall'] ) ? (int) $lpgen_settings['preserve_data_on_uninstall'] : 1;

if ( 1 === $lpgen_preserve ) {
	// Only remove options/tables/caps; keep campaign and item posts.
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . 'lpgen_jobs' ) );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . 'lpgen_logs' ) );
	delete_option( 'lpgen_settings' );
	delete_option( 'lpgen_db_version' );
	delete_option( 'lpgen_job_lock' );
	return;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . 'lpgen_jobs' ) );
$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . 'lpgen_logs' ) );
delete_option( 'lpgen_settings' );
delete_option( 'lpgen_db_version' );
delete_option( 'lpgen_job_lock' );

$lpgen_posts = get_posts(
	array(
		'post_type'      => array( 'lpgen_campaign', 'lpgen_item' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $lpgen_posts as $lpgen_post_id ) {
	wp_delete_post( (int) $lpgen_post_id, true );
}

$lpgen_roles = array( 'administrator' );
if ( isset( $lpgen_settings['allowed_role'] ) && '' !== $lpgen_settings['allowed_role'] ) {
	$lpgen_roles[] = (string) $lpgen_settings['allowed_role'];
}
foreach ( array_unique( $lpgen_roles ) as $lpgen_role_name ) {
	$lpgen_role = get_role( $lpgen_role_name );
	if ( $lpgen_role ) {
		$lpgen_role->remove_cap( 'lpgen_manage' );
	}
}
