<?php
/** Regression: all 12 reviewed designs bind AI copy to stable section/image slots. */
declare(strict_types=1);
require_once __DIR__ . '/fixtures/lp-data.php';

use LPG\DesignContract;
use LPG\Frameworks;
use LPG\LandingPage;
use LPG\Prompts;

foreach ( Frameworks::presets() as $slug => $preset ) {
	$definition = DesignContract::definition( $slug );
	$contract   = DesignContract::contract( $slug );
	lpg_assert_same( $slug . '-1', $definition['design_id'], $slug . ': kode desain stabil' );
	lpg_assert_same( count( $preset['steps'] ), count( $contract ), $slug . ': satu slot per langkah framework' );
	lpg_assert_same( count( $contract ), count( array_unique( array_column( $contract, 'id' ) ) ), $slug . ': section ID unik' );
	lpg_assert_same( count( $contract ), count( array_unique( array_filter( array_column( $contract, 'image_slot' ) ) ) ) + count( array_filter( array_column( $contract, 'image_slot' ) ) ) * 0 + ( count( $contract ) - count( array_filter( array_column( $contract, 'image_slot' ) ) ) ), $slug . ': slot gambar tidak saling menimpa' );

	$fixture   = lpg_lp_fixture( $slug );
	$campaign  = $fixture['campaign'];
	$data      = $fixture['data'];
	$plan      = $campaign['framework_plan'];
	lpg_assert_true( DesignContract::active( $campaign ), $slug . ': kontrak aktif setelah plan di-bind' );
	lpg_assert_same( $definition['design_id'], $plan['design_id'], $slug . ': rencana menyimpan kode desain' );
	foreach ( $contract as $index => $slot ) {
		lpg_assert_same( $slot['id'], $plan['steps'][ $index ]['section_id'], $slug . ': section ID rencana langkah ' . ( $index + 1 ) );
		lpg_assert_same( $slot['image_slot'], $plan['steps'][ $index ]['image_slot'], $slug . ': slot gambar rencana langkah ' . ( $index + 1 ) );
		lpg_assert_same( $slot['hint'], $plan['steps'][ $index ]['section_hint'], $slug . ': hint mengikuti desain langkah ' . ( $index + 1 ) );
		lpg_assert_same( $slot['layout'], $plan['steps'][ $index ]['layout'], $slug . ': layout mengikuti desain langkah ' . ( $index + 1 ) );
		lpg_assert_same( $slot['id'], $data['sections'][ $index ]['section_id'], $slug . ': section ID konten langkah ' . ( $index + 1 ) );
		lpg_assert_same( $slot['image_slot'], $data['sections'][ $index ]['image_slot'], $slug . ': slot gambar konten langkah ' . ( $index + 1 ) );
	}
	LandingPage::validate( $data, $campaign );
	$html = LandingPage::render( $data, $campaign );
	lpg_assert_true( str_contains( $html, 'lpg-lp--' . $slug ), $slug . ': renderer memakai framework pilihan' );
	lpg_assert_true( str_contains( $html, 'data-section-id="' . $contract[0]['id'] . '"' ), $slug . ': alamat section tersedia di HTML' );
}

$prompt = Prompts::build_framework_plan( lpg_lp_fixture( 'pas' )['campaign'] );
lpg_assert_true( str_contains( $prompt['user'], 'pas-1' ) && str_contains( $prompt['user'], 'pas-problem-visual' ), 'prompt perencana menyuntik kontrak PAS' );
$fixture  = lpg_lp_fixture( 'pas' );
$prompt   = Prompts::build_lp( $fixture['campaign'] );
lpg_assert_true( str_contains( $prompt['user'], 'pas-1' ) && str_contains( $prompt['user'], 'pas-solution' ), 'prompt penulis LP menyuntik kontrak PAS' );
lpg_assert_true( ! in_array( 'design_id', $prompt['required'], true ), 'AI tidak perlu mengirim design_id struktural' );
lpg_assert_true( str_contains( $prompt['user'], 'do NOT return those keys' ), 'prompt menjelaskan ID diisi plugin' );

$bound = DesignContract::bind_content( $fixture['data'], $fixture['campaign'] );
lpg_assert_same( 'pas-1', $bound['design_id'], 'bind_content menetapkan kode desain' );
lpg_assert_same( 'pas-solution', $bound['sections'][2]['section_id'], 'bind_content menetapkan section ID' );
lpg_assert_same( 'pas-solution-visual', $bound['sections'][2]['image_slot'], 'bind_content menetapkan slot gambar' );
$wrong            = $fixture['data'];
$wrong['design_id'] = 'aida-1';
$wrong['sections'][0]['section_id'] = 'aida-attention';
$bound = DesignContract::bind_content( $wrong, $fixture['campaign'] );
lpg_assert_same( 'pas-1', $bound['design_id'], 'bind_content menimpa kode desain salah' );
lpg_assert_same( 'pas-problem', $bound['sections'][0]['section_id'], 'bind_content menimpa section ID salah' );
LandingPage::validate( $bound, $fixture['campaign'] );

$bad = $fixture['data'];
$bad['sections'][1]['section_id'] = 'pas-problem';
funnel_throws( static fn() => LandingPage::validate( $bad, $fixture['campaign'] ), 'section ID yang tertukar ditolak' );
$bad = $fixture['data'];
$bad['design_id'] = 'aida-1';
funnel_throws( static fn() => LandingPage::validate( $bad, $fixture['campaign'] ), 'kode desain campaign lain ditolak' );

$image = array(
	'id' => 9,
	'url' => 'https://example.test/solution.webp',
	'alt' => 'Ilustrasi proses layanan',
	'width' => 1200,
	'height' => 800,
	'slot_id' => 'pas-solution-visual',
	'section_id' => 'pas-solution',
	'design_id' => 'pas-1',
	'campaign_id' => 101,
	'campaign_fingerprint' => Frameworks::fingerprint( $fixture['campaign'] ),
);
$html = LandingPage::render( $fixture['data'], $fixture['campaign'], array( $image ) );
lpg_assert_same( 1, substr_count( $html, 'solution.webp' ), 'gambar slot tidak digandakan' );
lpg_assert_true( strpos( $html, 'solution.webp' ) > strpos( $html, 'data-section-id="pas-agitate"' ), 'gambar PAS ditempatkan pada section Solution' );
lpg_assert_true( str_contains( $html, 'data-image-slot="pas-solution-visual"' ), 'renderer menandai slot gambar yang dipakai' );
$bad = $image;
$bad['design_id'] = 'aida-1';
funnel_throws( static fn() => LandingPage::render( $fixture['data'], $fixture['campaign'], array( $bad ) ), 'gambar dari desain lain ditolak' );
$bad = $image;
$bad['slot_id'] = 'aida-hero-visual';
funnel_throws( static fn() => LandingPage::render( $fixture['data'], $fixture['campaign'], array( $bad ) ), 'slot gambar asing ditolak' );
