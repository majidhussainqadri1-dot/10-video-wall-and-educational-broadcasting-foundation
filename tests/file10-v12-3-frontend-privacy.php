<?php
/**
 * V12.3 File10 frontend privacy test — synthetic WordPress data/HTML.
 * Covers source behavior, NOT live deployment, streaming provider, or Staging.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('VWLB_TEXT_DOMAIN', 'vwlb');
class WP_Error { public function __construct(public string $code='error') {} }
function is_wp_error($x) { return $x instanceof WP_Error; }
function __($s,$domain='') { return (string)$s; }
function esc_html__($s,$domain='') { return htmlspecialchars((string)$s,ENT_QUOTES); }
function esc_attr__($s,$domain='') { return htmlspecialchars((string)$s,ENT_QUOTES); }
function esc_html_e($s,$domain='') { echo esc_html__($s,$domain); }
function esc_attr_e($s,$domain='') { echo esc_attr__($s,$domain); }
function esc_html($s) { return htmlspecialchars((string)$s,ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string)$s,ENT_QUOTES); }
function esc_url($s) { return htmlspecialchars((string)$s,ENT_QUOTES); }
function esc_url_raw($s,$protocols=[]) { return (string)$s; }
function home_url($s='') { return 'https://example.test'.$s; }
function rest_url($s='') { return 'https://example.test/wp-json/'.$s; }
function wp_enqueue_style($s) {}
function wp_enqueue_script($s) {}
function get_query_var($key) {
    return ['vwlb_video_id'=>'vid_demo123', 'vwlb_channel_slug'=>'private-studies'][$key]??'';
}
function wp_kses_post($s) { return (string)$s; }
function wpautop($s) { return '<p>'.(string)$s.'</p>'; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 77; }
function status_header($status) { $GLOBALS['latest_http_status']=(int)$status; }
function wp_strip_all_tags($s) { return strip_tags((string)$s); }
function sanitize_title($s) { return (string)$s; }
function get_option($key,$fallback=null) {return $key==='date_format'?'Y-m-d':($key==='time_format'?'H:i':$fallback);}
function get_date_from_gmt($date,$format='') { return (string)$date; }

class VWLB_Helpers {
    public static function is_public_id($id) {return preg_match('/^[a-z][a-z0-9]*_[a-z0-9]+$/D',(string)$id)===1;}
    public static function json($value) {if(is_array($value))return $value;return json_decode((string)$value,true)?:[];}
    public static function no_cache_private() {}
    public static function table($entity){return 'wp_vwlb_'.$entity;}
}
class VWLB_Contracts {
    const CANONICAL_API_NAMESPACE='sabri/v2/file-10';
}
class VWLB_Repository {
    public static array $video=[];
    public static array $asset=[];
    public static bool $readError=false;
    public static string $failOn='';
    public static function find($entity,$id) {
        if(self::$failOn===$entity)self::$readError=true;
        if($entity==='videos')return self::$video;
        if($entity==='media_assets')return self::$asset;
        return null;
    }
    public static function read_failed() {return self::$readError;}
    public static function reset_read_failure() {self::$readError=false;}
    public static function browse_videos($args=[]) {return ['items'=>[],'next_cursor'=>''];}
}
class VWLB_Videos {
    public static function playback($public_id) {return $GLOBALS['mock_playback'];}
}
class VWLB_Extensions {
    public static function chapters($entity,$id) {return [];}
}
class VWLB_R109_Rights_Consent_Replay_Guard {
    public static function policy_read_failed() {return !empty($GLOBALS['mock_policy_read_failed']);}
}
class VWLB_Security {
    public static function can_view($row,$purpose='history') {
        if(!empty($GLOBALS['mock_policy_read_outage'])){
            $GLOBALS['mock_policy_read_failed']=true;
            return false;
        }
        return ($row['status']??'')==='published'
            && ($row['visibility']??'')==='public'
            && ($row['rights_status']??'')!=='revoked'
            && ($row['consent_status']??'')!=='withdrawn'
            && empty($row['deleted_at']);
    }
}
class VWLB_DB {
    public static function read_results($query,$context) {
        if(str_contains((string)$query,'wp_vwlb_live_events')) {
            $GLOBALS['wall_live_sql']=(string)$query;
            $rows=$GLOBALS['mock_live_rows']??[];
            if(str_contains((string)$query,'kill_switch=0')) {
                return array_values(array_filter($rows,static fn($row)=>empty($row['kill_switch'])));
            }
            return $rows;
        }
        return $GLOBALS['mock_history_rows']??[];
    }
    public static function read_row($query,$context) {
        $GLOBALS['channel_sql']=$query;
        // This fake DB faithfully applies the public+not-deleted predicate.
        if(str_contains($query,"visibility='public'") && !str_contains($query,'deleted_at')){
            return (($GLOBALS['mock_channel']['visibility']??'')==='public')
                ? $GLOBALS['mock_channel'] : null;
        }
        return $GLOBALS['mock_channel'];
    }
}
class File10PrivacyWpdb {
    public function prepare($sql,...$args) {return (string)$sql;}
}
$wpdb=new File10PrivacyWpdb();
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-frontend.php';
$checks=0;
function check_privacy($passed,$label) {
    global $checks;
    ++$checks;
    if(!$passed){fwrite(STDERR,"F10 FRONTEND PRIVACY FAIL: ".$label."\n");exit(1);}
}
$frontend=new VWLB_Frontend();
$low='https://private.example.test/raw-low.mp4';
VWLB_Repository::$video=[
  'id'=>18,'public_id'=>'vid_demo123','asset_id'=>19,'status'=>'published',
  'visibility'=>'private','consent_status'=>'documented','owner_id'=>77
];
VWLB_Repository::$asset=[
  'id'=>19,'status'=>'ready','scan_status'=>'passed',
  'derivatives_json'=>json_encode(['mp4_low'=>$low]),
];
$GLOBALS['mock_playback']=[
  'video'=>[
    'public_id'=>'vid_demo123','title'=>'Authorized lesson',
    'description'=>'Current content','visibility'=>'private','captions'=>[]
  ],
  'playback'=>['type'=>'file','url'=>'https://grant.example.test/temporary/authorized.mp4'],
  'session'=>['resume_seconds'=>15]
];
$html=$frontend->video();
check_privacy(str_contains($html,'https://grant.example.test/temporary/authorized.mp4'),'authorized standard playback retained');
check_privacy(!str_contains($html,$low),'private asset low-bandwidth URL cannot be exposed');

$GLOBALS['mock_playback']['video']['visibility']='public';
VWLB_Repository::$video['visibility']='public';
VWLB_Repository::$video['consent_status']='not_patient_case';
$html=$frontend->video();
check_privacy(str_contains($html,$low),'approved public non-case low-bandwidth rendition still works');

VWLB_Repository::$video['consent_status']='documented';
$html=$frontend->video();
check_privacy(!str_contains($html,$low),'patient-case video must not expose independent raw low URL');

VWLB_Repository::$video['consent_status']='not_patient_case';
VWLB_Repository::$failOn='media_assets';
VWLB_Repository::$readError=false;
$GLOBALS['latest_http_status']=0;
$html=$frontend->video();
check_privacy($GLOBALS['latest_http_status']===503 && !str_contains($html,$low),
    'failed secondary asset read is not presented as successful private/public source');
VWLB_Repository::$failOn='';
VWLB_Repository::$readError=false;

// Historic progress must not disclose metadata from now-restricted videos.
$GLOBALS['mock_history_rows']=[
  ['id'=>21,'public_id'=>'vid_private123','slug'=>'revoked','title'=>'Withdrawn patient case details',
   'progress_seconds'=>19,'visibility'=>'public','status'=>'restricted',
   'consent_status'=>'withdrawn','rights_status'=>'revoked','deleted_at'=>null,'published_at'=>'2026-11-01 00:00:00'],
  ['id'=>22,'public_id'=>'vid_allowed123','slug'=>'lesson','title'=>'Current authorized lesson',
   'progress_seconds'=>35,'visibility'=>'public','status'=>'published',
   'consent_status'=>'not_patient_case','rights_status'=>'verified','deleted_at'=>null,'published_at'=>'2026-11-01 00:00:00'],
];
$GLOBALS['latest_http_status']=0;
$history=$frontend->history();
check_privacy(!str_contains($history,'Withdrawn patient case details'),'revoked video title removed from private watch history');
check_privacy(str_contains($history,'Current authorized lesson'),'current authorized watch history retained');
$GLOBALS['mock_policy_read_outage']=true;
$GLOBALS['mock_policy_read_failed']=false;
$GLOBALS['latest_http_status']=0;
$history=$frontend->history();
check_privacy($GLOBALS['latest_http_status']===503
    && !str_contains($history,'Current authorized lesson'),
    'unverifiable consent/rights must fail closed before history is rendered');
$GLOBALS['mock_policy_read_outage']=false;
$GLOBALS['mock_policy_read_failed']=false;

$GLOBALS['mock_channel']=[
 'slug'=>'private-studies','title'=>'Confidential scholarly circle',
 'description'=>'A secret channel description','visibility'=>'private',
 'status'=>'active','deleted_at'=>null,'id'=>9
];
$channel=$frontend->channel();
check_privacy(!str_contains($channel,'Confidential scholarly circle')
    && !str_contains($channel,'secret channel description'),
    'active private channel slug does not expose private metadata');
check_privacy(str_contains((string)($GLOBALS['channel_sql']??''),"visibility='public'")
    && !str_contains((string)($GLOBALS['channel_sql']??''),'deleted_at'),
    'channel must bind public visibility without referencing nonexistent deleted_at column');
// Emergency-stopped Live sessions must never remain in public Wall discovery.
$GLOBALS['mock_live_rows']=[
 ['public_id'=>'live_active123','title'=>'Approved scheduled public broadcast',
  'scheduled_start'=>'2026-11-16 08:00:00','status'=>'scheduled','kill_switch'=>0],
 ['public_id'=>'live_stopped123','title'=>'Emergency-ended sensitive session',
  'scheduled_start'=>'2026-11-16 09:00:00','status'=>'live','kill_switch'=>1],
];
$wall=$frontend->wall();
check_privacy(!str_contains($wall,'Emergency-ended sensitive session'),
 'emergency kill must prevent stale Live-now public discovery');
check_privacy(str_contains($wall,'Approved scheduled public broadcast'),
 'eligible scheduled event remains discoverable');
check_privacy(str_contains((string)($GLOBALS['wall_live_sql']??''),'kill_switch=0'),
 'public live discovery must require active kill-switch state in canonical SQL');

echo "F10 FRONTEND PRIVACY PASS: {$checks} synthetic checks; no real WordPress/Live inferred.\n";
