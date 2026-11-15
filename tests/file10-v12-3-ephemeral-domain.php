<?php
/**
 * Fresh, throwaway WordPress/MySQL domain-flow checks for V12.3 F10-FUT-019/020.
 * Synthetic educational records and test-only File00 claims. Not clinical data,
 * not an external provider, not Staging/Live or independent File24 acceptance.
 */
declare(strict_types=1);
$_SERVER['HTTP_HOST']='file10-ephemeral.invalid';
$_SERVER['SERVER_NAME']='file10-ephemeral.invalid';
$_SERVER['REQUEST_URI']='/';
require '/var/www/html/wp-load.php';
$fail=static function(string $why): void{
    fwrite(STDERR,'F10-EPHEMERAL DOMAIN FAIL: '.$why.PHP_EOL);
    exit(2);
};
$must=static function(bool $okay,string $why)use($fail):void{
    if(!$okay)$fail($why);
};
$check_error=static function($value,int $status,string $why)use($must):void{
    $must(is_wp_error($value)&&($value->get_error_data()['status']??0)===$status,$why);
};
$admin=get_user_by('login','file10_qa_admin');
$must($admin instanceof WP_User && (int)$admin->ID>0,'isolated admin not installed');
wp_set_current_user((int)$admin->ID);
// This is a strictly synthetic File00 test fixture; none of these assertions
// are evidence that File00 deployed verified these conditions on the website.
add_filter('vwlb_identity_claims',static function($prior,$userId,$context)use($admin){
    if((int)$userId!==(int)$admin->ID)return null;
    return array(
        'identity_approved'=>true,'age_ok'=>true,'guardian_ok'=>true,
        'suspended'=>false,'entitlements'=>array(),
        'claims_version'=>'isolated-test-1','source'=>'file00-ephemeral-fixture',
        'founder'=>false,'verified_doctor'=>false
    );
},10,3);
$must(VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,null,'ephemeral_test'),
    'test-only reviewer role cannot enter intended domain test');
$must(VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,null,'ephemeral_test'),
    'test-only broadcaster role cannot enter intended domain test');
global $wpdb;
$now=gmdate('Y-m-d H:i:s');
$channel=VWLB_Helpers::table('channels');
$videos=VWLB_Helpers::table('videos');
$live=VWLB_Helpers::table('live_events');
$must(1===$wpdb->insert($channel,array(
    'public_id'=>'chn_ephem1','owner_id'=>(int)$admin->ID,'title'=>'Synthetic QA Channel',
    'slug'=>'ephemeral-qa-channel','created_at'=>$now,'updated_at'=>$now
)), 'could not seed synthetic channel');
$channel_id=(int)$wpdb->insert_id;
$must(1===$wpdb->insert($videos,array(
    'public_id'=>'vid_ephem1','channel_id'=>$channel_id,'owner_id'=>(int)$admin->ID,
    'title'=>'Ephemeral Educational Source','slug'=>'ephemeral-educational-source',
    'visibility'=>'public','status'=>'published','rights_status'=>'verified',
    'consent_status'=>'not_patient_case','published_at'=>$now,
    'duration_seconds'=>120,'created_at'=>$now,'updated_at'=>$now
)), 'could not seed public synthetic video');
$must(1===$wpdb->insert($live,array(
    'public_id'=>'live_ephem1','channel_id'=>$channel_id,'owner_id'=>(int)$admin->ID,
    'title'=>'Ephemeral Teaching Session','slug'=>'ephemeral-teaching-session',
    'visibility'=>'public','status'=>'live','provider'=>'manual',
    'scheduled_start'=>$now,'created_at'=>$now,'updated_at'=>$now
)), 'could not seed synthetic live session');

// Real MariaDB path: index reviewed educational text, search it by a current
// owner-approved viewer and verify correct timestamp/language and no data leak.
$segment=VWLB_Future_Intelligence::index_transcript_segment('vid_ephem1',array(
    'text'=>'Classical research evidence علم',
    'start_ms'=>1200,'end_ms'=>3200,'language'=>'ur','status'=>'reviewed'
));
$must(is_array($segment)&&($segment['start_ms']??0)===1200,'reviewed transcript index failed');
$found=VWLB_Future_Intelligence::search_transcript('vid_ephem1','علم','ur');
$must(is_array($found)&&count($found['items']??array())===1,'reviewed Urdu search not found in real MySQL');
$must((int)($found['items'][0]['start_ms']??-1)===1200,'real transcript timestamp was not preserved');
$short=VWLB_Future_Intelligence::search_transcript('vid_ephem1','x','ur');
$check_error($short,422,'too-short query must be rejected');

// Real MariaDB path: educational poll persists options and all selected
// answers; unknown 2nd choice must leave all prior response rows unchanged.
$poll=VWLB_Future_Intelligence::create_poll('live_ephem1',array(
    'question'=>'Which sources need human review?',
    'poll_type'=>'multiple','status'=>'open',
    'options'=>array(
        array('text'=>'Imported documents'),
        array('text'=>'AI-generated suggestions')
    )
));
$must(is_array($poll)&&count($poll['options']??array())===2,'educational poll was not created');
$poll_public=(string)($poll['public_id']??'');
$option_one=(string)($poll['options'][0]['public_id']??'');
$option_two=(string)($poll['options'][1]['public_id']??'');
$must(''!==$poll_public&&''!==$option_one&&''!==$option_two,'poll public identifiers unavailable');
$saved=VWLB_Future_Intelligence::answer_poll($poll_public,array($option_one,$option_two));
$must(is_array($saved)&&($saved['accepted']??false)===true,'valid multi-answer was not accepted');
$responded=$wpdb->get_var($wpdb->prepare(
    'SELECT COUNT(*) FROM '.VWLB_Helpers::table('live_poll_responses').' WHERE user_id=%d',
    (int)$admin->ID
));
$must((int)$responded===2,'two valid poll response rows were not persisted');
$invalid=VWLB_Future_Intelligence::answer_poll($poll_public,array($option_one,'opt_doesnotexist'));
$check_error($invalid,422,'unknown second poll option must abort entire answer');
$unchanged=$wpdb->get_var($wpdb->prepare(
    'SELECT COUNT(*) FROM '.VWLB_Helpers::table('live_poll_responses').' WHERE user_id=%d',
    (int)$admin->ID
));
$must((int)$unchanged===2,'invalid poll option altered the prior response');

// The proposed File26 adapter must be verifiable against genuine WP/MariaDB
// owner rows without being registered or activated on the production bus.
require_once WP_PLUGIN_DIR.'/video-wall-and-live-broadcasting/includes/class-vwlb-file26-connector.php';
$manifest=VWLB_File26_Connector::manifest();
$must(($manifest['status']??'')==='proposed'
    && ($manifest['deletion_semantics']??'')==='owner_outbox_tombstone_gate_pending',
    'File26 search connector may not claim live cutover');
$indexed=VWLB_File26_Connector::fetch_object('video','vid_ephem1');
$must(is_array($indexed)&&($indexed['object_id']??'')==='vid_ephem1'
    && ($indexed['visibility']??'')==='public',
    'canonical public video was not projected by proposed File26 owner callback');

// The current authoritative source must suppress private/restricted search
// immediately on read, even before File26/CDN eventual downstream cleanup.
$changed=$wpdb->update($videos,array('status'=>'restricted'),array('public_id'=>'vid_ephem1'));
$must(1===$changed,'synthetic video restriction did not persist');
$restricted=VWLB_Future_Intelligence::search_transcript('vid_ephem1','علم','ur');
$check_error($restricted,404,'restricted video was still searchable by a former viewer');
$must(VWLB_File26_Connector::fetch_object('video','vid_ephem1')===null,
    'proposed File26 source fetch must hide restricted canonical video');
$must(VWLB_File26_Connector::can_view($indexed)===false,
    'old public File26 projection must be denied after canonical owner restriction');
$pollTooLong=VWLB_Future_Intelligence::create_poll('live_ephem1',array(
    'question'=>'Invalid oversized poll','status'=>'open',
    'options'=>array_fill(0,21,'Unbounded choice')
));
$check_error($pollTooLong,422,'oversized poll bypassed canonical 20-option rule');
// Real WordPress/DB Video Wall: an emergency kill hides an otherwise public
// live row even while its lifecycle status is still live.
$frontend=new VWLB_Frontend();
$wall=$frontend->wall();
$must(is_string($wall)&&str_contains($wall,'Ephemeral Teaching Session'),
    'eligible real public live session absent from Video Wall');
$must(1===$wpdb->update($live,array('kill_switch'=>1),array('public_id'=>'live_ephem1')),
    'synthetic emergency kill state did not persist in MariaDB');
$wall=$frontend->wall();
$must(is_string($wall)&&!str_contains($wall,'Ephemeral Teaching Session'),
    'real MariaDB emergency-killed session leaked via Video Wall');

echo 'F10-EPHEMERAL DOMAIN PASS: real MariaDB video/transcript/restriction/poll/create/answer/invalid rollback; synthetic File00 claims; NOT STAGING/LIVE'.PHP_EOL;
