<?php
error_reporting(E_ALL);
define('ABSPATH',__DIR__);define('VWLB_TEXT_DOMAIN','x');define('HOUR_IN_SECONDS',3600);
class WP_Error {public function __construct(public $code,public $message,public $data=null){} public function get_error_code(){return $this->code;} public function get_error_data(){return $this->data;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function __($s,$d=''){return $s;}
function absint($v){return abs((int)$v);}
function get_current_user_id(){return 8;}
function sanitize_key($v){return (string)$v;}
function rest_url($v){return 'https://example.com/'.$v;}
function apply_filters($k,$v,...$p){return $v;}
class VWLB_Contracts {const CAP_SUBMIT='submit';const MEDIA_CLASSES=['video'];const CANONICAL_API_NAMESPACE='vwlb/v1';}
class VWLB_Security {static function can(...$a){return true;}static function rate_limit(...$a){return true;}}
class VWLB_Providers {static function all(){return ['local'=>true];}}
class VWLB_Helpers {
 static function enum($v,$valid,$default){return in_array($v,$valid,true)?$v:$default;}
 static function text($v,$limit=255){return (string)$v;}
 static function now(){return '2026-10-19 15:00:00';}
 static function public_id($prefix){return $prefix.'_mock';}
 static function json_encode($x){return json_encode($x);}
 static function table($x){return 'wp_'.$x;}
 static function error($c,$m,$status=500){return new WP_Error($c,$m,['status'=>$status]);}
 static function audit(...$args){$GLOBALS['audits']++;}
}
class DBStub {public $insert_id=99;public $failed=true;public function insert($table,$values){return $this->failed?false:1;}}
$GLOBALS['wpdb']=new DBStub();$GLOBALS['audits']=0;
require __DIR__.'/../video-wall-and-live-broadcasting/includes/class-vwlb-media.php';
$a=VWLB_Media::initiate(['media_class'=>'video','mime'=>'video/mp4','bytes'=>100]);
if(!is_wp_error($a)||$a->get_error_code()!=='vwlb_database_error'||$GLOBALS['audits']!==0)throw new Exception('Failed INSERT must not succeed/audit');
$GLOBALS['wpdb']->failed=false;$GLOBALS['wpdb']->insert_id=107;$GLOBALS['audits']=0;
$b=VWLB_Media::initiate(['media_class'=>'video','mime'=>'video/mp4','bytes'=>100]);
if(is_wp_error($b)||$b['id']!==107||$GLOBALS['audits']!==1)throw new Exception('Successful INSERT must return new ID and audit');
echo "R270 media INSERT 2/2 PASS\n";
