<?php
/** R45: rollback-safe resumable chunk persistence for the canonical REST upload surface. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R45_Upload_Durability {
	public static function register(){add_filter('rest_request_before_callbacks',array(__CLASS__,'intercept_chunk'),20,3);}
	private static function route_matches($request){
		if(!$request instanceof WP_REST_Request||'PUT'!==strtoupper((string)$request->get_method()))return false;
		$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if(preg_match('#^/'.preg_quote($n,'#').'/media/resumable/[A-Za-z0-9_-]+/chunk$#',$route))return true;return false;
	}
	private static function load_session($public_id){global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('upload_sessions').' WHERE public_id=%s LIMIT 1',VWLB_Helpers::text($public_id,64)),ARRAY_A);}
	private static function authorize($session,$token){
		if(!$session||'active'!==($session['status']??'')||strtotime(($session['expires_at']??'').' UTC')<=time())return VWLB_Helpers::error('vwlb_upload_session_expired',__('The upload session is unavailable or expired.',VWLB_TEXT_DOMAIN),410);
		if((int)$session['owner_id']!==get_current_user_id()||!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'write_upload_chunk'))return VWLB_Helpers::error('vwlb_not_found',__('Upload session not found.',VWLB_TEXT_DOMAIN),404);
		if(!$token||!password_verify((string)$token,(string)$session['token_hash']))return VWLB_Helpers::error('vwlb_upload_token_invalid',__('Upload token is invalid.',VWLB_TEXT_DOMAIN),401);return true;
	}
	private static function upload_path($filename){
		$base=trailingslashit(WP_CONTENT_DIR).VWLB_Extensions::PRIVATE_DIR;if(is_link($base)||!is_dir($base))return VWLB_Helpers::error('vwlb_private_storage_unavailable',__('Private media storage is unavailable.',VWLB_TEXT_DOMAIN),503);
		$name=sanitize_file_name(basename((string)$filename));if(!$name||!str_ends_with($name,'.part'))return VWLB_Helpers::error('vwlb_private_filename_invalid',__('Private upload path is invalid.',VWLB_TEXT_DOMAIN),500);
		$path=trailingslashit($base).$name;if(is_link($path))return VWLB_Helpers::error('vwlb_private_upload_symlink_forbidden',__('Private upload files cannot be symbolic links.',VWLB_TEXT_DOMAIN),503);return $path;
	}
	private static function mark_failed($session,$reason){global $wpdb;$changed=$wpdb->update(VWLB_Helpers::table('upload_sessions'),array('status'=>'failed','updated_at'=>VWLB_Helpers::now()),array('id'=>(int)$session['id'],'status'=>'active'));VWLB_Helpers::audit('upload_session',$session['id'],'chunk_compensation_failed','active','failed',VWLB_Helpers::text($reason,191));return false!==$changed;}
	private static function rollback_locked($fp,$path,$offset){if(!ftruncate($fp,$offset)||!fflush($fp))return false;clearstatcache(true,$path);return filesize($path)===$offset;}
	private static function persist($session,$offset,$body,$chunk_sha256){
		$offset=max(0,(int)$offset);if($offset!==(int)$session['next_offset'])return VWLB_Helpers::error('vwlb_upload_offset_conflict',__('Upload offset is stale. Resume from the server offset.',VWLB_TEXT_DOMAIN),409,array('next_offset'=>(int)$session['next_offset']));
		$body=(string)$body;$length=strlen($body);if($length<1||$length>(int)$session['chunk_size'])return VWLB_Helpers::error('vwlb_chunk_size_invalid',__('Upload chunk size is invalid.',VWLB_TEXT_DOMAIN),413);if($offset+$length>(int)$session['expected_bytes'])return VWLB_Helpers::error('vwlb_upload_overflow',__('Upload exceeds the declared file size.',VWLB_TEXT_DOMAIN),422);
		$chunk_sha256=strtolower(trim((string)$chunk_sha256));if($chunk_sha256&&!hash_equals($chunk_sha256,hash('sha256',$body)))return VWLB_Helpers::error('vwlb_chunk_checksum_mismatch',__('Upload chunk checksum did not match.',VWLB_TEXT_DOMAIN),422);
		$path=self::upload_path($session['private_filename']);if(is_wp_error($path))return $path;$fp=@fopen($path,'c+b');if(!$fp)return VWLB_Helpers::error('vwlb_private_storage_unavailable',__('Private media storage is unavailable.',VWLB_TEXT_DOMAIN),503);if(!flock($fp,LOCK_EX)){fclose($fp);return VWLB_Helpers::error('vwlb_upload_lock_failed',__('Upload is busy. Retry safely.',VWLB_TEXT_DOMAIN),409);}
		clearstatcache(true,$path);$actual=filesize($path);if($actual!==$offset){flock($fp,LOCK_UN);fclose($fp);return VWLB_Helpers::error('vwlb_upload_storage_offset_conflict',__('Stored upload offset differs from the request.',VWLB_TEXT_DOMAIN),409,array('next_offset'=>(int)$actual));}
		$write_ok=0===fseek($fp,$offset);$written=0;
		while($write_ok&&$written<$length){$n=fwrite($fp,substr($body,$written));if(false===$n||0===$n){$write_ok=false;break;}$written+=$n;}
		if($write_ok)$write_ok=fflush($fp);
		if(!$write_ok||$written!==$length){$rolled=self::rollback_locked($fp,$path,$offset);flock($fp,LOCK_UN);fclose($fp);if(!$rolled)self::mark_failed($session,'partial_chunk_write_rollback_failed');return VWLB_Helpers::error($rolled?'vwlb_upload_write_failed':'vwlb_upload_compensation_failed',$rolled?__('The upload chunk could not be stored completely.',VWLB_TEXT_DOMAIN):__('The upload chunk failed and storage rollback could not be verified; the session was stopped safely.',VWLB_TEXT_DOMAIN),500);}
		flock($fp,LOCK_UN);fclose($fp);$new_offset=$offset+$length;global $wpdb;$updated=$wpdb->update(VWLB_Helpers::table('upload_sessions'),array('received_bytes'=>$new_offset,'next_offset'=>$new_offset,'version'=>(int)$session['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>(int)$session['id'],'version'=>(int)$session['version'],'next_offset'=>$offset),array('%d','%d','%d','%s'),array('%d','%d','%d'));
		if(1!==$updated){$rollback=@fopen($path,'c+b');$rolled=false;if($rollback&&flock($rollback,LOCK_EX)){clearstatcache(true,$path);if(filesize($path)===$new_offset)$rolled=self::rollback_locked($rollback,$path,$offset);flock($rollback,LOCK_UN);fclose($rollback);}if(!$rolled)self::mark_failed($session,'chunk_database_cas_rollback_failed');return VWLB_Helpers::error('vwlb_upload_concurrency_conflict',__('The upload changed concurrently. Resume from the server offset.',VWLB_TEXT_DOMAIN),409);}
		return array('session_id'=>$session['public_id'],'received_bytes'=>$new_offset,'next_offset'=>$new_offset,'complete'=>$new_offset===(int)$session['expected_bytes']);
	}
	public static function intercept_chunk($response,$handler,$request){
		if(null!==$response||!self::route_matches($request))return $response;$rate=VWLB_Security::rate_limit('upload_chunk',240,HOUR_IN_SECONDS);if(is_wp_error($rate))return $rate;$session=self::load_session($request['id']);$token=VWLB_Helpers::text($request->get_header('X-VWLB-Upload-Token'),200);if(!$token){$json=$request->get_json_params();if(is_array($json))$token=VWLB_Helpers::text($json['upload_token']??'',200);}$auth=self::authorize($session,$token);if(is_wp_error($auth))return $auth;$result=self::persist($session,(int)($request->get_header('Upload-Offset')?:0),$request->get_body(),VWLB_Helpers::text($request->get_header('X-Chunk-SHA256'),128));if(is_wp_error($result))return $result;$out=rest_ensure_response($result);$out->header('X-Sabri-File','10');$out->header('X-VWLB-Version',VWLB_VERSION);return $out;
	}
}
