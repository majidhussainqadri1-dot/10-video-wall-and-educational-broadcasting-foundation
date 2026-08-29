<?php
/** R66: request-scoped DB failure truth for direct wpdb reads/writes outside the canonical repository wrapper. */
defined('ABSPATH') || exit;
final class VWLB_R66_Request_DB_Guard {
	private static $file10=false;
	public static function register(){add_filter('rest_request_before_callbacks',array(__CLASS__,'before'),0,3);add_filter('rest_request_after_callbacks',array(__CLASS__,'after'),5,3);}
	private static function route($request){$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if(str_starts_with($route,'/'.$n.'/'))return true;return false;}
	public static function before($response,$handler,$request){self::$file10=$request instanceof WP_REST_Request&&self::route($request);if(self::$file10){global $wpdb;$wpdb->last_error='';}return $response;}
	public static function after($response,$handler,$request){if(!self::$file10||!$request instanceof WP_REST_Request)return $response;global $wpdb;if(''===(string)$wpdb->last_error)return $response;do_action('vwlb_operational_failure','database','vwlb_request_database_failure',array('route_hash'=>hash('sha256',(string)$request->get_route())));return VWLB_Helpers::error('vwlb_database_read_failed',__('File 10 could not verify the requested database state safely.',VWLB_TEXT_DOMAIN),503);}
}
