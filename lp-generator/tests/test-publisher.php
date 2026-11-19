<?php
/**
 * Publisher block builder tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\Publisher;

$cta = Publisher::build_cta_block( 'https://example.test/checkout', 'Beli Sekarang', false );
lpg_assert_true( false !== strpos( $cta, 'https://example.test/checkout' ), 'CTA URL hadir' );
lpg_assert_true( false !== strpos( $cta, 'Beli Sekarang' ), 'CTA text hadir' );
lpg_assert_true( false === strpos( $cta, 'sponsored' ), 'CTA produk tanpa sponsored' );

$affiliate = Publisher::build_cta_block( 'https://example.test/ref', 'Cek Harga', true );
lpg_assert_true( false !== strpos( $affiliate, 'rel="sponsored nofollow"' ), 'CTA afiliasi punya rel' );
lpg_assert_true( false !== strpos( $affiliate, 'target="_blank"' ), 'CTA afiliasi buka tab baru' );

$linked = Publisher::linkify_cta( 'Silakan lihat panduan lengkapnya sekarang.', 'https://example.test/mofu', 'panduan lengkapnya' );
lpg_assert_true( false !== strpos( $linked, '<a href="https://example.test/mofu">panduan lengkapnya</a>' ), 'anchor di-linkifikasi' );

$fallback = Publisher::linkify_cta( 'Lanjutkan membaca.', 'https://example.test/bofu', 'rekomendasi kami' );
lpg_assert_true( false !== strpos( $fallback, '<a href="https://example.test/bofu">rekomendasi kami</a>' ), 'anchor ditambahkan bila tidak ada' );

$blocks = Publisher::build_blocks(
	array(
		'title'    => 'Review X',
		'intro'    => 'Pendahuluan.',
		'sections' => array(
			array(
				'heading' => 'Kelebihan',
				'body'    => 'Isi kelebihan.',
				'bullets' => array( 'Cepat', 'Murah' ),
			),
		),
		'faq'      => array( array( 'q' => 'Apakah aman?', 'a' => 'Ya.' ) ),
		'cta_text' => 'Cek Harga',
		'cta_url'  => 'https://example.test/ref',
	),
	'article',
	array( 'offer_type' => 'affiliate', 'cta' => 'Cek Harga', 'offer_url' => 'https://example.test/ref' )
);
lpg_assert_true( false !== strpos( $blocks, 'Disclosure:' ), 'disclosure affiliate hadir' );
lpg_assert_true( false !== strpos( $blocks, 'wp:heading' ), 'blok heading hadir' );
lpg_assert_true( false !== strpos( $blocks, 'wp:list' ), 'blok list hadir' );
lpg_assert_true( false === strpos( $blocks, 'wp:buttons' ), 'artikel pendukung tidak melompati funnel ke CTA offer' );
$lp_blocks = Publisher::build_blocks( array( 'cta_text' => 'Cek harga', 'cta_url' => 'https://example.test/ref' ), 'lp', array( 'offer_type' => 'affiliate' ) );
lpg_assert_true( false !== strpos( $lp_blocks, 'rel="sponsored nofollow noopener"' ), 'CTA affiliate tetap tersedia pada LP' );

$product_blocks = Publisher::build_blocks(
	array( 'title' => 'Produk A', 'sections' => array() ),
	'lp',
	array( 'offer_type' => 'product' )
);
lpg_assert_true( false === strpos( $product_blocks, 'Disclosure:' ), 'produk tanpa disclosure' );

$image_record = array( array( 'id' => 7, 'url' => 'https://example.test/hero.webp', 'alt' => 'Ilustrasi', 'section_hint' => '' ) );
$article_media = Publisher::build_blocks(
	array( 'title' => 'Artikel', 'intro' => 'Pendahuluan.', 'sections' => array( array( 'heading' => 'Bagian', 'body' => 'Isi.' ) ), 'cta_paragraph' => 'Baca panduan kami sekarang.', 'anchor_suggestion' => 'panduan kami' ),
	'article',
	array( 'offer_type' => 'service' ),
	$image_record
);
lpg_assert_true( false === strpos( $article_media, 'wp:gallery' ), 'gambar artikel tidak disisipkan di dalam konten' );
lpg_assert_true( false === strpos( $article_media, 'hero.webp' ), 'URL gambar artikel tidak muncul di body' );
