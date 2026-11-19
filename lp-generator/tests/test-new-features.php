<?php
/**
 * Tests for Amazon discovery, keyword entities, templates, tags and scheduling.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\Amazon;
use LPG\Prompts;
use LPG\Utils;

$template = Utils::render_template(
	'{{keyword}} di {{city}} — {{promise}} Hubungi {{phone}} | {{unknown}}',
	array(
		'keyword' => 'Jasa AC',
		'city'    => 'Bandung',
		'promise' => 'Dingin dalam 30 menit',
		'phone'   => '0812-3456-7890',
	)
);
lpg_assert_same(
	'Jasa AC di Bandung — Dingin dalam 30 menit Hubungi 0812-3456-7890',
	$template,
	'render_template mengisi placeholder dan membuang yang unknown'
);
lpg_assert_same(
	'Jasa AC di Bandung dan d…',
	Utils::render_template( '{{keyword}} di {{city}} dan detail panjang', array( 'keyword' => 'Jasa AC', 'city' => 'Bandung' ), 25 ),
	'render_template memotong sesuai batas'
);

$tags = Utils::sanitize_tags( array( 'AC Bandung', 'ac bandung', 'Service AC', 'Cuci AC', 'Galon' ), 3 );
lpg_assert_same( array( 'AC Bandung', 'Service AC', 'Cuci AC' ), $tags, 'sanitize_tags maksimal 3 dan case-insensitive unik' );
lpg_assert_same( array( 'seo', 'konten' ), Utils::sanitize_tags( 'seo, konten, seo', 3 ), 'sanitize_tags menerima CSV' );

$immediate = Utils::schedule_timestamp( array( 'schedule_mode' => 'immediate' ), 3 );
lpg_assert_true( $immediate >= time() && $immediate <= time() + 10, 'schedule immediate dekat dengan sekarang' );
$interval = Utils::schedule_timestamp(
	array(
		'schedule_mode'     => 'interval',
		'schedule_interval' => 15,
		'schedule_jitter'   => 0,
	),
	2
);
lpg_assert_true( $interval >= time() + 1790 && $interval <= time() + 1810, 'schedule interval menjeda 2 slot x 15 menit' );
$daily = Utils::schedule_timestamp( array( 'schedule_mode' => 'daily', 'schedule_time' => '09:00' ), 0 );
lpg_assert_true( $daily > time(), 'schedule daily berada di masa depan' );
lpg_assert_same( 'immediate', Utils::clean_schedule_mode( 'bogus' ), 'mode jadwal invalid jatuh ke immediate' );
lpg_assert_same( '09:30', Utils::clean_time( '09:30' ), 'clean_time menerima HH:MM valid' );
lpg_assert_same( '09:00', Utils::clean_time( '25:99' ), 'clean_time menolak jam invalid' );

lpg_assert_same( 'co.uk', Amazon::normalize_region( 'CO.UK' ), 'normalize_region case-insensitive' );
lpg_assert_same( 'com', Amazon::normalize_region( 'mars' ), 'normalize_region memakai allowlist' );
lpg_assert_same(
	'wireless headphones site:amazon.com',
	Amazon::build_query( '  wireless   headphones ', 'com' ),
	'build_query menambah filter site:amazon'
);
lpg_assert_true(
	false !== strpos( Amazon::build_search_url( 'a b' ), 'https://www.bing.com/images/async?q=' ),
	'build_search_url memakai endpoint Bing Images'
);
lpg_assert_true(
	false !== strpos( Amazon::affiliate_url( 'wireless headphones', 'com', 'mytag-20' ), 'field-keywords=wireless%20headphones' )
	&& false !== strpos( Amazon::affiliate_url( 'wireless headphones', 'com', 'mytag-20' ), 'tag=mytag-20' ),
	'affiliate_url menyisipkan keyword dan tag'
);
lpg_assert_same(
	'https://i0.wp.com/images.example.test/pic.jpg',
	Amazon::proxy_image( 'http://images.example.test/pic.jpg', true ),
	'proxy_image memakai Photon seperti referensi'
);
lpg_assert_same(
	'https://images.example.test/pic.jpg',
	Amazon::proxy_image( 'https://images.example.test/pic.jpg', false ),
	'proxy_image tidak mengubah bila dinonaktifkan'
);

$fixture = '<div><a class="iusc" m="'
	. htmlspecialchars( wp_json_encode( array( 'murl' => 'https://images.example.test/a.jpg', 'purl' => 'https://www.amazon.com/dp/B0', 't' => 'Produk  A', 'turl' => 'https://images.example.test/thumb.jpg' ) ), ENT_QUOTES, 'UTF-8' )
	. '"></a><a class="iusc" m="'
	. htmlspecialchars( wp_json_encode( array( 'murl' => 'ftp://bad.test/x.jpg', 'purl' => 'https://www.amazon.com/dp/B1', 't' => 'Bad' ) ), ENT_QUOTES, 'UTF-8' )
	. '"></a></div>';
$parsed = Amazon::parse_images( $fixture );
lpg_assert_same( 1, count( $parsed ), 'parse_images hanya menerima URL http(s)' );
lpg_assert_same( 'Produk A', $parsed[0]['title'], 'parse_images membersihkan judul' );
lpg_assert_same( 'www.amazon.com', $parsed[0]['domain'], 'parse_images membaca domain' );

$entity_prompt = Prompts::build_entities( array( 'language' => 'id', 'entity_count' => 6, 'phone' => '0812' ) );
lpg_assert_same( array( 'entities' ), $entity_prompt['required'], 'build_entities required key' );
lpg_assert_true( false !== strpos( $entity_prompt['user'], 'exactly 8 entities' ), 'build_entities memakai entity_count' );
lpg_assert_true( false !== strpos( $entity_prompt['user'], '<CAMPAIGN_JSON>' ), 'build_entities menyertakan campaign context' );

$tag_prompt = Prompts::build_tag_article(
	array(
		'language' => 'id',
		'tag'      => 'service ac',
		'entities' => array( array( 'term' => 'service ac bandung' ) ),
		'tag_max'  => 3,
	)
);
lpg_assert_true( false !== strpos( $tag_prompt['user'], 'service ac' ), 'build_tag_article menyertakan tag' );
lpg_assert_true( false !== strpos( $tag_prompt['user'], 'service ac bandung' ), 'build_tag_article menyertakan entitas' );
lpg_assert_true( in_array( 'tags', $tag_prompt['required'], true ), 'build_tag_article meminta tags' );

$article_prompt = Prompts::build_article( array( 'language' => 'en', 'tag_max' => 2, 'stage' => 'tofu' ) );
lpg_assert_true( false !== strpos( $article_prompt['user'], 'exactly 2 short' ), 'article prompt memakai tag_max' );

$fixed_start = gmdate( 'Y-m-d\TH:i', time() + 2 * DAY_IN_SECONDS );
$fixed_result = Utils::schedule_timestamp( array( 'schedule_mode' => 'fixed', 'schedule_start' => $fixed_start, 'schedule_time' => '01:00', 'schedule_interval' => 15 ), 2 );
lpg_assert_same( strtotime( str_replace( 'T', ' ', $fixed_start ) . ':00 UTC' ) + 1800, $fixed_result, 'jadwal fixed memakai tanggal dan jam input tepat sekali beserta interval' );
lpg_assert_true( $fixed_result > time() + DAY_IN_SECONDS, 'tanggal fixed masa depan tidak berubah menjadi publish secepatnya' );
