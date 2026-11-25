<?php
/** V2 final QA: public output, schema safety, experiment SEO. Load before focuskw regression. */
declare(strict_types=1);
require_once __DIR__ . '/fixtures/lp-data.php';

use LPG\LandingPage;
use LPG\SchemaRegistry;
use LPG\SeoMetadataEngine;

foreach ( array( 'service', 'product', 'amazon_affiliate' ) as $lp_type ) {
	foreach ( array( 'id', 'en' ) as $language ) {
		$fixture = lpg_lp_fixture( 'aida' );
		$fixture['campaign']['lp_type'] = $lp_type;
		$fixture['campaign']['language'] = $language;
		$fixture['campaign']['city'] = 'Solo';
		$fixture['campaign']['target_areas'] = array( 'Solo', 'Sukoharjo' );
		$html = LandingPage::render( $fixture['data'], $fixture['campaign'] );
		$label = $lp_type . '/' . $language;
		lpg_assert_true( false === stripos( $html, 'Split Proof' ), $label . ': tidak ada label Split Proof' );
		lpg_assert_true( ! preg_match( '/\b(TOFU|MOFU)\b/i', $html ), $label . ': tidak ada label TOFU/MOFU' );
		lpg_assert_true( false === strpos( $html, '>TAG<' ), $label . ': tidak ada label Tag' );
		$area_label = 'en' === $language ? 'Service area' : 'Area layanan';
		lpg_assert_true( false !== strpos( $html, $area_label ) && false !== strpos( $html, 'Solo' ), $label . ': footer lokasi terlokalisasi' );
		if ( 'service' === $lp_type ) {
			lpg_assert_true( false === strpos( $html, 'lpm-offerbar' ), $label . ': tidak ada price bar di atas' );
			lpg_assert_true( false !== strpos( $html, 'lpg-lpt__price-card' ), $label . ': kartu harga di tahap-action' );
			lpg_assert_true( false !== strpos( $html, 'lpg-lpm__breadcrumb' ) && false !== strpos( $html, 'lpg-lpm__nav-links' ), $label . ': navigasi & breadcrumb hadir' );
			lpg_assert_true( false !== strpos( $html, 'lpg-lpt__section-grid' ), $label . ': section dua kolom' );
			lpg_assert_true( substr_count( $html, 'lpg-lpt__support-card' ) >= 3, $label . ': support content cards hadir' );
			$footer_copy_needle = 'en' === $language ? 'Start with what your business needs.' : 'Mulai dari kebutuhan bisnis Anda.';
			lpg_assert_true( false !== strpos( $html, 'lpg-lpm__footer-about' ) && false !== strpos( $html, $footer_copy_needle ), $label . ': footer punya paragraf copywriting' );
			lpg_assert_true( false !== strpos( $html, '<strong>' . $area_label . '</strong><span>' ), $label . ': area layanan menurun, bukan koma' );
			$nav_labels = 'en' === $language
				? array( 'Home', 'Blog', 'Services', 'Contact us', 'About Us' )
				: array( 'Beranda', 'Blog', 'Jasa', 'Hubungi kami', 'Tentang Kami' );
			foreach ( $nav_labels as $nav_label ) {
				lpg_assert_true( false !== strpos( $html, '>' . $nav_label . '</a>' ), $label . ': menu ' . $nav_label );
			}
			lpg_assert_true( false !== strpos( $html, '?s=' ) && false !== strpos( $html, 'Solo' ), $label . ': blog mengikuti keyword + kota' );
			lpg_assert_true( false !== strpos( $html, '/jasa/website/solo/' ), $label . ': menu Jasa mengikuti kota' );
		}
		if ( 'en' === $language && 'service' === $lp_type ) {
			$indonesian_chrome = array( 'MULAI DARI', 'PAKET & INVESTASI', 'Ringkasan penawaran', 'Panduan terkait', 'Pelajari sebelum menentukan pilihan', 'Baca panduan', 'Rekomendasi singkat', 'Pertanyaan yang sering diajukan', 'Pesan sekarang', 'Hubungi kami', 'Tersedia', 'Sebagai Amazon Associate', 'Halaman ini memuat tautan afiliasi' );
			foreach ( $indonesian_chrome as $needle ) {
				lpg_assert_true( false === strpos( $html, $needle ), $label . ': tidak ada string UI Indonesia "' . $needle . '"' );
			}
			lpg_assert_true( false !== strpos( $html, 'Pricing &amp; investment' ) || false !== strpos( $html, 'Pricing & investment' ), $label . ': kartu harga EN' );
		}
		if ( 'en' === $language ) {
			lpg_assert_same( 'Related guides', LPG\LanguageService::strings( 'en' )['related'], $label . ': label related EN tersedia' );
		}
	}
}

$graph = SchemaRegistry::graph(
	array( 'lp_type' => 'amazon_affiliate', 'language' => 'en', 'amazon_source_reference' => 'https://example.test/source' ),
	array( 'title' => 'Review', 'meta_description' => 'Analisis' ),
	array( 'url' => 'https://example.test/review/' )
);
$ids = array_column( $graph, '@id' );
lpg_assert_same( count( $ids ), count( array_unique( $ids ) ), 'schema @id tidak duplikat' );
lpg_assert_true( in_array( 'Article', array_column( $graph, '@type' ), true ), 'schema affiliate memakai Article' );
lpg_assert_true( false === strpos( (string) wp_json_encode( $graph ), 'aggregateRating' ), 'schema affiliate tanpa aggregateRating' );

$tier_fixture = lpg_lp_fixture( 'aida' );
$tier_fixture['campaign']['lp_type'] = 'service';
$tier_fixture['campaign']['price'] = '';
$tier_fixture['campaign']['price_tiers'] = array(
 array( 'name' => 'Starter', 'price' => 'Rp 500.000', 'description' => 'Untuk kebutuhan dasar.', 'features' => array( 'Halaman profil', 'Responsif' ), 'badge' => '' ),
 array( 'name' => 'Bisnis', 'price' => 'Rp 800.000', 'description' => 'Untuk usaha yang ingin lebih banyak manfaat.', 'features' => array( 'Katalog', 'SEO lokal' ), 'badge' => 'Populer' ),
 array( 'name' => 'Premium', 'price' => 'Rp 1.500.000', 'description' => 'Untuk kebutuhan lanjutan.', 'features' => array( 'Booking', 'Multi-bahasa' ), 'badge' => '' ),
);
$tier_html = LandingPage::render( $tier_fixture['data'], $tier_fixture['campaign'] );
lpg_assert_same( 3, substr_count( $tier_html, 'lpg-lpt__price-tier' ) - substr_count( $tier_html, 'lpg-lpt__price-tier--featured' ), 'tiga kartu harga tampil' );
lpg_assert_true( false !== strpos( $tier_html, '>Starter<' ) && false !== strpos( $tier_html, 'Rp 1.500.000' ), 'nama dan harga tiap tier tampil' );
lpg_assert_true( false !== strpos( $tier_html, '>Populer<' ) && false !== strpos( $tier_html, 'lpg-lpt__price-tier--featured' ), 'tier unggulan ditandai' );
lpg_assert_same( 3, count( LPG\ContentRepository::price_tiers( wp_json_encode( $tier_fixture['campaign']['price_tiers'] ) ) ), 'price_tiers dinormalisasi' );

$service_schema = SchemaRegistry::graph(
 array( 'lp_type' => 'service', 'offer_name' => 'Jasa Website', 'city' => 'Solo', 'target_areas' => array( 'Solo', 'Sukoharjo' ), 'benefits' => array( 'Cepat', 'Responsif' ) ),
 array( 'title' => 'LP', 'intro' => 'Intro', 'faq' => array( array( 'q' => 'Berapa lama?', 'a' => 'Tergantung ruang lingkup.' ) ) ),
 array( 'url' => 'https://example.test/jasa/' )
);
$service_types = array_column( $service_schema, '@type' );
lpg_assert_true( in_array( 'FAQPage', $service_types, true ), 'schema FAQPage di-inject' );
lpg_assert_true( false !== strpos( (string) wp_json_encode( $service_schema ), '#offer-catalog' ), 'schema offer catalog punya @id unik' );
lpg_assert_same( count( $service_schema ), count( array_unique( array_column( $service_schema, '@id' ) ) ), 'schema service @id unik' );

$meta = SeoMetadataEngine::build(
	array( 'seo_title' => 'Judul', 'meta_description' => 'Deskripsi', 'title' => 'Halaman' ),
	array( 'experiment_mode' => 'CONTENT_PREVIEW', 'language' => 'id' ),
	array( 'url' => 'https://example.test/preview/' )
);
lpg_assert_same( 'noindex,follow', $meta['robots'], 'metadata eksperimen noindex' );
lpg_assert_true( ! $meta['sitemap_eligible'], 'eksperimen tidak eligible sitemap' );
lpg_assert_same( array(), $meta['blockers'], 'metadata eksperimen valid' );
