<?php
error_reporting(E_ALL);define('ABSPATH',__DIR__);define('VWLB_TEXT_DOMAIN','x');define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
class WP_Error {public function __construct(public $code,public $message,public $data=null){}public function get_error_code(){return $this->code;}public function get_error_message(){return $this->message;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function __($s,$d=''){return $s;}
function sanitize_key($s){return (string)$s;}
function do_action($event,...$rest){$GLOBALS['signals'][]=[$event,...$rest];}
class VWLB_Helpers {
 static function table($t){return 'wp_'.$t;}
 static function error($c,$msg,$status=500,$extra=[]){return new WP_Error($c,$msg,['status'=>$status]);}
 static function now(){return '2026-10-19 15:00:00';}
 static function audit(...$args){$GLOBALS['audits'][]=$args;}
}
class VWLB_Repository {static function find(...$args){$GLOBALS['repo_reads']++;return null;}static function read_failed(){return true;}}
class VWLB_Media {static function verify_magic(...$args){return false;}}
class FakeDb {public $queries=[];public function prepare($sql,...$args){return $sql;}public function query($sql){$this->queries[]=$sql;return 1;}}
$GLOBALS['wpdb']=new FakeDb();$GLOBALS['signals']=[];$GLOBALS['audits']=[];$GLOBALS['repo_reads']=0;
require __DIR__.'/../video-wall-and-live-broadcasting/includes/class-vwlb-jobs.php';
$job=['id'=>9,'asset_id'=>71,'job_type'=>'verify_and_process','attempts'=>5,'max_attempts'=>5,'locked_by'=>'worker_x','input_json'=>'{}'];
(new ReflectionMethod('VWLB_Jobs','run_job'))->invoke(null,$job);
if(($GLOBALS['audits'][0][6]['code']??'')!=='vwlb_processing_owner_read_failed'||count($GLOBALS['signals'])!==1||$GLOBALS['repo_reads']!==1)throw new Exception('Worker failed DB-outage classification');
echo "R271 worker DB read 1/1 PASS\n";
