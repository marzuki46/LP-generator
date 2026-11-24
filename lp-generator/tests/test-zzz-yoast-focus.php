<?php
/** Last-loaded regression: SEO metadata engine must not overwrite content focus keyword. */
declare(strict_types=1);
if ( ! defined( 'WPSEO_VERSION' ) ) { define( 'WPSEO_VERSION', '28.9' ); }
use LPG\SeoMetadataEngine;

$GLOBALS['funnel_posts'] = array();
$GLOBALS['funnel_meta'] = array();
funnel_fixture( 999, 'Campaign', '', 'lpgen_campaign' );
funnel_fixture( 998, 'Item LP', 'konten', 'lpgen_item' );
update_post_meta( 998, '_lpgen_campaign_id', 999 );
update_post_meta( 998, '_yoast_wpseo_focuskw', 'website katalog' );

$meta = SeoMetadataEngine::build(
 array( 'seo_title' => 'Judul SEO Berbeda', 'meta_description' => 'Deskripsi', 'title' => 'LP' ),
 array( 'language' => 'id' ),
 array( 'post_id' => 998, 'url' => 'https://example.test/lp/' )
);
SeoMetadataEngine::apply_yoast( 998, $meta );

lpg_assert_same( 'website katalog', (string) get_post_meta( 998, '_yoast_wpseo_focuskw', true ), 'focus keyword tidak ditimpa SEO title' );
lpg_assert_same( 'Judul SEO Berbeda', (string) get_post_meta( 998, '_yoast_wpseo_title', true ), 'title Yoast tetap diperbarui engine' );
$GLOBALS['funnel_posts'] = array();
$GLOBALS['funnel_meta'] = array();
