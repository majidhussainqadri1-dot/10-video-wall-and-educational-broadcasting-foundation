<?php
/** R132: fail-closed cache policy for caption responses bound to non-public videos. */
defined( 'ABSPATH' ) || exit;

final class VWLB_R132_Caption_Cache_Guard {
	public static function register() {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'enforce' ), 100, 3 );
	}

	private static function is_caption_route( $route ) {
		$route = (string) $route;
		foreach ( VWLB_Contracts::namespaces() as $namespace ) {
			$prefix = '/' . trim( $namespace, '/' ) . '/captions/';
			if ( str_starts_with( $route, $prefix ) ) return true;
		}
		return false;
	}

	public static function enforce( $response, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request || ! self::is_caption_route( $request->get_route() ) ) return $response;
		if ( is_wp_error( $response ) ) return $response;
		$wrapped = rest_ensure_response( $response );
		if ( (int) $wrapped->get_status() >= 400 ) return $response;

		$caption = VWLB_Repository::find( 'captions', (string) $request['id'] );
		$video = is_array( $caption ) ? VWLB_Repository::find( 'videos', (int) ( $caption['video_id'] ?? 0 ) ) : null;
		$is_public = is_array( $video ) && 'public' === ( $video['visibility'] ?? 'private' );

		$wrapped->header( 'Cache-Control', $is_public ? 'public, max-age=300' : 'private, no-store' );
		return $wrapped;
	}
}
