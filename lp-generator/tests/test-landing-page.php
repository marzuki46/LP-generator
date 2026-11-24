<?php
/** HTML rendering and campaign-specific framework regression tests. @package LPG */
declare(strict_types=1);
require_once __DIR__.'/fixtures/lp-data.php';
use LPG\Frameworks;
use LPG\LandingPage;
use LPG\LpTemplate;
foreach(array_merge(array_keys(Frameworks::presets()),array('ai')) as $slug) {
 $fixture=lpg_lp_fixture($slug);$campaign=$fixture['campaign'];$data=$fixture['data'];
 LandingPage::validate($data,$campaign);
 $html=LandingPage::render($data,$campaign);
 lpg_assert_true(str_starts_with($html,"<!-- wp:html -->\n"),$slug.': WordPress HTML block');
 lpg_assert_same(count($campaign['framework_plan']['steps']),substr_count($html,'data-step='),$slug.': seluruh langkah framework tampil');
 lpg_assert_same(1,preg_match_all('/<h1\b/', $html),$slug.': satu headline utama');
 lpg_assert_true(strpos($html,'lpg-lp__button')<strpos($html,'data-step="2"'),$slug.': CTA tersedia pada hero');
 lpg_assert_same(4,substr_count($html,'<details>'),$slug.': FAQ tanpa JavaScript');
 $previous=-1;
 foreach($data['sections'] as $section) { $position=strpos($html,'data-step="'.$section['step'].'"'); lpg_assert_true($position>$previous,$slug.': urutan langkah '.$section['step']);$previous=$position; }
 $other=$campaign;$other['campaign_id']=999;
 lpg_assert_same(array(),Frameworks::campaign_plan($other),$slug.': rencana campaign lain ditolak');
 $other=$campaign;$other['city']='Bandung';
 lpg_assert_same(array(),Frameworks::campaign_plan($other),$slug.': perubahan kota membatalkan rencana lama');
}
foreach (array_keys(LpTemplate::MODELS) as $model) {
 $fixture=lpg_lp_fixture('aida');$fixture['campaign']['lp_model']=$model;
 $html=LandingPage::render($fixture['data'],$fixture['campaign']);
 lpg_assert_true(str_contains($html,'data-lp-model="'.$model.'"'),'model LP '.$model.' tercantum di HTML');
 lpg_assert_true(str_contains($html,'lpg-lpm--'.$model),'model LP '.$model.' memakai class layout');
 lpg_assert_true(str_contains($html,'lpg-lpm__trust') && str_contains($html,'lpg-lpm__sticky-cta'),'model '.$model.' punya trust strip dan sticky CTA');
 lpg_assert_true(str_contains($html,'<!-- lpg-related:start -->'),'model '.$model.' punya slot related link');
}
lpg_assert_same('editorial',LpTemplate::clean('tidak-ada'),'model tidak dikenal jatuh ke editorial');
foreach (array('service','product','amazon_affiliate') as $lp_type) {
 $fixture=lpg_lp_fixture('aida');$fixture['campaign']['lp_type']=$lp_type;$fixture['campaign']['price']='Mulai Rp 800.000';
 $fixture['campaign']['product_specs']="Responsive\nSEO lokal";$fixture['campaign']['product_status']='available';
 $html=LandingPage::render($fixture['data'],$fixture['campaign']);
 lpg_assert_true(str_contains($html,'data-lp-type="'.$lp_type.'"') && str_contains($html,'lpg-lpt--'.$lp_type),'template tipe '.$lp_type.' dipakai');
 lpg_assert_true(str_contains($html,'Area layanan') && str_contains($html,'Solo') && str_contains($html,'lpg-lpm__footer-col'),'footer menyesuaikan lokasi campaign');
 lpg_assert_same(1,preg_match_all('/<h1\b/',$html),'tipe '.$lp_type.' tetap satu H1');
 lpg_assert_same(count($fixture['data']['sections']),substr_count($html,'data-step='),'tipe '.$lp_type.' mempertahankan langkah framework');
 if ('service'===$lp_type) { lpg_assert_true(str_contains($html,'lpg-lpt__contact') && !str_contains($html,'lpg-lpt__disclosure-top'),'service memakai blok kontak'); }
 if ('product'===$lp_type) { lpg_assert_true(str_contains($html,'lpg-lpt__product-banner') && str_contains($html,'Mulai Rp 800.000'),'product memakai banner harga/spec'); }
 if ('amazon_affiliate'===$lp_type) { lpg_assert_true(str_contains($html,'lpg-lpt__disclosure-top') && str_contains($html,'Amazon Associate') && str_contains($html,'lpg-lpt__quick'),'affiliate memakai disclosure dan rekomendasi cepat'); }
}
$fixture=lpg_lp_fixture('dipada');
lpg_assert_same('Desire',$fixture['campaign']['framework_plan']['steps'][0]['name'],'DIPADA Desire pertama dipertahankan');
lpg_assert_same('Desire',$fixture['campaign']['framework_plan']['steps'][4]['name'],'DIPADA Desire kedua dipertahankan');
$bad=$fixture['data'];array_pop($bad['sections']);
funnel_throws(static fn()=>LandingPage::validate($bad,$fixture['campaign']),'LP dengan langkah hilang ditolak');
$bad=$fixture['data'];$bad['sections']=array_reverse($bad['sections']);
funnel_throws(static fn()=>LandingPage::validate($bad,$fixture['campaign']),'LP dengan langkah terbalik ditolak');
$bad=$fixture['campaign']['framework_plan'];$bad['steps'][0]['name']='Action';
funnel_throws(static fn()=>Frameworks::validate_plan($bad,$fixture['campaign']),'AI tidak boleh mengganti formula pilihan');
$bad=$fixture['data'];$bad['sections'][0]['body']='{TESTIMONI_1}';
funnel_throws(static fn()=>LandingPage::validate($bad,$fixture['campaign']),'placeholder testimoni tidak diterbitkan');
$bad=$fixture['data'];$bad['sections'][0]['layout']='raw-script';
funnel_throws(static fn()=>LandingPage::validate($bad,$fixture['campaign']),'layout di luar allowlist ditolak');
$fixture=lpg_lp_fixture('fab');$html=LandingPage::render($fixture['data'],$fixture['campaign']);
lpg_assert_true(str_contains($html,'<table>') && str_contains($html,'scope="col"'),'FAB menyajikan tabel spesifikasi yang aksesibel');
$fixture=lpg_lp_fixture('bab');$html=LandingPage::render($fixture['data'],$fixture['campaign']);
lpg_assert_true(str_contains($html,'<ol class="lpg-lp__cards">'),'BAB bridge menyajikan proses berurutan');
$fixture['campaign']['offer_type']='affiliate';$html=LandingPage::render($fixture['data'],$fixture['campaign']);
lpg_assert_true(strpos($html,'lpg-lp__disclosure')<strpos($html,'sponsored nofollow'),'disclosure mendahului CTA afiliasi pertama');
lpg_assert_true(str_contains($html,'rel="sponsored nofollow noopener"'),'CTA afiliasi tetap ditandai');
$fixture['data']['title']='<script>alert(1)</script>';
$fixture['data']['sections'][0]['cards']=array(array('title'=>'<img src=x onerror=alert(1)>','body'=>'<iframe src=x></iframe>'));
$fixture['data']['cta_url']='https://untrusted.test';
$html=LandingPage::render($fixture['data'],$fixture['campaign']);
lpg_assert_true(!str_contains($html,'<script>') && !str_contains($html,'<iframe'),'HTML AI di-escape, tidak dieksekusi');
lpg_assert_true(!str_contains($html,'href="https://untrusted.test"'),'URL CTA diambil dari data campaign');
$prompt=\LPG\Prompts::build_framework_plan(lpg_lp_fixture('pas')['campaign']);
lpg_assert_true(str_contains($prompt['user'],'Problem') && str_contains($prompt['user'],'Agitate') && str_contains($prompt['user'],'EXACTLY'),'prompt rencana mempertahankan PAS');
$prompt=\LPG\Prompts::build_lp(lpg_lp_fixture()['campaign']);
lpg_assert_true(str_contains($prompt['user'],'framework_plan.steps exactly'),'penulisan LP menggunakan rencana campaign');

// Exercise the actual AI client + campaign persistence with a deterministic HTTP response.
if(!function_exists('wp_list_pluck')) { function wp_list_pluck($list,$field) { return array_column($list,$field); } }
if(!function_exists('get_transient')) { function get_transient($key) { return $GLOBALS['lpg_test_transients'][$key] ?? false; } }
if(!function_exists('set_transient')) { function set_transient($key,$value,$expiry) { $GLOBALS['lpg_test_transients'][$key]=$value;return true; } }
if(!function_exists('delete_transient')) { function delete_transient($key) { unset($GLOBALS['lpg_test_transients'][$key]); } }
function wp_remote_post($url,$args) {
 $GLOBALS['lpg_test_requests'][]=array('url'=>$url,'args'=>$args);
 if(!isset($GLOBALS['lpg_test_ai'])) { throw new RuntimeException('No test AI response configured; network disabled.'); }
 return array('response'=>array('code'=>200),'body'=>json_encode(array('choices'=>array(array('message'=>array('content'=>json_encode($GLOBALS['lpg_test_ai'])))),'usage'=>array('total_tokens'=>75))));
}
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) {return $response['body'];}
$GLOBALS['lpg_test_options']['lpgen_settings']=array('ninerouter_url'=>'https://router.example.test','models'=>'fixture');
funnel_fixture(501,'Website Solo','','lpgen_campaign');
$sample=lpg_lp_fixture('pas',501);
foreach($sample['campaign'] as $key=>$value) {
 if(!in_array($key,\LPG\ContentRepository::CAMPAIGN_KEYS,true)) {continue;}
 if(in_array($key,array('benefits','bonus','target_areas'),true)) {$value=implode("\n",$value);}
 if($key==='framework') {$value='pas';}
 update_post_meta(501,'_lpgen_'.$key,$value);
}
$GLOBALS['lpg_test_ai']=$sample['campaign']['framework_plan'];
$GLOBALS['lpg_test_requests']=array();
$result=\LPG\Generator::generate_framework(501);
lpg_assert_true($result['ok'],'framework campaign dibuat melalui client AI dan penyimpanan nyata');
lpg_assert_same(1,count($GLOBALS['lpg_test_requests']),'satu request AI untuk satu rencana framework lengkap');
lpg_assert_same('pas',get_post_meta(501,'_lpgen_framework',true),'pilihan PAS tidak berubah menjadi custom AI');
$reloaded=\LPG\ContentRepository::get_campaign(501);
lpg_assert_same(array('Problem','Agitate','Solution'),$reloaded['framework_steps'],'rencana campaign dimuat ulang sesuai PAS');
lpg_assert_same(501,$reloaded['framework_plan']['campaign_id'],'rencana terikat pada ID campaign');
$stored=get_post_meta(501,'_lpgen_ai_framework',true);
$GLOBALS['lpg_test_ai']['steps']=array_reverse($GLOBALS['lpg_test_ai']['steps']);
$result=\LPG\Generator::generate_framework(501);
lpg_assert_true(!$result['ok'],'rencana AI yang salah urutan ditolak tanpa fallback AIDA');
lpg_assert_same($stored,get_post_meta(501,'_lpgen_ai_framework',true),'rencana valid tidak ditimpa hasil AI yang tidak valid');
update_post_meta(501,'_lpgen_city','Bandung');
lpg_assert_same(array(),\LPG\ContentRepository::get_campaign(501)['framework_plan'],'rencana lama tidak digunakan setelah offer/lokasi berubah');
unset($GLOBALS['lpg_test_ai'],$GLOBALS['lpg_test_options']);

// Verify canvas isolation affects only generated LPs and only theme-specific styles.
function is_singular($types=array()) {return $GLOBALS['lpg_test_singular'] ?? true;}
function post_password_required() {return $GLOBALS['lpg_test_password'] ?? false;}
function get_queried_object_id() {return 601;}
function get_template_directory_uri() {return 'https://example.test/wp-content/themes/parent';}
function get_stylesheet_directory_uri() {return 'https://example.test/wp-content/themes/child';}
function is_admin() { return $GLOBALS['lpg_test_admin'] ?? true; }
function get_post_field($field,$id) {return get_post($id)->{$field} ?? '';}
function wp_styles() {return $GLOBALS['lpg_test_styles'];}
function wp_dequeue_style($handle) {$GLOBALS['lpg_test_dequeued'][]=$handle;}
function wp_enqueue_style(...$args) {$GLOBALS['lpg_test_enqueued'][]=$args;}
function wp_add_inline_style($handle,$css) {$GLOBALS['lpg_test_inline'][$handle]=$css;}
if(!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($text) {return strip_tags($text);} }
if(!defined('LPG_PATH')) {define('LPG_PATH',dirname(__DIR__).'/');}
if(!defined('LPG_URL')) {define('LPG_URL','https://example.test/wp-content/plugins/lp-generator/');}
if(!defined('LPG_VERSION')) {define('LPG_VERSION','1.5.0');}
$GLOBALS['lpg_test_styles']=(object)array('queue'=>array('parent','child','lpgen-landing-page','other-plugin'),'registered'=>array(
 'parent'=>(object)array('src'=>'https://example.test/wp-content/themes/parent/style.css'),
 'child'=>(object)array('src'=>'//example.test/wp-content/themes/child/style.css'),
 'lpgen-landing-page'=>(object)array('src'=>LPG_URL.'public/css/landing-page.css'),
 'other-plugin'=>(object)array('src'=>'https://example.test/wp-content/plugins/other/style.css')
));
$GLOBALS['lpg_test_dequeued']=array();
LandingPage::prepare_canvas_assets();
lpg_assert_same(array(),$GLOBALS['lpg_test_dequeued'],'halaman biasa tidak mengubah stylesheet tema');
update_post_meta(601,'_lpgen_canvas',1);
LandingPage::prepare_canvas_assets();
lpg_assert_true(in_array('parent',$GLOBALS['lpg_test_dequeued'],true) && in_array('child',$GLOBALS['lpg_test_dequeued'],true),'canvas mengisolasi CSS tema induk dan anak');
lpg_assert_true(!in_array('other-plugin',$GLOBALS['lpg_test_dequeued'],true) && !in_array('lpgen-landing-page',$GLOBALS['lpg_test_dequeued'],true),'stylesheet plugin tetap tersedia');
lpg_assert_same(LPG_PATH.'templates/landing-page.php',LandingPage::template('theme.php'),'LP canvas memakai template plugin');
$GLOBALS['lpg_test_password']=true;
lpg_assert_same('theme.php',LandingPage::template('theme.php'),'halaman berkata sandi mempertahankan template proteksinya');
$GLOBALS['lpg_test_password']=false;update_post_meta(601,'_lpgen_canvas',0);
lpg_assert_same('theme.php',LandingPage::template('theme.php'),'pilihan ikuti tema mempertahankan template asli');
$css='.lpg-lp { --lpg-accent: #123456; } .lpg-lp a::after { content:"\2192"; }';
lpg_assert_same($css,LandingPage::sanitize_css($css),'CSS tambahan mempertahankan escape dan variabel');
lpg_assert_true(!str_contains(LandingPage::sanitize_css('</style><script>alert(1)</script>'),'<'),'CSS tambahan tidak dapat menutup tag style dan menyisipkan HTML');
$GLOBALS['lpg_test_options']['lpgen_settings']=array('lp_custom_css'=>$css);
LandingPage::enqueue_assets();
lpg_assert_same($css,$GLOBALS['lpg_test_inline']['lpgen-aida'],'CSS tambahan ditambahkan sesudah CSS dasar melalui WordPress');
unset($GLOBALS['lpg_test_options']);
$GLOBALS['funnel_posts']=array();$GLOBALS['funnel_meta']=array();

$GLOBALS['lpg_test_admin']=false;
$GLOBALS['lpg_test_enqueued']=array();
LandingPage::enqueue_assets();
lpg_assert_same(array(),$GLOBALS['lpg_test_enqueued'],'CSS LP tidak dimuat pada halaman frontend biasa');
unset($GLOBALS['lpg_test_admin']);
