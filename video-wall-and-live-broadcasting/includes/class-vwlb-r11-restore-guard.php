<?php
/** R11 sequential review: case-bound restoration guard across moderation, takedown and consent restrictions. */
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

	private static function assert_restore_allowed( $target_type, $target_id, $exclude_kind, $exclude_id ) {
		global $wpdb;
		if ( 'video' === $target_type ) {
			$consent = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . VWLB_Helpers::table('consent_links') . " WHERE video_id=%d AND (status IN ('expired','withdrawn') OR (status='active' AND expires_at IS NOT NULL AND expires_at<=%s)) ORDER BY id DESC LIMIT 1",
				$target_id, VWLB_Helpers::now()
			) );
			if ( $consent ) return VWLB_Helpers::error( 'vwlb_restore_blocked_by_consent', __( 'This video cannot be restored while a consent restriction remains active.', VWLB_TEXT_DOMAIN ), 409 );
		}

		$moderation_table = VWLB_Helpers::table( 'moderation' );
		$moderation_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,action,status,evidence_json FROM $moderation_table WHERE target_type=%s AND target_id=%d ORDER BY id DESC LIMIT 100",
			$target_type, $target_id
		), ARRAY_A );
		if ( ! is_array( $moderation_rows ) ) return VWLB_Helpers::error( 'vwlb_restore_blocker_check_failed', __( 'Restoration blockers could not be verified safely.', VWLB_TEXT_DOMAIN ), 503 );
		foreach ( $moderation_rows as $row ) {
			if ( 'moderation' === $exclude_kind && (int) $row['id'] === $exclude_id ) continue;
			if ( 'closed' === ( $row['status'] ?? '' ) && in_array( $row['action'] ?? '', array( 'restrict','remove' ), true ) && self::proven_restriction( $row['evidence_json'] ?? '{}' ) ) {
				return VWLB_Helpers::error( 'vwlb_restore_blocked_by_moderation', __( 'Another moderation case still requires this content to remain restricted.', VWLB_TEXT_DOMAIN ), 409 );
			}
		}

		$takedown_table = VWLB_Helpers::table( 'takedowns' );
		$takedown_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,status,evidence_json FROM $takedown_table WHERE target_type=%s AND target_id=%d ORDER BY id DESC LIMIT 100",
			$target_type, $target_id
		), ARRAY_A );
		if ( ! is_array( $takedown_rows ) ) return VWLB_Helpers::error( 'vwlb_restore_blocker_check_failed', __( 'Restoration blockers could not be verified safely.', VWLB_TEXT_DOMAIN ), 503 );
		foreach ( $takedown_rows as $row ) {
			if ( 'takedown' === $exclude_kind && (int) $row['id'] === $exclude_id ) continue;
			if ( 'restored' !== ( $row['status'] ?? '' ) && self::proven_restriction( $row['evidence_json'] ?? '{}' ) ) {
				return VWLB_Helpers::error( 'vwlb_restore_blocked_by_takedown', __( 'Another takedown case still requires this content to remain restricted.', VWLB_TEXT_DOMAIN ), 409 );
			}
		}
		return $GLOBALS['wp_rest_server'] instanceof WP_REST_Server ? null : null;
	}
}
