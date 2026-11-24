<?php
/** BOFU path conflict: reuse campaign-owned page, reject foreign-owned page. */
declare(strict_types=1);
use LPG\Publisher;

$GLOBALS['funnel_posts'] = array();
$GLOBALS['funnel_meta']  = array();
unset( $GLOBALS['funnel_paths'] );

$make_page = static function ( int $id, string $slug, int $parent, int $owner ): void {
	funnel_fixture( $id, 'Halaman ' . $slug, '', 'page' );
	$GLOBALS['funnel_posts'][ $id ]->post_name   = $slug;
	$GLOBALS['funnel_posts'][ $id ]->post_parent = $parent;
	update_post_meta( $id, '_lpgen_campaign_id', $owner );
};

$make_page( 600, 'jasa', 0, 600 );
$make_page( 601, 'website', 600, 600 );
$make_page( 602, 'jakarta', 601, 600 );
funnel_fixture( 610, 'LP Jakarta baru', Publisher::paragraph_block( 'Isi LP hasil review yang orisinal untuk pengujian path.' ), 'lpgen_item' );
$path_entities = wp_json_encode( array_map( static fn( $term ) => array( 'term' => $term ), array( 'jasa website Jakarta', 'desain responsif', 'katalog produk', 'Jakarta', 'kecepatan halaman', 'formulir kontak', 'biaya pemeliharaan', 'nama domain', 'konten usaha', 'pengalaman pengguna' ) ) );
foreach ( array( 'campaign_id' => 600, 'kind' => 'lp', 'stage' => 'bofu', 'status' => 'approved', 'structured' => '{}', 'entities' => $path_entities ) as $key => $value ) {
	update_post_meta( 610, '_lpgen_' . $key, $value );
}
$campaign = array(
	'campaign_id'     => 600,
	'bofu_path'       => 'jasa/website/jakarta',
	'lp_post_type'    => 'page',
	'publish_status'  => 'publish',
	'index_enabled'   => 0,
	'offer_name'      => 'Jasa Website Jakarta',
	'city'            => 'Jakarta',
	'lp_target_keyword' => 'jasa website Jakarta',
);
$reused = Publisher::publish_item( 610, $campaign );
lpg_assert_same( 602, (int) $reused, 'path milik campaign yang sama dipakai ulang, bukan error' );
lpg_assert_same( 602, (int) get_post_meta( 610, '_lpgen_post_id', true ), 'item tersambung ke halaman lama' );

$GLOBALS['funnel_posts'] = array();
$GLOBALS['funnel_meta']  = array();
$make_page( 700, 'jasa', 0, 999 );
$make_page( 701, 'website', 700, 999 );
$make_page( 702, 'jakarta', 701, 999 );
funnel_fixture( 710, 'LP Jakarta asing', Publisher::paragraph_block( 'Isi LP hasil review yang orisinal untuk pengujian path asing.' ), 'lpgen_item' );
foreach ( array( 'campaign_id' => 600, 'kind' => 'lp', 'stage' => 'bofu', 'status' => 'approved', 'structured' => '{}', 'entities' => $path_entities ) as $key => $value ) {
	update_post_meta( 710, '_lpgen_' . $key, $value );
}
$foreign_rejected = false;
try {
	Publisher::publish_item( 710, $campaign );
} catch ( RuntimeException $exception ) {
	$foreign_rejected = false !== strpos( $exception->getMessage(), 'campaign lain' );
}
lpg_assert_true( $foreign_rejected, 'path milik campaign lain tetap ditolak' );

$GLOBALS['funnel_posts'] = array();
$GLOBALS['funnel_meta']  = array();
