<?php
/**
 * Fail-closed provider/AI orchestration for the File 10 Future 24 layer.
 * External services are processors only; returned material is mapped back to File 10
 * canonical IDs and remains review-gated where content/medical/rights meaning exists.
 */
defined( 'ABSPATH' ) || exit;

final class VWLB_Future_Adapters {
	private static function event( $id ) { return VWLB_Repository::find( 'live_events', $id ); }
	private static function video( $id ) { return VWLB_Repository::find( 'videos', $id ); }
	private static function config( $event_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VWLB_Helpers::table('future_live_config') . ' WHERE live_event_id=%d', absint($event_id) ), ARRAY_A );
	}

	/** F10-FUT-004/005/006/008 — apply saved DVR/latency/ingest/redundancy policy to an adapter. */
	public static function apply_live_policy( $live_id ) {
		$event=self::event($live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_OPERATE,$event,'future_apply_live_policy'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot apply this live-provider policy.',VWLB_TEXT_DOMAIN),403);
		$config=self::config($event['id']);if(!$config)return VWLB_Helpers::error('vwlb_future_config_missing',__('Future live configuration is not set.',VWLB_TEXT_DOMAIN),409);
		$payload=array('event_public_id'=>$event['public_id'],'provider'=>$event['provider'],'latency_mode'=>$config['latency_mode'],'dvr_window_seconds'=>(int)$config['dvr_window_seconds'],'backup_provider'=>$config['backup_provider'],'redundant_recording'=>(bool)$config['redundant_recording'],'protocols'=>VWLB_Helpers::json($config['protocols_json']),'translation_languages'=>VWLB_Helpers::json($config['translation_languages_json']));
		$result=apply_filters('vwlb_provider_apply_future_live_policy',null,$event,$payload);
		if(!is_array($result)||empty($result['accepted']))return VWLB_Helpers::error('vwlb_provider_future_policy_unavailable',__('The configured provider adapter cannot apply this live policy.',VWLB_TEXT_DOMAIN),503,array('provider'=>$event['provider']));
		$safe=array('accepted'=>true,'provider'=>$event['provider'],'mode'=>VWLB_Helpers::enum($result['mode']??$config['latency_mode'],array('standard','low','ultra_low'),$config['latency_mode']),'dvr_window_seconds'=>max(0,min(6*HOUR_IN_SECONDS,(int)($result['dvr_window_seconds']??$config['dvr_window_seconds']))),'protocol'=>VWLB_Helpers::enum($result['protocol']??'',array('rtmp','srt','webrtc'),''),'backup_ready'=>!empty($result['backup_ready']),'recording_redundant'=>!empty($result['recording_redundant']));
		VWLB_Helpers::audit('live',$event['id'],'future_provider_policy_applied',$event['status'],$event['status'],'',array('provider'=>$event['provider'],'result'=>$safe));
		return $safe;
	}

	/** F10-FUT-007 — activate/deactivate one configured simulcast target through an adapter. */
	public static function transition_simulcast( $live_id, $target_id, $action ) {
		$event=self::event($live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_OPERATE,$event,'future_simulcast_transition'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot operate simulcast.',VWLB_TEXT_DOMAIN),403);
		global $wpdb;$table=VWLB_Helpers::table('simulcast_targets');$column=is_numeric($target_id)?'id':'public_id';$target=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE $column=%s",(string)$target_id),ARRAY_A);if(!$target||(int)$target['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_simulcast_missing',__('Simulcast target not found.',VWLB_TEXT_DOMAIN),404);
		$action=VWLB_Helpers::enum($action,array('start','stop','retry'),'');if(!$action)return VWLB_Helpers::error('vwlb_simulcast_action_invalid',__('Simulcast action is invalid.',VWLB_TEXT_DOMAIN),422);
		$result=apply_filters('vwlb_simulcast_adapter_transition',null,$action,$event,array('platform'=>$target['platform'],'provider_target_ref'=>$target['provider_target_ref'],'credential_ref'=>$target['credential_ref'],'config'=>VWLB_Helpers::json($target['config_json'])));
		if(!is_array($result)||empty($result['accepted']))return VWLB_Helpers::error('vwlb_simulcast_adapter_unavailable',__('No configured adapter accepted the simulcast operation.',VWLB_TEXT_DOMAIN),503);
		$status=VWLB_Helpers::enum($result['status']??('stop'===$action?'disabled':'active'),array('disabled','ready','active','degraded','failed'),'failed');$changed=$wpdb->update($table,array('status'=>$status,'last_state_json'=>VWLB_Helpers::json_encode(array_intersect_key($result,array_flip(array('status','provider_code','message','updated_at')))),'version'=>(int)$target['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$target['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast state changed concurrently.',VWLB_TEXT_DOMAIN),409);
		VWLB_Helpers::outbox('LiveSimulcastStateChanged','live',$event['id'],array('target_public_id'=>$target['public_id'],'status'=>$status));return array('target_public_id'=>$target['public_id'],'status'=>$status);
	}

	/** F10-FUT-012/013/014/015 — request a processor-generated auxiliary track. */
	public static function request_track_generation( $object_type, $object_id, $track_type, $language='', $options=array() ) {
		$object_type=VWLB_Helpers::enum($object_type,array('video','live'),'');$track_type=VWLB_Helpers::enum($track_type,array('translation','dub','audio_description','sign_language'),'');if(!$object_type||!$track_type)return VWLB_Helpers::error('vwlb_track_type_invalid',__('Track generation type is invalid.',VWLB_TEXT_DOMAIN),422);
		$object='video'===$object_type?self::video($object_id):self::event($object_id);$cap='video'===$object_type?VWLB_Contracts::CAP_PUBLISH:VWLB_Contracts::CAP_BROADCAST;if(!$object||!VWLB_Security::can($cap,$object,'future_generate_track'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot request this media track.',VWLB_TEXT_DOMAIN),403);
		$request=array('object_type'=>$object_type,'object_public_id'=>$object['public_id'],'track_type'=>$track_type,'language'=>VWLB_Helpers::text($language,32),'options'=>(array)$options,'require_human_review'=>true,'medical_interpretation_authority'=>false);
		$result=apply_filters('vwlb_media_track_generation_request',null,$request,$object);
		if(!is_array($result)||empty($result['accepted']))return VWLB_Helpers::error('vwlb_track_generator_unavailable',__('No approved media-track processor is configured.',VWLB_TEXT_DOMAIN),503);
		$data=array('track_type'=>$track_type,'language'=>$request['language'],'source'=>!empty($result['ai_assisted'])?'ai_assisted':'provider','status'=>'candidate','file_ref'=>esc_url_raw($result['file_ref']??''),'provider_ref'=>VWLB_Helpers::text($result['provider_ref']??'',191),'metadata'=>array('request_ref'=>VWLB_Helpers::text($result['request_ref']??'',191),'processor'=>VWLB_Helpers::text($result['processor']??'',64),'human_review_required'=>true,'original_preserved'=>true));
		return VWLB_Future_Intelligence::create_track($object_type,$object['id'],$data);
	}

	/** F10-FUT-016/017/018/024 — request AI/knowledge suggestions, always candidate/reviewed later. */
	public static function suggest_annotations( $video_id, $kinds=array('key_moment') ) {
		$video=self::video($video_id);if(!$video||!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$video,'future_suggest_annotations'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot request suggestions for this video.',VWLB_TEXT_DOMAIN),403);
		$allowed=array('key_moment','citation','overlay','knowledge_bridge');$kinds=array_values(array_unique(array_intersect(array_map('sanitize_key',(array)$kinds),$allowed)));if(!$kinds)$kinds=array('key_moment');
		$result=apply_filters('vwlb_video_intelligence_suggestions',null,array('video_public_id'=>$video['public_id'],'kinds'=>$kinds,'require_sources'=>true,'auto_publish'=>false,'clinical_authority'=>false),$video);
		if(!is_array($result)||empty($result['accepted'])||!is_array($result['items']??null))return VWLB_Helpers::error('vwlb_video_intelligence_unavailable',__('No approved video-intelligence suggestion processor is configured.',VWLB_TEXT_DOMAIN),503);
		$created=array();foreach(array_slice($result['items'],0,100) as $item){if(!is_array($item))continue;$kind=VWLB_Helpers::enum($item['kind']??'', $allowed, '');if(!$kind||!in_array($kind,$kinds,true))continue;$data=array('kind'=>$kind,'source'=>'ai_assisted','start_ms'=>max(0,(int)($item['start_ms']??0)),'end_ms'=>max(0,(int)($item['end_ms']??0)),'title'=>VWLB_Helpers::text($item['title']??'',255),'body'=>VWLB_Helpers::textarea($item['body']??''),'source_owner'=>VWLB_Helpers::text($item['source_owner']??'',64),'source_ref'=>VWLB_Helpers::text($item['source_ref']??'',191),'metadata'=>array('processor'=>VWLB_Helpers::text($result['processor']??'',64),'confidence'=>isset($item['confidence'])?max(0,min(1,(float)$item['confidence'])):null,'human_review_required'=>true));$row=VWLB_Future_Intelligence::create_annotation($video['id'],$data);if(!is_wp_error($row))$created[]=$row;}
		return array('accepted'=>true,'created_candidates'=>count($created),'items'=>$created);
	}
}
