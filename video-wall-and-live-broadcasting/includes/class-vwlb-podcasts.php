<?php
/** Podcast/audio domain owned by File 10 under F10-CEN-01 and CV-111. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Podcasts {
	public static function install_schema() {
		global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
		dbDelta("CREATE TABLE ".VWLB_Helpers::table('podcast_series')." (
			id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(64) NOT NULL, owner_id bigint unsigned NOT NULL,
			channel_id bigint unsigned NOT NULL DEFAULT 0, title varchar(255) NOT NULL, slug varchar(191) NOT NULL,
			description longtext NULL, language varchar(20) NOT NULL DEFAULT 'en-US', artwork_id bigint unsigned NOT NULL DEFAULT 0,
			rights_status varchar(32) NOT NULL DEFAULT 'declared', status varchar(32) NOT NULL DEFAULT 'draft',
			visibility varchar(32) NOT NULL DEFAULT 'private', version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL, updated_at datetime NOT NULL, deleted_at datetime NULL,
			PRIMARY KEY(id), UNIQUE KEY public_id(public_id), UNIQUE KEY slug(slug), KEY owner_status(owner_id,status)
		) $c");
		dbDelta("CREATE TABLE ".VWLB_Helpers::table('podcast_episodes')." (
			id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(64) NOT NULL, series_id bigint unsigned NOT NULL DEFAULT 0,
			owner_id bigint unsigned NOT NULL, asset_id bigint unsigned NOT NULL, title varchar(255) NOT NULL, slug varchar(191) NOT NULL,
			description longtext NULL, language varchar(20) NOT NULL DEFAULT 'en-US', duration_seconds int unsigned NOT NULL DEFAULT 0,
			rights_status varchar(32) NOT NULL DEFAULT 'declared', consent_status varchar(32) NOT NULL DEFAULT 'not_patient_case',
			visibility varchar(32) NOT NULL DEFAULT 'private', status varchar(32) NOT NULL DEFAULT 'draft',
			transcript_caption_id bigint unsigned NOT NULL DEFAULT 0, transcript_text longtext NULL, transcript_status varchar(32) NOT NULL DEFAULT 'draft', download_allowed tinyint(1) NOT NULL DEFAULT 0,
			published_at datetime NULL, version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL,
			updated_at datetime NOT NULL, deleted_at datetime NULL,
			PRIMARY KEY(id), UNIQUE KEY public_id(public_id), UNIQUE KEY series_slug(series_id,slug),
			KEY owner_status(owner_id,status), KEY asset_id(asset_id), KEY published_at(published_at)
		) $c");
	}

	public static function create_series($data){
		if(!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,null,'create_podcast_series'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot create podcast series.',VWLB_TEXT_DOMAIN),403);
		$title=VWLB_Helpers::text($data['title']??'',255);if(!$title)return VWLB_Helpers::error('vwlb_title_required',__('Title is required.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$now=VWLB_Helpers::now();$public=VWLB_Helpers::public_id('pod');
		$slug=sanitize_title($data['slug']??$title.'-'.substr($public,-6));
		$ok=$wpdb->insert(VWLB_Helpers::table('podcast_series'),array(
			'public_id'=>$public,'owner_id'=>get_current_user_id(),'channel_id'=>absint($data['channel_id']??0),
			'title'=>$title,'slug'=>$slug,'description'=>VWLB_Helpers::textarea($data['description']??''),
			'language'=>VWLB_Helpers::text($data['language']??'en-US',20),'artwork_id'=>absint($data['artwork_id']??0),
			'rights_status'=>VWLB_Helpers::enum($data['rights_status']??'declared',array('declared','verified','restricted'),'declared'),
			'status'=>'draft','visibility'=>VWLB_Helpers::enum($data['visibility']??'private',VWLB_Contracts::VISIBILITIES,'private'),
			'version'=>1,'created_at'=>$now,'updated_at'=>$now
		));
		if(!$ok)return VWLB_Helpers::error('vwlb_database_error',__('Podcast series could not be created.',VWLB_TEXT_DOMAIN),500);
		$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('podcast_series',$id,'create','','draft');
		return array('id'=>$id,'public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);
	}

	public static function create_episode($data){
		if(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'create_podcast_episode'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot create podcast episodes.',VWLB_TEXT_DOMAIN),403);
		$title=VWLB_Helpers::text($data['title']??'',255);$asset=VWLB_Repository::find('media_assets',$data['asset_id']??0);
		if(!$title||!$asset)return VWLB_Helpers::error('vwlb_episode_fields_required',__('Title and audio asset are required.',VWLB_TEXT_DOMAIN),422);
		if((int)$asset['owner_id']!==get_current_user_id()&&!VWLB_Security::can(VWLB_Contracts::CAP_MANAGE,$asset,'create_podcast_episode'))return VWLB_Helpers::error('vwlb_not_found',__('Audio asset not found.',VWLB_TEXT_DOMAIN),404);
		if(!in_array($asset['media_class'],array('audio','podcast'),true))return VWLB_Helpers::error('vwlb_episode_asset_invalid',__('Podcast episodes require an audio asset.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$now=VWLB_Helpers::now();$public=VWLB_Helpers::public_id('ep');
		$slug=sanitize_title($data['slug']??$title.'-'.substr($public,-6));
		$ok=$wpdb->insert(VWLB_Helpers::table('podcast_episodes'),array(
			'public_id'=>$public,'series_id'=>absint($data['series_id']??0),'owner_id'=>get_current_user_id(),
			'asset_id'=>$asset['id'],'title'=>$title,'slug'=>$slug,'description'=>VWLB_Helpers::textarea($data['description']??''),
			'language'=>VWLB_Helpers::text($data['language']??'en-US',20),'duration_seconds'=>(int)$asset['duration_seconds'],
			'rights_status'=>VWLB_Helpers::enum($data['rights_status']??$asset['rights_status'],array('declared','verified','disputed','restricted'),'declared'),
			'consent_status'=>VWLB_Helpers::enum($data['consent_status']??'not_patient_case',array('not_patient_case','documented','anonymized','approved','missing','withdrawn'),'not_patient_case'),
			'visibility'=>VWLB_Helpers::enum($data['visibility']??'private',VWLB_Contracts::VISIBILITIES,'private'),
			'status'=>'draft','transcript_caption_id'=>absint($data['transcript_caption_id']??0),
			'transcript_text'=>VWLB_Helpers::textarea($data['transcript']??'',500000),'transcript_status'=>VWLB_Helpers::enum($data['transcript_status']??(!empty($data['transcript'])?'review':'draft'),array('draft','review','published','rejected'),'draft'),
			'download_allowed'=>!empty($data['download_allowed'])?1:0,'version'=>1,'created_at'=>$now,'updated_at'=>$now
		));
		if(!$ok)return VWLB_Helpers::error('vwlb_database_error',__('Podcast episode could not be created.',VWLB_TEXT_DOMAIN),500);
		$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('podcast_episode',$id,'create','','draft');
		return array('id'=>$id,'public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);
	}

	public static function publish_episode($id,$expected_version){
		$ep=self::episode($id,true);if(!$ep)return VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);
		if(!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$ep,'publish_podcast'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot publish this podcast.',VWLB_TEXT_DOMAIN),403);
		if((int)$ep['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('The podcast changed. Refresh before publishing.',VWLB_TEXT_DOMAIN),409);
		$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);if(!$asset||'ready'!==$asset['status']||'passed'!==$asset['scan_status'])return VWLB_Helpers::error('vwlb_media_not_ready',__('The audio asset must be scanned and ready.',VWLB_TEXT_DOMAIN),422);
		if(!in_array($ep['rights_status'],array('declared','verified'),true)||in_array($ep['consent_status'],array('missing','withdrawn'),true))return VWLB_Helpers::error('vwlb_rights_consent_gate',__('Rights or consent review is incomplete.',VWLB_TEXT_DOMAIN),422);
		if((!$ep['transcript_caption_id'] && !trim((string)($ep['transcript_text']??''))) || !in_array(($ep['transcript_status']??'draft'),array('published','review'),true))return VWLB_Helpers::error('vwlb_transcript_required',__('A reviewed transcript is required before podcast publication.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$now=VWLB_Helpers::now();$updated=$wpdb->update(VWLB_Helpers::table('podcast_episodes'),array('status'=>'published','published_at'=>$now,'version'=>(int)$ep['version']+1,'updated_at'=>$now),array('id'=>$ep['id'],'version'=>$ep['version']));
		if(1!==$updated)return VWLB_Helpers::error('vwlb_version_conflict',__('The podcast changed concurrently.',VWLB_TEXT_DOMAIN),409);
		VWLB_Helpers::audit('podcast_episode',$ep['id'],'publish',$ep['status'],'published');
		VWLB_Helpers::outbox('PodcastEpisodePublished','podcast',$ep['id'],array('public_id'=>$ep['public_id'],'series_id'=>$ep['series_id']));
		return self::episode($ep['id'],true);
	}

	public static function episode($id,$private=false){
		global $wpdb;$where=is_numeric($id)?$wpdb->prepare('id=%d',absint($id)):$wpdb->prepare('public_id=%s',VWLB_Helpers::text($id,64));
		$row=$wpdb->get_row("SELECT * FROM ".VWLB_Helpers::table('podcast_episodes')." WHERE $where AND deleted_at IS NULL LIMIT 1",ARRAY_A);
		if(!$row)return null;
		if(!$private&&('published'!==$row['status']||!VWLB_Security::can_view($row,'podcast_playback')))return null;
		return $row;
	}

	public static function public_episode_dto($id){
		$ep=self::episode($id,false);if(!$ep)return null;$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);$der=VWLB_Helpers::json($asset['derivatives_json']??'{}');
		return array(
			'id'=>$ep['public_id'],'series_id'=>(int)$ep['series_id'],'title'=>$ep['title'],'slug'=>$ep['slug'],
			'description'=>$ep['description'],'language'=>$ep['language'],'duration_seconds'=>(int)$ep['duration_seconds'],
			'rights_status'=>$ep['rights_status'],'visibility'=>$ep['visibility'],'published_at'=>VWLB_Helpers::iso_utc($ep['published_at']),
			'audio_url'=>esc_url_raw($der['audio_only']??$der['mp3']??$der['mp4_low']??''),
			'download_available'=>(bool)$ep['download_allowed'],'transcript'=>($ep['transcript_status']??'')==='published'?(string)($ep['transcript_text']??''):'','chapters'=>VWLB_Extensions::chapters('podcast',$ep['id']),
		);
	}


	public static function publish_series($id,$expected_version){
		global $wpdb;$series=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE (id=%d OR public_id=%s) AND deleted_at IS NULL LIMIT 1',absint($id),VWLB_Helpers::text($id,64)),ARRAY_A);
		if(!$series)return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);
		if(!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$series,'publish_podcast_series'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot publish this podcast series.',VWLB_TEXT_DOMAIN),403);
		if((int)$series['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('The podcast series changed. Refresh and retry.',VWLB_TEXT_DOMAIN),409);
		if(!in_array($series['rights_status'],array('declared','verified'),true))return VWLB_Helpers::error('vwlb_rights_gate',__('Podcast rights review is incomplete.',VWLB_TEXT_DOMAIN),422);
		$updated=$wpdb->update(VWLB_Helpers::table('podcast_series'),array('status'=>'published','version'=>(int)$series['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$series['id'],'version'=>$series['version']));
		if(1!==$updated)return VWLB_Helpers::error('vwlb_version_conflict',__('The podcast series changed concurrently.',VWLB_TEXT_DOMAIN),409);
		VWLB_Helpers::audit('podcast_series',$series['id'],'publish',$series['status'],'published');
		VWLB_Helpers::outbox('PodcastSeriesPublished','podcast_series',$series['id'],array('public_id'=>$series['public_id']));
		return array('id'=>$series['public_id'],'status'=>'published','version'=>(int)$series['version']+1);
	}

	public static function rss_xml($series_id){
		$feed=self::feed($series_id);if(is_wp_error($feed))return $feed;
		$series=$feed['series'];$xml='<?xml version="1.0" encoding="UTF-8"?>';
		$xml.='<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
		$xml.='<title>'.esc_html($series['title']).'</title><description>'.esc_html($series['description']).'</description>';
		$xml.='<language>'.esc_html($series['language']).'</language>';
		foreach($feed['episodes'] as $ep){$xml.='<item><guid isPermaLink="false">'.esc_html($ep['id']).'</guid><title>'.esc_html($ep['title']).'</title><description>'.esc_html(wp_strip_all_tags($ep['description'])).'</description>';
			if(!empty($ep['published_at']))$xml.='<pubDate>'.esc_html(gmdate(DATE_RSS,strtotime($ep['published_at']))).'</pubDate>';
			if(!empty($ep['audio_url']))$xml.='<enclosure url="'.esc_attr($ep['audio_url']).'" type="audio/mpeg" />';
			$xml.='</item>';}
		$xml.='</channel></rss>';return $xml;
	}

	public static function feed($series_id){
		global $wpdb;$series=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE (id=%d OR public_id=%s) AND status=%s AND visibility IN (%s,%s) AND deleted_at IS NULL LIMIT 1',absint($series_id),VWLB_Helpers::text($series_id,64),'published','public','unlisted'),ARRAY_A);
		if(!$series)return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);
		$episodes=$wpdb->get_results($wpdb->prepare('SELECT public_id,title,slug,description,language,duration_seconds,published_at,asset_id FROM '.VWLB_Helpers::table('podcast_episodes').' WHERE series_id=%d AND status=%s AND visibility IN (%s,%s) AND deleted_at IS NULL ORDER BY published_at DESC,id DESC LIMIT 200',$series['id'],'published','public','unlisted'),ARRAY_A);
		$items=array();foreach($episodes as $ep){$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);$der=VWLB_Helpers::json($asset['derivatives_json']??'{}');$items[]=array('id'=>$ep['public_id'],'title'=>$ep['title'],'description'=>$ep['description'],'published_at'=>VWLB_Helpers::iso_utc($ep['published_at']),'duration_seconds'=>(int)$ep['duration_seconds'],'audio_url'=>esc_url_raw($der['audio_only']??$der['mp3']??''));}
		return array('contract'=>'File10PodcastFeed.v1','series'=>array('id'=>$series['public_id'],'title'=>$series['title'],'description'=>$series['description'],'language'=>$series['language']),'episodes'=>$items,'rss_ready'=>true,'canonical_owner'=>'File 10');
	}
}
