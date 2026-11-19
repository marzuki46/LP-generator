<?php
/**
 * Prompt builder tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\CognitiveEngine;
use LPG\Prompts;
use LPG\VisualIdentity;

$context = array(
	'offer_type'       => 'affiliate',
	'offer_name'       => 'Hosting X',
	'audience'         => 'pemula',
	'language'         => 'id',
	'framework'        => 'PAS',
	'framework_steps'  => array( 'Problem', 'Agitate', 'Solution' ),
	'stage'            => 'article',
	'item_index'       => 0,
	'target_stage'     => 'bofu',
	'existing_titles'  => array( 'Judul lama' ),
	'target_areas'     => array( 'Solo', 'Sukoharjo' ),
);

$lp = Prompts::build_lp( $context );
lpg_assert_same(
	array( 'title', 'intro', 'seo_title', 'meta_description', 'sections', 'cta_text', 'cta_url', 'faq', 'tags', 'anchor_suggestion' ),
	$lp['required'],
	'LP required keys'
);
lpg_assert_true( false !== strpos( $lp['user'], 'PAS' ), 'LP prompt memuat framework' );
lpg_assert_true( false !== strpos( $lp['user'], '<CAMPAIGN_JSON>' ), 'LP prompt memuat blok data' );
lpg_assert_true( false !== strpos( $lp['user'], 'Hosting X' ), 'LP prompt memuat nama offer' );
lpg_assert_true( false !== strpos( $lp['system'], 'ONLY a single valid JSON object' ), 'instruksi JSON murni' );

$topic = Prompts::build_topic( array_merge( $context, array( 'stage' => 'mofu' ) ) );
lpg_assert_true( in_array( 'funnel_link_angle', $topic['required'], true ), 'topic punya funnel_link_angle' );
lpg_assert_true( false !== strpos( $topic['user'], 'MOFU' ), 'topic prompt menyebut stage' );
lpg_assert_true( false !== strpos( $topic['user'], 'Solo' ), 'topic prompt memakai target area' );

$article = Prompts::build_article( array_merge( $context, array( 'topic_title' => 'Cara Memilih Hosting' ) ) );
lpg_assert_true( in_array( 'anchor_suggestion', $article['required'], true ), 'artikel punya anchor_suggestion' );
lpg_assert_true( false !== strpos( $article['user'], 'Cara Memilih Hosting' ), 'artikel prompt memuat topik' );

$repaired = Prompts::repair( $lp, '{broken' );
lpg_assert_true( false !== strpos( $repaired['user'], 'not valid JSON' ), 'repair prompt' );

$encoded = Prompts::format_context( array( 'offer_name' => 'A "quoted" name' ) );
lpg_assert_true( false !== strpos( $encoded, '\\"quoted\\"' ), 'context di-encode aman' );

$suggest = Prompts::build_campaign_suggestion( 'Jasa Website Solo', 'id' );
lpg_assert_true( in_array( 'audience', $suggest['required'], true ), 'suggestion required audience' );
lpg_assert_true( in_array( 'problem', $suggest['required'], true ), 'suggestion required problem' );
lpg_assert_true( in_array( 'promise', $suggest['required'], true ), 'suggestion required promise' );
lpg_assert_true( false !== strpos( $suggest['user'], 'Jasa Website Solo' ), 'suggestion memuat keyword user' );
lpg_assert_true( false !== strpos( $suggest['user'], 'Bahasa Indonesia' ), 'suggestion meminta Bahasa Indonesia' );

$heavy                    = $context;
$heavy['framework_plan']  = array( 'design_id' => 'pas-1', 'steps' => array( array( 'name' => 'Problem', 'heading_goal' => str_repeat( 'tujuan panjang ', 40 ) ) ) );
$heavy['entities']        = array( array( 'term' => 'entitas', 'notes' => str_repeat( 'catatan panjang ', 40 ) ) );
$heavy['existing_titles'] = array();
for ( $i = 0; $i < 150; $i++ ) { $heavy['existing_titles'][] = 'Judul lama ' . $i; }
$full = Prompts::format_context( $heavy );
$lean = Prompts::format_lean_context( $heavy );
lpg_assert_true( strlen( $lean ) < strlen( $full ) / 2, 'prompt pendukung jauh lebih ringkas' );
lpg_assert_true( ! str_contains( $lean, 'framework_plan' ), 'prompt pendukung tidak mengirim rencana penuh' );
lpg_assert_true( ! str_contains( $lean, 'Judul lama 149' ), 'prompt pendukung memotong daftar judul lama' );
lpg_assert_true( str_contains( $lean, 'Judul lama 0' ), 'prompt pendukung tetap memberi contoh judul lama' );

lpg_assert_same( 2026, CognitiveEngine::active_year(), 'tahun aktif terkunci 2026' );
lpg_assert_true( str_contains( CognitiveEngine::time_line(), 'current year is 2026' ), 'anchor waktu menyebut tahun berjalan' );

$topic_plan_prompt = Prompts::build_topic( array_merge( $context, array( 'campaign_id' => 77, 'stage' => 'tofu' ) ) );
lpg_assert_true( in_array( 'pain_points', $topic_plan_prompt['required'], true ), 'tahap 1 merencanakan pain points' );
lpg_assert_true( in_array( 'target_table', $topic_plan_prompt['required'], true ), 'tahap 1 merencanakan tabel' );
lpg_assert_true( str_contains( $topic_plan_prompt['user'], 'current year is 2026' ), 'prompt topik memuat anchor waktu' );

$plan_context = array(
	'topic_pain_points' => array( 'Biaya tidak jelas', 'Proses lama' ),
	'topic_target_table' => array( 'columns' => array( 'Opsi', 'Biaya' ), 'rows' => array( array( 'A', 'B' ) ) ),
);
$plan_line = CognitiveEngine::plan_line( $plan_context );
lpg_assert_true( str_contains( $plan_line, 'Biaya tidak jelas' ) && str_contains( $plan_line, 'Opsi | Biaya' ), 'rencana dua tahap diteruskan ke penulis' );

$saved_posts = $GLOBALS['funnel_posts'] ?? array();
$saved_meta  = $GLOBALS['funnel_meta'] ?? array();
$GLOBALS['funnel_posts'][9001] = (object) array( 'ID' => 9001, 'post_title' => 'Brief artikel', 'post_content' => '', 'post_excerpt' => '', 'post_type' => 'lpgen_item', 'post_status' => 'generated' );
update_post_meta( 9001, '_lpgen_kind', 'article' );
update_post_meta( 9001, '_lpgen_campaign_id', 77 );
update_post_meta( 9001, '_lpgen_anchor', 'hosting murah' );
update_post_meta( 9001, '_lpgen_entities', wp_json_encode( array( array( 'term' => 'hosting UMKM' ) ) ) );
funnel_fixture( 9002, 'Artikel lama tentang hosting', 'isi lama', 'post' );
update_post_meta( 9002, '_lpgen_item_id', 9001 );
update_post_meta( 9002, '_lpgen_campaign_id', 77 );
$memory = CognitiveEngine::topic_memory( 77 );
lpg_assert_same( 1, count( $memory ), 'memori topik membaca artikel terbit' );
lpg_assert_same( 'hosting murah', $memory[0]['anchor'], 'memori topik menyimpan anchor lama' );
$article_with_memory = Prompts::build_article( array_merge( $context, $plan_context, array( 'campaign_id' => 77, 'topic_title' => 'Cara memilih hosting' ) ) );
lpg_assert_true( str_contains( $article_with_memory['user'], 'TOPIC MEMORY' ), 'prompt artikel memuat memori topik' );
lpg_assert_true( str_contains( $article_with_memory['user'], 'TOPIC_PLAN' ), 'prompt artikel memuat rencana tahap 1' );
$GLOBALS['funnel_posts'] = $saved_posts;
$GLOBALS['funnel_meta']  = $saved_meta;
