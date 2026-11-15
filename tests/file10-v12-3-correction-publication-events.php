<?php
/**
 * File10 V12.3 actual timestamp-correction publication lifecycle.
 * Approved reviewer draft creation is NOT the publication event.
 * Isolated source test only; not a staged or deployed WordPress instance.
 */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('VWLB_TEXT_DOMAIN','vwlb-test');
define('ARRAY_A','ARRAY_A');
final class WP_Error {
    public function __construct(private string $code,private string $message,private array $data=[]){}
    public function get_error_code(): string{return $this->code;}
    public function get_error_data(): array{return $this->data;}
}
function is_wp_error($v): bool{return $v instanceof WP_Error;}
function __($s,$domain=''): string{return (string)$s;}
function absint($v): int{return abs((int)$v);}
function sanitize_key($v): string{return (string)$v;}
function get_current_user_id(): int{return 20;}
function wp_strip_all_tags($v): string{return strip_tags((string)$v);}
function do_action(...$args): void{}
function apply_filters($k,$default,...$args){return $default;}
$GLOBALS['events']=[];
final class VWLB_Helpers{
    public static function error($code,$message,$status=400,$extra=[]): WP_Error{
        return new WP_Error($code,$message,array_merge(['status'=>$status],$extra));
    }
    public static function enum($x,$allowed,$default=''){return in_array($x,$allowed,true)?$x:$default;}
    public static function text($x,$n=191): string{return mb_substr((string)$x,0,$n);}
    public static function textarea($x,$n=100000): string{return mb_substr((string)$x,0,$n);}
    public static function contains_raw_secret($v): bool{return false;}
    public static function now(): string{return '2026-11-15 00:00:00';}
    public static function public_id($x): string{return $x.'_mock';}
    public static function table($x): string{return 'wp_vwlb_'.$x;}
    public static function json_encode($x): string{return json_encode($x);}
    public static function audit(...$args): void{}
    public static function outbox($name,...$args): void{$GLOBALS['events'][]=$name;}
}
final class VWLB_Contracts{
    public const CAP_PUBLISH='publish';
    public const CAP_REVIEW='review';
}
final class VWLB_Security{
    public static bool $publish=true,$review=true;
    public static function can($cap,$object=null,$purpose=''): bool{
        return $cap===VWLB_Contracts::CAP_PUBLISH?self::$publish:self::$review;
    }
}
final class VWLB_Repository{
    public static function find($table,$id,$lock=false): ?array{
        if($table!=='videos')return null;
        return ['id'=>7,'public_id'=>'vid_source','status'=>'published','duration_seconds'=>120,'owner_id'=>20];
    }
}
final class MockDB{
    public int $writes=0,$insert_id=31;
    public ?array $annotation=null;
    public function prepare($sql,...$args): string{return $sql;}
    public function insert($table,$row): int{
        ++$this->writes;
        if(str_contains($table,'video_annotations'))$this->annotation=array_merge($row,['id'=>$this->insert_id]);
        return 1;
    }
    public function get_row($sql,$type=null): ?array{
        return str_contains($sql,'video_annotations')?$this->annotation:null;
    }
    public function update($table,$row,$where): int{
        if(!str_contains($table,'video_annotations'))return 0;
        if(!$this->annotation||$where['version']!==$this->annotation['version'])return 0;
        ++$this->writes;
        $this->annotation=array_merge($this->annotation,$row);
        return 1;
    }
}
$GLOBALS['wpdb']=new MockDB();
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php';
$GLOBALS['assert_count']=0;
function check(bool $condition,string $label): void{
    ++$GLOBALS['assert_count'];
    if(!$condition){fwrite(STDERR,"File10 V12.3 correction lifecycle FAIL: $label\n");exit(1);}
}
function expected_error($value,int $status,string $label): void{
    check(is_wp_error($value)&&($value->get_error_data()['status']??0)===$status,$label);
}
$draft=VWLB_Future_Intelligence::create_annotation('vid_source',[
    'kind'=>'correction','source'=>'manual','start_ms'=>1000,'end_ms'=>2000,
    'title'=>'Corrected source claim','body'=>'Scholarly correction with source attribution'
]);
check(is_array($draft)&&($draft['status']??'')==='reviewed','new correction remains reviewed, not published');
check($GLOBALS['wpdb']->writes===1,'a correction creates exactly one canonical candidate');
check(!in_array('VideoTimestampCorrectionPublished',$GLOBALS['events'],true),
    'reviewed-only correction must not publish a correction event');
check(!in_array('VideoAnnotationPublished',$GLOBALS['events'],true),
    'reviewed-only correction must not publish an annotation event');
$wrong=VWLB_Future_Intelligence::transition_annotation(31,'publish',0);
expected_error($wrong,409,'version missing prevents publish');
check($GLOBALS['wpdb']->writes===1&&$GLOBALS['events']===[],
    'invalid publish does not emit events');
$published=VWLB_Future_Intelligence::transition_annotation(31,'publish',1);
check(is_array($published)&&($published['status']??'')==='published','correct transition reviewed->published');
check($GLOBALS['wpdb']->writes===2,'publish update is one canonical versioned write');
check($GLOBALS['events']===['VideoAnnotationPublished','VideoTimestampCorrectionPublished'],
    'exactly one publication pair emitted at the genuine transition');
$duplicate=VWLB_Future_Intelligence::transition_annotation(31,'publish',2);
expected_error($duplicate,409,'cannot publish an already published correction');
check(count($GLOBALS['events'])===2&&$GLOBALS['wpdb']->writes===2,
    'duplicate transition emits no extra event and causes no write');
echo "File10 V12.3 correction publish boundary PASS: {$GLOBALS['assert_count']} assertions; source mock only".PHP_EOL;
