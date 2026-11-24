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

$meta = SeoMetadataEngine::build(
	array( 'seo_title' => 'Judul', 'meta_description' => 'Deskripsi', 'title' => 'Halaman' ),
	array( 'experiment_mode' => 'CONTENT_PREVIEW', 'language' => 'id' ),
	array( 'url' => 'https://example.test/preview/' )
);
lpg_assert_same( 'noindex,follow', $meta['robots'], 'metadata eksperimen noindex' );
lpg_assert_true( ! $meta['sitemap_eligible'], 'eksperimen tidak eligible sitemap' );
lpg_assert_same( array(), $meta['blockers'], 'metadata eksperimen valid' );
