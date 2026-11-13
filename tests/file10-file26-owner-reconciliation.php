<?php
/**
 * File10 -> File26 owner changefeed proposal: isolated authoritative-state contract.
 * These mocks do NOT establish live WordPress/File26 delivery or staging acceptance.
 */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    private $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function get_option($key, $fallback = false) { return $GLOBALS['safe_mode'] ?? $fallback; }
function sanitize_title($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '-', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value, $remove_breaks = false) { return strip_tags((string) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
class VWLB_Helpers {
    public static function table($entity) { return 'mock_' . $entity; }
    public static function is_public_id($value) {
        return is_string($value) && (bool) preg_match('/^[a-z][a-z0-9]*_[a-z0-9]+$/', $value);
    }
}
class VWLB_R109_Rights_Consent_Replay_Guard {
    public static function policy_read_failed() { return (bool) ($GLOBALS['policy_read_failed'] ?? false); }
}
class VWLB_Security {
    public static function can_view($row, $purpose = '') {
        if (!empty($GLOBALS['simulate_consent_read_failure'])) {
            $GLOBALS['policy_read_failed'] = true;
            return false;
        }
        return is_array($row) && 'public' === ($row['visibility'] ?? '') &&
            !in_array($row['status'] ?? '', array('restricted', 'removed', 'deleted'), true);
    }
}
class VWLB_Repository {
    public static $rows = array();
    public static $read_outage = false;
    public static function reset_read_failure() { /* this mock preserves true outage flag */ }
    public static function read_failed() { return self::$read_outage; }
    public static function find($entity, $id) {
        return self::$read_outage ? null : (self::$rows[$entity][(string) $id] ?? null);
    }
}
require dirname(__DIR__) . '/video-wall-and-live-broadcasting/includes/class-vwlb-file26-connector.php';

function check_reconcile($condition, $label) {
    $GLOBALS['checks']++;
    if (!$condition) {
        fwrite(STDERR, "F10-F26 reconciliation proposal FAIL: " . $label . PHP_EOL);
        exit(1);
    }
}
$GLOBALS['checks'] = 0;
$GLOBALS['safe_mode'] = false;
$GLOBALS['policy_read_failed'] = false;
$GLOBALS['simulate_consent_read_failure'] = false;

$video = array(
    'id' => 431, 'public_id' => 'vid_abc123', 'version' => 7,
    'owner_id' => 87654, 'visibility' => 'public', 'status' => 'published',
    'rights_status' => 'verified', 'consent_status' => 'not_patient_case',
    'title' => 'Reviewed classical homeopathy', 'excerpt' => 'An original public educational video',
    'language' => 'ur', 'slug' => 'reviewed', 'published_at' => '2026-11-01 00:00:00',
    'deleted_at' => null
);
$live = array(
    'id' => 501, 'public_id' => 'live_abc123', 'version' => 3,
    'owner_id' => 87654, 'visibility' => 'public', 'status' => 'scheduled',
    'kill_switch' => 0, 'title' => 'Public scheduled educational broadcast',
    'language' => 'ar', 'slug' => 'public-live', 'deleted_at' => null
);
VWLB_Repository::$rows = array(
    'videos' => array('vid_abc123'=>$video),
    'live_events' => array('live_abc123'=>$live)
);
$ev = array('public_id'=>'evt_abc123','event_name'=>'VideoPublished.v1','object_id'=>431,'object_type'=>'video');
$proposal = VWLB_File26_Connector::propose_outbox_reconciliation($ev, array('public_id'=>'vid_abc123'));
check_reconcile(is_array($proposal) && $proposal['action']==='upsert', 'current public media yields proposal only');
check_reconcile(($proposal['object_version']??null)===7, 'uses authoritative current version');
check_reconcile(($proposal['document']['object_version']??null)===7, 'upsert document uses current version');
check_reconcile(($proposal['status']??'')==='proposed', 'never claims index acceptance');
check_reconcile(strpos(json_encode($proposal), '87654')===false && strpos(json_encode($proposal), '"object_id":431')===false, 'no private numeric actor or object ID in proposal');

$old = $video; $old['version']=8; $old['status']='restricted';
VWLB_Repository::$rows['videos']['vid_abc123']=$old;
$proposal = VWLB_File26_Connector::propose_outbox_reconciliation($ev, array('public_id'=>'vid_abc123'));
check_reconcile(is_array($proposal) && $proposal['action']==='tombstone', 'restricted owner creates versioned tombstone proposal');
check_reconcile(($proposal['object_version']??null)===8, 'tombstone version comes from owner row');
check_reconcile(!array_key_exists('document',$proposal), 'tombstone contains no content body');
$old=$video; $old['rights_status']='revoked'; $old['version']=9;
VWLB_Repository::$rows['videos']['vid_abc123']=$old;
$proposal = VWLB_File26_Connector::propose_outbox_reconciliation($ev, array('public_id'=>'vid_abc123'));
check_reconcile(is_array($proposal) && $proposal['action']==='tombstone', 'revoked rights creates tombstone proposal');

VWLB_Repository::$rows['videos']['vid_abc123']=$video;
$lev=array('public_id'=>'evt_def456','event_name'=>'LiveBroadcastEnded.v1','object_id'=>501);
$proposal = VWLB_File26_Connector::propose_outbox_reconciliation($lev, array('public_id'=>'live_abc123'));
check_reconcile(is_array($proposal) && $proposal['action']==='upsert' && $proposal['entity_type']==='live_video', 'public live owner maps to live_video');
VWLB_Repository::$rows['live_events']['live_abc123']['kill_switch']=1;
$proposal = VWLB_File26_Connector::propose_outbox_reconciliation($lev, array('public_id'=>'live_abc123'));
check_reconcile(is_array($proposal) && $proposal['action']==='tombstone', 'emergency kill creates tombstone proposal');
VWLB_Repository::$rows['live_events']['live_abc123']=$live;

VWLB_Repository::$read_outage=true;
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation($ev,array('public_id'=>'vid_abc123')) instanceof WP_Error, 'DB read outage cannot fabricate tombstone');
VWLB_Repository::$read_outage=false;
$GLOBALS['simulate_consent_read_failure']=true;
$GLOBALS['policy_read_failed']=false;
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation($ev,array('public_id'=>'vid_abc123')) instanceof WP_Error, 'consent policy outage cannot fabricate tombstone');
$GLOBALS['simulate_consent_read_failure']=false;
$GLOBALS['policy_read_failed']=false;

unset(VWLB_Repository::$rows['videos']['vid_abc123']);
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation($ev,array('public_id'=>'vid_abc123')) instanceof WP_Error, 'hard-delete without owner version cannot fabricate tombstone');
VWLB_Repository::$rows['videos']['vid_abc123']=$video;
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation($ev,array('public_id'=>'123456')) instanceof WP_Error, 'native numeric ID cannot replace public identifier');
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation(array('public_id'=>'431','event_name'=>'VideoPublished.v1'),array('public_id'=>'vid_abc123')) instanceof WP_Error, 'numeric event ID cannot be accepted');
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation(array('public_id'=>'evt_abc123','event_name'=>'UnrecognizedSecret.v1'),array('public_id'=>'vid_abc123')) instanceof WP_Error, 'unsupported event cannot become search mutation');
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation($ev,array('public_id'=>'other_noncanonical_more_underscores')) instanceof WP_Error, 'noncanonical owner ID denied');

$GLOBALS['safe_mode']=true;
check_reconcile(VWLB_File26_Connector::propose_outbox_reconciliation($ev,array('public_id'=>'vid_abc123')) instanceof WP_Error, 'safe mode disables event proposals');
$GLOBALS['safe_mode']=false;
check_reconcile(VWLB_File26_Connector::manifest()['status']==='proposed', 'manifest remains deliberately proposed');
check_reconcile(VWLB_File26_Connector::manifest()['deletion_semantics']==='owner_outbox_tombstone_gate_pending', 'consumer tombstone acceptance still pending');
echo "F10-F26 reconciliation proposal isolated contracts PASS: ".$GLOBALS['checks']." checks; no deployment inferred.".PHP_EOL;
