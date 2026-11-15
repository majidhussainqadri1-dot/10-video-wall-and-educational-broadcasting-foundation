<?php
/**
 * File10 V12.3: failure must never be mistaken for a missing/empty
 * transcript or a partially accepted knowledge-check answer.
 *
 * Exercises the ACTUAL VWLB_Future_Intelligence PHP class through
 * isolated WordPress/DB mocks. Neither Staging nor Live acceptance.
 */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('VWLB_TEXT_DOMAIN','file10-v123-test');
define('ARRAY_A','ARRAY_A');

final class WP_Error {
    public function __construct(private string $code,private string $message,private array $data=[]){}
    public function get_error_code(): string{return $this->code;}
    public function get_error_data(): array{return $this->data;}
}
function is_wp_error($x): bool{return $x instanceof WP_Error;}
function __($x,$domain=''): string{return (string)$x;}
function absint($n): int{return abs((int)$n);}
function sanitize_key($s): string{return (string)$s;}
function get_current_user_id(): int{return 44;}
function is_user_logged_in(): bool{return true;}
function wp_strip_all_tags($s): string{return strip_tags((string)$s);}
function do_action(...$args): void {}
function apply_filters($tag,$value,...$args){return $value;}

final class VWLB_Contracts{
    public const CAP_REVIEW='review';
    public const CAP_BROADCAST='broadcast';
}
final class VWLB_Helpers{
    public static function error($code,$message,$status=400,$extra=[]): WP_Error{
        return new WP_Error($code,$message,array_merge(['status'=>$status],$extra));
    }
    public static function text($value,$max=191): string{return mb_substr((string)$value,0,$max);}
    public static function table($name): string{return 'wp_vwlb_'.$name;}
    public static function now(): string{return '2026-11-15 00:00:00';}
    public static function public_id($prefix): string{return $prefix.'_mock';}
    public static function iso_utc($str): ?string{return $str ? '2026-11-15T00:00:00Z' : null;}
    public static function enum($value,$values,$fallback){return in_array($value,$values,true)?$value:$fallback;}
    public static function json_encode($v): string{return json_encode($v);}
}
final class VWLB_Security{
    public static bool $canView=true,$canEdit=true;
    public static function can($cap,$object=null,$purpose=''): bool{return self::$canEdit;}
    public static function can_view($object): bool{return self::$canView&&is_array($object);}
}
final class VWLB_Repository{
    public static bool $outage=false,$missing=false,$failed=false;
    public static function reset_read_failure(): void{self::$failed=false;}
    public static function read_failed(): bool{return self::$failed;}
    public static function find($entity,$id,$for_update=false): ?array{
        if(self::$outage){self::$failed=true;return null;}
        if(self::$missing)return null;
        if($entity==='videos')return [
            'id'=>12,'public_id'=>'vid_mock','visibility'=>'public','status'=>'published',
            'duration_seconds'=>120,'owner_id'=>44,'published_at'=>'2026-11-15 00:00:00'
        ];
        if($entity==='live_events')return [
            'id'=>71,'public_id'=>'live_mock','visibility'=>'public','status'=>'live','owner_id'=>44,
            'scheduled_start'=>'2026-11-15 00:00:00'
        ];
        return null;
    }
}
final class MockDB{
    public string $last_error='';
    public bool $outageTranscript=false,$outageTrack=false,$outagePoll=false,$outageOptions=false,$outageOptionId=false;
    public bool $trackMissing=false,$pollMissing=false;
    public string $outageOptionName='opt_two';
    public int $writes=0,$insert_id=27,$optionReadCount=0;
    public array $transcripts=[['start_ms'=>1000,'end_ms'=>2000,'language'=>'ur','segment_text'=>'علم']];
    public array $pollOptions=[
        ['id'=>101,'public_id'=>'opt_one','option_text'=>'First','is_correct'=>1,'sort_order'=>0],
        ['id'=>102,'public_id'=>'opt_two','option_text'=>'Second','is_correct'=>0,'sort_order'=>1]
    ];
    public function prepare($sql,...$args): string{return $sql.' /*'.json_encode($args,JSON_UNESCAPED_UNICODE).'*/';}
    public function esc_like($str): string{return addcslashes((string)$str,'_%');}
    public function get_row($sql,$format=null): ?array{
        if(str_contains($sql,'wp_vwlb_media_tracks')){
            $this->last_error=$this->outageTrack?'track read failed':'';
            return $this->outageTrack||$this->trackMissing?null:[
                'id'=>27,'public_id'=>'track_mock','object_type'=>'video','object_id'=>12,
                'status'=>'reviewed','language'=>'ur'
            ];
        }
        if(str_contains($sql,'wp_vwlb_live_polls')){
            $this->last_error=$this->outagePoll?'poll read failed':'';
            return $this->outagePoll||$this->pollMissing?null:[
                'id'=>8,'public_id'=>'poll_mock','live_event_id'=>71,'poll_type'=>'multiple',
                'status'=>'open','opens_at'=>null,'closes_at'=>null,
                'question'=>'What is healthy evidence?','version'=>1
            ];
        }
        $this->last_error='';return null;
    }
    public function get_results($sql,$format=null): array{
        if(str_contains($sql,'wp_vwlb_transcript_segments')){
            $this->last_error=$this->outageTranscript?'transcript SQL outage':'';
            return $this->outageTranscript?[]:$this->transcripts;
        }
        if(str_contains($sql,'wp_vwlb_live_poll_options')){
            $this->last_error=$this->outageOptions?'poll option SQL outage':'';
            return $this->outageOptions?[]:$this->pollOptions;
        }
        $this->last_error='';return [];
    }
    public function get_var($sql): int|null{
        if(str_contains($sql,'COUNT(DISTINCT user_id)')){
            $this->last_error='';return 1;
        }
        ++$this->optionReadCount;
        if($this->outageOptionId&&str_contains($sql,$this->outageOptionName)){
            $this->last_error='option ID SQL outage';return null;
        }
        $this->last_error='';
        if(str_contains($sql,'opt_one'))return 101;
        if(str_contains($sql,'opt_two'))return 102;
        return null;
    }
    public function insert($table,$data): int{++$this->writes;return 1;}
    public function delete($table,$where): int{++$this->writes;return 1;}
}
$GLOBALS['wpdb']=new MockDB();

final class VWLB_DB{
    public static function read_row($q,$context=''){
        global $wpdb;$wpdb->last_error='';$row=$wpdb->get_row($q,ARRAY_A);
        return $wpdb->last_error?VWLB_Helpers::error('vwlb_database_read_failed','Owner query unavailable',503):$row;
    }
    public static function read_results($q,$context=''){
        global $wpdb;$wpdb->last_error='';$rows=$wpdb->get_results($q,ARRAY_A);
        return $wpdb->last_error?VWLB_Helpers::error('vwlb_database_read_failed','Query unavailable',503):$rows;
    }
    public static function read_var($q,$context=''){
        global $wpdb;$wpdb->last_error='';$v=$wpdb->get_var($q);
        return $wpdb->last_error?VWLB_Helpers::error('vwlb_database_read_failed','Query unavailable',503):$v;
    }
    public static function transaction($callback){return $callback();}
}
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php';

$GLOBALS['checks']=0;
function check(bool $condition,string $label): void{
    ++$GLOBALS['checks'];
    if(!$condition){fwrite(STDERR,"File10 V12.3 transcript/poll NEGATIVE FAIL: $label\n");exit(1);}
}
function check_err($value,int $status,string $label): void{
    check(is_wp_error($value)&&($value->get_error_data()['status']??0)===$status,
        $label.' expecting '. $status.' got '.(is_wp_error($value)?$value->get_error_code():get_debug_type($value)));
}
function reset_state(): void{
    $GLOBALS['wpdb']=new MockDB();
    VWLB_Repository::$outage=false;VWLB_Repository::$missing=false;VWLB_Repository::$failed=false;
    VWLB_Security::$canView=true;VWLB_Security::$canEdit=true;
}

// FUT-020 search must distinguish SQL outage, protected-owner outage, missing, and empty.
reset_state();VWLB_Repository::$outage=true;
check_err(VWLB_Future_Intelligence::search_transcript('vid_mock','علم'),503,'transcript owner SQL outage');
reset_state();$GLOBALS['wpdb']->outageTranscript=true;
check_err(VWLB_Future_Intelligence::search_transcript('vid_mock','علم'),503,'transcript segments SQL outage');
reset_state();VWLB_Repository::$missing=true;
check_err(VWLB_Future_Intelligence::search_transcript('missing','علم'),404,'genuine missing source');
reset_state();VWLB_Security::$canView=false;
check_err(VWLB_Future_Intelligence::search_transcript('vid_mock','علم'),404,'forbidden video search');
reset_state();$GLOBALS['wpdb']->transcripts=[];
$empty=VWLB_Future_Intelligence::search_transcript('vid_mock','علم');
check(is_array($empty)&&($empty['items']??null)===[],'healthy no-results list');
reset_state();
$hit=VWLB_Future_Intelligence::search_transcript('vid_mock','علم','ur');
check(is_array($hit)&&count($hit['items']??[])===1,'healthy Urdu segment found');

// FUT-020 index must not mutate after either DB owner or candidate track read failure.
reset_state();VWLB_Repository::$outage=true;
check_err(VWLB_Future_Intelligence::index_transcript_segment('vid_mock',['text'=>'علم']),503,'index owner SQL outage');
check($GLOBALS['wpdb']->writes===0,'no index insert after owner SQL outage');
reset_state();$GLOBALS['wpdb']->outageTrack=true;
check_err(VWLB_Future_Intelligence::index_transcript_segment('vid_mock',['text'=>'علم','track_id'=>27]),503,'index track SQL outage');
check($GLOBALS['wpdb']->writes===0,'no index insert after track SQL outage');
reset_state();$GLOBALS['wpdb']->trackMissing=true;
check_err(VWLB_Future_Intelligence::index_transcript_segment('vid_mock',['text'=>'علم','track_id'=>27]),422,'genuine missing reviewed track');
reset_state();
$indexed=VWLB_Future_Intelligence::index_transcript_segment('vid_mock',['text'=>'علم','track_id'=>27,'language'=>'ur']);
check(is_array($indexed)&&$GLOBALS['wpdb']->writes===1&&($indexed['track_public_id']??'')==='track_mock','healthy approved transcript index');

// FUT-019 poll viewing: canonical poll and live read faults are 503, missing remains null.
reset_state();$GLOBALS['wpdb']->outagePoll=true;
check_err(VWLB_Future_Intelligence::poll('poll_mock'),503,'poll root SQL outage');
reset_state();VWLB_Repository::$outage=true;
check_err(VWLB_Future_Intelligence::poll('poll_mock'),503,'poll live owner SQL outage');
reset_state();$GLOBALS['wpdb']->pollMissing=true;
check(VWLB_Future_Intelligence::poll('missing')===null,'true absent poll remains absent');
reset_state();$GLOBALS['wpdb']->outageOptions=true;
check_err(VWLB_Future_Intelligence::poll('poll_mock'),503,'poll options SQL outage');
reset_state();
$p=VWLB_Future_Intelligence::poll('poll_mock');
check(is_array($p)&&count($p['options'])===2&&!array_key_exists('is_correct',$p['options'][0]),'healthy anonymous poll hides correct answer');

// FUT-019 answers: no partial success if any option query fails/misses.
reset_state();$GLOBALS['wpdb']->outagePoll=true;
check_err(VWLB_Future_Intelligence::answer_poll('poll_mock',['opt_one']),503,'answer root SQL outage');
check($GLOBALS['wpdb']->writes===0,'no writes after poll SQL outage');
reset_state();VWLB_Repository::$outage=true;
check_err(VWLB_Future_Intelligence::answer_poll('poll_mock',['opt_one']),503,'answer live owner SQL outage');
check($GLOBALS['wpdb']->writes===0,'no writes after live SQL outage');
reset_state();$GLOBALS['wpdb']->outageOptionId=true;
check_err(VWLB_Future_Intelligence::answer_poll('poll_mock',['opt_one','opt_two']),503,'second option SQL failure must abort entire answer');
check($GLOBALS['wpdb']->writes===0,'no deletion or insert after second choice SQL failure');
reset_state();
check_err(VWLB_Future_Intelligence::answer_poll('poll_mock',['opt_one','opt_unknown']),422,'unknown second option must reject entire answer');
check($GLOBALS['wpdb']->writes===0,'unknown option did not delete old answer');
reset_state();check_err(VWLB_Future_Intelligence::answer_poll('poll_mock',[]),422,'no choices invalid');
reset_state();
$answer=VWLB_Future_Intelligence::answer_poll('poll_mock',['opt_one','opt_two']);
check(is_array($answer)&&($answer['accepted']??false)===true&&$GLOBALS['wpdb']->writes===3,'two valid options atomically replace previous answer');
echo 'File10 V12.3 transcript/poll actual class source QA PASS: '.$GLOBALS['checks'].' assertions; Staging/Live NOT tested'.PHP_EOL;
