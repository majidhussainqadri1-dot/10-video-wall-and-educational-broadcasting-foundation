<?php
/** Current sequential review hardening. Each section is tied to a completed review round. */
defined( 'ABSPATH' ) || exit;

final class VWLB_Sequential_Review_Hardening {
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_overrides' ), 100 );
		add_filter( 'vwlb_asset_technical_validation', array( __CLASS__, 'enforce_private_signature_validation' ), 100, 2 );
	}

	public static function register_rest_overrides() {
		foreach ( VWLB_Contracts::namespaces() as $namespace ) {
			register_rest_route(
				$namespace,
				'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete',
				array(
					'methods' => 'POST',
					'callback' => array( __CLASS__, 'complete_resumable' ),
					'permission_callback' => function() { return is_user_logged_in(); },
				),
				true
			);
		}
	}

	private static function body( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : array();
	}

	private static function upload_token( WP_REST_Request $request ) {
		$body = self::body( $request );
		return VWLB_Helpers::text( $request->get_header( 'X-VWLB-Upload-Token' ) ?: ( $body['upload_token'] ?? '' ), 200 );
	}

	private static function response( $value ) {
		if ( is_wp_error( $value ) ) return $value;
		$response = rest_ensure_response( $value );
		$response->header( 'X-Sabri-File', '10' );
		$response->header( 'X-VWLB-Version', VWLB_VERSION );
		$response->header( 'X-VWLB-Canonical-API', VWLB_Contracts::CANONICAL_API_NAMESPACE );
		return $response;
	}

	/** R03: refuse completion when signature validation is unavailable, unknown or disallowed. */
	public static function complete_resumable( WP_REST_Request $request ) {
		global $wpdb;
		$public_id = VWLB_Helpers::text( $request['id'], 64 );
		$token = self::upload_token( $request );
		$table = VWLB_Helpers::table( 'upload_sessions' );
		$session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE public_id=%s LIMIT 1", $public_id ), ARRAY_A );
		if ( ! $session || 'active' !== ( $session['status'] ?? '' ) || strtotime( ( $session['expires_at'] ?? '' ) . ' UTC' ) <= time() || (int) $session['owner_id'] !== get_current_user_id() || ! $token || ! password_verify( $token, (string) $session['token_hash'] ) ) {
			return self::response( VWLB_Extensions::complete_resumable( $public_id, $token ) );
		}
		if ( ! class_exists( 'finfo' ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_unavailable', __( 'Server-side file signature validation is unavailable. Retry after the media validator is restored.', VWLB_TEXT_DOMAIN ), 503 );
		}
		$name = sanitize_file_name( basename( (string) $session['private_filename'] ) );
		if ( ! $name || ! str_ends_with( $name, '.part' ) ) {
			return VWLB_Helpers::error( 'vwlb_private_filename_invalid', __( 'Private upload path is invalid.', VWLB_TEXT_DOMAIN ), 500 );
		}
		$path = trailingslashit( WP_CONTENT_DIR ) . VWLB_Extensions::PRIVATE_DIR . '/' . $name;
		if ( ! is_file( $path ) ) {
			return self::response( VWLB_Extensions::complete_resumable( $public_id, $token ) );
		}
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$detected = strtolower( trim( (string) $finfo->file( $path ) ) );
		if ( ! $detected ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_unknown', __( 'The uploaded file signature could not be determined.', VWLB_TEXT_DOMAIN ), 415 );
		}
		$asset = VWLB_Repository::find( 'media_assets', (int) $session['asset_id'] );
		if ( ! $asset ) return VWLB_Helpers::error( 'vwlb_asset_missing', __( 'Asset not found.', VWLB_TEXT_DOMAIN ), 404 );
		$declared = strtolower( trim( (string) ( $asset['mime'] ?? '' ) ) );
		if ( $declared && ! self::mime_compatible( $declared, $detected ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_mismatch', __( 'File signature does not match the declared media type.', VWLB_TEXT_DOMAIN ), 415 );
		}
		if ( ! $declared && ! self::detected_mime_allowed( $detected, $asset['media_class'] ?? 'video' ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_not_allowed', __( 'The detected file type is not allowed for media ingest.', VWLB_TEXT_DOMAIN ), 415 );
		}
		return self::response( VWLB_Extensions::complete_resumable( $public_id, $token ) );
	}

	/** R03: the asynchronous verification worker must not turn a clean malware result into a MIME/signature bypass. */
	public static function enforce_private_signature_validation( $allowed, $asset ) {
		if ( ! $allowed || ! is_array( $asset ) ) return false;
		$storage = VWLB_Helpers::json( $asset['storage_json'] ?? '{}' );
		if ( 'private_file' !== ( $storage['driver'] ?? '' ) ) return $allowed;
		if ( ! class_exists( 'finfo' ) ) return false;
		$name = sanitize_file_name( basename( (string) ( $storage['relative_path'] ?? '' ) ) );
		if ( ! $name || ! str_ends_with( $name, '.part' ) ) return false;
		$path = trailingslashit( WP_CONTENT_DIR ) . VWLB_Extensions::PRIVATE_DIR . '/' . $name;
		if ( ! is_file( $path ) ) return false;
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$detected = strtolower( trim( (string) $finfo->file( $path ) ) );
		if ( ! $detected ) return false;
		$declared = strtolower( trim( (string) ( $asset['mime'] ?? '' ) ) );
		if ( $declared ) return self::mime_compatible( $declared, $detected );
		return self::detected_mime_allowed( $detected, $asset['media_class'] ?? 'video' );
	}

	private static function detected_mime_allowed( $detected, $media_class ) {
		$allowed = (array) apply_filters( 'vwlb_allowed_mimes', array(
			'video/mp4','application/mp4','video/webm','video/ogg','application/ogg',
			'audio/mpeg','audio/mp3','audio/mp4','audio/ogg','image/jpeg','image/png','text/vtt',
		), $media_class );
		return in_array( strtolower( trim( (string) $detected ) ), array_map( 'strtolower', $allowed ), true );
	}

	private static function mime_compatible( $declared, $detected ) {
		$declared = strtolower( trim( (string) $declared ) );
		$detected = strtolower( trim( (string) $detected ) );
		if ( $declared === $detected ) return true;
		$families = array(
			'video/mp4'=>array('video/mp4','application/mp4'),
			'video/webm'=>array('video/webm','application/octet-stream'),
			'video/ogg'=>array('video/ogg','application/ogg'),
			'audio/mpeg'=>array('audio/mpeg','audio/mp3'),
			'audio/mp4'=>array('audio/mp4','video/mp4','application/mp4'),
			'audio/ogg'=>array('audio/ogg','application/ogg'),
		);
		return isset( $families[$declared] ) && in_array( $detected, $families[$declared], true );
	}
}
