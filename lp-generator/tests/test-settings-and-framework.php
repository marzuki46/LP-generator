<?php
/**
 * AI framework, hub lock, and image placement tests.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

lpg_assert_true( \LPG\Indexer::PUSH_HUB === 'https://pubsubhubbub.appspot.com/publish', 'WebSub hub terkunci konstanta' );

$raw = array(
	'label' => 'Rantai Bukti',
	'steps' => array(
		array(
			'name'          => 'Jangkar',
			'section_hint'  => 'hero',
			'image_role'    => 'hero',
			'image_keyword' => 'produk unggulan',
		),
		array(
			'name'          => 'Bukti',
			'section_hint'  => 'bukti',
			'image_role'    => 'bogus',
			'image_keyword' => 'testimoni',
		),
	),
);
$clean = \LPG\Frameworks::sanitize_generated( $raw );
lpg_assert_same( 'Rantai Bukti', $clean['label'], 'label framework dibersihkan' );
lpg_assert_same( 'hero', $clean['steps'][0]['section_hint'], 'hint valid dipertahankan' );
lpg_assert_same( 'problem', $clean['steps'][1]['section_hint'], 'hint invalid diganti fallback' );
lpg_assert_same( 'problem', $clean['steps'][1]['image_role'], 'role invalid mengikuti hint' );

$names = \LPG\Frameworks::clean_step_names( array( 'A', 'A', '', 'B' ) );
lpg_assert_same( array( 'A', 'B' ), $names, 'nama langkah unik dan kosong dibuang' );

$line = \LPG\Prompts::framework_line(
	array(
		'framework'             => 'Rantai Bukti',
		'framework_steps'       => array( 'Jangkar', 'Bukti' ),
		'framework_hints'       => array( 'hero', 'benefits' ),
		'framework_image_roles' => array( 'hero', 'benefit' ),
	)
);
lpg_assert_true( false !== strpos( $line, 'hero' ), 'prompt framework memuat section hint' );
lpg_assert_true( false !== strpos( $line, 'image roles' ), 'prompt framework memuat instruksi gambar' );

$structured = array(
	'sections' => array(
		array(
			'heading' => 'Pengantar',
			'hint'    => 'hero',
			'body'    => 'Isi hero.',
		),
		array(
			'heading' => 'Keunggulan',
			'hint'    => 'benefits',
			'body'    => 'Isi benefit.',
		),
	),
);
$images = array(
	array( 'id' => 11, 'url' => 'https://example.com/a.webp', 'section_hint' => 'benefits' ),
);
$blocks = \LPG\Publisher::build_blocks( $structured, 'lp', array(), $images );
$benefit_pos = strpos( $blocks, 'Keunggulan' );
$gallery_pos = strpos( $blocks, 'lpg-lp__gallery' );
lpg_assert_true( false !== $gallery_pos && false !== $benefit_pos && $gallery_pos > $benefit_pos, 'gallery ditempatkan setelah section yang sesuai hint' );