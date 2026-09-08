<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
if ( ! defined( 'VWLB_PURGE_CONFIRMED' ) || true !== VWLB_PURGE_CONFIRMED ) return;
if ( ! (bool) get_option( 'vwlb_allow_purge', false ) ) return;

global $wpdb;
$failures = array();
$record_failure = static function ( $code, $context = '' ) use ( &$failures ) {
	$failures[] = array( 'code' => (string) $code, 'context' => (string) $context );
};

$page_map = (array) get_option( 'vwlb_page_map', array() );
foreach ( array_unique( array_map( 'absint', $page_map ) ) as $page_id ) {
	if ( ! $page_id ) continue;
	$post = get_post( $page_id );
	if ( $post && 'page' === $post->post_type && false !== strpos( (string) $post->post_content, '[vwlb_' ) ) {
		if ( false === wp_delete_post( $page_id, true ) && get_post( $page_id ) ) {
			$record_failure( 'page_delete_failed', (string) $page_id );
		}
	}
}

$tables = array(
	'live_poll_responses','live_poll_options','live_polls','transcript_segments','video_annotations','media_tracks','watermark_policies','consent_links','broadcast_health_samples','simulcast_targets','broadcast_guests','production_scenes','production_sources','future_live_config','podcast_episodes','podcast_series','creator_metrics_daily','download_tokens','live_resources','live_questions','live_attendees','chapters','premieres','provider_health','upload_sessions','interactions','playback_sessions','captions','playlist_items','playlists','stream_credentials','live_events','videos','processing_jobs','media_assets','channel_members','channels','moderation','takedowns','webhooks','inbox','outbox','audit','rate_limits','idempotency','rollback_snapshots'
);
foreach ( $tables as $table ) {
	$name = $wpdb->prefix . 'vwlb_' . $table;
	$wpdb->last_error = '';
	$result = $wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
	if ( false === $result || '' !== (string) $wpdb->last_error ) {
		$record_failure( 'table_drop_failed', $name );
		continue;
	}
	$wpdb->last_error = '';
	$remaining = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) );
	if ( '' !== (string) $wpdb->last_error || null !== $remaining ) {
		$record_failure( 'table_drop_unverified', $name );
	}
}

$private = trailingslashit( WP_CONTENT_DIR ) . 'vwlb-private-media';
if ( is_link( $private ) ) {
	$record_failure( 'private_root_symlink_refused', $private );
} elseif ( is_dir( $private ) ) {
	$root = realpath( $private );
	$content = realpath( WP_CONTENT_DIR );
	if ( ! $root || ! $content || ! str_starts_with( $root, trailingslashit( $content ) ) ) {
		$record_failure( 'private_root_unverifiable', $private );
	} else {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $item ) {
			$path = $item->getPathname();
			if ( $item->isLink() || $item->isFile() ) {
				if ( ! @unlink( $path ) && file_exists( $path ) ) $record_failure( 'private_entry_delete_failed', $path );
			} elseif ( $item->isDir() ) {
				if ( ! @rmdir( $path ) && is_dir( $path ) ) $record_failure( 'private_directory_delete_failed', $path );
			}
		}
		if ( is_dir( $root ) && ! @rmdir( $root ) && is_dir( $root ) ) {
			$record_failure( 'private_root_delete_failed', $root );
		}
	}
}

$options = array(
	'vwlb_schema_version','vwlb_ext_schema_version','vwlb_future_schema_version','vwlb_version','vwlb_safe_mode','vwlb_page_map','vwlb_legacy_migration_complete','vwlb_legacy_migration_cursor','vwlb_schema_migration_lock','vwlb_schema_verified_release','vwlb_schema_verified_at','vwlb_schema_verification_lock','vwlb_r10_structural_verified_release','vwlb_r30_evidence_fallback_migration','vwlb_r30_reconcile_cursor_audit','vwlb_r30_reconcile_cursor_outbox','vwlb_retry_reconcile_cursor','vwlb_retry_cleanup_cursor','vwlb_operational_metrics','vwlb_r60_activation_snapshot','vwlb_r61_activation_role_snapshot','vwlb_r69_webhook_reconcile_cursor','vwlb_r76_upload_cleanup_cursor','vwlb_r108_live_reconcile_cursor','vwlb_r108_redundancy_reconcile_cursor','vwlb_r109_consent_expiry_cursor','vwlb_allow_purge','vwlb_uninstall_purge_failed'
);
foreach ( $options as $option ) {
	$sentinel = '__vwlb_missing_' . wp_generate_uuid4();
	$before = get_option( $option, $sentinel );
	if ( $sentinel === $before ) continue;
	$deleted = delete_option( $option );
	$after = get_option( $option, $sentinel );
	if ( ! $deleted && $sentinel !== $after ) {
		$record_failure( 'option_delete_failed', $option );
	} elseif ( $sentinel !== $after ) {
		$record_failure( 'option_delete_unverified', $option );
	}
}

$pattern_deletes = array(
	array( $wpdb->esc_like( 'vwlb_audit_fallback_' ) . '%', $wpdb->esc_like( 'vwlb_outbox_fallback_' ) . '%', $wpdb->esc_like( 'vwlb_inbox_retry_' ) . '%' ),
	array( $wpdb->esc_like( 'vwlb_retry_erasure_cursor_' ) . '%' ),
	array( $wpdb->esc_like( 'vwlb_r60_external_guard_' ) . '%' ),
	array( $wpdb->esc_like( 'vwlb_r105_privacy_cursor_' ) . '%' ),
);
foreach ( $pattern_deletes as $patterns ) {
	$wpdb->last_error = '';
	if ( 3 === count( $patterns ) ) {
		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $patterns[0], $patterns[1], $patterns[2] );
	} else {
		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $patterns[0] );
	}
	$result = $wpdb->query( $sql );
	if ( false === $result || '' !== (string) $wpdb->last_error ) {
		$record_failure( 'pattern_option_delete_failed', implode( ',', $patterns ) );
	}
}

if ( $failures ) {
	$payload = array( 'at' => gmdate( 'c' ), 'failures' => $failures );
	$saved = update_option( 'vwlb_uninstall_purge_failed', $payload, false );
	if ( ! $saved && get_option( 'vwlb_uninstall_purge_failed', null ) !== $payload ) {
		error_log( 'VWLB destructive purge incomplete; failure marker could not be persisted.' );
	} else {
		error_log( 'VWLB destructive purge incomplete; residual state requires operator review.' );
	}
	return;
}

error_log( 'VWLB explicit destructive purge completed after dual confirmation.' );
