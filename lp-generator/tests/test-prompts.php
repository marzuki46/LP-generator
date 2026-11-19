<?php
/**
 * Prompt builder tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\CognitiveEngine;
use LPG\Prompts;
use LPG\Utils;
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

lpg_assert_same( 'WIB', Utils::timezone_label(), 'label timezone memakai WIB' );
lpg_assert_same( '2026-11-19 16:19:21', Utils::display_time( '2026-11-19 09:19:21' ), 'waktu UTC ditampilkan sebagai WIB' );
lpg_assert_same( 2026, CognitiveEngine::active_year(), 'tahun aktif terkunci 2026' );
lpg_assert_true( str_contains( CognitiveEngine::time_line(), 'TIME ANCHOR (from WordPress settings)' ) && str_contains( CognitiveEngine::time_line(), 'Do not force the year into every article' ), 'anchor waktu berasal dari WP dan tidak dipaksakan' );

$topic_plan_prompt = Prompts::build_topic( array_merge( $context, array( 'campaign_id' => 77, 'stage' => 'tofu' ) ) );
lpg_assert_true( in_array( 'pain_points', $topic_plan_prompt['required'], true ), 'tahap 1 merencanakan pain points' );
lpg_assert_true( in_array( 'target_table', $topic_plan_prompt['required'], true ), 'tahap 1 merencanakan tabel' );
lpg_assert_true( str_contains( $topic_plan_prompt['user'], 'TIME ANCHOR (from WordPress settings)' ), 'prompt topik memuat anchor waktu' );

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

$source_json = wp_json_encode(
	array(
		'sources' => array(
			array(
				'url' => 'https://contoh.test/harga',
				'title' => 'Panduan Harga',
				'claims' => array(
					array( 'type' => 'price', 'text' => 'Kisaran biaya', 'as_of' => '2025-01-01', 'exact_quote' => 'Kisaran biaya Rp 3 juta.' ),
				),
			),
		),
	)
);
$missing_asof = json_decode( $source_json, true );
unset( $missing_asof['sources'][0]['claims'][0]['as_of'] );
$parsed = LPG\ResearchSource::parse( $missing_asof );
lpg_assert_true( count( $parsed['errors'] ) > 0, 'klaim price tanpa as_of ditolak' );
$valid_json = str_replace( '2025-01-01', '2026-10-12', $source_json );
$parsed     = LPG\ResearchSource::parse( $valid_json );
lpg_assert_same( 1, count( $parsed['sources'] ), 'sumber valid diterima' );
lpg_assert_same( LPG\ResearchSource::anchor()['date'], $parsed['sources'][0]['checked_at'], 'checked_at diisi dari WP' );
lpg_assert_true( LPG\ResearchSource::is_stale( array( 'as_of' => '2025-01-01' ) ), 'klaim tahun lampau ditandai basi' );
lpg_assert_true( ! LPG\ResearchSource::is_stale( array( 'as_of' => '2026-10-12' ) ), 'klaim tahun berjalan tidak basi' );
$source_line = LPG\ResearchSource::prompt_line( $parsed['sources'] );
lpg_assert_true( str_contains( $source_line, '[1]' ) && str_contains( $source_line, 'Do not invent' ), 'prompt menyisipkan klaim bersumber' );
lpg_assert_true( LPG\ResearchSource::quote_exists( 'Kisaran biaya Rp 3 juta.', $parsed['sources'] ), 'kutipan persis dikenali' );
lpg_assert_true( ! LPG\ResearchSource::quote_exists( 'Harga pasti Rp 1 juta.', $parsed['sources'] ), 'kutipan rekaan ditolak' );

$valid_outline = array(
	'title' => 'Cara memilih jasa website di Solo',
	'angle' => 'Panduan praktis untuk UMKM yang ingin website pertama.',
	'intent' => 'informational',
	'keywords' => array( 'website solo', 'jasa website', 'UMKM' ),
	'outline' => array( 'Kebutuhan', 'Struktur', 'Biaya', 'Kontak' ),
	'pain_points' => array( 'Biaya tidak jelas', 'Proses lama', 'Takut salah pilih' ),
	'target_table' => null,
	'funnel_link_angle' => 'Lanjutkan ke konsultasi untuk kebutuhan website Anda.',
);
lpg_assert_same( array(), LPG\StructuredOutput::validate( $valid_outline, LPG\StructuredOutput::topic_schema() ), 'outline valid lolos schema' );
$invalid = $valid_outline; unset( $invalid['pain_points'] );
lpg_assert_true( count( LPG\StructuredOutput::validate( $invalid, LPG\StructuredOutput::topic_schema() ) ) > 0, 'outline tanpa pain points ditolak' );
$quoted = $valid_outline;
$quoted['source_proof'] = array( 'exact_quote' => 'Kutipan yang tidak ada di sumber.' );
lpg_assert_true( count( LPG\StructuredOutput::verify_quotes( $quoted, $parsed['sources'] ) ) > 0, 'kutipan tanpa sumber ditolak validator' );
lpg_assert_same( 'write', LPG\Orchestrator::route_after_validate( true ), 'valid outline routing ke write' );
lpg_assert_same( 'retry_outline', LPG\Orchestrator::route_after_validate( false, 0 ), 'outline gagal di-retry' );
lpg_assert_same( 'stop', LPG\Orchestrator::route_after_validate( false, 2 ), 'retry dibatasi' );

funnel_fixture( 9101, 'Item state', 'isi', 'lpgen_item' );
lpg_assert_true( LPG\Orchestrator::transition( 9101, 'new', 'research' ), 'transisi new→research diterima' );
lpg_assert_true( LPG\Orchestrator::transition( 9101, 'new', 'research' ), 'transisi yang sama idempoten' );
lpg_assert_true( ! LPG\Orchestrator::transition( 9101, 'new', 'review' ), 'transisi ilegal ditolak' );

$saved_memory = $GLOBALS['lpg_topic_memory_rows'] ?? null;
$GLOBALS['lpg_topic_memory_rows'] = array();
LPG\TopicMemory::add( array( 'item_id' => 1, 'keyword' => 'jasa website Solo terbaik', 'summary' => 'Perbandingan jasa website di Solo.', 'outline' => array( 'Biaya', 'Proses' ), 'entities' => array( 'website', 'Solo' ), 'embedding' => array( 1, 0 ), 'checked_at' => gmdate( 'Y-m-d H:i:s' ) ) );
LPG\TopicMemory::add( array( 'item_id' => 2, 'keyword' => 'jasa website Solo terbaik', 'summary' => 'Perbandingan jasa website di Solo.', 'outline' => array( 'Biaya', 'Proses' ), 'entities' => array( 'website', 'Solo' ), 'embedding' => array( 1, 0 ), 'checked_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$found = LPG\TopicMemory::search( 'jasa website Solo terbaik', 2 );
lpg_assert_same( 2, count( $found ), 'memori menyimpan dua entri ADD-only' );
lpg_assert_true( $found[0]['duplicate_risk'], 'kemiripan tinggi ditandai duplikat' );
lpg_assert_true( str_contains( LPG\TopicMemory::prompt_line( 'jasa website Solo terbaik' ), 'SEMANTIC MEMORY' ), 'peringatan memori masuk prompt' );
if ( null === $saved_memory ) { unset( $GLOBALS['lpg_topic_memory_rows'] ); } else { $GLOBALS['lpg_topic_memory_rows'] = $saved_memory; }
