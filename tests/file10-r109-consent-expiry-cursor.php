<?php
/** R109: 100-row patient consent expiry page must not skip an errored ID. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    private $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$value)); }
function get_option($key, $default = 0) {
    return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default;
}
function update_option($key, $value, $autoload = false) {
    $GLOBALS['options'][$key] = $value;
    return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function do_action($hook, ...$args) {
    if ($hook === 'vwlb_operational_failure') { $GLOBALS['failure_reports'][] = $args; }
}
class VWLB_Helpers {
    public static function table($name) { return 'test_vwlb_' . $name; }
    public static function now() { return '2026-11-13 00:00:00'; }
}
class MockConsentDB {
    public $last_error = '';
    public $rows = array();
    public $requested_cursor = array();
    public function __construct() {
        for ($id=1; $id<=150; ++$id) { $this->rows[] = array('id'=>$id); }
    }
    public function prepare($sql, ...$args) { return array('sql'=>$sql,'args'=>$args); }
    public function get_results($query, $output = null) {
        $after = (int) $query['args'][1];
        $limit = (int) $query['args'][2];
        $this->requested_cursor[] = $after;
        return array_slice(array_values(array_filter($this->rows,
            static function($row) use($after){return $row['id']>$after;})),0,$limit);
    }
}
class VWLB_DB {
    public static $processed = array();
    public static $failed_id = 2;
    public static function transaction($handler) {
        $captures=(new ReflectionFunction($handler))->getStaticVariables();
        $id=(int)($captures['id']??0);
        self::$processed[]=$id;
        if (self::$failed_id === $id) {
            return new WP_Error('simulated_consent_expiry_db_outage', 'Fault injected at canonical consent row');
        }
        return array('changed'=>false);
    }
}
$GLOBALS['options'] = array();
$GLOBALS['failure_reports'] = array();
$wpdb = new MockConsentDB();

require __DIR__ . '/../video-wall-and-live-broadcasting/includes/class-vwlb-r109-rights-consent-replay-guard.php';
function require_case($ok,$message) {
    if(!$ok){fwrite(STDERR,"File10 consent cursor FAIL: ".$message.PHP_EOL);exit(1);}
}
$cursor_key=VWLB_R109_Rights_Consent_Replay_Guard::CONSENT_CURSOR_OPTION;
VWLB_R109_Rights_Consent_Replay_Guard::reconcile_consent_expiry();
require_case(get_option($cursor_key,0)===1,'first 100-row page with row2 failure must persist cursor1, never skip row2');
require_case(in_array(2,VWLB_DB::$processed,true),'failed consent #2 was attempted');
require_case(count($GLOBALS['failure_reports'])===1,'first failure reports an operational signal');
require_case(count(VWLB_DB::$processed)<=2,'no continuation after first unsafe failure');
VWLB_DB::$processed=array();
VWLB_R109_Rights_Consent_Replay_Guard::reconcile_consent_expiry();
require_case($wpdb->requested_cursor[count($wpdb->requested_cursor)-1]===1,'next scheduled batch starts after last successfully handled ID');
require_case(isset(VWLB_DB::$processed[0]) && VWLB_DB::$processed[0]===2,'failed consent ID is retried at next scan');
VWLB_DB::$failed_id=null;
VWLB_DB::$processed=array();
VWLB_R109_Rights_Consent_Replay_Guard::reconcile_consent_expiry();
require_case(get_option($cursor_key,0)===101,'after recovery, 100 successful rows permit bounded cursor advancement');
require_case(VWLB_DB::$processed[0]===2 && end(VWLB_DB::$processed)===101,'recovery processes originally failed row and bounded remainder');
echo "File10 consent expiry full-page failure cursor regression PASS (retry without silent skip).".PHP_EOL;
