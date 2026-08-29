<?php
/** Idempotent migration from the 0.1/0.2 SVW foundation. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Compatibility {
	public static function migrate_legacy(){
		if(get_option('vwlb_legacy_migration_complete'))return true;
		global $wpdb;$legacy=$wpdb->prefix.'svw_videos';
		$wpdb->last_error='';$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$legacy));
		if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_legacy_probe_failed',__('Legacy migration source state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
		if($exists===$legacy){
			$snapshot=VWLB_DB::snapshot('legacy_options',array('svw_page_map'=>get_option('svw_page_map'),'svw_version'=>get_option('svw_version')));if(is_wp_error($snapshot))return $snapshot;
			$result=VWLB_DB::transaction(function()use($wpdb,$legacy){
				$wpdb->last_error='';$rows=$wpdb->get_results("SELECT * FROM $legacy ORDER BY id ASC LIMIT 10000",ARRAY_A);
				if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_legacy_read_failed',__('Legacy videos could not be read safely.',VWLB_TEXT_DOMAIN),500);
				foreach((array)$rows as $row){
					$wpdb->last_error='';$already=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('videos').' WHERE source_url=%s AND title=%s LIMIT 1',$row['video_url']??'',$row['title']??''));
					if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_legacy_dedupe_read_failed',__('Legacy video deduplication state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
					if($already)continue;
					$public=VWLB_Helpers::public_id('vid');
					$saved=$wpdb->insert(VWLB_Helpers::table('videos'),array('public_id'=>$public,'owner_id'=>absint($row['author_id']??0),'title'=>VWLB_Helpers::text($row['title']??__('Legacy video',VWLB_TEXT_DOMAIN)),'slug'=>sanitize_title(($row['title']??'legacy-video').'-'.substr($public,-6)),'description'=>VWLB_Helpers::textarea($row['description']??''),'provider'=>VWLB_Helpers::enum($row['provider']??'local',array('local','youtube','vimeo'),'local'),'source_url'=>esc_url_raw($row['video_url']??''),'visibility'=>'public','status'=>('publish'===($row['status']??''))?'published':'review','published_at'=>!empty($row['published_at'])?$row['published_at']:null,'rights_status'=>'declared','consent_status'=>'not_patient_case','created_at'=>$row['created_at']??VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()));
					if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_legacy_insert_failed',__('A legacy video could not be migrated safely.',VWLB_TEXT_DOMAIN),500);
				}
				return true;
			});
			if(is_wp_error($result))return $result;
		}
		$stamp=VWLB_Helpers::now();$saved=update_option('vwlb_legacy_migration_complete',$stamp,false);
		if(!$saved&&get_option('vwlb_legacy_migration_complete')!==$stamp)return VWLB_Helpers::error('vwlb_legacy_marker_failed',__('Legacy migration completion could not be recorded durably.',VWLB_TEXT_DOMAIN),500);
		return true;
	}
	public static function legacy_notice(){if(current_user_can(VWLB_Contracts::CAP_MANAGE)&&defined('SVW_VERSION'))echo '<div class="notice notice-warning"><p>'.esc_html__('The legacy Video Wall plugin is active. Deactivate it after File 10 migration and staging verification to prevent duplicate routes.',VWLB_TEXT_DOMAIN).'</p></div>';}
}
