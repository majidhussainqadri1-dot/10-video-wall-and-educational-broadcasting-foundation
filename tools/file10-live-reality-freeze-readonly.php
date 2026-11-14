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
if ( is_link( $root ) ) {
    // Even if the expanded files are readable, the deployment root itself must
    // not silently redirect the collector to a different release artifact.
    $report['plugin']['fingerprint_status'] = 'incomplete_symlink_root';
} elseif ( is_dir( $root ) && is_readable( $root ) ) {
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
            if ( $item->isLink() ) {
                // A symlink may reference deployed executable source outside
                // this fingerprint's root. Never call that partial hash complete.
                $truncated = true;
                break;
            }
            if ( ! $item->isFile() ) { continue; }
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


// V12.3: File 10's legacy main branch uses video-wall/video-wall.php.
// Its identity is distinct from the modern candidate above. Never describe an
// active legacy install as "File 10 not installed" merely because a new slug is
// absent. Every checksum is for that candidate's expanded tree, NOT an ZIP.
$legacy_slug = 'video-wall';
$legacy_relative = $legacy_slug . '/video-wall.php';
$legacy_root = WP_PLUGIN_DIR . '/' . $legacy_slug;
$legacy_entry = $legacy_root . '/video-wall.php';
$report['legacy_plugin'] = array(
    'slug' => $legacy_slug,
    'entry_exists' => is_readable( $legacy_entry ),
    'active' => false,
    'network_active' => false,
    'header_version' => 'unverified',
    'expanded_tree_sha256' => null,
    'expanded_tree_file_count' => 0,
    'fingerprint_status' => 'not_collected',
    'file_fingerprints' => array(),
);
if ( function_exists( 'is_plugin_active' ) ) {
    $report['legacy_plugin']['active'] = (bool) is_plugin_active( $legacy_relative );
}
if ( function_exists( 'is_plugin_active_for_network' ) ) {
    $report['legacy_plugin']['network_active'] = (bool) is_plugin_active_for_network( $legacy_relative );
}
if ( is_readable( $legacy_entry ) && function_exists( 'get_file_data' ) ) {
    $header = get_file_data( $legacy_entry, array( 'Version' => 'Version' ), 'plugin' );
    $report['legacy_plugin']['header_version'] =
        substr( (string) ( $header['Version'] ?? '' ), 0, 80 );
}
if ( is_link( $legacy_root ) ) {
    $report['legacy_plugin']['fingerprint_status'] = 'incomplete_symlink_root';
} elseif ( is_dir( $legacy_root ) && is_readable( $legacy_root ) ) {
    // The legacy artifact uses the same no-follow, bounded, reproducible policy.
    $legacy_files = array();
    $legacy_bytes = 0;
    $legacy_incomplete = false;
    $legacy_max_files = 1000;
    $legacy_max_bytes = 50 * 1024 * 1024;
    try {
        $legacy_root_real = realpath( $legacy_root );
        $legacy_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $legacy_root, FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $legacy_iterator as $item ) {
            if ( $item->isLink() ) {
                $legacy_incomplete = true;
                break;
            }
            if ( ! $item->isFile() ) {
                continue;
            }
            $absolute = $item->getPathname();
            $real = realpath( $absolute );
            if ( false === $legacy_root_real || false === $real ||
                ! str_starts_with( $real, rtrim( $legacy_root_real, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) {
                $legacy_incomplete = true;
                break;
            }
            $relative = substr( $absolute, strlen( $legacy_root ) + 1 );
            $bytes = (int) $item->getSize();
            if ( count( $legacy_files ) >= $legacy_max_files || $bytes < 0 ||
                $legacy_bytes + $bytes > $legacy_max_bytes ) {
                $legacy_incomplete = true;
                break;
            }
            $file_sha = hash_file( 'sha256', $absolute );
            if ( false === $file_sha ) {
                $legacy_incomplete = true;
                break;
            }
            $legacy_files[$relative] = array( 'bytes' => $bytes, 'sha256' => $file_sha );
            $legacy_bytes += $bytes;
        }
        ksort( $legacy_files, SORT_STRING );
        $manifest = array();
        foreach ( $legacy_files as $name => $entry ) {
            $manifest[] = $name . '|' . $entry['bytes'] . '|' . $entry['sha256'];
        }
        $report['legacy_plugin']['fingerprint_status'] = $legacy_incomplete ? 'incomplete' : 'complete';
        $report['legacy_plugin']['expanded_tree_sha256'] = $legacy_incomplete
            ? null : hash( 'sha256', implode( "\n", $manifest ) );
        $report['legacy_plugin']['expanded_tree_file_count'] = count( $legacy_files );
        $report['legacy_plugin']['bytes_fingerprinted'] = $legacy_bytes;
        $report['legacy_plugin']['file_fingerprints'] = $legacy_files;
    } catch ( Throwable $e ) {
        $report['legacy_plugin']['fingerprint_status'] = 'filesystem_read_error';
    }
}
if ( function_exists( 'is_plugin_active' ) ) {
    $modern_is_active = $report['plugin']['active'] || $report['plugin']['network_active'];
    $legacy_is_active = $report['legacy_plugin']['active'] || $report['legacy_plugin']['network_active'];
    $report['file10_active_plugin_identity'] = $modern_is_active && $legacy_is_active
        ? 'ambiguous_both_active' : ( $modern_is_active ? 'modern' : ( $legacy_is_active ? 'legacy' : 'none_active' ) );
} else {
    $report['file10_active_plugin_identity'] = 'activity_unverified';
}
// Neither the collector nor source metadata may promote any candidate to
// deployed parity: only a separately checked package/tree comparison can.

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
