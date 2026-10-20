<?php
/** R310: media-validation exceptions may never trigger processing. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__); define('VWLB_TEXT_DOMAIN','file10-test');
define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60);
class WP_Error {
 private string $code; private string $message; private array $data;
 public function __construct(string $code,string $message,array $data=[]){$this->code=$code;$this->message=$message;$this->data=$data;}
 public function get_error_code(){return $this->code;}
 public function get_error_message(){return $this->message;}
 public function get_error_data(){return $this->data;}
}
function is_wp_error($x){return $x instanceof WP_Error;}
function __($x,$domain=''){return $x;}
function sanitize_key($value){return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/','',(string)$value));}
function do_action($event,...$args){$GLOBALS['signals'][]=[$event,...$args];}
function apply_filters($event,$value,...$args){return $value;}
class VWLB_Helpers {
 static function table($x){return 'wp_'.$x;}
 static function error($code,$msg,$status=500,$extra=[]){return new WP_Error($code,$msg,['status'=>$status]+$extra);}
 static function now(){return '2026-10-20 09:00:00';}
 static function json($value){return is_array($value)?$value:[];}
 static function json_encode($value){return json_encode($value);}
 static function audit(...$args){$GLOBALS['audit'][]=$args;}
 static function outbox(...$args){$GLOBALS['outbox'][]=$args;}
}
class FakeDB{
 public int $updates=0; public int $queries=0;
 public function update($table,$data,$where){$this->updates++;return 1;}
 public function query($query){$this->queries++;return 1;}
 public function prepare($query,...$args){return $query;}
}
class VWLB_Repository {
 static function find($table,$id,$lock=false){return ['id'=>7,'public_id'=>'asset_abc','media_class'=>'video','source_url'=>'https://example.test/asset.mp4','bytes'=>999,'version'=>1,'provider'=>'local','derivatives_json'=>'{}','status'=>'uploaded'];}
 static function read_failed(){return false;}
}
class VWLB_Media {
 static string $mode='throw';
 static function verify_magic($asset){if(self::$mode==='throw')throw new RuntimeException('validator exception');return self::$mode==='accept';}
}
class DummyProvider {public function process_asset($asset,$job){$GLOBALS['processing_called']++;return ['status'=>'ready','derivatives'=>['mp4'=>'https://example.test/asset.mp4']];}}
class VWLB_Providers {static function get($id){return new DummyProvider();}}
class VWLB_Observability {static function provider_available($id,$op){return true;}static function record_provider(...$args){}}
class VWLB_DB {static function transaction($fn){return $fn();}}
$GLOBALS['wpdb']=new FakeDB();$GLOBALS['signals']=[];$GLOBALS['audit']=[];$GLOBALS['outbox']=[];$GLOBALS['processing_called']=0;
require __DIR__.'/../video-wall-and-live-broadcasting/includes/class-vwlb-jobs.php';
$job=['id'=>12,'public_id'=>'job_12','asset_id'=>7,'job_type'=>'verify_and_process','attempts'=>1,'max_attempts'=>3,'locked_by'=>'worker_abc','status'=>'running','input_json'=>'{}'];
(new ReflectionMethod('VWLB_Jobs','run_job'))->invoke(null,$job);
if($GLOBALS['processing_called']!==0)throw new RuntimeException('provider processed after validator exception');
if($GLOBALS['wpdb']->updates!==0)throw new RuntimeException('asset mutated after validator exception');
if($GLOBALS['wpdb']->queries!==1)throw new RuntimeException('validator exception did not enqueue retry');
$s=$GLOBALS['signals'][0]??[];
if(($s[0]??null)!=='vwlb_operational_failure'||($s[2]??null)!=='vwlb_asset_validation_exception')throw new RuntimeException('no operational exception signal');
VWLB_Media::$mode='reject';$GLOBALS['wpdb']=new FakeDB();$GLOBALS['processing_called']=0;$GLOBALS['signals']=[];
(new ReflectionMethod('VWLB_Jobs','run_job'))->invoke(null,$job);
if($GLOBALS['processing_called']!==0||$GLOBALS['wpdb']->updates!==0||$GLOBALS['wpdb']->queries!==1)throw new RuntimeException('explicit rejected validation advanced');
VWLB_Media::$mode='accept';$GLOBALS['wpdb']=new FakeDB();$GLOBALS['processing_called']=0;$GLOBALS['signals']=[];
(new ReflectionMethod('VWLB_Jobs','run_job'))->invoke(null,$job);
if($GLOBALS['processing_called']!==1||$GLOBALS['wpdb']->updates!==2||$GLOBALS['wpdb']->queries!==1)throw new RuntimeException('successful validation regressed');
echo "R310: exception, rejection and accepted processing paths PASS\n";
