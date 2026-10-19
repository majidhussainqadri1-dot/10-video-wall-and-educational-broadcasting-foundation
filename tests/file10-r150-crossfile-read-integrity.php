<?php
/** R150–R189: verify fail-closed owner read semantics for File11, File17, and playlist lock. */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('VWLB_TEXT_DOMAIN', 'vwlb-test');
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code, $message, $data = null) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text,$domain=null) { return $text; }
function absint($n) { return abs((int)$n); }
class VWLB_Helpers {
    public static function error($code,$message,$status=500) { return new WP_Error($code,$message,array('status'=>$status)); }
    public static function json($v){return array();}
}
class VWLB_Contracts { const CAP_PUBLISH='vwlb_publish_video'; }
class VWLB_Security {
    public static function can($cap,$obj=null,$purpose=''){ return true; }
    public static function can_view($record,$purpose=''){return true;}
}
class VWLB_DB {
    public static function transaction($cb){return $cb();}
}
class VWLB_Repository {
    public static $failed=false; public static $count=0; public static $fail_on=0; public static $not_found=false;
    public static function reset_read_failure(){self::$failed=false;self::$count=0;}
    public static function read_failed(){return self::$failed;}
    public static function find($entity,$id,$for_update=false){self::$count++;if(self::$count===self::$fail_on){self::$failed=true;return null;}return self::$not_found?null:array('id'=>12,'public_id'=>'vid_abcdef','version'=>0,'visibility'=>'public','status'=>'live','chat_policy_json'=>'{}');}
    public static function video_bundle($id){self::$count++;if(self::$count===self::$fail_on){self::$failed=true;return null;}return null;}
}
class FakeDB {public $writes=0;public function delete(...$args){$this->writes++;return 0;}public function insert(...$args){$this->writes++;return 1;}}
$GLOBALS['wpdb']=new FakeDB();
$root=dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/';
require $root.'class-vwlb-videos.php';
require $root.'class-vwlb-extensions.php';
require $root.'class-vwlb-integrations.php';
function expect_error($value,$code,$status){if(!is_wp_error($value)||$value->get_error_code()!==$code||($value->get_error_data()['status']??0)!==$status){fwrite(STDERR,'FAIL expected '.$code.':'.$status.' got '.(is_wp_error($value)?$value->get_error_code():get_debug_type($value))."\n");exit(1);}}
VWLB_Repository::$fail_on=2; VWLB_Repository::$not_found=false;
expect_error(VWLB_Videos::set_playlist_items('pl_abc',array(5),0),'vwlb_playlist_lock_unavailable',503);
if($GLOBALS['wpdb']->writes!==0){fwrite(STDERR,"FAIL destructive writes without a verified lock\n");exit(1);}
VWLB_Repository::$fail_on=1; VWLB_Repository::reset_read_failure();
expect_error(VWLB_Videos::set_playlist_items('pl_abc',array(5),0),'vwlb_playlist_read_unavailable',503);
VWLB_Repository::$fail_on=0; VWLB_Repository::$not_found=true; VWLB_Repository::reset_read_failure();
expect_error(VWLB_Videos::set_playlist_items('pl_abc',array(5),0),'vwlb_playlist_missing',404);
VWLB_Repository::$not_found=false; VWLB_Repository::$fail_on=1; VWLB_Repository::reset_read_failure();
expect_error(VWLB_Extensions::reels_media_contract(null,'vid_abc'),'vwlb_reel_source_unavailable',503);
VWLB_Repository::$fail_on=1; VWLB_Repository::reset_read_failure();
expect_error(VWLB_Extensions::media_contract(null,'vid_abc'),'vwlb_media_source_unavailable',503);
VWLB_Repository::$fail_on=1; VWLB_Repository::reset_read_failure();
expect_error((new VWLB_Integrations())->file17_live_context(null,'live_abc'),'vwlb_live_context_unavailable',503);
VWLB_Repository::$fail_on=0; VWLB_Repository::$not_found=true; VWLB_Repository::reset_read_failure();
expect_error((new VWLB_Integrations())->file17_live_context(null,'live_abc'),'vwlb_not_found',404);
echo "R150–R189: seven cross-file/playlist DB-negative cases PASS\n";
