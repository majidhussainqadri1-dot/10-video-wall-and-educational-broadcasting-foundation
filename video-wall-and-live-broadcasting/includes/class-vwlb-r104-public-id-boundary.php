<?php
/** R104: public playlist membership accepts opaque File 10 video identifiers only. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R104_Public_ID_Boundary {
	public static function register(){add_filter('rest_request_before_callbacks',array(__CLASS__,'playlist_items'),11,3);}
	private static function matches($request){
		if(!$request instanceof WP_REST_Request||'PUT'!==strtoupper((string)$request->get_method()))return false;
		$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if(preg_match('#^/'.preg_quote($n,'#').'/playlists/[A-Za-z0-9_-]+/items$#',$route))return true;return false;
	}
	private static function response($value){if(is_wp_error($value))return $value;$r=rest_ensure_response($value);$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);$r->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE);return $r;}
	public static function playlist_items($response,$handler,$request){
		if(null!==$response||!self::matches($request))return $response;
		$data=$request->get_json_params();$data=is_array($data)?$data:array();
		if(array_key_exists('video_ids',$data))return VWLB_Helpers::error('vwlb_internal_identifier_forbidden',__('Internal video identifiers are not accepted on the public API. Use video_public_ids.',VWLB_TEXT_DOMAIN),422);
		$public_ids=array_values(array_unique(array_filter(array_map(function($v){return VWLB_Helpers::text($v,64);},(array)($data['video_public_ids']??array())))));
		if(count($public_ids)>500)return VWLB_Helpers::error('vwlb_playlist_too_large',__('Playlist item limit exceeded.',VWLB_TEXT_DOMAIN),422);
		$video_ids=array();VWLB_Repository::reset_read_failure();
		foreach($public_ids as $public_id){
			if(is_numeric($public_id)||!preg_match('/^vid_[A-Za-z0-9_-]+$/',$public_id))return VWLB_Helpers::error('vwlb_public_identifier_invalid',__('Every playlist video must use a valid opaque File 10 public identifier.',VWLB_TEXT_DOMAIN),422);
			$video=VWLB_Repository::find('videos',$public_id);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_playlist_video_read_failed',__('Playlist video state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$video)return VWLB_Helpers::error('vwlb_video_missing',__('A playlist video was not found.',VWLB_TEXT_DOMAIN),404);$video_ids[]=(int)$video['id'];
		}
		return self::response(VWLB_Videos::set_playlist_items($request['id'],$video_ids,absint($data['version']??0)));
	}
}
