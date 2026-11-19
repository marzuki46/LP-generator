<?php
/**
 * Prompt builder tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\Prompts;

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
