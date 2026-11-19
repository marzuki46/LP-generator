<?php
/** Queue response and startup failure regressions. @package LPG */
declare(strict_types=1);
use LPG\Database;
use LPG\Generator;
$old_db=$GLOBALS['wpdb'];
$GLOBALS['wpdb']=new class {
 public string $prefix='wp_';public int $insert_id=0;public bool $fail=false;public array $rows=array();
 public function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[ds]/',static function($m)use(&$i,$args){$value=$args[$i++];return $m[0]==='%s'?"'".$value."'":(string)(int)$value;},$sql);}
 public function get_var($sql){$rows=$this->rows;if(preg_match('/campaign_id = (\d+)/',$sql,$m))$rows=array_filter($rows,static fn($r)=>$r['campaign_id']==(int)$m[1]);if(preg_match("/status = '([^']+)'/",$sql,$m))$rows=array_filter($rows,static fn($r)=>$r['status']===$m[1]);return str_contains($sql,'MIN(run_at)')?($rows?min(array_column($rows,'run_at')):null):count($rows);}
 public function insert(...$args){if($this->fail)return false;++$this->insert_id;return 1;}
};
$future=gmdate('Y-m-d H:i:s',time()+600);
$GLOBALS['wpdb']->rows=array(array('campaign_id'=>1159,'status'=>'running','run_at'=>$future),array('campaign_id'=>1159,'status'=>'done','run_at'=>$future));
$result=Database::queue_snapshot(1159);
lpg_assert_true(!$result['done'],'antrean belum selesai jika masih ada job running meskipun queued kosong');
lpg_assert_same(1,$result['running'],'jumlah worker running terlihat pada respons');
$GLOBALS['wpdb']->rows[0]['status']='queued';
lpg_assert_same(strtotime($future.' UTC'),Database::queue_snapshot(1159)['next_run_at'],'waktu tunggu antrean dikirim sebagai UTC timestamp');
$GLOBALS['wpdb']->rows[]=array('campaign_id'=>42,'status'=>'queued','run_at'=>gmdate('Y-m-d H:i:s',time()+1));
lpg_assert_same(strtotime($future.' UTC'),Database::queue_snapshot(1159)['next_run_at'],'jadwal campaign lain tidak mencampuri respons campaign ini');
$GLOBALS['wpdb']->rows[0]['status']='done';
lpg_assert_true(Database::queue_snapshot(1159)['done'],'seluruh job selesai menghasilkan done true');
lpg_assert_true(!Database::queue_snapshot(999)['done'],'antrean kosong tidak diklaim berhasil selesai');
$GLOBALS['wpdb']->fail=true;
funnel_throws(static fn()=>Database::insert_job(array('campaign_id'=>1159,'kind'=>'generate_lp')),'gagal menyimpan job tidak dilaporkan sebagai antrean berhasil dibuat');
funnel_fixture(1159,'Campaign tersimpan','','lpgen_campaign');
unset($GLOBALS['lpg_test_options']);
$result=Generator::start(1159);
lpg_assert_true(!$result['ok'] && $result['jobs']===0,'konfigurasi AI kosong berhenti dengan pesan sebelum membuat antrean');
$GLOBALS['wpdb']=$old_db;$GLOBALS['funnel_posts']=array();$GLOBALS['funnel_meta']=array();
