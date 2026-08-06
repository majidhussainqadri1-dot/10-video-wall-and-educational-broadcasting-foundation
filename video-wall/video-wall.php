<?php
/**
 * Plugin Name: Video Wall and Educational Broadcasting Foundation
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Moderated American English educational video publishing, discovery, reactions, saves, viewing history, reports, audit trails, privacy controls, and VideoObject data.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * License: GPL-2.0-or-later
 * Text Domain: video-wall
 */

defined( 'ABSPATH' ) || exit;

define( 'SVW_VERSION', '0.2.0' );
define( 'SVW_FILE', __FILE__ );
define( 'SVW_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVW_URL', plugin_dir_url( __FILE__ ) );

require_once SVW_DIR . 'includes/class-svw-helpers.php';
require_once SVW_DIR . 'includes/class-svw-activator.php';
require_once SVW_DIR . 'includes/class-svw-frontend.php';
require_once SVW_DIR . 'includes/class-svw-interactions.php';
require_once SVW_DIR . 'includes/class-svw-admin.php';
require_once SVW_DIR . 'includes/class-svw-privacy.php';
require_once SVW_DIR . 'includes/class-svw-seo.php';
require_once SVW_DIR . 'includes/class-svw-plugin.php';

register_activation_hook( SVW_FILE, array( 'SVW_Activator', 'activate' ) );
register_deactivation_hook( SVW_FILE, array( 'SVW_Activator', 'deactivate' ) );

/**
 * Start File 10 after companion plugins have loaded.
 *
 * @return void
 */
function svw_start() {
	( new SVW_Plugin() )->run();
}
add_action( 'plugins_loaded', 'svw_start', 50 );
