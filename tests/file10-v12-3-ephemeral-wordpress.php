<?php
/**
 * File10 V12.3 ephemeral ONLY: real WordPress/MariaDB smoke.
 * Executes inside throwaway Docker containers. Does not know any production host
 * or credential and MUST NOT be used as a deployment/acceptance script.
 */
declare(strict_types=1);

$phase = $argv[1] ?? '';
if (!in_array($phase, array('install', 'activate', 'verify'), true)) {
    fwrite(STDERR, "F10-EPHEMERAL FAIL: unsupported phase\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'file10-ephemeral.invalid';
$_SERVER['SERVER_NAME'] = 'file10-ephemeral.invalid';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS'] = 'off';
if ($phase === 'install') {
    define('WP_INSTALLING', true);
}
require '/var/www/html/wp-load.php';

$deny = static function(string $reason): void {
    fwrite(STDERR, 'F10-EPHEMERAL FAIL: '.$reason.PHP_EOL);
    exit(2);
};
$confirm = static function(bool $ok,string $reason) use($deny): void {
    if (!$ok) $deny($reason);
};
$plugin = 'video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php';

if ($phase === 'install') {
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $confirm(!is_blog_installed(), 'test instance unexpectedly preinstalled');
    $pwd = getenv('QA_F10_ADMIN_PASSWORD');
    $confirm(is_string($pwd) && strlen($pwd) >= 24, 'ephemeral-only credential unavailable');
    $result = wp_install(
        'File10 Isolated WordPress QA', 'file10_qa_admin',
        'file10-ephemeral@example.invalid', false, '', $pwd
    );
    $confirm(is_array($result) && (int)($result['user_id'] ?? 0) > 0, 'fresh WordPress installation failed');
    echo "F10-EPHEMERAL INSTALL PASS: isolated WordPress core installed; no real users\n";
    exit(0);
}
require_once ABSPATH.'wp-admin/includes/plugin.php';

if ($phase === 'activate') {
    $confirm(!is_plugin_active($plugin), 'test plugin was unexpectedly already active');
    $result = activate_plugin($plugin);
    if (is_wp_error($result)) {
        $deny('WordPress plugin activation rejected: '.sanitize_key($result->get_error_code()));
    }
    $confirm(is_plugin_active($plugin), 'activation did not persist active plugin state');
    echo "F10-EPHEMERAL ACTIVATE PASS: real plugin activation completed\n";
    exit(0);
}
$confirm(is_plugin_active($plugin), 'expected active test plugin unavailable');
$confirm(class_exists('VWLB_Activator') && class_exists('VWLB_Future_Intelligence'),
    'core class loading did not occur on plugins_loaded');
$confirm(defined('VWLB_VERSION') && VWLB_VERSION === '1.2.25-rc1',
    'candidate plugin version not the expected source release');
global $wp_version, $wpdb;
$confirm(version_compare((string)$wp_version, '7.0.1', '>='), 'WordPress below frozen 7.0.1 minimum');
$confirm(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP below V12.3 baseline');
$confirm((string)get_option('vwlb_version') === VWLB_VERSION,
    'the actual active plugin version option differs from code');
$confirm((string)get_option('vwlb_schema_version') === VWLB_SCHEMA_VERSION,
    'base schema option was not verified');
$confirm((string)get_option('vwlb_future_schema_version') === VWLB_FUTURE_SCHEMA_VERSION,
    'Future schema option was not verified');
$confirm((string)get_option('vwlb_ext_schema_version') === VWLB_EXT_SCHEMA_VERSION,
    'extension schema option was not verified');

foreach (array(
    'channels', 'videos', 'live_events', 'outbox', 'media_tracks',
    'transcript_segments', 'live_polls', 'live_poll_options',
    'live_poll_responses', 'video_annotations', 'consent_links'
) as $name) {
    $table = $wpdb->prefix.'vwlb_'.$name;
    $wpdb->last_error = '';
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    $confirm('' === (string)$wpdb->last_error && $found === $table,
        'expected table absent or unverifiable: '.$name);
}
// Verify a fresh reconcile is idempotently safe in the isolated throwaway DB.
$reconciled = VWLB_Activator::reconcile_schema();
$confirm($reconciled === true, 'a second schema reconciliation failed');
$confirm((string)get_option('vwlb_schema_version') === VWLB_SCHEMA_VERSION,
    'base schema changed after idempotent reconciliation');
$confirm((string)get_option('vwlb_future_schema_version') === VWLB_FUTURE_SCHEMA_VERSION,
    'Future schema changed after idempotent reconciliation');
$server = rest_get_server();
$endpoint = '/'.VWLB_Contracts::CANONICAL_API_NAMESPACE.'/future/capabilities';
$routes = $server->get_routes();
$confirm(isset($routes[$endpoint]), 'Future capabilities REST route was not registered');
$public = rest_do_request(new WP_REST_Request('GET', $endpoint));
$confirm($public instanceof WP_REST_Response && $public->get_status() === 200,
    'Future capabilities public REST request did not return 200');
$payload = $public->get_data();
$confirm(is_array($payload) && ($payload['capability_semantics'] ?? '') === 'implementation_presence_not_runtime_readiness',
    'source implementation presence must not be falsely advertised as provider runtime readiness');
$confirm(($payload['runtime_readiness']['overall'] ?? '') === 'unverified',
    'ephemeral QA must never report real external provider readiness');

$protected = '/'.VWLB_Contracts::CANONICAL_API_NAMESPACE.'/live-events/live_abc123/polls';
$rejected = rest_do_request(new WP_REST_Request('POST', $protected));
$confirm($rejected instanceof WP_REST_Response && in_array($rejected->get_status(), array(401,403), true),
    'anonymous user was not denied on a protected poll creation route');

// True missing objects and unauthenticated users remain correctly classified.
$notFound = VWLB_Future_Intelligence::search_transcript('vid_absent', 'source');
$confirm(is_wp_error($notFound) && (int)($notFound->get_error_data()['status'] ?? 0) === 404,
    'absent video search did not fail closed in real WordPress');
$unauth = VWLB_Future_Intelligence::answer_poll('poll_absent', array('opt_absent'));
$confirm(is_wp_error($unauth) && (int)($unauth->get_error_data()['status'] ?? 0) === 401,
    'unauthenticated poll answer did not fail closed in real WordPress');

echo 'F10-EPHEMERAL VERIFY PASS: WP '.(string)$wp_version.' / PHP '.PHP_VERSION.
     '; 11 tables, 3 schema options, idempotent reconcile, public REST, denied poll create; external Staging/Live UNVERIFIED'.PHP_EOL;
