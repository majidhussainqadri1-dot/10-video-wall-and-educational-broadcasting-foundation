<?php
/** File10 R230-R269: DB fault injection and valid-absence/provider controls. */
error_reporting(E_ALL & ~E_DEPRECATED);
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A'); define('VWLB_TEXT_DOMAIN','vwlb-test'); define('HOUR_IN_SECONDS',3600);
class WP_Error {
 private $code,$data;
 public function __construct($c,$m,$d=[]){$this->code=$c;$this->data=is_array($d)?$d:['status'=>$d];}
 public function get_error_code(){return $this->code;}
 public function get_error_data(){return $this->data;}
}
function is_wp_error($x){return $x instanceof WP_Error;}
function __($s,$domain=''){return $s;}
function absint($n){return abs((int)$n);}
function get_current_user_id(){return 19;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function current_user_can(...$a){return true;}
function apply_filters($tag,$val,...$args){return $val;}
function do_action($tag,...$args){$GLOBALS['events'][]=[$tag,...$args];}
function get_option($k,$d=false){return $d;}
function rest_url($x){return 'https://example.com/'.$x;}
class VWLB_Helpers {
 static function error($c,$m,$status=500,$data=[]){return new WP_Error($c,$m,['status'=>$status]+$data);}
 static function table($t){return 'wp_vwlb_'.$t;}
 static function now(){return '2026-10-19 15:00:00';}
 static function text($s,$n=191){return (string)$s;}
 static function enum($s,$a,$d){return in_array($s,$a,true)?$s:$d;}
 static function remote_url($url){return $url?'https://example.org/x':'';}
 static function json_encode($x){return json_encode($x);}
 static function public_id($prefix){return $prefix.'_testing';}
 static function duration_seconds($d){return absint($d);}
 static function audit(...$x){}
 static function outbox(...$x){}
}
class VWLB_Contracts {
 const CAP_SUBMIT='vwlb_submit';
 const MEDIA_CLASSES=['video','audio'];
 const CANONICAL_API_NAMESPACE='video-wall-live-broadcasting/v1';
}
class VWLB_Security {
 static function can(...$a){return true;}
 static function rate_limit(...$a){return true;}
}
class VWLB_Providers {static function all(){return ['local'=>true];}}
class VWLB_Repository {
 static $failed=true,$null=true;
 static function find($table,$id,$lock=false){
  return self::$null?null:['id'=>81,'public_id'=>'asset_mock','provider'=>'vimeo','status'=>'ended'];
 }
 static function read_failed(){return self::$failed;}
 static function update_versioned(...$a){return null;}
}
class VWLB_DB {static function transaction($f){return $f();}}
class FakeDB {
 public $last_error='',$insert_id=81,$writes=0,$results_calls=0,$last_values=[];
 public function insert($table,$vals,$types=[]){$this->writes++;$this->last_values=$vals;return true;}
 public function prepare($sql,...$args){return $sql;}
 public function get_results($sql,$mode=null){
  $this->results_calls++;
  if($this->results_calls===1)return [['id'=>41,'public_id'=>'vid_test','status'=>'scheduled','scheduled_at'=>'2025-01-01 00:00:00']];
  return [];
 }
}
$wpdb=new FakeDB();
$GLOBALS['wpdb']=$wpdb;
$GLOBALS['events']=[];
$root=__DIR__.'/../video-wall-and-live-broadcasting/includes/';
require $root.'class-vwlb-media.php';
require $root.'class-vwlb-jobs.php';
function must($predicate,$label){
 if(!$predicate){fwrite(STDERR,"FAIL File10 media integrity: $label\n");exit(1);}
 echo "PASS $label\n";
}
$e=VWLB_Media::complete('asset_abcdef',['source_url'=>'https://example.com/media']);
must(is_wp_error($e)&&($e->get_error_data()['status']??0)===503,'failed media owner read returns 503');
VWLB_Repository::$failed=false;
$e=VWLB_Media::complete('asset_abcdef',['source_url'=>'https://example.com/media']);
must(is_wp_error($e)&&($e->get_error_data()['status']??0)===404,'genuinely absent media returns 404');
VWLB_Repository::$failed=true;
$wpdb->writes=0;
$bad=VWLB_Media::enqueue(19,'verify_and_process');
must(!$bad && $wpdb->writes===0,'failed queue owner read cannot mutate');
VWLB_Repository::$failed=false;
$wpdb->writes=0;
$none=VWLB_Media::enqueue(19,'verify_and_process');
must(!$none && $wpdb->writes===0,'missing owner cannot queue job');
VWLB_Repository::$null=false;
$wpdb->writes=0;
$valid=VWLB_Media::enqueue(19,'verify_and_process');
must($valid===81 && $wpdb->writes===1 && ($wpdb->last_values['provider']??'')==='vimeo','valid media queues exact canonical provider');
VWLB_Repository::$null=true;
VWLB_Repository::$failed=true;
$wpdb->results_calls=0;
$GLOBALS['events']=[];
VWLB_Jobs::reconcile();
$fail=array_filter($GLOBALS['events'],fn($x)=>$x[0]==='vwlb_operational_failure'&&$x[1]==='scheduled_publish');
must(count($fail)>0,'scheduled-publication lock DB failure is surfaced');
VWLB_Repository::$failed=false;
$wpdb->results_calls=0;
$GLOBALS['events']=[];
VWLB_Jobs::reconcile();
$fail=array_filter($GLOBALS['events'],fn($x)=>$x[0]==='vwlb_operational_failure'&&$x[1]==='scheduled_publish');
must(count($fail)===0,'genuine scheduled-row absence is not false DB failure');
echo "File10 R230-R269 seven PHP runtime controls PASS\n";
