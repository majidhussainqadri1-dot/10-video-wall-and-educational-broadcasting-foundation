<?php
/**
 * File 10's native, public-only File 26 search connector.
 *
 * Owner truth: File 10 media DB and security. No direct File 26 writes,
 * no private captions, no stream tokens, no patient case material.
 * This contract intentionally enters PROPOSED status. Event/tombstone,
 * rights, media/staging and owner acceptance must precede activation.
 */
defined( 'ABSPATH' ) || exit;

final class VWLB_File26_Connector {
	private const SLUG = 'file10-public-media';
	private const TABLES = array(
		'videos'      => array( 'domain' => 'video', 'entity_type' => 'video' ),
		'live_events' => array( 'domain' => 'live_video', 'entity_type' => 'live_video' ),
		'channels'    => array( 'domain' => 'channel', 'entity_type' => 'channel' ),
	);

	public static function register() {
		add_filter( 'sabri_file26_connector_manifests', array( __CLASS__, 'append_manifest' ), 20 );
		add_action( 'sabri_file26_connectors_ready', array( __CLASS__, 'register_when_ready' ), 20 );
		// File 26 might already be booted if the plugin loader ordering differs.
		if ( function_exists( 'sabri_file26_register_connector' ) ) {
			sabri_file26_register_connector( self::manifest() );
		}
	}

	public static function register_when_ready( $connectors ) {
		if ( is_object( $connectors ) && method_exists( $connectors, 'register' ) ) {
			$connectors->register( self::manifest() );
		}
	}

	public static function append_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		foreach ( $manifests as $manifest ) {
			if ( is_array( $manifest ) && isset( $manifest['slug'] ) && self::SLUG === $manifest['slug'] ) {
				return $manifests;
			}
		}
		$manifests[] = self::manifest();
		return $manifests;
	}

	public static function manifest() {
		return array(
			'slug'              => self::SLUG,
			'owner_file'        => 'File 10',
			'contract_version'  => '1.0',
			'entity_types'      => array( 'video', 'live_video', 'channel' ),
			'privacy_classes'   => array( 'public' ),
			'visibility_fields' => array( 'public' ),
			'deletion_semantics'=> 'owner_outbox_tombstone_gate_pending',
			'event_contract'    => 'File10MediaOutbox.v1.pending-file26-reconciliation',
			'index_schema'      => 'sabri.file26.document.v1.1',
			'status'            => 'proposed',
			'list_batch'        => array( __CLASS__, 'list_batch' ),
			'can_view'          => array( __CLASS__, 'can_view' ),
			'health'            => array( __CLASS__, 'health' ),
			'fetch_object'      => array( __CLASS__, 'fetch_object' ),
		);
	}

	private static function phase( $cursor ) {
		if ( '' === $cursor || null === $cursor ) { return array( 'videos', 0 ); }
		if ( ! is_string( $cursor ) || ! preg_match( '/^(videos|live_events|channels):([0-9]{1,18})$/D', $cursor, $matches ) ) {
			return null;
		}
		return array( $matches[1], (int) $matches[2] );
	}

	private static function ready() {
		return class_exists( 'VWLB_Repository' ) &&
			class_exists( 'VWLB_Helpers' ) &&
			class_exists( 'VWLB_Security' ) &&
			! (bool) get_option( 'vwlb_safe_mode', false );
	}

	/** Public row eligibility, independent of the current viewer's WP role. */
	private static function eligible( $entity, $row ) {
		if ( ! is_array( $row ) || ! self::ready() || 'public' !== ( $row['visibility'] ?? '' ) ||
			empty( $row['public_id'] ) || (int) ( $row['version'] ?? 0 ) < 1 ) {
			return false;
		}
		if ( 'channels' === $entity ) {
			return 'active' === ( $row['status'] ?? '' );
		}
		if ( ! empty( $row['deleted_at'] ) ) { return false; }
		if ( 'videos' === $entity ) {
			if ( 'published' !== ( $row['status'] ?? '' ) || 'not_patient_case' !== ( $row['consent_status'] ?? '' ) ) { return false; }
			if ( in_array( $row['rights_status'] ?? '', array( 'revoked', 'rejected', 'disputed', 'blocked' ), true ) ) { return false; }
			if ( ! empty( $row['published_at'] ) && strtotime( $row['published_at'] . ' UTC' ) > time() ) { return false; }
		} elseif ( 'live_events' === $entity ) {
			if ( ! in_array( $row['status'] ?? '', array( 'scheduled', 'live', 'ended', 'replay_published' ), true ) ||
				! empty( $row['kill_switch'] ) ) { return false; }
		} else { return false; }
		return VWLB_Security::can_view( $row, 'search' ) === true;
	}

	private static function project( $entity, $row ) {
		if ( ! self::eligible( $entity, $row ) ) { return null; }
		$public_id = (string) $row['public_id'];
		if ( ! preg_match( '/^[A-Za-z0-9_-]{3,80}$/D', $public_id ) || ctype_digit( $public_id ) ) { return null; }
		$category = self::TABLES[ $entity ];
		$slug = sanitize_title( $row['slug'] ?? '' );
		if ( 'videos' === $entity ) {
			$url = home_url( '/video/' . rawurlencode( $public_id ) . '/' . $slug . '/' );
			$locale = $row['language'] ?? 'en-US';
		} elseif ( 'live_events' === $entity ) {
			$url = home_url( '/live/' . rawurlencode( $public_id ) . '/' );
			$locale = $row['language'] ?? 'en-US';
		} else {
			$url = home_url( '/channel/' . $slug . '/' );
			$locale = 'en-US';
		}
		$title = sanitize_text_field( $row['title'] ?? '' );
		if ( '' === $title ) { return null; }
		$excerpt = wp_strip_all_tags( $row['excerpt'] ?? '', true );
		$version = (int) $row['version'];
		return array(
			'connector_slug'       => self::SLUG,
			'domain'               => $category['domain'],
			'object_id'            => $public_id,
			'object_version'       => $version,
			'source_event_sequence'=> $version,
			'entity_type'          => $category['entity_type'],
			'locale'               => substr( sanitize_text_field( $locale ), 0, 20 ),
			'state'                => 'channels' === $entity ? 'active' : 'published',
			'visibility'           => 'public',
			'title'                => $title,
			'excerpt'              => $excerpt,
			'search_text'          => $title . ' ' . $excerpt,
			'canonical_url'        => $url,
			'author_key'           => '', // No internal File00/WordPress principal ID in public File26 documents.
			'safety_class'         => 'general',
			'payload'              => array(
				'language' => substr( sanitize_text_field( $locale ), 0, 20 ),
				'content_type_label' => $category['entity_type'],
			),
		);
	}

	/**
	 * Stable bounded source scan. No filtering of deleted/hidden rows at SQL
	 * level: cursor can advance across all rows. Shadow cutover will still
	 * reject missing live objects without File10's own versioned tombstones.
	 */
	public static function list_batch( $cursor = '', $limit = 50, $scope = array() ) {
		global $wpdb;
		if ( ! self::ready() ) { return new WP_Error( 'file10_search_unavailable', 'File10 media schema/security is unavailable.' ); }
		$decoded = self::phase( $cursor );
		if ( ! $decoded ) { return new WP_Error( 'file10_search_cursor_invalid', 'Invalid File10 search cursor.' ); }
		if ( isset( $scope['object_id'] ) ) {
			return new WP_Error( 'file10_search_partial_scope_unavailable', 'File10 supports complete public-source snapshots only.' );
		}
		list( $entity, $id ) = $decoded;
		$limit = max( 1, min( 50, (int) $limit ) );
		$table = VWLB_Helpers::table( $entity );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE id > %d ORDER BY id ASC LIMIT %d", $id, $limit + 1
		), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return new WP_Error( 'file10_search_snapshot_unreadable', 'Canonical File10 media snapshot could not be read.' );
		}
		$more = count( $rows ) > $limit;
		if ( $more ) { array_pop( $rows ); }
		$items = array();
		foreach ( $rows as $row ) {
			$doc = self::project( $entity, $row );
			if ( $doc ) { $items[] = $doc; }
			$id = max( $id, (int) $row['id'] );
		}
		if ( $more ) { return array( 'items' => $items, 'done' => false, 'next_cursor' => $entity . ':' . $id ); }
		$phases = array_keys( self::TABLES );
		$next_index = array_search( $entity, $phases, true ) + 1;
		return $next_index < count( $phases )
			? array( 'items' => $items, 'done' => false, 'next_cursor' => $phases[ $next_index ] . ':0' )
			: array( 'items' => $items, 'done' => true, 'next_cursor' => '' );
	}

	public static function fetch_object( $entity_type, $public_id = '' ) {
		$entity = null;
		foreach ( self::TABLES as $table => $contract ) {
			if ( (string) $entity_type === $contract['entity_type'] ) { $entity = $table; break; }
		}
		if ( null === $entity || ! is_string( $public_id ) || '' === $public_id || ctype_digit( $public_id ) ) { return null; }
		$row = VWLB_Repository::find( $entity, $public_id );
		return $row ? self::project( $entity, $row ) : null;
	}

	/** Always re-check the File10 owner DB, not the stale File26 index row. */
	public static function can_view( $document, $audience = array() ) {
		if ( ! is_array( $document ) || ( $document['connector_slug'] ?? '' ) !== self::SLUG ||
			( $document['visibility'] ?? '' ) !== 'public' ) { return false; }
		$public_id = (string) ( $document['object_id'] ?? '' );
		if ( '' === $public_id || ctype_digit( $public_id ) || strlen( $public_id ) > 80 ) { return false; }
		$version = $document['object_version'] ?? null;
		if ( ! is_int( $version ) && ( ! is_string( $version ) || ! preg_match( '/^[1-9][0-9]{0,17}$/D', $version ) ) ) { return false; }
		if ( (int) $version < 1 ) { return false; }
		$entity_type = (string) ( $document['entity_type'] ?? '' );
		foreach ( self::TABLES as $entity => $contract ) {
			if ( $entity_type !== $contract['entity_type'] ) { continue; }
			$row = VWLB_Repository::find( $entity, $public_id );
			// An old public index row cannot remain viewable after canonical metadata changes.
			return is_array( $row ) && (int) ( $row['version'] ?? 0 ) === (int) $version && self::eligible( $entity, $row );
		}
		return false;
	}

	public static function health() {
		global $wpdb;
		if ( ! self::ready() ) {
			return array( 'state' => 'degraded', 'owner_file' => 'File 10', 'reason' => 'file10_media_unavailable_or_safe_mode' );
		}
		foreach ( array_keys( self::TABLES ) as $entity ) {
			$name = VWLB_Helpers::table( $entity );
			$wpdb->last_error = '';
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );
			if ( $found !== $name || '' !== (string) $wpdb->last_error ) {
				return array( 'state' => 'degraded', 'owner_file' => 'File 10', 'reason' => 'source_schema_unverified' );
			}
		}
		return array( 'state' => 'healthy', 'owner_file' => 'File 10', 'search_status' => 'proposed', 'public_only' => true, 'event_tombstone_cutover_approved' => false );
	}
}
