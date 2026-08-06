<?php
/**
 * Plugin runtime coordinator.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Plugin {
	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( 'SVW_Activator', 'register' ) );
		add_action( 'init', array( 'SVW_Activator', 'maybe_upgrade' ), 20 );
		( new SVW_Frontend() )->hooks();
		( new SVW_Interactions() )->hooks();
		( new SVW_Admin() )->hooks();
		( new SVW_Privacy() )->hooks();
		( new SVW_SEO() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	/**
	 * Enqueue public assets only on Video Wall surfaces.
	 *
	 * @return void
	 */
	public function assets() {
		global $post;
		$map = (array) get_option( 'svw_page_map', array() );
		if ( ! is_singular( SVW_Helpers::TYPE ) && ! ( $post instanceof WP_Post && in_array( $post->ID, array_map( 'absint', $map ), true ) ) ) {
			return;
		}

		wp_enqueue_style( 'svw', SVW_URL . 'assets/css/video-wall.css', array(), SVW_VERSION );
		wp_enqueue_script( 'svw', SVW_URL . 'assets/js/video-wall.js', array(), SVW_VERSION, true );
		wp_localize_script(
			'svw',
			'svwData',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'svw_action' ),
				'i18n'  => array(
					'error'       => 'The request could not be completed. Please try again.',
					'reportSent'  => 'Report received.',
					'saving'      => 'Saving…',
				),
			)
		);
	}

	/**
	 * Enqueue admin CSS only for the owned menus.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function admin_assets( $hook_suffix ) {
		if ( false !== strpos( $hook_suffix, 'video-wall' ) ) {
			wp_enqueue_style( 'svw-admin', SVW_URL . 'assets/css/admin.css', array(), SVW_VERSION );
		}
	}
}
