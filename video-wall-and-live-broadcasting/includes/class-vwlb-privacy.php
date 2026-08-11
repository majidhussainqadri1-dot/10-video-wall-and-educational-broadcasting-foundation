<?php
/** WordPress privacy lifecycle including File 10 v1.1 private media/live extensions. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Privacy {
	public function exporters($exporters){$exporters['vwlb']=array('exporter_friendly_name'=>__('Video Wall and Live Broadcasting',VWLB_TEXT_DOMAIN),'callback'=>array($this,'export'));return $exporters;}
	public function erasers($erasers){$erasers['vwlb']=array('eraser_friendly_name'=>__('Video Wall and Live Broadcasting',VWLB_TEXT_DOMAIN),'callback'=>array($this,'erase'));return $erasers;}
	public function policy($content){
		$text='<p>'.esc_html__('File 10 stores private watch progress, saves/reactions, resumable-upload state, live waiting-room and recording-consent records, questions, rights-aware download grants, creator aggregates, reports, moderation history and short-lived security metadata as required for the service. Raw private uploads and stream credentials are never public. Eligible user data can be exported or erased; rights, safety and audit evidence may instead be anonymized or retained only under a scoped lawful hold.',VWLB_TEXT_DOMAIN).'</p>';
		return $content.$text;
	}
	private function item($group,$label,$id,$row){
		$data=array();foreach($row as $k=>$v){if(in_array($k,array('token_hash','private_filename'),true))continue;$data[]=array('name'=>ucwords(str_replace('_',' ',$k)),'value'=>is_scalar($v)?(string)$v:wp_json_encode($v));}
		return array('group_id'=>$group,'group_label'=>$label,'item_id'=>$id,'data'=>$data);
	}
	public function export($email,$page=1){
		$user=get_user_by('email',$email);if(!$user)return array('data'=>array(),'done'=>true);
		global $wpdb;$limit=100;$offset=(max(1,(int)$page)-1)*$limit;$data=array();$max_count=0;
		$queries=array(
			'history'=>array(__('Video history',VWLB_TEXT_DOMAIN),'playback_sessions','user_id','object_type,object_id,progress_seconds,duration_seconds,completed,updated_at'),
			'interactions'=>array(__('Video interactions',VWLB_TEXT_DOMAIN),'interactions','user_id','video_id,interaction,created_at,updated_at'),
			'live'=>array(__('Live participation and recording consent',VWLB_TEXT_DOMAIN),'live_attendees','user_id','public_id,live_event_id,state,reminder_minutes,recording_consent,consent_version,consented_at,joined_at,left_at,updated_at'),
			'questions'=>array(__('Live questions',VWLB_TEXT_DOMAIN),'live_questions','user_id','public_id,live_event_id,question,status,answer,created_at,updated_at'),
			'uploads'=>array(__('Private upload sessions',VWLB_TEXT_DOMAIN),'upload_sessions','owner_id','public_id,asset_id,expected_bytes,received_bytes,status,expires_at,created_at,updated_at'),
			'downloads'=>array(__('Media download grants',VWLB_TEXT_DOMAIN),'download_tokens','user_id','public_id,object_type,object_id,max_downloads,download_count,status,expires_at,created_at,updated_at'),
		);
		foreach($queries as $gid=>$q){
			[$label,$table,$column,$fields]=$q;
			$rows=$wpdb->get_results($wpdb->prepare("SELECT $fields FROM ".VWLB_Helpers::table($table)." WHERE $column=%d ORDER BY id LIMIT %d OFFSET %d",$user->ID,$limit,$offset),ARRAY_A);
			$max_count=max($max_count,count($rows));foreach($rows as $i=>$row)$data[]=$this->item('vwlb-'.$gid,$label,$gid.'-'.($offset+$i+1),$row);
		}
		return array('data'=>$data,'done'=>$max_count<$limit);
	}
	public function erase($email,$page=1){
		$user=get_user_by('email',$email);if(!$user)return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);
		global $wpdb;$uid=(int)$user->ID;$removed=false;
		$affected=$wpdb->get_col($wpdb->prepare('SELECT DISTINCT video_id FROM '.VWLB_Helpers::table('interactions').' WHERE user_id=%d',$uid));
		foreach(array('playback_sessions','interactions','download_tokens') as $table){$result=$wpdb->delete(VWLB_Helpers::table($table),array('user_id'=>$uid));$removed=$removed||$result>0;}
		$uploads=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('upload_sessions').' WHERE owner_id=%d',$uid),ARRAY_A);
		foreach($uploads as $s){if('active'===$s['status']){$base=trailingslashit(WP_CONTENT_DIR).VWLB_Extensions::PRIVATE_DIR;$path=$base.'/'.sanitize_file_name($s['private_filename']);if(is_file($path))@unlink($path);}}
		$result=$wpdb->delete(VWLB_Helpers::table('upload_sessions'),array('owner_id'=>$uid));$removed=$removed||$result>0;
		foreach($affected as $video_id){foreach(array('like','dislike') as $type){$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.VWLB_Helpers::table('interactions').' WHERE video_id=%d AND interaction=%s',$video_id,$type));$wpdb->update(VWLB_Helpers::table('videos'),array($type.'_count'=>$count),array('id'=>$video_id));}}
		// Safety/rights evidence is retained only in anonymized form.
		$wpdb->update(VWLB_Helpers::table('live_attendees'),array('user_id'=>0,'state'=>'anonymized'),array('user_id'=>$uid));
		$wpdb->update(VWLB_Helpers::table('live_questions'),array('user_id'=>0,'question'=>'[erased by privacy request]'),array('user_id'=>$uid));
		$wpdb->update(VWLB_Helpers::table('moderation'),array('reporter_id'=>0,'note'=>'[anonymized by privacy erasure]'),array('reporter_id'=>$uid));
		$wpdb->update(VWLB_Helpers::table('audit'),array('actor_id'=>0),array('actor_id'=>$uid));
		$wpdb->update(VWLB_Helpers::table('creator_metrics_daily'),array('owner_id'=>0),array('owner_id'=>$uid));
		VWLB_Helpers::audit('privacy',$uid,'erase','','complete','Eligible File 10 private data erased; retained safety/rights evidence anonymized',array('purpose'=>'privacy_erasure'));
		return array('items_removed'=>$removed,'items_retained'=>true,'messages'=>array(__('Safety, copyright, recording-consent and audit records were anonymized where retention was required.',VWLB_TEXT_DOMAIN)),'done'=>true);
	}
	public function private_headers(){
		$private_slugs=array('video-history','studio-video','studio-live','podcasts');
		if(is_page(array_values((array)get_option('vwlb_page_map',array())))&&(is_user_logged_in()||is_page($private_slugs)))VWLB_Helpers::no_cache_private();
		if(get_query_var('vwlb_video_id')||get_query_var('vwlb_live_id')||get_query_var('vwlb_podcast_id'))VWLB_Helpers::no_cache_private();
	}
}
