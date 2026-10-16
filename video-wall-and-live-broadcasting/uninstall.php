<?php
/** Non-destructive by default. Purge requires a separately guarded option. */
defined('WP_UNINSTALL_PLUGIN')||exit;
if(!get_option('vwlb_allow_purge'))return;
if(!defined('VWLB_PURGE_CONFIRM')||true!==VWLB_PURGE_CONFIRM)return;
global $wpdb;
$tables=array('channel_members','playlist_items','captions','processing_jobs','stream_credentials','playback_sessions','interactions','moderation','takedowns','audit','outbox','inbox','webhooks','idempotency','rate_limits','rollback_snapshots','playlists','channels','media_assets','videos','live_events');
foreach($tables as $name)$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'vwlb_'.$name);
foreach(array('vwlb_version','vwlb_schema_version','vwlb_page_map','vwlb_safe_mode','vwlb_allow_purge','vwlb_legacy_migration_complete') as $option)delete_option($option);
