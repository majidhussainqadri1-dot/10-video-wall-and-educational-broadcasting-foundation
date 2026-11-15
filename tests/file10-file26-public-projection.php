<?php
/**
 * File10 -> File26 proposed adapter: isolated source regression test.
 * Do not interpret these mocks as real WordPress/MySQL integration acceptance.
 */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
}
$GLOBALS['fake_safe_mode'] = false;
function get_option($key, $fallback = false) {
    return 'vwlb_safe_mode' === $key ? $GLOBALS['fake_safe_mode'] : $fallback;
}
function sanitize_title($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '-', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value, $remove_breaks = false) { return strip_tags((string) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
class VWLB_Helpers {
    public static function table($entity) { return 'mock_' . $entity; }
    public static function is_public_id($value) { return is_string($value) && (bool) preg_match('/^[a-z][a-z0-9]*_[a-z0-9]+$/', $value); }
}
class VWLB_Security {
    public static function can_view($row, $purpose = '') {
        if (!empty($GLOBALS['fake_nested_repository_outage'])) { VWLB_Repository::$fake_read_failed = true; }
        if (!empty($GLOBALS['fake_policy_outage'])) {
            $GLOBALS['fake_policy_failed'] = true;
            return false;
        }
        return is_array($row) && 'public' === ($row['visibility'] ?? '');
    }
}
class VWLB_R109_Rights_Consent_Replay_Guard {
    public static function policy_read_failed() { return !empty($GLOBALS['fake_policy_failed']); }
}
class VWLB_Repository {
    public static $owners = array();
    public static $fake_read_failed = false;
    public static $throw_on_find = false;
    public static $return_row_and_flag_failure = false;
    public static $find_calls = 0;
    public static function reset_read_failure() {}
    public static function read_failed() { return self::$fake_read_failed; }
    public static function find($entity, $public_id) {
        ++self::$find_calls;
        if (self::$throw_on_find) { throw new RuntimeException('simulated owner read exception'); }
        $row = self::$owners[$entity][(string) $public_id] ?? null;
        if (self::$return_row_and_flag_failure) { self::$fake_read_failed = true; return $row; }
        return self::$fake_read_failed ? null : $row;
    }
}
class File10MockDB {
    public $rows = array();
    public $last_error = '';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql, $format = null) {
        foreach (array('videos', 'live_events', 'channels') as $entity) {
            if (strpos($sql, 'mock_' . $entity) !== false) { return $this->rows[$entity] ?? array(); }
        }
        return null;
    }
}
$wpdb = new File10MockDB();
require_once __DIR__ . '/../video-wall-and-live-broadcasting/includes/class-vwlb-file26-connector.php';
function check_file10($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "File26 owner-projection FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}
$row = array(
    'id' => 11,
    'public_id' => 'vid_abc1',
    'version' => 4,
    'owner_id' => 431,
    'visibility' => 'public',
    'status' => 'published',
    'rights_status' => 'approved',
    'consent_status' => 'not_patient_case',
    'deleted_at' => null,
    'published_at' => '2026-11-01 00:00:00',
    'title' => 'New corrected title',
    'excerpt' => 'Revised public excerpt',
    'slug' => 'corrected',
    'language' => 'ur'
);
VWLB_Repository::$owners['videos']['vid_abc1'] = $row;
$doc = array('connector_slug'=>'file10-public-media', 'entity_type'=>'video',
    'object_id'=>'vid_abc1', 'object_version'=>4, 'visibility'=>'public');
check_file10(VWLB_File26_Connector::can_view($doc) === true, 'current canonical version must be available');
$stale = $doc; $stale['object_version'] = 3;
check_file10(VWLB_File26_Connector::can_view($stale) === false, 'stale indexed version must fail closed');
$missing = $doc; unset($missing['object_version']);
check_file10(VWLB_File26_Connector::can_view($missing) === false, 'missing owner version must fail closed');
$invalid = $doc; $invalid['object_version'] = 'not-a-version';
check_file10(VWLB_File26_Connector::can_view($invalid) === false, 'invalid owner version must fail closed');
$revoked = $row; $revoked['rights_status'] = 'revoked';
VWLB_Repository::$owners['videos']['vid_abc1'] = $revoked;
check_file10(VWLB_File26_Connector::can_view($doc) === false, 'revoked rights must fail closed');
$private = $row; $private['visibility'] = 'private';
VWLB_Repository::$owners['videos']['vid_abc1'] = $private;
check_file10(VWLB_File26_Connector::can_view($doc) === false, 'non-public source must fail closed');
VWLB_Repository::$owners['videos']['vid_abc1'] = $row;
$wpdb->rows['videos'] = array($row);
$batch = VWLB_File26_Connector::list_batch('', 20);
check_file10(is_array($batch) && count($batch['items']) === 1, 'one current public candidate emitted');
$projection = $batch['items'][0];
check_file10(($projection['object_version'] ?? null) === 4, 'source version required');
check_file10(($projection['author_key'] ?? null) === '', 'internal numeric owner must not cross public projection');
check_file10(strpos(json_encode($projection), 'file10-owner-431') === false, 'private WordPress user ID must not be exposed');
$numeric = $row; $numeric['public_id'] = '123456';
$wpdb->rows['videos'] = array($numeric);
$batch = VWLB_File26_Connector::list_batch('', 20);
check_file10(is_array($batch) && count($batch['items']) === 0, 'numeric-only public ID must not enter the index');
$noncanonical = $row;
$noncanonical['public_id'] = 'f26_video_demo';
$wpdb->rows['videos'] = array($noncanonical);
$batch = VWLB_File26_Connector::list_batch('', 20);
check_file10(is_array($batch) && count($batch['items']) === 0, 'modern File10 rejects noncanonical source identifier');
VWLB_Repository::$owners['videos']['f26_video_demo'] = $noncanonical;
$bad = $doc; $bad['object_id'] = 'f26_video_demo';
check_file10(VWLB_File26_Connector::can_view($bad) === false, 'modern File10 rejects noncanonical click reference');
// Direct owner reads must distinguish legitimate absent/private from operational errors.
$direct = VWLB_File26_Connector::fetch_object('video','vid_abc1');
check_file10(is_array($direct) && $direct['object_version'] === 4, 'valid owner direct fetch remains public');
check_file10(VWLB_File26_Connector::fetch_object('video','vid_missing123') === null, 'legitimate missing object stays null');
VWLB_Repository::$owners['videos']['vid_abc1']=$private;
check_file10(VWLB_File26_Connector::fetch_object('video','vid_abc1') === null, 'private object is not a public source');
VWLB_Repository::$owners['videos']['vid_abc1']=$row;
VWLB_Repository::$fake_read_failed=true;
check_file10(VWLB_File26_Connector::fetch_object('video','vid_abc1') instanceof WP_Error, 'DB outage returns typed owner failure, never null');
VWLB_Repository::$fake_read_failed=false;
$GLOBALS['fake_policy_outage']=true;
$GLOBALS['fake_policy_failed']=false;
check_file10(VWLB_File26_Connector::fetch_object('video','vid_abc1') instanceof WP_Error, 'consent policy outage returns typed failure, never false deletion');
$GLOBALS['fake_policy_outage']=false;
$GLOBALS['fake_policy_failed']=false;

// Regression: a later repository read triggered within the authorization projection
// must not look like a verified public document.
$GLOBALS['fake_nested_repository_outage'] = true;
VWLB_Repository::$fake_read_failed = false;
$late_direct = VWLB_File26_Connector::fetch_object('video','vid_abc1');
check_file10($late_direct instanceof WP_Error, 'late repository read failure in direct projection must be typed, not public success');
$GLOBALS['fake_nested_repository_outage'] = false;
VWLB_Repository::$fake_read_failed = false;

// Regression: search click authorization must remain boolean and fail closed.
VWLB_Repository::$throw_on_find = true;
try { $throw_guarded = VWLB_File26_Connector::can_view($doc); }
catch (Throwable $e) { $throw_guarded = 'uncaught_exception'; }
check_file10($throw_guarded === false, 'owner exception must not escape can_view boolean guard');
VWLB_Repository::$throw_on_find = false;
VWLB_Repository::$return_row_and_flag_failure = true;
VWLB_Repository::$fake_read_failed = false;
check_file10(VWLB_File26_Connector::can_view($doc) === false, 'owner row with failed repository read must not be public');
VWLB_Repository::$return_row_and_flag_failure = false;
VWLB_Repository::$fake_read_failed = false;
$find_calls_before = VWLB_Repository::$find_calls;
$GLOBALS['fake_safe_mode'] = true;
check_file10(VWLB_File26_Connector::can_view($doc) === false, 'safe mode must fail closed');
check_file10(VWLB_Repository::$find_calls === $find_calls_before, 'safe mode must not read owner DB on unregistered candidate');
$GLOBALS['fake_safe_mode'] = false;

$wpdb->rows['videos']=array($private);
$private_batch=VWLB_File26_Connector::list_batch('',20);
check_file10(is_array($private_batch) && count($private_batch['items'])===0,
    'legitimate private source excluded without an operational failure');
$wpdb->rows['videos']=array($row);
$GLOBALS['fake_policy_outage']=true;
$GLOBALS['fake_policy_failed']=false;
check_file10(VWLB_File26_Connector::list_batch('',20) instanceof WP_Error,
    'consent policy read outage must abort entire owner batch, not emit empty success');
$GLOBALS['fake_policy_outage']=false;
$GLOBALS['fake_policy_failed']=false;
$GLOBALS['fake_safe_mode'] = true;
check_file10(VWLB_File26_Connector::can_view($doc) === false, 'safe mode denies cached search result');
check_file10(VWLB_File26_Connector::list_batch('', 20) instanceof WP_Error, 'safe mode denies snapshot');
check_file10(VWLB_File26_Connector::fetch_object('video','vid_abc1') instanceof WP_Error, 'safe mode returns owner-unavailable not hard deletion');
echo "File26 owner-projection PHP contracts PASS" . PHP_EOL;
