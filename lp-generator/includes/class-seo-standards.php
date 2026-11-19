<?php
/** Crawlable, campaign-bound links and non-fabricated SEO metadata. @package LPG */
declare(strict_types=1);
namespace LPG;
defined('ABSPATH') || exit;
final class SeoStandards {
 public static function assert_internal(string $url): void {
  $target=parse_url($url);$home=parse_url(home_url());
  if (!is_array($target) || !in_array($target['scheme'] ?? '',array('http','https'),true) || strtolower($target['host'] ?? '')!==strtolower($home['host'] ?? '') || ($target['port'] ?? null)!==($home['port'] ?? null) || !empty($target['user']) || !empty($target['pass'])) {
   throw new \RuntimeException('Internal link harus menuju situs WordPress ini, bukan domain lain.');
  }
 }
 /** Update only the dedicated resource slot of a plugin-generated AIDA LP. */
 public static function refresh_related(int $campaign_id): void {
  $lp=null;$resources=array();
  foreach(ContentRepository::items($campaign_id) as $item) {
   if ('published'!==($item['status'] ?? '') || 'publish'!==get_post_status((int)($item['post_id'] ?? 0))) {continue;}
   if ('lp'===$item['kind']) {$lp=$item;continue;}
   if ('article'===$item['kind'] && in_array($item['stage'],array('tofu','mofu'),true)) {$resources[]=$item;}
  }
  if (!$lp || !$resources) {return;}
  $post=get_post((int)$lp['post_id']);
  if (!$post || !str_contains($post->post_content,'data-design-id="aida-1"') || (int)get_post_meta($post->ID,'_lpgen_campaign_id',true)!==$campaign_id) {return;}
  $list='';
  foreach(array_slice($resources,0,6) as $resource) {
   $url=(string)get_permalink((int)$resource['post_id']);self::assert_internal($url);
   $list.='<li><a href="'.esc_url($url).'">'.esc_html((string)$resource['title']).'</a></li>';
  }
  $nav='<nav class="lpg-aida__related" aria-label="'.esc_attr__('Panduan terkait','lp-generator').'"><h2>'.esc_html__('Pelajari sebelum menentukan pilihan','lp-generator').'</h2><ul>'.$list.'</ul></nav>';
  $updated=preg_replace_callback('/<!-- lpg-related:start -->.*?<!-- lpg-related:end -->/s',static fn(): string=>'<!-- lpg-related:start -->'.$nav.'<!-- lpg-related:end -->',$post->post_content,1);
  if ($updated!==$post->post_content) {wp_update_post(array('ID'=>$post->ID,'post_content'=>wp_kses_post($updated)));}
 }
 /** WordPress supplies canonical/sitemap; a dedicated SEO plugin keeps control of head metadata. */
 public static function document_title(string $title): string {
  if (''!==trim($title)) {return $title;}
  if (!is_singular(array('page','post')) || defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) {return $title;}
  $id=get_queried_object_id();$generated=(string)get_post_meta($id,'_lpgen_seo_title',true);
  return (int)get_post_meta($id,'_lpgen_item_id',true) && ''!==trim($generated) ? esc_html($generated) : $title;
 }
 public static function meta_description(): void {
  if (!is_singular(array('page','post')) || defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) {return;}
  $id=get_queried_object_id();
  if (!(int)get_post_meta($id,'_lpgen_item_id',true) || !apply_filters('lpgen_output_meta_description',true,$id)) {return;}
  $description=(string)get_post_meta($id,'_lpgen_meta_description',true);
  if (''!==trim($description)) {echo '<meta name="description" content="'.esc_attr($description).'" />'."\n";}
 }
}
