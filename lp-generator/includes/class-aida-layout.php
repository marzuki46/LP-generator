<?php
/** AIDA design 1: native HTML, stable slots, no fabricated business data. @package LPG */
declare(strict_types=1);
namespace LPG;
defined( 'ABSPATH' ) || exit;
final class AidaLayout {
 public static function render(array $data,array $campaign,array $images=array()): string {
  DesignContract::validate_images($images,$campaign);
  $slots=array_column($images,null,'slot_id');
  $sections=$data['sections'] ?? array();
  $en='en'===($campaign['language'] ?? 'id');
  $affiliate='affiliate'===($campaign['offer_type'] ?? '');
  $button=LandingPage::button((string)($campaign['offer_url'] ?? ''),(string)($data['cta_text'] ?? $campaign['cta'] ?? ''),$affiliate);
  $brand=trim(implode(' · ',array_filter(array((string)($campaign['brand'] ?? ''),(string)($campaign['city'] ?? '')))));
  $has_brief=!empty($campaign['benefits']) || isset($slots['aida-hero-visual']);
  $html='<div class="lpg-lp lpg-lp--aida lpg-aida" data-design-id="aida-1">';
  $html.='<nav class="lpg-aida__nav" aria-label="'.esc_attr__('Navigasi halaman','lp-generator').'"><span data-lpg-bind="brand">'.LandingPage::text($brand).'</span><div><a href="#aida-interest">'.($en?'Your needs':esc_html__('Kebutuhan','lp-generator')).'</a><a href="#aida-desire">'.($en?'Benefits':esc_html__('Manfaat','lp-generator')).'</a><a href="#aida-action">'.($en?'Next step':esc_html__('Langkah berikutnya','lp-generator')).'</a></div></nav>';
  $html.='<header id="aida-attention" class="lpg-aida__hero'.($has_brief?'':' lpg-aida__hero--single').'" data-section-id="aida-attention" data-step="1"><div class="lpg-aida__hero-copy"><p class="lpg-lp__eyebrow" data-lpg-bind="offer">'.LandingPage::text($campaign['offer_name'] ?? '').'</p><h1 data-lpg-bind="title">'.LandingPage::text($data['title'] ?? '').'</h1>';
  if($affiliate) {$html.='<p class="lpg-lp__disclosure">'.($en?'This page contains affiliate links. We may earn a commission at no additional cost to you. Check prices and terms on the official website.':esc_html__('Halaman ini memuat tautan afiliasi. Kami dapat menerima komisi tanpa biaya tambahan untuk Anda. Periksa harga dan ketentuan pada situs resmi.','lp-generator')).'</p>';}
  $html.='<div class="lpg-lp__intro" data-lpg-bind="intro">'.LandingPage::paragraphs($data['intro'] ?? '').'</div><div class="lpg-aida__cta" data-lpg-bind="cta">'.$button.'</div></div>';
  $html.='<aside class="lpg-aida__brief"'.($has_brief?'':' hidden').'><div data-image-slot="aida-hero-visual">'.self::image($slots,'aida-hero-visual',true).'</div><p class="lpg-aida__label">'.($en?'What is included':esc_html__('Yang Anda dapatkan','lp-generator')).'</p><ul data-lpg-bind="benefits">';
  foreach(array_slice((array)($campaign['benefits'] ?? array()),0,6) as $benefit) {$html.='<li>'.LandingPage::text($benefit).'</li>';}
  $html.='</ul></aside></header>';
  foreach(array(1,2) as $index) {
   $section=$sections[$index] ?? array();$contract=DesignContract::aida()[$index];
   $html.='<section id="'.$contract['id'].'" class="lpg-aida__section lpg-aida__section--'.($index===1?'interest':'desire').'" data-section-id="'.$contract['id'].'" data-step="'.($index+1).'">';
   $html.='<div class="lpg-aida__section-heading"><h2 data-lpg-bind="'.$contract['id'].'.heading">'.LandingPage::text($section['heading'] ?? '').'</h2></div><div class="lpg-aida__section-content"><div data-lpg-bind="'.$contract['id'].'.body">'.LandingPage::paragraphs($section['body'] ?? '').'</div>';
   if(!empty($section['bullets'])) {$html.='<ul class="lpg-lp__points">';foreach($section['bullets'] as $bullet){$html.='<li>'.LandingPage::text($bullet).'</li>';}$html.='</ul>';}
   $html.='<ul class="lpg-lp__cards" data-lpg-bind="'.$contract['id'].'.cards">';
   foreach((array)($section['cards'] ?? array()) as $card){if(is_array($card)){$html.='<li><h3>'.LandingPage::text($card['title'] ?? '').'</h3>'.LandingPage::paragraphs($card['body'] ?? '').'</li>';}}
   $html.='</ul><div data-image-slot="'.$contract['image_slot'].'">'.self::image($slots,$contract['image_slot']).'</div></div></section>';
  }
  if(!empty($data['faq'])) {$html.='<section class="lpg-aida__faq"><h2>'.($en?'Before you decide':esc_html__('Sebelum Anda memutuskan','lp-generator')).'</h2><div data-lpg-bind="faq">';foreach($data['faq'] as $pair){$html.='<details><summary>'.LandingPage::text($pair['q'] ?? '').'</summary>'.LandingPage::paragraphs($pair['a'] ?? '').'</details>';}$html.='</div></section>';}
  $section=$sections[3] ?? array();
  $html.='<section id="aida-action" class="lpg-aida__action" data-section-id="aida-action" data-step="4"><div><h2 data-lpg-bind="aida-action.heading">'.LandingPage::text($section['heading'] ?? '').'</h2><div data-lpg-bind="aida-action.body">'.LandingPage::paragraphs($section['body'] ?? '').'</div></div><div class="lpg-aida__action-box"><p class="lpg-lp__price" data-lpg-bind="price">'.LandingPage::text($campaign['price'] ?? '').'</p><div data-lpg-bind="guarantee">'.LandingPage::paragraphs($campaign['guarantee'] ?? '').'</div><div data-lpg-bind="cta">'.$button.'</div></div></section>';
  $html.='<!-- lpg-related:start --><!-- lpg-related:end --></div>';
  return "<!-- wp:html -->\n".$html."\n<!-- /wp:html -->";
 }
 private static function image(array $slots,string $id,bool $hero=false): string {
  return isset($slots[$id]) ? LandingPage::images(array($slots[$id]),$hero) : '';
 }
}
