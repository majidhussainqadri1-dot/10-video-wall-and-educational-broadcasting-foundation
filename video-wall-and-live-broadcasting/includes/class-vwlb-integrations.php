<?php
/** Versioned cross-file contracts without direct foreign writes. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Integrations {
	public function register(){
		add_filter('sabri_contract_registry',array($this,'contracts'));
		add_filter('sabri_route_registry',array($this,'routes'));
		add_filter('sabri_owner_registry',array($this,'owners'));
		add_action('vwlb_domain_event',array($this,'publish_event'),10,3);
		add_action('vwlb_verified_webhook',array($this,'process_webhook'),10,3);
		add_action('sabri_event_bus_consume_file10',array($this,'receive_bus_event'),10,3);
		add_filter('file11_media_source_contract',array($this,'file11_media_source'),10,3);
		add_filter('file17_live_context_contract',array($this,'file17_live_context'),10,3);
	}
	public function contracts($registry){
		$registry=is_array($registry)?$registry:array();
		$registry['file10.media.v1']=array(
			'owner'=>'File 10','canonical_api'=>VWLB_Contracts::CANONICAL_API_NAMESPACE,
			'queries'=>array('browse_video_wall','get_media_asset','get_video_playback','get_live_state','get_creator_studio','get_podcast_feed'),
			'commands'=>array('initiate_resumable_upload','complete_upload','publish_video','schedule_live','issue_stream_credential','create_premiere','publish_podcast'),
			'events'=>VWLB_Contracts::PUBLISHED_EVENTS,
			'privacy'=>'audience-specific DTOs; raw assets, stream credentials and private progress are never shared as public contracts',
		);
		$registry['file10.reels-source.v1']=array(
			'owner'=>'File 10','consumer'=>'File 11','required'=>true,
			'query'=>'apply_filters(file11_media_source_contract, null, media_id, context)',
			'rule'=>'File 11 may reference server-verified File 10 derivatives only; raw upload/transcode/storage truth remains File 10; 60-600 seconds is enforced server-side.',
		);
		$registry['file10.live-chat-context.v1']=array(
			'owner'=>'File 17 for chat/relationships','context_owner'=>'File 10 for live event/access/moderation policy',
			'rule'=>'File 10 supplies opaque live context and moderation policy; File 17 supplies conversation transport. Neither module writes the other module database directly.',
		);
		$registry['file10.discovery-events.v1']=array(
			'consumers'=>array('File 19','File 21','File 26'),
			'events'=>array('VideoPublished.v1','VideoRestricted.v1','LiveEventScheduled.v1','LiveBroadcastStarted.v1','LiveBroadcastEnded.v1','LiveReplayPublished.v1','PodcastEpisodePublished.v1','VideoPremiereScheduled.v1'),
			'rule'=>'Cards, notifications, search/ranking and analytics are derivative projections; File 10 remains video/live truth.',
		);
		return $registry;
	}
	public function routes($routes){
		$routes=is_array($routes)?$routes:array();
		foreach(array(
			'/videos/'=>'public','/video/{id}/{slug}/'=>'conditional','/live/{id}/'=>'conditional','/channel/{slug}/'=>'public',
			'/studio/video/'=>'private','/studio/live/'=>'private','/podcast/{id}/'=>'conditional'
		) as $path=>$access){$routes[$path]=array('owner'=>'File 10','version'=>1,'access'=>$access,'layout'=>'content','shell_owner'=>'File 20');}
		return $routes;
	}
	public function owners($owners){
		$owners=is_array($owners)?$owners:array();
		foreach(array(
			'recorded_video','video_channel','video_playlist','media_asset','upload_session','video_chapter','caption_track',
			'podcast_series','podcast_episode','live_event','premiere','stream_credential','live_attendee','live_question','live_resource',
			'playback_session','download_token','creator_media_metrics','media_moderation','copyright_takedown','provider_health'
		) as $entity){$owners[$entity]='File 10';}
		return $owners;
	}
	public function publish_event($name,$payload,$event){
		do_action('sabri_event_bus_publish',$name,$payload,array('owner'=>'File 10','event_id'=>$event['public_id'],'contract_version'=>1,'privacy'=>'event-specific'));
	}
	public function process_webhook($provider,$data,$webhook_id){do_action('vwlb_provider_webhook_'.$provider,$data,$webhook_id);}
	public function receive_bus_event($event_id,$event_name,$payload){return $this->consume($event_id,$event_name,$payload);}
	public function consume($event_id,$event_name,$payload){
		$event_name=(string)$event_name;if(!in_array($event_name,VWLB_Contracts::CONSUMED_EVENTS,true))return array('ignored'=>true);
		global $wpdb;$hash=hash('sha256',VWLB_Helpers::json_encode($payload));
		$result=$wpdb->insert(VWLB_Helpers::table('inbox'),array('event_id'=>VWLB_Helpers::text($event_id,100),'event_name'=>VWLB_Helpers::text($event_name,100),'payload_hash'=>$hash,'status'=>'received','received_at'=>VWLB_Helpers::now()));
		if(false===$result)return array('duplicate'=>true);
		if('MembershipEntitlementChanged.v1'===$event_name){do_action('vwlb_membership_entitlement_changed',$payload);}
		elseif('MessageUserBlocked.v1'===$event_name){do_action('vwlb_context_user_blocked',$payload);}
		elseif('CopyrightReportFiled.v1'===$event_name){do_action('vwlb_external_copyright_report',$payload);}
		do_action('vwlb_consume_event',$event_name,$payload);
		$wpdb->update(VWLB_Helpers::table('inbox'),array('status'=>'processed','processed_at'=>VWLB_Helpers::now()),array('event_id'=>$event_id));
		return array('processed'=>true);
	}
	public function file11_media_source($value,$media_id,$context=array()){
		return VWLB_Extensions::reels_media_contract($value,$media_id,is_array($context)?$context:array());
	}
	public function file17_live_context($value,$live_id,$viewer=array()){
		$event=VWLB_Repository::find('live_events',$live_id);if(!$event||!VWLB_Security::can_view($event,'file17_live_context'))return VWLB_Helpers::error('vwlb_not_found',__('Live context not found.',VWLB_TEXT_DOMAIN),404);
		$chat=VWLB_Helpers::json($event['chat_policy_json']??'{}');
		return array('contract'=>'File10LiveContext.v1','owner'=>'File 10','live_public_id'=>$event['public_id'],'status'=>$event['status'],'visibility'=>$event['visibility'],'chat_policy'=>array('enabled'=>!empty($chat['enabled']),'moderated'=>!empty($chat['moderated']),'slow_mode_seconds'=>max(0,(int)($event['slow_mode_seconds']??$chat['slow_mode_seconds']??0))),'conversation_owner'=>'File 17','duplicate_chat_forbidden'=>true);
	}
}
