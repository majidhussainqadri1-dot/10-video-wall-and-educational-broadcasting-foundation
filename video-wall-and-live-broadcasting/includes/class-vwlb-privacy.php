<?php
/** WordPress privacy lifecycle including File 10 v1.2 private media/live/Future extensions. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Privacy {
	public function exporters($exporters){$exporters['vwlb']=array('exporter_friendly_name'=>__('Video Wall and Live Broadcasting',VWLB_TEXT_DOMAIN),'callback'=>array($this,'export'));return $exporters;}
	public function erasers($erasers){$erasers['vwlb']=array('eraser_friendly_name'=>__('Video Wall and Live Broadcasting',VWLB_TEXT_DOMAIN),'callback'=>array($this,'erase'));return $erasers;}
	public function policy($content){$text='<p>'.esc_html__('File 10 stores private watch progress, saves/reactions, resumable-upload state, live waiting-room and recording-consent records, questions, poll responses, guest delegation, media-review attribution, rights-aware download grants, creator aggregates, reports, moderation history and short-lived security metadata as required for the service. Raw private uploads and stream credentials are never public. Eligible user data can be exported or erased; rights, safety and audit evidence may instead be anonymized or retained only under a scoped lawful hold.',VWLB_TEXT_DOMAIN).'</p>';return $content.$text;}
	private function item($group,$label,$id,$row){$data=array();foreach($row as $k=>$v){if(in_array($k,array('token_hash','private_filename','credential_hash','metadata_json','scope_json'),true))continue;$data[]=array('name'=>ucwords(str_replace('_',' ',$k)),'value'=>is_scalar($v)?(string)$v:wp_json_encode($v));}return array('group_id'=>$group,'group_label'=>$label,'item_id'=>$id,'data'=>$data);}
	private function export_query($user_id,$table,$column,$fields,$limit,$offset){global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT $fields FROM ".VWLB_Helpers::table($table)." WHERE $column=%d ORDER BY id LIMIT %d OFFSET %d",$user_id,$limit,$offset),ARRAY_A);}
	public function export($email,$page=1){
		$user=get_user_by('email',$email);if(!$user)return array('data'=>array(),'done'=>true);global $wpdb;$limit=100;$offset=(max(1,(int)$page)-1)*$limit;$data=array();$max_count=0;
		$queries=array(
			'history'=>array(__('Video history',VWLB_TEXT_DOMAIN),'playback_sessions','user_id','public_id,object_type,object_id,quality,progress_seconds,duration_seconds,completed,created_at,updated_at,expires_at'),
			'interactions'=>array(__('Video interactions',VWLB_TEXT_DOMAIN),'interactions','user_id','video_id,interaction,created_at,updated_at'),
			'live'=>array(__('Live participation and recording consent',VWLB_TEXT_DOMAIN),'live_attendees','user_id','public_id,live_event_id,state,reminder_minutes,recording_consent,consent_version,consented_at,joined_at,left_at,updated_at'),
			'questions'=>array(__('Live questions',VWLB_TEXT_DOMAIN),'live_questions','user_id','public_id,live_event_id,question,status,answer,created_at,updated_at'),
			'uploads'=>array(__('Private upload sessions',VWLB_TEXT_DOMAIN),'upload_sessions','owner_id','public_id,asset_id,expected_bytes,received_bytes,status,expires_at,created_at,updated_at'),
			'downloads'=>array(__('Media download grants',VWLB_TEXT_DOMAIN),'download_tokens','user_id','public_id,object_type,object_id,max_downloads,download_count,status,expires_at,created_at,updated_at'),
			'guests'=>array(__('Broadcast guest participation',VWLB_TEXT_DOMAIN),'broadcast_guests','user_id','public_id,live_event_id,role_name,status,expires_at,accepted_at,created_at,updated_at'),
			'polls'=>array(__('Live poll responses',VWLB_TEXT_DOMAIN),'live_poll_responses','user_id','poll_id,option_id,created_at'),
		);
		foreach($queries as $gid=>$q){[$label,$table,$column,$fields]=$q;$rows=$this->export_query($user->ID,$table,$column,$fields,$limit,$offset);$max_count=max($max_count,count($rows));foreach($rows as $i=>$row)$data[]=$this->item('vwlb-'.$gid,$label,$gid.'-'.($offset+$i+1),$row);}
		return array('data'=>$data,'done'=>$max_count<$limit);
	}
	private function delete_user_rows($table,$column,$uid,&$removed){global $wpdb;$result=$wpdb->delete(VWLB_Helpers::table($table),array($column=>$uid));if(false===$result)return VWLB_Helpers::error('vwlb_privacy_delete_failed',__('Privacy erasure could not delete an eligible File 10 record.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));$removed=$removed||$result>0;return true;}
	private function anonymize_user_rows($table,$column,$uid,$changes){global $wpdb;$result=$wpdb->update(VWLB_Helpers::table($table),$changes,array($column=>$uid));return false===$result?VWLB_Helpers::error('vwlb_privacy_anonymize_failed',__('Privacy erasure could not anonymize a retained File 10 record.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'column'=>$column)):true;}
	public function erase($email,$page=1){
		$user=get_user_by('email',$email);if(!$user)return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);global $wpdb;$uid=(int)$user->ID;$removed=false;$messages=array();
		$uploads=$wpdb->get_results($wpdb->prepare('SELECT id,private_filename,status FROM '.VWLB_Helpers::table('upload_sessions').' WHERE owner_id=%d',$uid),ARRAY_A);foreach($uploads as $s){if('active'===$s['status']&&!empty($s['private_filename'])){$base=trailingslashit(WP_CONTENT_DIR).VWLB_Extensions::PRIVATE_DIR;$path=$base.'/'.sanitize_file_name($s['private_filename']);if(is_file($path)&&!unlink($path))return array('items_removed'=>$removed,'items_retained'=>true,'messages'=>array(__('A private upload could not be deleted from storage; database erasure was stopped so the request can be retried safely.',VWLB_TEXT_DOMAIN)),'done'=>false);}}
		$affected=$wpdb->get_col($wpdb->prepare('SELECT DISTINCT video_id FROM '.VWLB_Helpers::table('interactions').' WHERE user_id=%d',$uid));
		$result=VWLB_DB::transaction(function()use($wpdb,$uid,$affected,&$removed){
			foreach(array(array('playback_sessions','user_id'),array('interactions','user_id'),array('download_tokens','user_id'),array('upload_sessions','owner_id'),array('live_poll_responses','user_id'),array('broadcast_guests','user_id')) as $spec){$ok=$this->delete_user_rows($spec[0],$spec[1],$uid,$removed);if(is_wp_error($ok))return $ok;}
			foreach($affected as $video_id){foreach(array('like','dislike') as $type){$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.VWLB_Helpers::table('interactions').' WHERE video_id=%d AND interaction=%s',$video_id,$type));$changed=$wpdb->update(VWLB_Helpers::table('videos'),array($type.'_count'=>$count),array('id'=>$video_id));if(false===$changed)return VWLB_Helpers::error('vwlb_privacy_counter_failed',__('Privacy erasure could not reconcile an interaction counter.',VWLB_TEXT_DOMAIN),500);}}
			$anonymize=array(
				array('live_attendees','user_id',array('user_id'=>0,'state'=>'anonymized')),
				array('live_questions','user_id',array('user_id'=>0,'question'=>'[erased by privacy request]')),
				array('moderation','reporter_id',array('reporter_id'=>0,'note'=>'[anonymized by privacy erasure]')),
				array('audit','actor_id',array('actor_id'=>0)),
				array('creator_metrics_daily','owner_id',array('owner_id'=>0)),
				array('production_sources','owner_id',array('owner_id'=>0)),array('production_scenes','owner_id',array('owner_id'=>0)),
				array('simulcast_targets','created_by',array('created_by'=>0)),array('media_tracks','created_by',array('created_by'=>0)),array('media_tracks','reviewed_by',array('reviewed_by'=>0)),
				array('video_annotations','created_by',array('created_by'=>0)),array('video_annotations','reviewed_by',array('reviewed_by'=>0)),array('live_polls','created_by',array('created_by'=>0)),
				array('consent_links','created_by',array('created_by'=>0)),array('watermark_policies','updated_by',array('updated_by'=>0)),array('broadcast_guests','invited_by',array('invited_by'=>0))
			);
			foreach($anonymize as $spec){$ok=$this->anonymize_user_rows($spec[0],$spec[1],$uid,$spec[2]);if(is_wp_error($ok))return $ok;}
			VWLB_Helpers::audit('privacy',$uid,'erase','','complete','Eligible File 10 private data erased; retained safety/rights evidence anonymized',array('purpose'=>'privacy_erasure'));return true;
		});
		if(is_wp_error($result)){return array('items_removed'=>$removed,'items_retained'=>true,'messages'=>array($result->get_error_message()),'done'=>false);}
		$messages[]=__('Safety, copyright, recording-consent, moderation and audit records were anonymized where retention was required.',VWLB_TEXT_DOMAIN);return array('items_removed'=>$removed,'items_retained'=>true,'messages'=>$messages,'done'=>true);
	}
	public function private_headers(){
		$private_slugs=array('video-history','studio-video','studio-live');if(is_page(array_values((array)get_option('vwlb_page_map',array())))&&(is_user_logged_in()||is_page($private_slugs)))VWLB_Helpers::no_cache_private();
		if(get_query_var('vwlb_video_id')){$v=VWLB_Repository::find('videos',get_query_var('vwlb_video_id'));if(!$v||'published'!==($v['status']??'')||'public'!==($v['visibility']??''))VWLB_Helpers::no_cache_private();}
		if(get_query_var('vwlb_live_id')){$e=VWLB_Repository::find('live_events',get_query_var('vwlb_live_id'));if(!$e||'public'!==($e['visibility']??'')||!in_array($e['status']??'',array('scheduled','live','ended','replay_published'),true))VWLB_Helpers::no_cache_private();}
	}
}
