<?php
error_reporting(E_ALL);define('ABSPATH',__DIR__);define('VWLB_TEXT_DOMAIN','x');define('ARRAY_A','ARRAY_A');
class WP_Error {public function __construct(public $code,public $message,public $data=null){}public function get_error_code(){return $this->code;}public function get_error_data(){return $this->data;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function __($s,$d=''){return $s;}
function absint($x){return abs((int)$x);}
function sanitize_key($x){return $x;}
function do_action(...$x){$GLOBALS['events'][]=$x;}
class VWLB_Helpers {
 static function error($c,$m,$status=500){return new WP_Error($c,$m,['status'=>$status]);}
 static function table($k){return 'wp_'.$k;}
 static function now(){return '2026-10-19 15:00:00';}
}
class DBStub {
 public $last_error='';public $update_fail=false;public $read_fail=true;
 public function update(...$x){return $this->update_fail?false:1;}
 public function prepare($q,...$x){return $q;}
 public function get_row(...$x){if($this->read_fail){$this->last_error='DB unavailable';return null;}return ['id'=>7,'version'=>2];}
}
$GLOBALS['wpdb']=new DBStub();$GLOBALS['events']=[];
require __DIR__.'/../video-wall-and-live-broadcasting/includes/class-vwlb-repository.php';
$out=VWLB_Repository::update_versioned('videos',7,1,['status'=>'published']);
if(!is_wp_error($out)||$out->get_error_code()!=='vwlb_updated_row_unverifiable'||($out->get_error_data()['status']??null)!==503||!VWLB_Repository::read_failed())throw new Exception('Postwrite DB failure needs typed 503');
$GLOBALS['wpdb']->read_fail=false;VWLB_Repository::reset_read_failure();
$out=VWLB_Repository::update_versioned('videos',7,1,['status'=>'published']);
if(is_wp_error($out)||($out['id']??0)!==7)throw new Exception('Confirmed update must succeed');
echo "R272 post-update read 2/2 PASS\n";
