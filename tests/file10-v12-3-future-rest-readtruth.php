<?php
/** V12.3: real Future REST and Safety class failures cannot masquerade as 404/empty/200.
 * Pure isolated WordPress/DB mocks, not a deployed WordPress acceptance claim.
 */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('VWLB_TEXT_DOMAIN','vwlb-test');
define('VWLB_VERSION','1.2.25-rc1');
define('ARRAY_A','ARRAY_A');
final class WP_Error {
    public function __construct(private string $code,private string $message,private array $data=[]) {}
    public function get_error_code(): string {return $this->code;}
    public function get_error_data(): array {return $this->data;}
}
function is_wp_error($x): bool {return $x instanceof WP_Error;}
function __($text,$domain=''): string {return $text;}
function absint($x): int {return abs((int)$x);}
function sanitize_key($x): string {return (string)$x;}
function do_action(...$args): void {}
function rest_ensure_response($x): WP_REST_Response {return new WP_REST_Response($x);}
final class WP_REST_Response {
    private int $status=200;
    private array $headers=[];
    public function __construct(private $data){}
    public function set_status($status): void {$this->status=(int)$status;}
    public function get_status(): int {return $this->status;}
    public function get_data(){return $this->data;}
    public function header($key,$value): void {$this->headers[$key]=$value;}
    public function get_headers(): array {return $this->headers;}
}
final class WP_REST_Request implements ArrayAccess {
    public function __construct(private array $args=[],private array $json=[]){}
    public function offsetExists(mixed $offset): bool {return isset($this->args[$offset]);}
    public function offsetGet(mixed $offset): mixed {return $this->args[$offset]??null;}
    public function offsetSet(mixed $offset,mixed $value): void {$this->args[$offset]=$value;}
    public function offsetUnset(mixed $offset): void {unset($this->args[$offset]);}
    public function get_json_params(): array {return $this->json;}
}
final class VWLB_Helpers {
    public static function error($code,$message,$status=400,$extra=[]): WP_Error {
        return new WP_Error($code,$message,array_merge(['status'=>$status],$extra));
    }
    public static function table($key): string {return 'wp_vwlb_'.$key;}
    public static function is_public_id($v): bool {return (bool)preg_match('/^[a-z][a-z0-9]*_[a-z0-9]+$/',(string)$v);}
    public static function text($v,$length=64): string {return substr((string)$v,0,$length);}
    public static function enum($v,$list,$fallback){return in_array($v,$list,true)?$v:$fallback;}
    public static function json($s): array {return is_array($s)?$s:(json_decode((string)$s,true)?:[]);}
}
final class VWLB_Contracts {const CAP_BROADCAST='broadcast';const CAP_REVIEW='review';}
final class VWLB_Security {
    public static $viewerAllowed=true;
    public static function can(...$a): bool {return true;}
    public static function can_view($obj): bool {return self::$viewerAllowed && is_array($obj);}
    public static function claims(): array {return ['claims_version'=>'test'];}
}
final class VWLB_Repository {
    public static bool $fail=false,$missing=false,$failed=false;
    public static int $lookups=0;
    public static function reset_read_failure(): void {self::$failed=false;}
    public static function read_failed(): bool {return self::$failed;}
    public static function find($table,$id,$locked=false): ?array {
        ++self::$lookups;
        if(self::$fail){self::$failed=true;return null;}
        if(self::$missing)return null;
        return ['id'=>9,'public_id'=>(string)$id,'owner_id'=>3,'status'=>'published',
            'visibility'=>'public','published_at'=>'2026-11-14 11:00:00',
            'scheduled_start'=>'2026-11-14 11:00:00','version'=>1];
    }
}
final class MockDB {
    public string $last_error='';
    public bool $fail=false;
    public int $lookups=0;
    public function prepare($sql,...$args): string {return $sql;}
    public function get_var($sql) {
        ++$this->lookups;
        $this->last_error=$this->fail?'Simulated SQL outage':'';
        return $this->fail?null:9;
    }
    public function get_results($sql,$type=null): array {
        $this->last_error=$this->fail?'Simulated SQL outage':'';
        return [];
    }
    public function get_row($sql,$type=null): ?array {
        $this->last_error=$this->fail?'Simulated SQL outage':'';
        return null;
    }
}
$GLOBALS['wpdb']=new MockDB();
final class VWLB_DB {
    public static function read_var($sql,$context='database_read') {
        global $wpdb; $wpdb->last_error='';$v=$wpdb->get_var($sql);
        return $wpdb->last_error ? VWLB_Helpers::error('vwlb_database_read_failed','Query unavailable',503) : $v;
    }
    public static function read_results($sql,$context='database_read') {
        global $wpdb;$wpdb->last_error='';$v=$wpdb->get_results($sql,ARRAY_A);
        return $wpdb->last_error?VWLB_Helpers::error('vwlb_database_read_failed','Query unavailable',503):$v;
    }
}
final class VWLB_Future_Intelligence {
    public static int $writes=0;
    public static function upsert_source(...$args) {++self::$writes;return ['public_id'=>'src_test','version'=>1];}
    public static function upsert_scene(...$args) {++self::$writes;return ['public_id'=>'scene_test','version'=>1];}
    public static function index_transcript_segment(...$args) {++self::$writes;return ['items'=>[]];}
    public static function watermark_payload($payload,$type,$obj,$session=[]) {return ['mode'=>'off'];}
}
final class VWLB_Live {
    public static bool $failDuringState=false;
    public static function state($id) {
        if(self::$failDuringState){VWLB_Repository::$failed=true;return VWLB_Helpers::error('vwlb_not_found','Live unavailable',404);}
        return ['public_id'=>$id,'status'=>'live'];
    }
}
final class VWLB_Extensions {public static function live_extras($row) {return ['chat'=>false];}}
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php';
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-future-safety.php';
function expect($condition,$label): void {
    $GLOBALS['checks']=($GLOBALS['checks']??0)+1;
    if(!$condition){fwrite(STDERR,"File10 V12.3 Future read-truth FAIL: $label\n");exit(1);}
}
function expect_status($value,$status,$label): void {
    expect(is_wp_error($value)&&($value->get_error_data()['status']??0)===$status,$label.' status '.$status.' got '.(is_wp_error($value)?$value->get_error_code():get_debug_type($value)));
}
function reset_fixtures(): void {
    VWLB_Repository::$fail=false;
    VWLB_Repository::$missing=false;
    VWLB_Repository::$failed=false;
    VWLB_Live::$failDuringState=false;
    VWLB_Security::$viewerAllowed=true;
    $GLOBALS['wpdb']->fail=false;
    $GLOBALS['wpdb']->last_error='';
    VWLB_Future_Intelligence::$writes=0;
}
$api=new VWLB_Future_REST();

// Both real 404 and DB outage must be distinguished at watermark grant boundary.
reset_fixtures();VWLB_Repository::$fail=true;
expect_status($api->watermark_grant(new WP_REST_Request(['object_type'=>'video','id'=>'vid_test'])),503,'video owner SQL outage');
reset_fixtures();VWLB_Repository::$fail=true;
expect_status($api->watermark_grant(new WP_REST_Request(['object_type'=>'live','id'=>'live_test'])),503,'live owner SQL outage');
reset_fixtures();VWLB_Repository::$missing=true;
expect_status($api->watermark_grant(new WP_REST_Request(['object_type'=>'video','id'=>'missing'])),404,'true video absence');
reset_fixtures();VWLB_Security::$viewerAllowed=false;
expect_status($api->watermark_grant(new WP_REST_Request(['object_type'=>'video','id'=>'vid_test'])),404,'true access denial');
reset_fixtures();
$ok=$api->watermark_grant(new WP_REST_Request(['object_type'=>'video','id'=>'vid_test']));
expect($ok instanceof WP_REST_Response && $ok->get_status()===200 && ($ok->get_headers()['Cache-Control']??'')==='private, no-store','authorized watermark grant and no-store');

// First studio owner lookup must preserve outage truth.
reset_fixtures();VWLB_Repository::$fail=true;
expect_status($api->production_state(new WP_REST_Request(['id'=>'live_test'])),503,'studio owner SQL outage');
reset_fixtures();VWLB_Repository::$missing=true;
expect_status($api->production_state(new WP_REST_Request(['id'=>'live_test'])),403,'studio denied on actual absence');

// Public-ID lookup SQL errors must propagate without writes.
reset_fixtures();$GLOBALS['wpdb']->fail=true;
expect_status($api->source_save(new WP_REST_Request(['id'=>'live_test'],['public_id'=>'src_abcdef'])),503,'source ID SQL outage');
expect(VWLB_Future_Intelligence::$writes===0,'source outage caused no mutation');
reset_fixtures();$GLOBALS['wpdb']->fail=true;
expect_status($api->scene_save(new WP_REST_Request(['id'=>'live_test'],['title'=>'Scene','source_public_ids'=>['src_abcdef']])),503,'scene membership ID SQL outage');
expect(VWLB_Future_Intelligence::$writes===0,'scene outage caused no mutation');
reset_fixtures();$GLOBALS['wpdb']->fail=true;
expect_status($api->transcript_index(new WP_REST_Request(['id'=>'vid_test'],['track_public_id'=>'track_abcdef'])),503,'transcript track ID SQL outage');
expect(VWLB_Future_Intelligence::$writes===0,'transcript index outage caused no mutation');
reset_fixtures();$GLOBALS['wpdb']->fail=false;
$created=$api->source_save(new WP_REST_Request(['id'=>'live_test'],['public_id'=>'src_abcdef']));
expect($created instanceof WP_REST_Response && $created->get_status()===201 && VWLB_Future_Intelligence::$writes===1,'healthy public-ID source save proceeds');

// Future safety layer must not convert failed owner read into empty enrichments.
reset_fixtures();VWLB_Repository::$fail=true;
expect_status(VWLB_Future_Safety::published_tracks('video','vid_test'),503,'published track owner SQL outage');
reset_fixtures();VWLB_Repository::$fail=true;
expect_status(VWLB_Future_Safety::annotations('vid_test'),503,'annotation owner SQL outage');
reset_fixtures();VWLB_Repository::$fail=true;
expect_status(VWLB_Future_Safety::rest_live(new WP_REST_Request(['id'=>'live_test'])),503,'live enrichment owner SQL outage');
reset_fixtures();VWLB_Live::$failDuringState=true;
expect_status(VWLB_Future_Safety::rest_live(new WP_REST_Request(['id'=>'live_test'])),503,'live state read failure cannot masquerade as 404');
reset_fixtures();VWLB_Repository::$missing=true;
expect_status(VWLB_Future_Safety::annotations('missing'),404,'true missing annotation owner remains 404');
reset_fixtures();
expect(VWLB_Future_Safety::published_tracks('video','vid_test')===[],'healthy empty tracks remain valid empty array');
reset_fixtures();
$live=VWLB_Future_Safety::rest_live(new WP_REST_Request(['id'=>'live_test']));
expect($live instanceof WP_REST_Response && isset($live->get_data()['media_tracks']) &&
    ($live->get_headers()['Cache-Control']??'')==='private, no-store','healthy live enrichment remains safe');
echo "File10 V12.3 Future REST read integrity PASS: ".$GLOBALS['checks']." assertions; isolated PHP mock only\n";
