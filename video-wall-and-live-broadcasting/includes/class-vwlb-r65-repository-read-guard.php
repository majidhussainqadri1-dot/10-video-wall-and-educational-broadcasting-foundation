<?php
/** R65: do not let canonical repository DB failures masquerade as 404/empty REST responses. */
defined('ABSPATH') || exit;
final class VWLB_R65_Repository_Read_Guard {
	public static function register(){add_filter('rest_request_before_callbacks',array(__CLASS__,'before'),1,3);add_filter('rest_request_after_callbacks',array(__CLASS__,'after'),6,3);}
	private static function file10($request){$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if(str_starts_with($route,'/'.$n.'/'))return true;return false;}
	public static function before($response,$handler,$request){if(null===$response&&$request instanceof WP_REST_Request&&self::file10($request))VWLB_Repository::reset_read_failure();return $response;}
	public static function after($response,$handler,$request){if(!$request instanceof WP_REST_Request||!self::file10($request)||!VWLB_Repository::read_failed())return $response;return VWLB_Helpers::error('vwlb_repository_read_failed',__('File 10 could not verify the requested repository state because a database read failed.',VWLB_TEXT_DOMAIN),503);}
}
