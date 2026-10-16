<?php
/** R74: takedown claimant ownership must never collapse to anonymous user_id=0. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R74_Takedown_Identity_Guard {
	public static function register(){add_filter('rest_request_before_callbacks',array(__CLASS__,'guard'),12,3);}
	private static function filing_route($request){if(!$request instanceof WP_REST_Request||'POST'!==strtoupper((string)$request->get_method()))return false;$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if('/'.$n.'/takedowns'===$route)return true;return false;}
	public static function guard($response,$handler,$request){if(null!==$response||!self::filing_route($request))return $response;if(!is_user_logged_in()||get_current_user_id()<1)return VWLB_Helpers::error('vwlb_takedown_identity_required',__('Sign in with a verified File 00 identity before filing a takedown claim.',VWLB_TEXT_DOMAIN),401);$claims=VWLB_Security::claims();if(empty($claims['identity_approved'])||!empty($claims['suspended']))return VWLB_Helpers::error('vwlb_takedown_identity_required',__('A verified, active identity is required for takedown claimant ownership.',VWLB_TEXT_DOMAIN),403);return $response;}
}
