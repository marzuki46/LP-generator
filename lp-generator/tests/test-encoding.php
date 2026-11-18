<?php
/**
 * Source encoding regression tests.
 *
 * UI strings used to be saved as UTF-8 bytes that had already been decoded as
 * Windows-1252 once. This test keeps those double-encoded byte sequences out
 * of the plugin source.
 *
 * @package LPG
 */

declare(strict_types=1);

$bad_sequences = array(
	"\xC3\xA2\xE2\x80\xA0" => 'panah ganda (arrow)',
	"\xC3\xA2\xE2\x82\xAC" => 'tanda hubung ganda (dash)',
	"\xC3\x82\xC2\xB7"     => 'titik tengah ganda (middot)',
	"\xC3\x82\xC2\xA0"     => 'spasi ganda (nbsp)',
);

$root      = dirname( __DIR__ );
$offenders = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}

	if ( ! in_array( strtolower( $file->getExtension() ), array( 'php', 'js', 'css', 'txt', 'md', 'json', 'pot' ), true ) ) {
		continue;
	}

	$path = $file->getPathname();
	if ( false !== strpos( $path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR ) ) {
		continue;
	}

	$bytes = (string) file_get_contents( $path );
	foreach ( $bad_sequences as $needle => $label ) {
		if ( false !== strpos( $bytes, $needle ) ) {
			$offenders[] = basename( $path ) . ' [' . $label . ']';
		}
	}
}

lpg_assert_true(
	0 === count( $offenders ),
	'tidak ada string UTF-8 yang ter-encode dua kali' . ( $offenders ? ': ' . implode( ', ', $offenders ) : '' )
);

$review_ui = (string) file_get_contents( $root . '/admin/class-review-ui.php' );
lpg_assert_true(
	false !== strpos( $review_ui, "\xE2\x80\x94 pilih campaign \xE2\x80\x94" ),
	'placeholder pilih campaign memakai em dash UTF-8'
);
lpg_assert_true(
	false !== strpos( $review_ui, 'Campaigns ' . "\xE2\x86\x92" . ' Add New.' ),
	'petunjuk campaign memakai arrow UTF-8'
);
lpg_assert_true(
	false !== strpos( $review_ui, 'selesai ' . "\xC2\xB7" ),
	'pemisah progres memakai middot UTF-8'
);

require_once $root . '/admin/class-settings.php';

$tabs_method = new ReflectionMethod( \LPG\Settings::class, 'tabs' );
$tabs_method->setAccessible( true );
$tabs = (array) $tabs_method->invoke( null );

lpg_assert_true( 8 === count( $tabs ), 'settings punya 8 tab' );
foreach ( $tabs as $slug => $definition ) {
	lpg_assert_true(
		! empty( $definition['icon'] ) && ! empty( $definition['description'] ),
		'tab ' . $slug . ' punya ikon dan deskripsi'
	);
}

$settings_src = (string) file_get_contents( $root . '/admin/class-settings.php' );
lpg_assert_true(
	false !== strpos( $settings_src, 'nav-tab-wrapper wp-clearfix lpgen-tabs' ),
	'tab settings memakai komponen nav-tab WordPress'
);
lpg_assert_true(
	false !== strpos( $settings_src, 'aria-current="page"' ),
	'tab aktif punya aria-current'
);

$admin_src = (string) file_get_contents( $root . '/admin/class-admin.php' );
lpg_assert_true(
	false === strpos( $admin_src, 'wp_safe_redirect( admin_url( \'edit.php?post_type=lpgen_campaign\' )' ),
	'Campaigns submenu tidak redirect setelah output admin'
);
