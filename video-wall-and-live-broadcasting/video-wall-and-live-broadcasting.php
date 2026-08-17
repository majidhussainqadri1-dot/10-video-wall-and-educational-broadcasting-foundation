<?php
/**
 * Plugin Name: Video Wall and Live Broadcasting
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: File 10 canonical owner for recorded video, channels, podcasts, media ingest/processing, captions/transcripts, playback, live events, stream authorization, moderation, recording/replay, rights, provider adapters, File 11 media contracts, and Future Video & Broadcasting Intelligence 24 enhancements.
 * Version: 1.2.5-rc1
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * License: GPL-2.0-or-later
 * Text Domain: video-wall-live-broadcasting
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

define( 'VWLB_VERSION', '1.2.5-rc1' );
define( 'VWLB_SCHEMA_VERSION', '1.1.0' );
define( 'VWLB_EXT_SCHEMA_VERSION', '1.1.0' );
define( 'VWLB_FUTURE_SCHEMA_VERSION', '1.2.0' );
define( 'VWLB_FILE', __FILE__ );
define( 'VWLB_DIR', plugin_dir_path( __FILE__ ) );
define( 'VWLB_URL', plugin_dir_url( __FILE__ ) );
define( 'VWLB_TEXT_DOMAIN', 'video-wall-live-broadcasting' );

$autoload = array(
	'class-vwlb-contracts.php', 'class-vwlb-helpers.php', 'class-vwlb-security.php',
	'class-vwlb-state-machine.php', 'class-vwlb-db.php', 'class-vwlb-activator.php',
	'class-vwlb-providers.php', 'class-vwlb-repository.php', 'class-vwlb-media.php',
	'class-vwlb-videos.php', 'class-vwlb-live.php', 'class-vwlb-moderation.php',
	'class-vwlb-jobs.php', 'class-vwlb-extensions.php', 'class-vwlb-podcasts.php',
	'class-vwlb-observability.php', 'class-vwlb-rest.php', 'class-vwlb-extended-rest.php',
	'class-vwlb-future-intelligence.php', 'class-vwlb-future-safety.php', 'class-vwlb-review-hardening.php', 'class-vwlb-sequential-review-hardening.php', 'class-vwlb-r10-integrity.php', 'class-vwlb-r11-restore-guard.php', 'class-vwlb-r18-durability.php', 'class-vwlb-r20-retry-privacy.php', 'class-vwlb-r3-playback.php', 'class-vwlb-r4-migration-guard.php', 'class-vwlb-future-adapters.php', 'class-vwlb-future-rest.php', 'class-vwlb-future-frontend.php',
	'class-vwlb-frontend.php', 'class-vwlb-admin.php', 'class-vwlb-privacy.php',
	'class-vwlb-seo.php', 'class-vwlb-integrations.php', 'class-vwlb-compatibility.php',
	'class-vwlb-diagnostics.php', 'class-vwlb-plugin.php',
);
foreach ( $autoload as $file ) {
	require_once VWLB_DIR . 'includes/' . $file;
}

register_activation_hook( VWLB_FILE, array( 'VWLB_Activator', 'activate' ) );
register_deactivation_hook( VWLB_FILE, array( 'VWLB_Activator', 'deactivate' ) );

function vwlb_boot() {
	// R21: do not register mutation/reconciliation hardening surfaces when the core boot failed (for example, while a schema migration lock is active).
	if ( true !== VWLB_Plugin::instance()->run() ) return;
	VWLB_R10_Integrity::register();
	VWLB_R11_Restore_Guard::register();
	VWLB_R18_Durability::register();
	VWLB_R20_Retry_Privacy::register();
	VWLB_Sequential_Review_Hardening::register();
}
add_action( 'plugins_loaded', 'vwlb_boot', 40 );
