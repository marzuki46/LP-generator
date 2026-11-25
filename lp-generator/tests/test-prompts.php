<?php
/**
 * Prompt builder tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\CognitiveEngine;
use LPG\LanguageService;
use LPG\LpTypeRegistry;
use LPG\SeoMetadataEngine;
use LPG\SchemaRegistry;
use LPG\InternalLinkEngine;
use LPG\ExperimentMode;
use LPG\PublishingWorkflow;
use LPG\DuplicateGuard;
use LPG\LocalValueGate;
use LPG\AffiliateValueGate;
use LPG\QualityGate;
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

$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();
funnel_fixture( 700, 'Campaign', '', 'lpgen_campaign' );
funnel_fixture( 701, 'Jasa website Solo terbaik', 'isi lama', 'lpgen_item' );
foreach ( array( 'campaign_id' => 700, 'kind' => 'article', 'stage' => 'tofu', 'status' => 'published' ) as $k => $v ) { update_post_meta( 701, '_lpgen_' . $k, $v ); }
$dup = DuplicateGuard::check( array( 'campaign_id' => 700, 'title' => 'Jasa website Bali terbaik', 'city' => 'Bali', 'known_cities' => array( 'Solo', 'Bali' ), 'exclude_item_id' => 702 ), array( 'title' => 'Jasa website Bali terbaik', 'sections' => array() ) );
lpg_assert_true( $dup['score'] >= 35, 'judul city-swapped terdeteksi duplikat' );
$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();

$local_empty = LocalValueGate::check( array( 'audience' => '' ), array( 'city' => 'Solo' ) );
lpg_assert_same( 'REVIEW_REQUIRED', $local_empty['decision'], 'local gate menahan bila bukti lokal kosong' );
$local_hold = LocalValueGate::check( array( 'audience' => 'UMKM' ), array( 'city' => 'Solo' ), array( 'local_differentiation' => 20 ) );
lpg_assert_same( 'HOLD', $local_hold['decision'], 'local gate menahan bila diferensiasi rendah' );
lpg_assert_same( 'area_hub', $local_hold['recommendation'], 'local gate menyarankan area hub' );
$local_pass = LocalValueGate::check( array( 'audience' => 'UMKM' ), array( 'city' => 'Solo', 'verified_examples' => 'contoh nyata', 'service_area_evidence' => 'data coverage', 'bofu_path' => 'jasa/website/solo' ), array( 'local_differentiation' => 75 ) );
lpg_assert_same( 'PASS', $local_pass['decision'], 'local gate lolos bila bukti lengkap' );

$aff_missing = AffiliateValueGate::check( array( 'offer_name' => 'Review', 'amazon_marketplace' => 'com', 'affiliate_tag' => 'tag-20', 'source_reference' => 'https://example.test', 'original_analysis' => str_repeat( 'Analisis asli dan mendalam. ', 10 ) ), array() );
lpg_assert_same( 'HOLD', $aff_missing['decision'], 'affiliate gate menahan tanpa ASIN' );
$aff_ok = AffiliateValueGate::check( array( 'offer_name' => 'Review', 'amazon_marketplace' => 'com', 'asin' => 'B0TEST', 'affiliate_tag' => 'tag-20', 'source_reference' => 'https://example.test', 'original_analysis' => str_repeat( 'Analisis asli dan mendalam. ', 10 ) ), array() );
lpg_assert_same( 'PASS', $aff_ok['decision'], 'affiliate gate lolos dengan data lengkap' );
$aff_fake = AffiliateValueGate::check( array( 'offer_name' => 'Review', 'amazon_marketplace' => 'com', 'asin' => 'B0TEST', 'affiliate_tag' => 'tag-20', 'source_reference' => 'https://example.test', 'original_analysis' => 'I tested this product myself ' . str_repeat( 'with detailed notes. ', 8 ) ), array() );
lpg_assert_same( 'HOLD', $aff_fake['decision'], 'klaim hands-on tanpa bukti ditahan' );

$quality = QualityGate::evaluate( array( 'campaign' => array( 'lp_type' => 'service', 'offer_url' => 'https://example.test/cta' ), 'data' => array( 'title' => 'LP', 'sections' => array( array( 'heading' => 'A', 'body' => str_repeat( 'isi ', 40 ) ) ) ), 'kind' => 'lp', 'seo' => array( 'blockers' => array( 'canonical: external host' ) ) ) );
lpg_assert_same( 'HOLD', $quality['decision'], 'hard blocker mengalahkan skor' );
lpg_assert_true( 25 === $quality['weights']['unique_information'], 'bobot quality gate sesuai spesifikasi' );

lpg_assert_same( 'CONTENT_PREVIEW', ExperimentMode::clean( 'content_preview' ), 'mode eksperimen dinormalisasi' );
lpg_assert_same( '', ExperimentMode::clean( 'bogus' ), 'mode tidak dikenal jatuh ke produksi' );
$experiment_campaign = array( 'experiment_mode' => 'CONTENT_PREVIEW' );
lpg_assert_true( ExperimentMode::should_noindex( $experiment_campaign ) && ! ExperimentMode::sitemap_eligible( $experiment_campaign ), 'eksperimen noindex dan di luar sitemap' );
lpg_assert_true( str_contains( ExperimentMode::admin_label( $experiment_campaign ), 'Experiment' ), 'label admin eksperimen tersedia' );
lpg_assert_true( str_contains( ExperimentMode::robots_meta( $experiment_campaign ), 'noindex,follow' ), 'fallback robots meta hanya untuk eksperimen' );
lpg_assert_same( '', ExperimentMode::robots_meta( array() ), 'campaign produksi tidak memakai fallback robots' );

$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();
funnel_fixture( 740, 'Campaign Baru', '', 'lpgen_campaign' );
LPG\ContentRepository::save_campaign_meta( 740, array( 'offer_name' => 'Jasa baru' ) );
$new_campaign = LPG\ContentRepository::get_campaign( 740 );
lpg_assert_same( 'CONTENT_PREVIEW', $new_campaign['experiment_mode'], 'campaign baru default CONTENT_PREVIEW' );
lpg_assert_same( 'draft', $new_campaign['publish_status'], 'campaign baru default publish draft' );
lpg_assert_true( ExperimentMode::should_noindex( $new_campaign ), 'campaign baru default noindex' );

$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();
funnel_fixture( 720, 'Campaign Eksperimen', '', 'lpgen_campaign' );
funnel_fixture( 721, 'LP Eksperimen', 'isi lp', 'lpgen_item' );
update_post_meta( 720, '_lpgen_experiment_mode', 'CONTENT_PREVIEW' );
update_post_meta( 720, '_lpgen_offer_url', 'https://example.test/cta' );
foreach ( array( 'campaign_id' => 720, 'status' => 'approved', 'content' => 'isi lp' ) as $k => $v ) { update_post_meta( 721, '_lpgen_' . $k, $v ); }
update_post_meta( 721, '_lpgen_structured', wp_json_encode( array( 'title' => 'LP Eksperimen' ) ) );
update_post_meta( 721, '_lpgen_v2_quality', wp_json_encode( array( 'decision' => 'ELIGIBLE_FOR_REVIEW', 'score' => 91 ) ) );
update_post_meta( 721, '_lpgen_v2_seo_meta', wp_json_encode( array( 'blockers' => array() ) ) );
ExperimentMode::apply_noindex( 721, LPG\ContentRepository::get_campaign( 720 ) );
lpg_assert_same( 1, (int) get_post_meta( 721, '_lpgen_noindex', true ), 'noindex diterapkan ke post eksperimen' );
$checks = ExperimentMode::promotion_checks( 720, 721 );
lpg_assert_same( 'WARNING', $checks['decision'], 'belum teruji tercatat sebagai warning, bukan fail' );
lpg_assert_same( 'PASS', $checks['checks']['quality'], 'kualitas lolos promosi' );
$promoted = ExperimentMode::promote( 720, 721 );
lpg_assert_true( $promoted['ok'], 'promosi eksperimen berhasil' );
lpg_assert_same( '', (string) get_post_meta( 720, '_lpgen_experiment_mode', true ), 'mode eksperimen dihapus saat promosi' );
lpg_assert_same( 0, (int) get_post_meta( 721, '_lpgen_noindex', true ), 'noindex dilepas saat promosi' );
$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();

funnel_fixture( 730, 'Item Workflow', '', 'lpgen_item' );
lpg_assert_same( 'DRAFT', PublishingWorkflow::state( 730 ), 'state awal workflow DRAFT' );
PublishingWorkflow::mark_generated( 730 );
lpg_assert_same( 'GENERATED', PublishingWorkflow::state( 730 ), 'generate memindahkan state ke GENERATED' );
lpg_assert_true( ! PublishingWorkflow::can( 'DRAFT', 'PUBLISHED' ), 'transisi DRAFT → PUBLISHED dilarang' );
lpg_assert_true( PublishingWorkflow::advance_to( 730, 'APPROVED' ), 'approval membuka jalur state' );
lpg_assert_same( 'APPROVED', PublishingWorkflow::state( 730 ), 'state akhir APPROVED' );
lpg_assert_true( PublishingWorkflow::advance_to( 730, 'APPROVED' ), 'transisi idempoten' );
lpg_assert_true( PublishingWorkflow::advance_to( 730, 'PUBLISHED' ), 'publish membuka jalur APPROVED → SCHEDULED → PUBLISHED' );
lpg_assert_true( count( PublishingWorkflow::history( 730 ) ) >= 4, 'history workflow tercatat' );
$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();

lpg_assert_same( array( 'service', 'product', 'amazon_affiliate' ), array_keys( LpTypeRegistry::all() ), 'registry punya 3 tipe LP' );
lpg_assert_same( 'amazon_affiliate', LpTypeRegistry::from_offer_type( 'affiliate' ), 'offer_type affiliate dipetakan ke amazon_affiliate' );
lpg_assert_same( 'service', LpTypeRegistry::clean( 'bogus' ), 'tipe tidak dikenal jatuh ke service' );
$service = LpTypeRegistry::get( 'service' );
lpg_assert_true( in_array( 'Service', $service->schema_types(), true ) && in_array( 'local_value_gate', $service->quality_checks(), true ), 'strategi service punya schema dan quality gate' );
$affiliate = LpTypeRegistry::get( 'amazon_affiliate' );
lpg_assert_true( str_contains( implode( ' ', $affiliate->cta_behavior() ), 'Amazon Associate' ), 'strategi afiliasi punya disclosure wajib' );
lpg_assert_same( 'en', LanguageService::clean( 'EN' ), 'bahasa dinormalisasi' );
lpg_assert_same( 'id', LanguageService::clean( 'de' ), 'bahasa tidak didukung jatuh ke id' );
lpg_assert_same( '/en/services/website/bali/', LanguageService::planned_path( 'en', 'jasa/website/bali' ), 'rencana slug EN memakai prefix /en/' );
lpg_assert_same( array( 'id' => 'https://example.test/id/' ), LanguageService::hreflang_plan( array( 'id' => 'https://example.test/id/' ) ), 'hreflang hanya self bila satu bahasa' );
$hreflang = LanguageService::hreflang_plan( array( 'id' => 'https://example.test/id/', 'en' => 'https://example.test/en/' ) );
lpg_assert_same( 'https://example.test/id/', $hreflang['x-default'], 'x-default hanya bila kedua bahasa ada' );
lpg_assert_same( 'Pesan sekarang', LanguageService::strings( 'id' )['cta_order'], 'string CTA dilokalisasi penuh' );

$brief_context = array( 'campaign_id' => 77, 'lp_type' => 'service', 'language' => 'en', 'offer_name' => 'Website Solo', 'audience' => 'UMKM', 'city' => 'Solo', 'promise' => 'Website cepat', 'phone' => '08123456789' );
$service_brief = LPG\LpBriefGenerator::generate( $brief_context );
lpg_assert_same( array(), LPG\LpBriefGenerator::validate( $service_brief ), 'brief service lolos dengan mapping contact_method dari phone' );
lpg_assert_same( '08123456789', $service_brief['contact_method'], 'contact_method diturunkan dari phone' );
lpg_assert_true( in_array( 'comparison_table', $service_brief['sections'], true ) === false && in_array( 'portfolio', $service_brief['sections'], true ), 'section schema service dipakai' );
$affiliate_context = array_merge( $brief_context, array( 'lp_type' => 'amazon_affiliate', 'amazon_region' => 'com', 'amazon_tag' => 'tag-20', 'research_sources' => wp_json_encode( array( array( 'url' => 'https://example.test/source' ) ) ), 'angle' => 'Analisis orisinal' ) );
$affiliate_brief = LPG\LpBriefGenerator::generate( $affiliate_context );
lpg_assert_same( 'com', $affiliate_brief['amazon_marketplace'], 'marketplace diturunkan dari amazon_region' );
lpg_assert_same( 'tag-20', $affiliate_brief['affiliate_tag'], 'tag diturunkan dari amazon_tag' );
lpg_assert_true( count( LPG\LpBriefGenerator::validate( $affiliate_brief ) ) > 0, 'brief affiliate tanpa ASIN ditolak' );
lpg_assert_true( str_contains( LPG\LpBriefGenerator::to_prompt( $service_brief ), 'CONTENT BRIEF' ), 'brief bisa disuntik ke prompt' );

$seo_meta = SeoMetadataEngine::build(
 array( 'seo_title' => str_repeat('Judul ', 20), 'meta_description' => str_repeat('Deskripsi ', 30), 'title' => 'LP Jasa' ),
 array( 'language' => 'id', 'offer_name' => 'Jasa Website', 'city' => 'Solo' ),
 array( 'experiment' => true, 'rendered_html' => '<h1>LP Jasa</h1>', 'images' => array() )
);
lpg_assert_same( 60, mb_strlen( $seo_meta['seo_title'] ), 'SEO title dipotong 60 karakter' );
lpg_assert_same( 'noindex,follow', $seo_meta['robots'], 'experiment memakai noindex' );
lpg_assert_true( ! $seo_meta['sitemap_eligible'], 'experiment tidak eligible sitemap' );
lpg_assert_true( in_array( 'h1: must be exactly one', $seo_meta['blockers'], true ) === false, 'H1 tunggal tidak diblokir' );

$svc_schema = SchemaRegistry::graph( array( 'lp_type' => 'service', 'offer_name' => 'Jasa Website', 'city' => 'Solo', 'price' => 'Rp800rb' ), array( 'title' => 'LP', 'intro' => 'Intro' ), array( 'url' => 'https://example.test/lp/' ) );
lpg_assert_true( in_array( 'Service', array_column( $svc_schema, '@type' ), true ), 'schema service punya node Service' );
lpg_assert_true( isset( $svc_schema[1]['areaServed'][0]['name'] ) && 'Solo' === $svc_schema[1]['areaServed'][0]['name'], 'areaServed memakai kota nyata' );
$aff_schema = SchemaRegistry::graph( array( 'lp_type' => 'amazon_affiliate', 'offer_name' => 'Review', 'language' => 'id' ), array( 'title' => 'Review', 'meta_description' => 'Desc' ), array( 'url' => 'https://example.test/review/' ) );
$aff_json = wp_json_encode( $aff_schema );
lpg_assert_true( str_contains( $aff_json, 'Article' ) && ! str_contains( $aff_json, 'aggregateRating' ) && ! str_contains( $aff_json, 'priceSpecification' ), 'schema affiliate tanpa rating/harga rekaan' );
$yoast_graph = array( array( '@id' => 'https://example.test/#organization', '@type' => 'Organization' ) );
$merged = SchemaRegistry::inject_yoast( $yoast_graph, (object) array( 'ID' => 0 ) );
lpg_assert_same( 1, count( $merged ), 'inject tidak menambah node untuk post non-LP' );

$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();
funnel_fixture( 500, 'Campaign Solo', '', 'lpgen_campaign' );
update_post_meta( 500, '_lpgen_bofu_path', 'jasa/website/solo' );
update_post_meta( 500, '_lpgen_city', 'Solo' );
update_post_meta( 500, '_lpgen_lp_type', 'service' );
update_post_meta( 500, '_lpgen_language', 'id' );
funnel_fixture( 601, 'Panduan website Solo', '', 'lpgen_item' );
foreach ( array( 'campaign_id' => 500, 'kind' => 'article', 'stage' => 'tofu', 'status' => 'approved', 'permalink' => 'https://example.test/panduan-solo' ) as $k => $v ) { update_post_meta( 601, '_lpgen_' . $k, $v ); }
funnel_fixture( 602, 'Panduan website Bali', '', 'lpgen_item' );
foreach ( array( 'campaign_id' => 500, 'kind' => 'article', 'stage' => 'tofu', 'status' => 'approved', 'permalink' => 'https://example.test/panduan-bali' ) as $k => $v ) { update_post_meta( 602, '_lpgen_' . $k, $v ); }
$links = InternalLinkEngine::suggest( 500, array(), 5 );
lpg_assert_true( count( $links ) >= 2 && 'Panduan website Solo' === $links[0]['title'], 'link ranking mengutamakan konten kota yang sama' );
$GLOBALS['funnel_posts'] = array(); $GLOBALS['funnel_meta'] = array();
