<?php
/** Regression coverage for the local funnel workflow using in-memory WP storage. */
declare(strict_types=1);
use LPG\FunnelContent;
use LPG\Publisher;
use LPG\ContentRepository;

require_once dirname(__DIR__) . '/includes/class-content-repository.php';
$GLOBALS['funnel_posts'] = array();
$GLOBALS['funnel_meta'] = array();
function get_posts( $args = array() ) {
 $posts = array_values(array_filter($GLOBALS['funnel_posts'], static fn($p) => in_array($p->post_type, (array)($args['post_type'] ?? array('post')), true)));
 usort($posts, static fn($a,$b) => $b->ID <=> $a->ID);
 return array_slice($posts, $args['offset'] ?? 0, $args['posts_per_page'] ?? 100);
}
class WP_Query { public array $posts; public function __construct($args) { $this->posts=array_values(array_filter($GLOBALS['funnel_posts'], static function($p) use($args) { if($p->post_type !== $args['post_type']) { return false; } foreach($args['meta_query'] ?? array() as $q) { if(get_post_meta($p->ID,$q['key'],true) != $q['value']) { return false; } } return true; })); } }
function get_post( $id ) { return $GLOBALS['funnel_posts'][$id] ?? null; }
function get_post_status( $id ) { return get_post($id)->post_status ?? false; }
function get_the_title( $id ) { return get_post($id)->post_title ?? ''; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['funnel_meta'][$id][$key] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['funnel_meta'][$id][$key]=$value; return true; }
function delete_post_meta( $id, $key ) { unset($GLOBALS['funnel_meta'][$id][$key]); }
function wp_insert_post( $args, $error = false ) {
 $id = $args['ID'] ?? (count($GLOBALS['funnel_posts'])+1000);
 $GLOBALS['funnel_posts'][$id]=(object)array_merge(array('ID'=>$id, 'post_title'=>'','post_content'=>'','post_excerpt'=>'','post_type'=>'post','post_status'=>'draft'),$args);
 return $id;
}
function wp_update_post( $args, $error = false ) {
 $id=$args['ID']; $old=(array)get_post($id); return wp_insert_post(array_merge($old,$args),$error);
}
function is_wp_error( $value ) { return false; }
function get_permalink( $id ) { return 'https://example.test/post/' . $id; }
function get_bloginfo( $what ) { return 'Test'; }
function wp_set_post_terms( ...$args ) { return array(); }
function get_page_by_path( $path, $output = null, $type = 'page' ) { return $GLOBALS['funnel_paths'][$path] ?? null; }
if (!defined('OBJECT')) { define('OBJECT','OBJECT'); }
function funnel_throws( callable $fn, string $label ): void {
 try { $fn(); lpg_assert_true(false,$label); } catch (RuntimeException $e) { lpg_assert_true(true,$label); }
}
function funnel_fixture( int $id, string $title, string $body, string $type='post' ): void {
 $GLOBALS['funnel_posts'][$id]=(object)array('ID'=>$id,'post_title'=>$title,'post_content'=>$body,'post_excerpt'=>'','post_type'=>$type,'post_status'=>'publish');
}
$entities=array();
foreach (array('website UMKM','desain responsif','katalog produk','Solo','kecepatan halaman','formulir kontak','biaya pemeliharaan','nama domain','konten usaha','pengalaman pengguna') as $term) { $entities[]=array('term'=>$term); }
lpg_assert_same(10,count(FunnelContent::entities($entities)),'10 entitas per konten diterima');
funnel_throws(static fn()=>FunnelContent::entities(array_slice($entities,0,7)), '7 entitas ditolak');
funnel_throws(static fn()=>FunnelContent::entities(array_fill(0,10,array('term'=>'Solo'))), 'entitas duplikat tidak memenuhi kuota');
funnel_throws(static fn()=>FunnelContent::anchor('<b>jasa website Solo</b>'), 'anchor HTML ditolak');
funnel_throws(static fn()=>FunnelContent::anchor('klik di sini'), 'anchor generik ditolak');
lpg_assert_same('jasa website Solo',FunnelContent::anchor('jasa website Solo'),'anchor keyword AI diterima');
lpg_assert_same('jasa/website/solo',FunnelContent::clean_path('/jasa/website/Solo/'),'path LP bertingkat dinormalisasi');
$body='';
for ($i=0;$i<90;$i++) { $body.="Pembahasan khusus nomor $i membantu pemilik bisnis Solo menentukan kebutuhan website mereka. "; }
lpg_assert_true(FunnelContent::similar($body,str_replace('Solo','Klaten',$body)),'hanya mengganti nama kota tetap dianggap duplikat');
lpg_assert_true(!FunnelContent::similar($body,'Panduan mengelola stok bahan baku dan distribusi barang untuk toko.'),'pembahasan berbeda diterima');
funnel_fixture(1,'Panduan website UMKM Solo',$body);
funnel_throws(static fn()=>FunnelContent::assert_unique('PANDUAN website UMKM Solo!','isi berbeda'), 'judul duplikat situs ditolak');
funnel_throws(static fn()=>FunnelContent::assert_unique('Judul berbeda',$body), 'body sama dari campaign lain ditolak');
FunnelContent::assert_unique('Panduan website UMKM Solo',$body,array(1));
lpg_assert_true(true,'item sendiri dikecualikan saat regenerasi');
// Verify scanning reaches older content beyond the first page of records.
for($i=2;$i<=105;$i++) { funnel_fixture($i,'Topik unik '.$i,'Isi berbeda '.$i); }
funnel_throws(static fn()=>FunnelContent::assert_unique('Judul lain',$body), 'duplikat ditemukan setelah pagination 100 konten');
$GLOBALS['funnel_posts']=array();
funnel_fixture(10,'Topik sumber','Outline sumber','lpgen_item');
update_post_meta(10,'_lpgen_kind','topic');
FunnelContent::assert_unique('Topik sumber','Pembahasan artikel orisinal',array(10));
lpg_assert_true(true,'artikel boleh memakai judul topik sumbernya sendiri');
$blocks=Publisher::paragraph_block('Untuk kebutuhan usaha, pertimbangkan jasa website Solo sesuai anggaran.');
$linked=Publisher::insert_funnel_link($blocks,'https://example.test/jasa/website/solo/','jasa website Solo','');
lpg_assert_true(str_contains($linked,'pertimbangkan <a href="https://example.test/jasa/website/solo/">jasa website Solo</a> sesuai'),'anchor masuk ke dalam kalimat natural');
lpg_assert_same(1,substr_count($linked,'<p>'),'tidak menambah paragraf bila anchor sudah ada');
$html=Publisher::paragraph_block('<a href="https://other.test">jasa website Solo</a>');
$linked=Publisher::insert_funnel_link($html,'https://example.test/lp','jasa website Solo','Temukan jasa website Solo yang sesuai.');
lpg_assert_true(!str_contains($linked,'<a href="https://other.test"><a'),'tidak membuat link bersarang');
// Editing must survive publishing; do not use AI's stale JSON body.
$GLOBALS['funnel_posts']=array();
funnel_fixture(20,'Judul hasil review','<!-- wp:paragraph --><p>Koreksi manual: pilih jasa website Solo sesuai kebutuhan.</p><!-- /wp:paragraph -->','lpgen_item');
foreach(array('kind'=>'article','stage'=>'tofu','status'=>'approved','campaign_id'=>500,'anchor'=>'jasa website Solo','entities'=>json_encode($entities),'structured'=>json_encode(array('title'=>'Judul lama','sections'=>array(array('body'=>'ISI AI LAMA')),'cta_paragraph'=>'Pilih jasa website Solo.'))) as $key=>$value) { update_post_meta(20,'_lpgen_'.$key,$value); }
$id=Publisher::publish_item(20,array('offer_type'=>'service','offer_url'=>'https://wa.me/123','cta'=>'Hubungi','campaign_id'=>500,'publish_status'=>'publish','index_enabled'=>0),'https://example.test/jasa/website/solo/');
$saved=get_post($id);
lpg_assert_true(str_contains($saved->post_content,'Koreksi manual'),'perubahan editor dipertahankan saat publish');
lpg_assert_true(!str_contains($saved->post_content,'ISI AI LAMA'),'isi JSON lama tidak mengganti hasil review');
lpg_assert_true(str_contains($saved->post_content,'href="https://example.test/jasa/website/solo/"'),'artikel TOFU menaut ke LP campaign');
lpg_assert_true(!str_contains($saved->post_content,'wa.me'),'artikel tidak menaut langsung ke checkout/WhatsApp');
$id2=Publisher::publish_item(20,array('offer_type'=>'service','publish_status'=>'publish','index_enabled'=>0),'https://example.test/jasa/website/solo/');
lpg_assert_same($id,$id2,'publish ulang memperbarui post yang sama');
// Missing/new settings must survive partial campaign updates.
update_post_meta(500,'_lpgen_bofu_mode','existing');
update_post_meta(500,'_lpgen_bofu_existing_id',123);
update_post_meta(500,'_lpgen_target_areas',"Solo\nSukoharjo");
ContentRepository::save_campaign_meta(500,array('ai_framework'=>'{}','bofu_path'=>'jasa/website/solo'));
lpg_assert_same('existing',get_post_meta(500,'_lpgen_bofu_mode',true),'pilihan BOFU bertahan saat update parsial');
lpg_assert_same(123,get_post_meta(500,'_lpgen_bofu_existing_id',true),'ID LP bertahan saat update framework');
lpg_assert_same("Solo\nSukoharjo",get_post_meta(500,'_lpgen_target_areas',true),'area target tersimpan');
lpg_assert_same('jasa/website/solo',get_post_meta(500,'_lpgen_bofu_path',true),'path BOFU tersimpan');
$GLOBALS['funnel_posts']=array(); $GLOBALS['funnel_meta']=array();

require_once dirname(__DIR__) . '/includes/class-database.php';
$GLOBALS['wpdb']=new class { public string $prefix='wp_'; public function insert(...$args) { return 1; } };
require_once dirname(__DIR__) . '/includes/class-generator.php';
funnel_fixture(30,'LP Website Solo',Publisher::paragraph_block('Isi LP hasil review orisinal.'),'lpgen_item');
foreach(array('kind'=>'lp','stage'=>'bofu','status'=>'approved','entities'=>json_encode($entities),'structured'=>'{}') as $key=>$value) { update_post_meta(30,'_lpgen_'.$key,$value); }
$campaign=array('bofu_path'=>'jasa/website/solo','lp_post_type'=>'page','publish_status'=>'publish','index_enabled'=>0);
funnel_throws(static fn()=>Publisher::publish_item(30,$campaign),'LP path bertingkat ditahan bila induk belum terbit');
$GLOBALS['funnel_paths']['jasa/website']=(object)array('ID'=>800,'post_status'=>'publish');
$new_id=Publisher::publish_item(30,$campaign);
lpg_assert_same('solo',get_post($new_id)->post_name,'slug terakhir LP sesuai path');
lpg_assert_same(800,get_post($new_id)->post_parent,'LP berada di halaman induk yang tepat');
funnel_fixture(31,'Artikel baru',Publisher::paragraph_block('Pembahasan khusus yang belum pernah ditulis.'),'lpgen_item');
foreach(array('kind'=>'article','stage'=>'tofu','status'=>'approved','campaign_id'=>500,'anchor'=>'jasa website Solo','entities'=>json_encode($entities),'structured'=>'{}') as $key=>$value) { update_post_meta(31,'_lpgen_'.$key,$value); }
funnel_fixture(500,'Campaign Solo','','lpgen_campaign');
update_post_meta(500,'_lpgen_bofu_mode','existing');
update_post_meta(500,'_lpgen_bofu_existing_id',9999);
$publish_job=new ReflectionMethod(\LPG\Generator::class,'publish_job');
funnel_throws(static fn()=>$publish_job->invoke(null,array('campaign_id'=>500,'item_id'=>31)),'artikel ditahan ketika LP target belum terbit');
funnel_fixture(9999,'Jasa Website Solo','Informasi layanan website Solo.','page');
$publish_job->invoke(null,array('campaign_id'=>500,'item_id'=>31));
$published=(int)get_post_meta(31,'_lpgen_post_id',true);
lpg_assert_true(str_contains(get_post($published)->post_content,'https://example.test/post/9999'),'artikel menggunakan permalink LP campaign yang dipilih');
// The final check catches duplicates introduced by manual edits.
funnel_fixture(32,'Artikel lain','Informasi layanan website Solo.','lpgen_item');
foreach(array('kind'=>'article','stage'=>'mofu','entities'=>json_encode($entities),'structured'=>'{}') as $key=>$value) { update_post_meta(32,'_lpgen_'.$key,$value); }
funnel_throws(static fn()=>Publisher::publish_item(32,array('publish_status'=>'publish','index_enabled'=>0),'https://example.test/lp'),'konten duplikat hasil edit diblokir sebelum publish');
$GLOBALS['funnel_posts']=array(); $GLOBALS['funnel_meta']=array();
