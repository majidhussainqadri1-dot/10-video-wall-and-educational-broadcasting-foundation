<?php
/**
 * Plugin Name: Video Wall and Live Broadcasting
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: File 10 canonical owner for recorded videos, channels, playlists, captions, playback, live events, stream authorization, moderation, recording/replay, rights and provider adapters.
 * Version: 1.0.0-rc1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * License: GPL-2.0-or-later
 * Text Domain: video-wall-live-broadcasting
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'VWLB_VERSION', '1.0.0-rc1' );
define( 'VWLB_SCHEMA_VERSION', '1.0.0' );
define( 'VWLB_FILE', __FILE__ );
define( 'VWLB_DIR', plugin_dir_path( __FILE__ ) );
define( 'VWLB_URL', plugin_dir_url( __FILE__ ) );
define( 'VWLB_TEXT_DOMAIN', 'video-wall-live-broadcasting' );

require_once VWLB_DIR . 'includes/class-vwlb-core.php';
require_once VWLB_DIR . 'includes/class-vwlb-media-video.php';
require_once VWLB_DIR . 'includes/class-vwlb-live-operations.php';
require_once VWLB_DIR . 'includes/class-vwlb-governance.php';
require_once VWLB_DIR . 'includes/class-vwlb-application.php';

register_activation_hook( VWLB_FILE, array( 'VWLB_Activator', 'activate' ) );
register_deactivation_hook( VWLB_FILE, array( 'VWLB_Activator', 'deactivate' ) );

function vwlb_boot() {
	VWLB_Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'vwlb_boot', 40 );
