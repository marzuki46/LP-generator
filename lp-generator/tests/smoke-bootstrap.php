<?php
/**
 * Bootstrap smoke test: load the whole plugin with WP stubs and register hooks.
 * Usage: php tests/smoke-bootstrap.php
 *
 * @package LPG
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/fake-wp/' );
}

$GLOBALS['lpg_hooks'] = array();

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), '/\\' ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.test/wp-content/plugins/lp-generator/';
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		$GLOBALS['lpg_hooks']['activation'][] = $callback;
	}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		$GLOBALS['lpg_hooks']['deactivation'][] = $callback;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['lpg_hooks'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['lpg_hooks'][ 'filter:' . $hook ][] = $callback;
		return true;
	}
}

function plugin_basename( $file ) { return 'lp-generator/' . basename( $file ); }

require_once dirname( __DIR__ ) . '/lp-generator.php';

\LPG\init();

$expected = array(
	'init',
	'lpgen_run_job',
	'lpgen_daily_cleanup',
	'admin_menu',
	'admin_enqueue_scripts',
	'admin_init',
	'add_meta_boxes',
	'save_post_lpgen_campaign',
	'admin_notices',
	'wp_ajax_lpgen_start_campaign',
	'wp_ajax_lpgen_publish_campaign',
	'wp_ajax_lpgen_run_now',
	'wp_ajax_lpgen_set_status',
	'wp_ajax_lpgen_regenerate',
	'wp_ajax_lpgen_generate_tag_content',
	'wp_ajax_lpgen_generate_entities',
	'wp_ajax_lpgen_test_connection',
	'wp_ajax_lpgen_test_amazon',
	'wp_ajax_lpgen_generate_key',
	'wp_ajax_lpgen_create_key_file',
);

$missing = array();
foreach ( $expected as $hook ) {
	if ( empty( $GLOBALS['lpg_hooks'][ $hook ] ) ) {
		$missing[] = $hook;
	}
}

if ( ! empty( $missing ) ) {
	echo "FAIL missing hooks: " . implode( ', ', $missing ) . "\n";
	exit( 1 );
}

echo 'PASS plugin bootstrapped; ' . count( $GLOBALS['lpg_hooks'] ) . " hook groups registered\n";
echo 'PASS activation + deactivation callbacks registered: '
	. ( count( $GLOBALS['lpg_hooks']['activation'] ) + count( $GLOBALS['lpg_hooks']['deactivation'] ) ) . "\n";
exit( 0 );
