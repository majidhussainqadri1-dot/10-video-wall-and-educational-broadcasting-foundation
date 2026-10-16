<?php
/** R94: any server-side failure after an emergency-end request is reconciliation-required. */
defined('ABSPATH') || exit;
final class VWLB_R94_Live_External_Uncertainty_Guard {
	public static function register(){add_filter('rest_request_after_callbacks',array(__CLASS__,'after'),8,3);}
	private static function kill_route($request){if(!$request instanceof WP_REST_Request||'POST'!==strtoupper((string)$request->get_method()))return false;$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if(preg_match('#^/'.preg_quote($n,'#').'/live-events/[A-Za-z0-9_-]+/kill$#',$route))return true;return false;}
	private static function status($response){if(is_wp_error($response)){$d=$response->get_error_data();return is_array($d)?absint($d['status']??500):500;}return $response instanceof WP_REST_Response?(int)$response->get_status():200;}
	public static function after($response,$handler,$request){if(!self::kill_route($request))return $response;$status=self::status($response);if($status<500)return $response;$original=is_wp_error($response)?$response->get_error_code():'http_'.$status;do_action('vwlb_operational_failure','live','vwlb_provider_emergency_end_reconcile_required',array('original_error_code'=>sanitize_key((string)$original),'route_hash'=>hash('sha256',(string)$request->get_route())));return VWLB_Helpers::error('vwlb_provider_emergency_end_reconcile_required',__('Emergency end reached an uncertain provider/local outcome. Reconcile the broadcast before retrying this idempotency key.',VWLB_TEXT_DOMAIN),503,array('original_error_code'=>sanitize_key((string)$original),'reconcile_required'=>true));}
}
