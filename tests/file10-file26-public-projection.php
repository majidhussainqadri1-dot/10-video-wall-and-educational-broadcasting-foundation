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
}
class VWLB_Security {
    public static function can_view($row, $purpose = '') {
        return is_array($row) && 'public' === ($row['visibility'] ?? '');
    }
}
class VWLB_Repository {
    public static $owners = array();
    public static function find($entity, $public_id) {
        return self::$owners[$entity][(string) $public_id] ?? null;
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
$GLOBALS['fake_safe_mode'] = true;
check_file10(VWLB_File26_Connector::can_view($doc) === false, 'safe mode denies cached search result');
check_file10(VWLB_File26_Connector::list_batch('', 20) instanceof WP_Error, 'safe mode denies snapshot');
echo "File26 owner-projection PHP contracts PASS" . PHP_EOL;
