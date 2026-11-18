<?php
/**
 * GitHub updater manifest tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\Updater;

$valid = Updater::sanitize_manifest(
	array(
		'version'      => '1.3.6',
		'package'      => 'https://raw.githubusercontent.com/marzuki46/LP-generator/main/lp-generator-1.3.6.zip',
		'homepage'     => 'https://github.com/marzuki46/LP-generator',
		'requires'     => '6.4',
		'requires_php' => '8.1',
		'tested'       => '6.8',
		'changelog'    => '<ul><li>Perbaikan</li></ul>',
	)
);
lpg_assert_same( '1.3.6', $valid['version'], 'updater menerima versi valid' );
lpg_assert_true( 0 === strpos( $valid['package'], 'https://' ), 'updater package wajib https' );
lpg_assert_same( '<ul><li>Perbaikan</li></ul>', $valid['changelog'], 'changelog dipertahankan' );

$bad = Updater::sanitize_manifest(
	array(
		'version' => '1.3.6',
		'package' => 'http://example.test/malware.zip',
	)
);
lpg_assert_same( array(), $bad, 'updater menolak package non-https' );

$bad_version = Updater::sanitize_manifest(
	array(
		'version' => 'latest',
		'package' => 'https://example.test/plugin.zip',
	)
);
lpg_assert_same( array(), $bad_version, 'updater menolak versi tidak semver' );

$plugin_src = (string) file_get_contents( dirname( __DIR__ ) . '/lp-generator.php' );
lpg_assert_true( false !== strpos( $plugin_src, 'Update URI:        https://github.com/marzuki46/LP-generator' ), 'header Update URI GitHub tersedia' );
lpg_assert_true(
	false !== strpos( $plugin_src, "define( 'LPG_VERSION', '1.3.6' );" ),
	'versi plugin adalah 1.3.6'
);
$repo_manifest = dirname( __DIR__, 2 ) . '/version.json';
if ( is_file( $repo_manifest ) ) {
	$repo_json = json_decode( (string) file_get_contents( $repo_manifest ), true );
	lpg_assert_same( '1.3.6', (string) ( $repo_json['version'] ?? '' ), 'version.json repo sinkron dengan plugin' );
} else {
	lpg_assert_true( true, 'version.json adalah manifest repo, tidak dikemas di zip (dilewati)' );
}
