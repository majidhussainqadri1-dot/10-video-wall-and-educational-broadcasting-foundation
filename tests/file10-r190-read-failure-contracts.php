<?php
/** Ten negative and two preservation controls for File 10 direct owner reads. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
define('VWLB_TEXT_DOMAIN','vwlb-test');
class WP_Error {
 public function __construct(private string $code,private string $message,private array $data=[]) {}
 public function get_error_code(){return $this->code;}
 public function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($t,$d=''){return $t;}
function absint($n){return abs((int)$n);}
function is_user_logged_in(){return true;}
function get_current_user_id(){return 45;}
function sanitize_title($s){return (string)$s;}
function sanitize_key($s){return (string)$s;}
function apply_filters($n,$v,...$args){return $v;}
class VWLB_Contracts {const CAP_PUBLISH='vwlb_publish_video';const CAP_SUBMIT='vwlb_submit_video';const CAP_REVIEW='vwlb_review_media';const VISIBILITIES=['public','private'];}
class VWLB_Helpers {
 public static function error($c,$m,$s=500){return new WP_Error($c,$m,['status'=>$s]);}
 public static function text($v,$l=255){return (string)$v;}
 public static function enum($v,$choices,$fallback){return in_array($v,$choices,true)?$v:$fallback;}
 public static function table($t){return 'wp_vwlb_'.$t;}
 public static function now(){return '2026-10-19 07:00:00';}
 public static function public_id($p){return $p.'_mock';}
 public static function json_encode($v){return json_encode($v);}
 public static function duration_seconds($v){return 120;}
 public static function datetime($v){return $v;}
 public static function textarea($v){return $v;}
 public static function audit(...$a){return true;}
 public static function outbox(...$a){return true;}
}
class VWLB_Security {public static function can(...$a){return true;}public static function can_view(...$a){return true;}}
class VWLB_Providers {
 public static function all(){return ['local'=>true];}
 public static function normalize($p,$url){return ['provider'=>$p,'source_url'=>$url,'embed_url'=>''];}
 public static function get($p){return null;}
}
class VWLB_DB {public static function transaction($callback){return $callback();}public static function read_var(...$a){return 1;}}
class VWLB_Repository {
 public static $failed=false,$count=0,$fail_on=1,$not_found=false,$asset_unready=false;
 public static function reset_read_failure(){self::$failed=false;self::$count=0;}
 public static function read_failed(){return self::$failed;}
 public static function find($entity,$id,$lock=false) {
   self::$count++;
   if(self::$count===self::$fail_on){self::$failed=true;return null;}
   if(self::$not_found)return null;
   return ['id'=>7,'public_id'=>'vid_mock','title'=>'x','version'=>1,'language'=>'en-US','rights_status'=>'verified',
    'consent_status'=>'not_patient_case','asset_id'=>13,
    'status'=>($entity==='media_assets'&&self::$asset_unready?'processing':'published'),
    'scan_status'=>'passed','visibility'=>'public','provider'=>'local','duration_seconds'=>120,'owner_id'=>45];
 }
 public static function video_bundle($id){return self::find('videos',$id);}
 public static function update_versioned(...$a){return ['version'=>2];}
}
class FakeDB {
 public $writes=0,$insert_id=7;
 public function insert(...$a){$this->writes++;return 1;}
 public function update(...$a){$this->writes++;return 1;}
 public function delete(...$a){$this->writes++;return 1;}
}
$GLOBALS['wpdb']=new FakeDB();
require __DIR__.'/../video-wall-and-live-broadcasting/includes/class-vwlb-videos.php';
$cases=[
 'playlist channel'=>fn()=>VWLB_Videos::create_playlist(['title'=>'x','channel_id'=>13]),
 'playlist child video'=>fn()=>VWLB_Videos::set_playlist_items('pl_mock',[12],1),
 'new video asset'=>fn()=>VWLB_Videos::create(['title'=>'x','asset_id'=>13],'idem'),
 'new video channel'=>fn()=>VWLB_Videos::create(['title'=>'x','channel_id'=>13],'idem'),
 'publish video'=>fn()=>VWLB_Videos::publish('vid_mock',1),
 'publish asset gate'=>fn()=>VWLB_Videos::publication_gate(['title'=>'x','language'=>'en-US','rights_status'=>'verified','consent_status'=>'not_patient_case','asset_id'=>13]),
 'caption video'=>fn()=>VWLB_Videos::add_caption('vid_mock',['content'=>'WEBVTT']),
 'playback video'=>fn()=>VWLB_Videos::playback('vid_mock'),
 'watch progress'=>fn()=>VWLB_Videos::progress('vid_mock',25,120),
 'video reaction'=>fn()=>VWLB_Videos::interact('vid_mock','like')
];
$count=0;
foreach($cases as $name=>$fn){
 VWLB_Repository::reset_read_failure();VWLB_Repository::$fail_on=$name==='playlist child video'?3:1;
 $answer=$fn();
 if(!is_wp_error($answer)||($answer->get_error_data()['status']??0)!==503)throw new RuntimeException("Unexpected read error outcome for $name");
 $count++;
}
VWLB_Repository::reset_read_failure();VWLB_Repository::$fail_on=0;VWLB_Repository::$not_found=true;
$missing=VWLB_Videos::publish('missing',1);
if(!is_wp_error($missing)||($missing->get_error_data()['status']??0)!==404)throw new RuntimeException('True absence must preserve 404');
VWLB_Repository::reset_read_failure();VWLB_Repository::$not_found=false;VWLB_Repository::$asset_unready=true;
$unready=VWLB_Videos::publication_gate(['title'=>'x','language'=>'en-US','rights_status'=>'verified','consent_status'=>'not_patient_case','asset_id'=>13]);
if(!is_wp_error($unready)||($unready->get_error_data()['status']??0)!==422)throw new RuntimeException('Genuinely unready media must preserve 422');
echo "File10 R190 read-integrity $count fault cases + 2 valid state controls PASS\n";
