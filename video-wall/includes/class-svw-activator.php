<?php
/**
 * Registration, activation, and schema upgrades.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Activator {
	const SCHEMA_VERSION = '2';

	/**
	 * Register the video post type and taxonomy.
	 *
	 * The admin UI is deliberately restricted to manage_video_wall. Verified
	 * doctors publish only through the controlled frontend moderation workflow.
	 *
	 * @return void
	 */
	public static function register() {
		$capabilities = array(
			'edit_post'              => 'manage_video_wall',
			'read_post'              => 'read',
			'delete_post'            => 'manage_video_wall',
			'edit_posts'             => 'manage_video_wall',
			'edit_others_posts'      => 'manage_video_wall',
			'publish_posts'          => 'manage_video_wall',
			'read_private_posts'     => 'manage_video_wall',
			'delete_posts'           => 'manage_video_wall',
			'delete_private_posts'   => 'manage_video_wall',
			'delete_published_posts' => 'manage_video_wall',
			'delete_others_posts'    => 'manage_video_wall',
			'edit_private_posts'     => 'manage_video_wall',
			'edit_published_posts'   => 'manage_video_wall',
			'create_posts'           => 'manage_video_wall',
		);

		register_post_type(
			SVW_Helpers::TYPE,
			array(
				'labels'             => array(
					'name'          => 'Video Wall',
					'singular_name' => 'Video',
				),
				'public'             => true,
				'show_ui'            => true,
				'show_in_rest'       => false,
				'has_archive'        => 'educational-videos',
				'rewrite'            => array( 'slug' => 'video' ),
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'comments', 'revisions' ),
				'taxonomies'         => array( SVW_Helpers::TAX ),
				'capabilities'       => $capabilities,
				'map_meta_cap'       => false,
				'publicly_queryable' => true,
			)
		);

		register_taxonomy(
			SVW_Helpers::TAX,
			SVW_Helpers::TYPE,
			array(
				'labels'       => array( 'name' => 'Video Categories' ),
				'public'       => true,
				'show_ui'      => false,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'video-category' ),
			)
		);
	}

	/**
	 * Activate or upgrade the plugin safely.
	 *
	 * @return void
	 */
	public static function activate() {
		self::register();
		self::grant_capability();
		self::create_terms();
		self::create_tables();
		self::configure_pages();
		update_option( 'svw_version', SVW_VERSION, false );
		update_option( 'svw_schema_version', self::SCHEMA_VERSION, false );
		set_transient( 'svw_notice', '1', 120 );
		flush_rewrite_rules();
	}

	/**
	 * Apply idempotent upgrades after a plugin update.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== (string) get_option( 'svw_schema_version', '' ) ) {
			self::grant_capability();
			self::create_terms();
			self::create_tables();
			self::configure_pages();
			update_option( 'svw_schema_version', self::SCHEMA_VERSION, false );
		}
		if ( SVW_VERSION !== (string) get_option( 'svw_version', '' ) ) {
			update_option( 'svw_version', SVW_VERSION, false );
		}
	}

	/**
	 * Add the administrative capability.
	 *
	 * @return void
	 */
	private static function grant_capability() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_video_wall' );
		}
	}

	/**
	 * Seed fixed categories.
	 *
	 * @return void
	 */
	private static function create_terms() {
		foreach ( SVW_Helpers::categories() as $slug => $name ) {
			if ( ! get_term_by( 'slug', $slug, SVW_Helpers::TAX ) ) {
				wp_insert_term( $name, SVW_Helpers::TAX, array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Create or upgrade owned database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}svw_reactions (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				video_id bigint(20) unsigned NOT NULL,
				reaction varchar(10) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_video (user_id,video_id),
				KEY video_id (video_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}svw_saves (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				video_id bigint(20) unsigned NOT NULL,
				progress int unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_video (user_id,video_id),
				KEY video_id (video_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}svw_reports (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				video_id bigint(20) unsigned NOT NULL,
				reason varchar(40) NOT NULL,
				details text NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'open',
				created_at datetime NOT NULL,
				updated_at datetime NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY video_id (video_id),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}svw_history (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				video_id bigint(20) unsigned NOT NULL,
				progress int unsigned NOT NULL DEFAULT 0,
				duration int unsigned NOT NULL DEFAULT 0,
				completed tinyint(1) unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_video (user_id,video_id),
				KEY video_id (video_id),
				KEY updated_at (updated_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}svw_audit (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				object_type varchar(30) NOT NULL,
				object_id bigint(20) unsigned NOT NULL,
				action varchar(40) NOT NULL,
				previous_state varchar(30) NOT NULL DEFAULT '',
				new_state varchar(30) NOT NULL DEFAULT '',
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				note text NOT NULL,
				metadata longtext NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY object_lookup (object_type,object_id),
				KEY actor_id (actor_id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/**
	 * Configure plugin pages without overwriting unrelated page content.
	 *
	 * @return void
	 */
	private static function configure_pages() {
		$spf      = (array) get_option( 'spf_page_map', array() );
		$map      = (array) get_option( 'svw_page_map', array() );
		$snapshot = (array) get_option( 'svw_activation_snapshot', array() );

		if ( empty( $snapshot ) ) {
			$snapshot = array(
				'spf_page_map' => $spf,
				'svw_page_map' => $map,
				'captured_at'  => current_time( 'mysql', true ),
			);
			update_option( 'svw_activation_snapshot', $snapshot, false );
		}

		$preferred_wall = ! empty( $spf['videos'] ) ? absint( $spf['videos'] ) : 0;
		$map['wall']     = self::ensure_page( $preferred_wall, 'Video Wall', 'video-wall', '[svw_video_wall]' );
		$map['create']   = self::ensure_page( 0, 'Create Video Publication', 'create-video-publication', '[svw_create_video]' );
		$map['saved']    = self::ensure_page( 0, 'Saved Videos', 'saved-videos', '[svw_saved_videos]' );
		$map['history']  = self::ensure_page( 0, 'Video History', 'video-history', '[svw_video_history]' );

		update_option( 'svw_page_map', $map, false );
		if ( ! empty( $map['wall'] ) ) {
			$spf['videos'] = $map['wall'];
			update_option( 'spf_page_map', $spf, false );
		}
	}

	/**
	 * Find or create a page while preserving unrelated content.
	 *
	 * @param int    $preferred_id Preferred existing page.
	 * @param string $title        Page title.
	 * @param string $slug         Preferred slug.
	 * @param string $shortcode    Required shortcode.
	 * @return int
	 */
	private static function ensure_page( $preferred_id, $title, $slug, $shortcode ) {
		$candidates = array();
		if ( $preferred_id ) {
			$candidates[] = get_post( $preferred_id );
		}
		$candidates[] = get_page_by_path( $slug );

		foreach ( $candidates as $page ) {
			if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type ) {
				continue;
			}

			if ( has_shortcode( $page->post_content, trim( $shortcode, '[]' ) ) ) {
				return (int) $page->ID;
			}

			if ( '1' === get_post_meta( $page->ID, '_svw_managed', true ) ) {
				self::snapshot_page( $page );
				$result = wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $shortcode,
					),
					true
				);
				if ( ! is_wp_error( $result ) ) {
					return (int) $page->ID;
				}
			}
		}

		$conflict = get_page_by_path( $slug );
		if ( $conflict instanceof WP_Post ) {
			$slug = 'svw-' . $slug;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $shortcode,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		update_post_meta( $page_id, '_svw_managed', '1' );
		$created   = array_map( 'absint', (array) get_option( 'svw_created_pages', array() ) );
		$created[] = absint( $page_id );
		update_option( 'svw_created_pages', array_values( array_unique( $created ) ), false );
		return absint( $page_id );
	}

	/**
	 * Preserve the pre-change state of a plugin-owned page.
	 *
	 * @param WP_Post $page Page object.
	 * @return void
	 */
	private static function snapshot_page( $page ) {
		$snapshots = (array) get_option( 'svw_page_snapshots', array() );
		if ( isset( $snapshots[ $page->ID ] ) ) {
			return;
		}
		$snapshots[ $page->ID ] = array(
			'post_title'    => $page->post_title,
			'post_name'     => $page->post_name,
			'post_content'  => $page->post_content,
			'post_status'   => $page->post_status,
			'post_modified' => $page->post_modified_gmt,
		);
		update_option( 'svw_page_snapshots', $snapshots, false );
	}

	/**
	 * Flush rewrites only; content and data are intentionally retained.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
