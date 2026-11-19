<?php
/** Contract regression: no image/section leakage, safe links, SEO and related resources. */
declare(strict_types=1);
use LPG\DesignContract;
use LPG\LandingPage;
use LPG\Frameworks;
use LPG\Publisher;
use LPG\SeoStandards;
$fixture=lpg_lp_fixture('aida');$campaign=$fixture['campaign'];$data=$fixture['data'];
$image=array('id'=>7,'url'=>'https://example.test/image.webp','alt'=>'Ilustrasi pemilik usaha menyiapkan informasi website','width'=>1200,'height'=>800,'slot_id'=>'aida-desire-visual','section_id'=>'aida-desire','design_id'=>'aida-1','campaign_id'=>101,'campaign_fingerprint'=>Frameworks::fingerprint($campaign));
$html=LandingPage::render($data,$campaign,array($image));
lpg_assert_true(strpos($html,'image.webp')>strpos($html,'id="aida-desire"') && strpos($html,'image.webp')<strpos($html,'id="aida-action"'),'gambar Desire berada hanya dalam section Desire');
lpg_assert_same(1,substr_count($html,'image.webp'),'gambar tidak digandakan di hero/fallback');
lpg_assert_true(str_contains($html,'width="1200" height="800"'),'dimensi gambar disertakan untuk kestabilan layout');
$bad=$image;$bad['slot_id']='aida-action-visual';
funnel_throws(static fn()=>LandingPage::render($data,$campaign,array($bad)),'slot gambar yang tidak tersedia ditolak');
$bad=$image;$bad['section_id']='aida-interest';
funnel_throws(static fn()=>LandingPage::render($data,$campaign,array($bad)),'gambar dengan section salah ditolak');
$bad=$image;$bad['campaign_id']=102;
funnel_throws(static fn()=>LandingPage::render($data,$campaign,array($bad)),'gambar campaign lain ditolak');
$bad=$image;$bad['campaign_fingerprint']='data-lama';
funnel_throws(static fn()=>LandingPage::render($data,$campaign,array($bad)),'gambar dari data campaign lama ditolak');
funnel_throws(static fn()=>LandingPage::render($data,$campaign,array($image,$image)),'slot gambar ganda ditolak');
$bad=$data;$bad['sections'][2]['section_id']='aida-interest';
funnel_throws(static fn()=>LandingPage::validate($bad,$campaign),'AI tidak boleh mengirim section_id yang tertukar');
$bad=$data;$bad['design_id']='pas-1';
funnel_throws(static fn()=>LandingPage::validate($bad,$campaign),'kode desain selain pilihan ditolak');
$bad=$data;$bad['sections'][0]['image_url']='https://untrusted.test/image.jpg';
funnel_throws(static fn()=>LandingPage::validate($bad,$campaign),'AI tidak boleh menyisipkan URL gambar sendiri');
$plain=Publisher::paragraph_block('Layanan website Solo sesuai kebutuhan.');
funnel_throws(static fn()=>Publisher::insert_funnel_link($plain,'https://other.test/lp','website Solo','Baca website Solo.'),'internal link domain lain ditolak');
$linked=Publisher::insert_funnel_link(Publisher::paragraph_block('websites Solo berbeda.'),'https://example.test/lp','website Solo','Bandingkan website Solo.');
lpg_assert_true(!str_contains($linked,'<a href="https://example.test/lp">website Solo</a>s'),'anchor tidak mengganti sebagian kata');
// Per-item metadata beats duplicate campaign-wide templates unless explicitly selected.
$item=array('id'=>910,'title'=>'Memilih website katalog','seo_title'=>'Website Katalog untuk Kebutuhan Usaha','meta_description'=>'Pahami kebutuhan katalog sebelum menentukan fitur website usaha Anda.','entities'=>json_encode(array(array('term'=>'website katalog'))));
Publisher::apply_seo_meta(911,$item,array('entities'=>array(array('term'=>'jasa website Solo'))));
lpg_assert_same('website katalog',get_post_meta(911,'_yoast_wpseo_focuskw',true),'focus keyword menggunakan entitas konten sendiri');
lpg_assert_same($item['seo_title'],get_post_meta(911,'_yoast_wpseo_title',true),'judul SEO memakai hasil khusus konten');
lpg_assert_same($item['meta_description'],get_post_meta(911,'_lpgen_meta_description',true),'deskripsi tersedia tanpa bergantung Yoast');
// Only published resources from the same campaign enter the reserved navigation slot.
$GLOBALS['funnel_posts']=array();$GLOBALS['funnel_meta']=array();
funnel_fixture(800,'LP asli',$html,'page');
update_post_meta(800,'_lpgen_campaign_id',101);
funnel_fixture(801,'Item LP',$html,'lpgen_item');
foreach(array('kind'=>'lp','stage'=>'bofu','status'=>'published','post_id'=>800,'campaign_id'=>101) as $k=>$v){update_post_meta(801,'_lpgen_'.$k,$v);}
funnel_fixture(810,'Panduan lokal','Artikel publik','post');funnel_fixture(811,'Item panduan','Buffer artikel','lpgen_item');
foreach(array('kind'=>'article','stage'=>'tofu','status'=>'published','post_id'=>810,'campaign_id'=>101) as $k=>$v){update_post_meta(811,'_lpgen_'.$k,$v);}
funnel_fixture(820,'Panduan campaign lain','Artikel berbeda','post');funnel_fixture(821,'Item lain','Buffer lain','lpgen_item');
foreach(array('kind'=>'article','stage'=>'mofu','status'=>'published','post_id'=>820,'campaign_id'=>202) as $k=>$v){update_post_meta(821,'_lpgen_'.$k,$v);}
SeoStandards::refresh_related(101);
$updated=get_post(800)->post_content;
lpg_assert_true(str_contains($updated,'href="https://example.test/post/810"'),'LP menaut ke panduan terbit dalam campaign yang sama');
lpg_assert_true(!str_contains($updated,'post/820'),'panduan campaign lain tidak masuk ke LP');
lpg_assert_true(str_contains($updated,$data['title']),'pembaruan slot internal link mempertahankan konten LP');
SeoStandards::refresh_related(101);
lpg_assert_same(1,substr_count(get_post(800)->post_content,'href="https://example.test/post/810"'),'refresh internal link tidak membuat tautan ganda');
$GLOBALS['funnel_posts']=array();$GLOBALS['funnel_meta']=array();

// Upload selection is bound to its exact slot, never inferred from an ordered gallery.
if(!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code,public string $message) {} public function get_error_message(){return $this->message;} } }
function get_attached_file($id) {$GLOBALS['aida_test_upload_id']=$id;return $GLOBALS['aida_test_upload_file'];}
function wp_get_image_mime($file) {return 'image/png';}
$temp=tempnam(sys_get_temp_dir(),'lpg-aida-');
file_put_contents($temp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));
$GLOBALS['aida_test_upload_file']=$temp;
$fixture=lpg_lp_fixture('aida');$context=$fixture['campaign'];$context['stage']='bofu';$context['aida_desire_image_id']=333;
// Rebind the plan after explicitly changing image assignments.
$plan=$context['framework_plan'];$plan=Frameworks::validate_plan($plan,$context);$context['ai_framework']=json_encode($plan);
$method=new ReflectionMethod(\LPG\ImagePipeline::class,'fetch_upload');
$result=$method->invoke(null,2,array(),$context);
lpg_assert_same(333,$GLOBALS['aida_test_upload_id'],'slot Desire mengambil hanya upload yang dipilih untuk Desire');
$context['image_upload_ids']='999,888,777';
$result=$method->invoke(null,0,array(),$context);
lpg_assert_true($result instanceof WP_Error,'slot Hero kosong tidak mengambil foto dari galeri umum atau slot lain');
unlink($temp);unset($GLOBALS['aida_test_upload_file']);
$fixture=lpg_lp_fixture('aida');$context=$fixture['campaign'];$old=Frameworks::fingerprint($context);$context['aida_hero_image_id']=123;
lpg_assert_true($old!==Frameworks::fingerprint($context),'mengganti pilihan gambar membatalkan provenance gambar lama');
