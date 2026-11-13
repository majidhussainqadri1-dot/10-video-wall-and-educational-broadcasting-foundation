<?php
/**
 * File 10 V12.1 G01: authorized, read-only WP-CLI deployed-state evidence.
 *
 * Usage after authorized staging/live access: wp eval-file /path/to/this/file.php
 * No mutation, credentials, patient rows, logs, network requests or parity claims.
 */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || true !== WP_CLI ) {
    fwrite( STDERR, "REFUSED: File10 deployed-state snapshot requires authorized WP-CLI.\n" );
    exit( 2 );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) || ! isset( $GLOBALS['wpdb'] ) ) {
    fwrite( STDERR, "REFUSED: WordPress plugin/database context is unavailable.\n" );
    exit( 2 );
}

global $wpdb, $wp_version;
$slug     = 'video-wall-and-live-broadcasting';
$relative = $slug . '/video-wall-and-live-broadcasting.php';
$root     = WP_PLUGIN_DIR . '/' . $slug;
$entry    = $root . '/video-wall-and-live-broadcasting.php';

$report = array(
    'report_kind'                     => 'File10V12.1DeployedStateReadOnly.v1',
    'collected_at_utc'                => gmdate( 'c' ),
    'read_only'                       => true,
    'source'                          => 'authorized_wp_cli_runtime',
    'deployed_vs_repository_parity'   => 'not_compared',
    'deployed_zip_checksum_status'    => 'not_available_from_expanded_tree',
    'live_verification_status'        => 'snapshot_only',
    'runtime_logs_status'             => 'not_read_private_log_review_required',
    'wordpress_version'               => (string) ( $wp_version ?? '' ),
    'php_version'                     => PHP_VERSION,
    'database_server_version'         => method_exists( $wpdb, 'db_version' )
        ? substr( (string) $wpdb->db_version(), 0, 80 ) : 'unavailable',
    'wordPress_environment'           => function_exists( 'wp_get_environment_type' )
        ? (string) wp_get_environment_type() : 'unknown',
    'runtime_flags'                   => array(
        'wp_debug_enabled'        => defined( 'WP_DEBUG' ) && true === WP_DEBUG,
        'wp_debug_log_enabled'    => defined( 'WP_DEBUG_LOG' ) && (bool) WP_DEBUG_LOG,
        'php_openssl_loaded'      => extension_loaded( 'openssl' ),
        'php_mbstring_loaded'     => extension_loaded( 'mbstring' ),
    ),
    'plugin'                          => array(
        'slug' => $slug,
        'entry_exists' => is_readable( $entry ),
        'active' => false,
        'network_active' => false,
        'header_version' => 'unverified',
        'expanded_tree_sha256' => null,
        'expanded_tree_file_count' => 0,
        'fingerprint_status' => 'not_collected',
        'file_fingerprints' => array(),
    ),
    'allowlisted_wp_options' => array(),
    'file10_tables' => array(),
);

$plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_readable( $plugin_api ) ) {
    require_once $plugin_api; // WP core's read-only plugin metadata helpers.
}
if ( function_exists( 'is_plugin_active' ) ) {
    $report['plugin']['active'] = (bool) is_plugin_active( $relative );
}
if ( function_exists( 'is_plugin_active_for_network' ) ) {
    $report['plugin']['network_active'] = (bool) is_plugin_active_for_network( $relative );
}
if ( is_readable( $entry ) && function_exists( 'get_file_data' ) ) {
    $header = get_file_data( $entry, array( 'Version' => 'Version' ), 'plugin' );
    $report['plugin']['header_version'] = substr( (string) ( $header['Version'] ?? '' ), 0, 80 );
}

// A deterministic deployed-file-tree fingerprint is NOT an installed ZIP SHA.
// Never follow symlinks or output file contents; cap files/bytes scanned.
if ( is_dir( $root ) && is_readable( $root ) ) {
    $files = array();
    $bytes_scanned = 0;
    $limit_files = 1000;
    $limit_bytes = 50 * 1024 * 1024;
    $truncated = false;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $item ) {
            if ( $item->isLink() || ! $item->isFile() ) { continue; }
            $path = $item->getPathname();
            $real_root = realpath( $root );
            $real_path = realpath( $path );
            if ( false === $real_root || false === $real_path ||
                ! str_starts_with( $real_path, rtrim( $real_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) {
                continue;
            }
            $relative_path = substr( $path, strlen( $root ) + 1 );
            $size = (int) $item->getSize();
            if ( count( $files ) >= $limit_files || $size < 0 ||
                $bytes_scanned + $size > $limit_bytes ) {
                $truncated = true;
                break;
            }
            $sha = hash_file( 'sha256', $path );
            if ( false === $sha ) { $truncated = true; break; }
            $files[$relative_path] = array( 'bytes' => $size, 'sha256' => $sha );
            $bytes_scanned += $size;
        }
        ksort( $files, SORT_STRING );
        $manifest = array();
        foreach ( $files as $name => $item ) {
            $manifest[] = $name . '|' . $item['bytes'] . '|' . $item['sha256'];
        }
        $report['plugin']['fingerprint_status'] = $truncated ? 'incomplete' : 'complete';
        $report['plugin']['expanded_tree_sha256'] = $truncated ? null :
            hash( 'sha256', implode( "\n", $manifest ) );
        $report['plugin']['expanded_tree_file_count'] = count( $files );
        $report['plugin']['bytes_fingerprinted'] = $bytes_scanned;
        $report['plugin']['file_fingerprints'] = $files;
    } catch ( Throwable $e ) {
        $report['plugin']['fingerprint_status'] = 'filesystem_read_error';
    }
}

// Only nonsecret schema/version/flag options. Never reveal migration-lock tokens,
// serialized option payloads, provider credentials, signed URLs or identities.
$version_options = array(
    'vwlb_version',
    'vwlb_schema_version',
    'vwlb_ext_schema_version',
    'vwlb_future_schema_version',
    'vwlb_schema_verified_release',
    'vwlb_schema_verified_at',
);
foreach ( $version_options as $key ) {
    $value = get_option( $key, null );
    $report['allowlisted_wp_options'][$key] =
        ( is_scalar( $value ) || null === $value )
        ? substr( (string) ( $value ?? 'missing' ), 0, 100 )
        : 'non_scalar_value_not_disclosed';
}
$report['allowlisted_wp_options']['vwlb_safe_mode'] = (bool) get_option( 'vwlb_safe_mode', false );
foreach ( array( 'vwlb_schema_migration_lock', 'vwlb_schema_verification_lock' ) as $lock ) {
    $report['allowlisted_wp_options'][$lock . '_present'] =
        false !== get_option( $lock, false ); // Deliberately no token contents.
}

// Table existence, column names/shape hashes and bounded ID samples only.
// Never SELECT patient fields, media tokens, private captions or raw DB rows.
$names = array(
    'channels','channel_members','playlists','playlist_items','media_assets',
    'processing_jobs','videos','captions','live_events','stream_credentials',
    'playback_sessions','interactions','moderation','takedowns','audit',
    'outbox','inbox','webhooks','idempotency','rate_limits','rollback_snapshots',
    'upload_sessions','chapters','podcast_series','podcast_episodes',
    'live_attendees','live_questions','creator_metrics_daily',
    'future_live_config','production_sources','production_scenes',
    'broadcast_guests','simulcast_targets','broadcast_health_samples',
    'media_tracks','transcript_segments','video_annotations','live_polls',
    'live_poll_options','live_poll_responses','consent_links','watermark_policies'
);
foreach ( $names as $short ) {
    $table = $wpdb->prefix . 'vwlb_' . $short;
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $table ) ) {
        $report['file10_tables'][$short] = array( 'status' => 'invalid_prefix' );
        continue;
    }
    $wpdb->last_error = '';
    $found = $wpdb->get_var( $wpdb->prepare(
        'SHOW TABLES LIKE %s', $wpdb->esc_like( $table )
    ) );
    if ( '' !== (string) $wpdb->last_error ) {
        $report['file10_tables'][$short] = array( 'status' => 'existence_read_error' );
        continue;
    }
    if ( $found !== $table ) {
        $report['file10_tables'][$short] = array( 'status' => 'missing' );
        continue;
    }
    $identifier = chr( 96 ) . $table . chr( 96 );
    $wpdb->last_error = '';
    $cols = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $identifier, ARRAY_A );
    if ( ! is_array( $cols ) || '' !== (string) $wpdb->last_error ) {
        $report['file10_tables'][$short] = array( 'status' => 'schema_read_error' );
        continue;
    }
    $names_only = array();
    foreach ( $cols as $col ) {
        $names_only[] = (string) ( $col['Field'] ?? '' );
    }
    $metadata = array(
        'status' => 'readable',
        'column_count' => count( $cols ),
        'column_names' => $names_only,
        'schema_sha256' => hash( 'sha256', wp_json_encode( $cols ) ),
        'primary_key_sample_count' => null,
        'sample_limit' => 1001,
    );
    if ( in_array( 'id', $names_only, true ) ) {
        $wpdb->last_error = '';
        $ids = $wpdb->get_col( 'SELECT id FROM ' . $identifier . ' ORDER BY id ASC LIMIT 1001' );
        if ( is_array( $ids ) && '' === (string) $wpdb->last_error ) {
            $metadata['primary_key_sample_count'] = count( $ids );
            $metadata['sample_limit_reached'] = count( $ids ) >= 1001;
        } else {
            $metadata['status'] = 'row_sample_read_error';
        }
    }
    $report['file10_tables'][$short] = $metadata;
}
fwrite( STDOUT, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
