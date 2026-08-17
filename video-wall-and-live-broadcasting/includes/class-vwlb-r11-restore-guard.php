<?php
/** R11/R28 sequential review: case-bound restoration guard across moderation, takedown and consent restrictions. */
defined( 'ABSPATH' ) || exit;

final class VWLB_R11_Restore_Guard {
	public static function register() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard_restore_request' ), 4, 3 );
	}

	private static function body( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : array();
	}

	public static function guard_restore_request( $response, $handler, $request ) {
		if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request ) return $response;
		$route = (string) $request->get_route();
		$body = self::body( $request );
		$case = null; $case_kind = ''; $requested_restore = false;
		if ( preg_match( '#/moderation/reports/[^/]+/decision$#', $route ) && 'restore' === sanitize_key( (string) ( $body['action'] ?? '' ) ) ) {
			$case = VWLB_Repository::find( 'moderation', $request['id'] );
			$case_kind = 'moderation'; $requested_restore = true;
		} elseif ( preg_match( '#/takedowns/[^/]+/transition$#', $route ) && 'restored' === sanitize_key( (string) ( $body['status'] ?? '' ) ) ) {
			$case = VWLB_Repository::find( 'takedowns', $request['id'] );
			$case_kind = 'takedown'; $requested_restore = true;
		}
		if ( ! $requested_restore ) return $response;
		if ( ! $case ) return VWLB_Helpers::error( 'vwlb_restore_case_missing', __( 'The restoration case could not be verified.', VWLB_TEXT_DOMAIN ), 404 );
		if ( ! in_array( $case['target_type'] ?? '', array( 'video','live' ), true ) ) return $response;
		return self::assert_restore_allowed( $case['target_type'], (int) $case['target_id'], $case_kind, (int) $case['id'] );
	}

	private static function proven_restriction( $evidence_json ) {
		$evidence = VWLB_Helpers::json( $evidence_json ?? '{}' );
		return ! empty( $evidence['target_previous_status'] );
	}

	/** R28: scan every potentially blocking case in bounded pages; a blocker older than the newest 100 cases must never be skipped. */
	private static function moderation_blocker_exists( $target_type, $target_id, $exclude_id ) {
		global $wpdb; $table = VWLB_Helpers::table('moderation'); $before = PHP_INT_MAX;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id,evidence_json FROM $table WHERE target_type=%s AND target_id=%d AND id<%d AND id<>%d AND status='closed' AND action IN ('restrict','remove') ORDER BY id DESC LIMIT 100",
				$target_type, $target_id, $before, max(0,(int)$exclude_id)
			), ARRAY_A );
			if ( null === $rows || '' !== (string) $wpdb->last_error ) return VWLB_Helpers::error( 'vwlb_restore_blocker_check_failed', __( 'Restoration blockers could not be verified safely.', VWLB_TEXT_DOMAIN ), 503 );
			foreach ( $rows as $row ) if ( self::proven_restriction( $row['evidence_json'] ?? '{}' ) ) return true;
			if ( count($rows) < 100 ) return false;
			$last = end($rows); $before = max(1,(int)($last['id'] ?? 1));
		} while ( $before > 1 );
		return false;
	}

	private static function takedown_blocker_exists( $target_type, $target_id, $exclude_id ) {
		global $wpdb; $table = VWLB_Helpers::table('takedowns'); $before = PHP_INT_MAX;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id,evidence_json FROM $table WHERE target_type=%s AND target_id=%d AND id<%d AND id<>%d AND status<>'restored' ORDER BY id DESC LIMIT 100",
				$target_type, $target_id, $before, max(0,(int)$exclude_id)
			), ARRAY_A );
			if ( null === $rows || '' !== (string) $wpdb->last_error ) return VWLB_Helpers::error( 'vwlb_restore_blocker_check_failed', __( 'Restoration blockers could not be verified safely.', VWLB_TEXT_DOMAIN ), 503 );
			foreach ( $rows as $row ) if ( self::proven_restriction( $row['evidence_json'] ?? '{}' ) ) return true;
			if ( count($rows) < 100 ) return false;
			$last = end($rows); $before = max(1,(int)($last['id'] ?? 1));
		} while ( $before > 1 );
		return false;
	}

	private static function assert_restore_allowed( $target_type, $target_id, $exclude_kind, $exclude_id ) {
		global $wpdb;
		if ( 'video' === $target_type ) {
			$consent = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . VWLB_Helpers::table('consent_links') . " WHERE video_id=%d AND (status IN ('expired','withdrawn') OR (status='active' AND expires_at IS NOT NULL AND expires_at<=%s)) ORDER BY id DESC LIMIT 1",
				$target_id, VWLB_Helpers::now()
			) );
			if ( '' !== (string) $wpdb->last_error ) return VWLB_Helpers::error( 'vwlb_restore_blocker_check_failed', __( 'Restoration blockers could not be verified safely.', VWLB_TEXT_DOMAIN ), 503 );
			if ( $consent ) return VWLB_Helpers::error( 'vwlb_restore_blocked_by_consent', __( 'This video cannot be restored while a consent restriction remains active.', VWLB_TEXT_DOMAIN ), 409 );
		}

		$moderation = self::moderation_blocker_exists( $target_type, $target_id, 'moderation' === $exclude_kind ? $exclude_id : 0 );
		if ( is_wp_error($moderation) ) return $moderation;
		if ( $moderation ) return VWLB_Helpers::error( 'vwlb_restore_blocked_by_moderation', __( 'Another moderation case still requires this content to remain restricted.', VWLB_TEXT_DOMAIN ), 409 );

		$takedown = self::takedown_blocker_exists( $target_type, $target_id, 'takedown' === $exclude_kind ? $exclude_id : 0 );
		if ( is_wp_error($takedown) ) return $takedown;
		if ( $takedown ) return VWLB_Helpers::error( 'vwlb_restore_blocked_by_takedown', __( 'Another takedown case still requires this content to remain restricted.', VWLB_TEXT_DOMAIN ), 409 );
		return null;
	}
}
