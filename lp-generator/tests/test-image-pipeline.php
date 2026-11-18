<?php
/**
 * Tests for the image pipeline helpers.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\ImagePipeline;

lpg_assert_same( 'auto', ImagePipeline::clean_source( 'bogus' ), 'unknown image source falls back to auto' );
lpg_assert_same( 'bing', ImagePipeline::clean_source( 'BING' ), 'image source is normalized' );
lpg_assert_same( '1024x1024', ImagePipeline::clean_ai_size( '800x600' ), 'unknown AI size falls back to default' );
lpg_assert_same( '1536x1024', ImagePipeline::clean_ai_size( '1536x1024' ), 'supported AI size is kept' );

lpg_assert_same( 97280, ImagePipeline::max_bytes( array( 'image_max_kb' => 95 ) ), 'max image size stays below 100 KB' );
lpg_assert_same( 99 * 1024, ImagePipeline::max_bytes( array( 'image_max_kb' => 200 ) ), 'max image size is hard capped' );
lpg_assert_same( 4, ImagePipeline::image_count( array( 'image_count' => 2 ), array( 'image_count' => 4 ) ), 'campaign image count overrides global' );
lpg_assert_same( 2, ImagePipeline::image_count( array( 'image_count' => 2 ), array( 'image_count' => 0 ) ), 'zero campaign count inherits global' );

lpg_assert_same(
	array( 12, 3 ),
	ImagePipeline::clean_upload_ids( array( '12', '12', 'abc', '3' ) ),
	'upload attachment IDs are sanitized and de-duplicated'
);
lpg_assert_same(
	array( 7, 8 ),
	ImagePipeline::clean_upload_ids( '7, 8, 7' ),
	'upload attachment CSV is supported'
);

$keywords = ImagePipeline::keywords(
	array(
		'entities'     => array( array( 'term' => 'AC Bandung' ) ),
		'tag'          => 'service ac',
		'title'        => 'Panduan AC',
		'offer_name'   => 'Jasa AC',
		'campaign_title' => 'Campaign AC',
		'content_tags' => array( 'service ac', 'Cuci AC' ),
	)
);
lpg_assert_same(
	array( 'AC Bandung', 'service ac', 'Panduan AC', 'Jasa AC', 'Campaign AC', 'Cuci AC' ),
	$keywords,
	'image keywords use entities, tag, title and campaign data without duplicates'
);

lpg_assert_same( array( 'upload', 'ai', 'bing' ), ImagePipeline::source_order( array(), array() ), 'auto engine order is upload, AI, Bing' );
lpg_assert_same( array( 'bing' ), ImagePipeline::source_order( array(), array( 'image_source' => 'bing' ) ), 'explicit engine is respected' );

lpg_assert_true(
	false !== strpos( ImagePipeline::build_bing_query( '  service   ac  ', array( 'language' => 'id' ) ), 'service ac foto ilustrasi berkualitas' ),
	'Bing query normalizes keyword and adds Indonesian suffix'
);
lpg_assert_true(
	false !== strpos( ImagePipeline::build_ai_prompt( 'jasa ac', array( 'language' => 'id', 'offer_name' => 'AC Cepat' ) ), 'No text' ),
	'AI prompt forbids rendered text'
);
lpg_assert_true(
	false !== strpos( ImagePipeline::build_ai_prompt( 'jasa ac', array( 'language' => 'id', 'offer_name' => 'AC Cepat' ) ), 'AC Cepat' ),
	'AI prompt includes campaign offer as data'
);

lpg_assert_true( ImagePipeline::safe_image_url( 'https://images.example.com/pic.jpg' ), 'public https image URL is accepted' );
lpg_assert_true( ! ImagePipeline::safe_image_url( 'ftp://images.example.com/pic.jpg' ), 'non-http scheme is rejected' );
lpg_assert_true( ! ImagePipeline::safe_image_url( 'http://127.0.0.1/pic.jpg' ), 'loopback image URL is rejected' );
lpg_assert_true( ! ImagePipeline::safe_image_url( 'http://10.0.0.5/pic.jpg' ), 'private network image URL is rejected' );

$gallery = ImagePipeline::gallery_block(
	array(
		array(
			'id'      => 12,
			'url'     => 'https://images.example.com/a.webp',
			'alt'     => 'AC "Bandung"',
			'keyword' => 'AC Bandung',
		),
	)
);
lpg_assert_true( false !== strpos( $gallery, '<!-- wp:gallery' ), 'gallery block uses wp:gallery' );
lpg_assert_true( false !== strpos( $gallery, 'wp-image-12' ), 'gallery block references the attachment ID' );
lpg_assert_true( false !== strpos( $gallery, 'AC &quot;Bandung&quot;' ), 'gallery block escapes alt text' );
lpg_assert_same( '', ImagePipeline::gallery_block( array() ), 'empty gallery renders nothing' );
