<?php
/** V12.3 actual watermark pre-callback and Future policy class behavior under SQL faults. */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('VWLB_TEXT_DOMAIN','test');
define('VWLB_VERSION','1.2.25-rc1');
define('ARRAY_A','ARRAY_A');
final class WP_Error {
    public function __construct(private string $code,private string $message,private array $data=[]) {}
    public function get_error_code(): string {return $this->code;}
    public function get_error_data(): array {return $this->data;}
}
function is_wp_error($x): bool {return $x instanceof WP_Error;}
function __($t,$d=''): string {return $t;}
function absint($a): int {return abs((int)$a);}
function sanitize_key($s): string {return (string)$s;}
function get_current_user_id(): int {return 9;}
function wp_generate_uuid4(): string {return 'e10f31e2-12ac-4a1f-8893-66a2c953d21d';}
function wp_salt($x): string {return 'test-only-salt';}
function apply_filters($name,$value,...$args) {return $value;}
function do_action(...$args): void {}
function rest_ensure_response($value): WP_REST_Response {return new WP_REST_Response($value);}
final class WP_REST_Response {
    private array $headers=[];
    public function __construct(public $data){}
    public function header($k,$v): void {$this->headers[$k]=$v;}
    public function get_headers(): array {return $this->headers;}
}
final class WP_REST_Request implements ArrayAccess {
    public function __construct(private array $args=[],private string $route='',private string $method='POST'){}
    public function get_method(): string {return $this->method;}
    public function get_route(): string {return $this->route;}
    public function get_header($name): string {return '';}
    public function offsetExists(mixed $offset): bool {return isset($this->args[$offset]);}
    public function offsetGet(mixed $offset): mixed {return $this->args[$offset]??null;}
    public function offsetSet(mixed $offset,mixed $value): void {$this->args[$offset]=$value;}
    public function offsetUnset(mixed $offset): void {unset($this->args[$offset]);}
}
final class VWLB_Helpers {
    public static int $audits=0;
    public static function error($code,$message,$status=400,$extra=[]): WP_Error {
        return new WP_Error($code,$message,array_merge(['status'=>$status],$extra));
    }
    public static function table($key): string {return 'wp_vwlb_'.$key;}
    public static function enum($v,$allowed,$fallback) {return in_array($v,$allowed,true)?$v:$fallback;}
    public static function text($v,$length=255): string {return substr((string)$v,0,$length);}
    public static function json_encode($v): string {return json_encode($v);}
    public static function contains_raw_secret($x): bool {return false;}
    public static function now(): string {return '2026-11-14 12:00:00';}
    public static function audit(...$args): void {++self::$audits;}
}
final class VWLB_Contracts {
    public const CAP_PUBLISH='publish';public const CAP_BROADCAST='broadcast';
    public static function namespaces(): array {return ['sabri/v2/file-10'];}
}
final class VWLB_Security {
    public static bool $can_view=true;
    public static function can(...$args): bool {return true;}
    public static function can_view($row): bool {return self::$can_view && is_array($row);}
}
final class VWLB_Repository {
    public static bool $readFail=false,$failed=false,$missing=false;
    public static function reset_read_failure(): void {self::$failed=false;}
    public static function read_failed(): bool {return self::$failed;}
    public static function find($table,$id,$locked=false): ?array {
        if(self::$readFail){self::$failed=true;return null;}
        if(self::$missing)return null;
        return ['id'=>13,'public_id'=>(string)$id,'visibility'=>'public','status'=>'published','owner_id'=>9,
                'published_at'=>'2026-11-14 12:00:00','version'=>1];
    }
}
final class MockDB {
    public string $last_error='';
    public bool $readFail=false,$failAfterWrite=false;
    public int $inserts=0,$updates=0,$insert_id=13;
    public ?array $policy=null;
    public function prepare($q,...$args): string {return $q;}
    public function get_row($q,$type=null) {
        if($this->readFail || ($this->failAfterWrite && $this->inserts+$this->updates>0)){
            $this->last_error='Simulated policy SQL outage';return null;
        }
        $this->last_error='';
        return $this->policy;
    }
    public function insert($table,$row): int {
        ++$this->inserts;
        $this->policy=array_merge($row,['id'=>13]);
        return 1;
    }
    public function update($table,$row,$where): int {
        ++$this->updates;
        $this->policy=array_merge($this->policy??[], $row);
        return 1;
    }
}
$GLOBALS['wpdb']=new MockDB();
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php';
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-r79-watermark-session-guard.php';
function assertion(bool $good,string $name): void {
    $GLOBALS['test_count']=($GLOBALS['test_count']??0)+1;
    if(!$good){fwrite(STDERR,"File10 V12.3 watermark failure: $name\n");exit(1);}
}
function status($value,int $code,string $label): void {
    assertion($value instanceof WP_Error && ($value->get_error_data()['status']??0)===$code,
        $label.' expecting '.$code.' got '.($value instanceof WP_Error?$value->get_error_code():get_debug_type($value)));
}
function resetstate(): void {
    VWLB_Repository::$readFail=false;VWLB_Repository::$missing=false;
    VWLB_Repository::$failed=false;VWLB_Security::$can_view=true;
    $GLOBALS['wpdb']=new MockDB();VWLB_Helpers::$audits=0;
}
function req($type='video'): WP_REST_Request {
    return new WP_REST_Request(['object_type'=>$type,'id'=>($type==='video'?'vid_abc123':'live_abc123')],
        '/sabri/v2/file-10/watermarks/'.$type.'/'.($type==='video'?'vid_abc123':'live_abc123').'/grant');
}
resetstate();VWLB_Repository::$readFail=true;
status(VWLB_R79_Watermark_Session_Guard::guard(null,[],req()),503,'guard video owner query error');
resetstate();VWLB_Repository::$readFail=true;
status(VWLB_R79_Watermark_Session_Guard::guard(null,[],req('live')),503,'guard live owner query error');
resetstate();VWLB_Repository::$missing=true;
status(VWLB_R79_Watermark_Session_Guard::guard(null,[],req()),404,'true video not found');
resetstate();VWLB_Security::$can_view=false;
status(VWLB_R79_Watermark_Session_Guard::guard(null,[],req()),404,'viewer denied');
resetstate();$GLOBALS['wpdb']->readFail=true;
status(VWLB_R79_Watermark_Session_Guard::guard(null,[],req()),503,'guard policy DB error');
resetstate();$GLOBALS['wpdb']->policy=['mode'=>'off','status'=>'active','version'=>1];
$ok=VWLB_R79_Watermark_Session_Guard::guard(null,[],req());
assertion($ok instanceof WP_REST_Response && ($ok->data['mode']??'')==='off' &&
    ($ok->get_headers()['Cache-Control']??'')==='private, no-store','healthy off-mode grant is private');

resetstate();$GLOBALS['wpdb']->readFail=true;
status(VWLB_Future_Intelligence::watermark_payload(['mode'=>'off'],'video', ['id'=>13,'public_id'=>'vid_abc123']),503,
    'filter policy read error may not become mode off');
resetstate();
$neutral=VWLB_Future_Intelligence::watermark_payload(['mode'=>'off'],'video', ['id'=>13,'public_id'=>'vid_abc123']);
assertion($neutral===['mode'=>'off'],'legitimate missing watermark policy preserves original payload');

resetstate();$GLOBALS['wpdb']->readFail=true;
status(VWLB_Future_Intelligence::set_watermark_policy('video','vid_abc123',['mode'=>'both']),503,
    'policy read failure must prevent insertion');
assertion($GLOBALS['wpdb']->inserts===0 && $GLOBALS['wpdb']->updates===0 && VWLB_Helpers::$audits===0,
    'no write or audit after unknown policy state');

resetstate();
$created=VWLB_Future_Intelligence::set_watermark_policy('video','vid_abc123',['mode'=>'visible']);
assertion(is_array($created) && ($created['mode']??'')==='visible' &&
    $GLOBALS['wpdb']->inserts===1,'first valid policy creation');
resetstate();$GLOBALS['wpdb']->policy=['id'=>13,'version'=>2,'mode'=>'visible','status'=>'active'];
$updated=VWLB_Future_Intelligence::set_watermark_policy('video','vid_abc123',['mode'=>'off','version'=>2]);
assertion(is_array($updated)&&($updated['version']??0)===3 &&
    $GLOBALS['wpdb']->updates===1,'versioned existing policy update');

resetstate();$GLOBALS['wpdb']->failAfterWrite=true;
status(VWLB_Future_Intelligence::set_watermark_policy('video','vid_abc123',['mode'=>'visible']),503,
    'post-write policy read uncertainty cannot falsely report success');
assertion($GLOBALS['wpdb']->inserts===1,'post-read fault did not cause duplicate write');
echo 'File10 V12.3 watermark actual class integrity PASS: '.($GLOBALS['test_count']??0).
    ' assertions (isolated mock, not WordPress live)'.PHP_EOL;
