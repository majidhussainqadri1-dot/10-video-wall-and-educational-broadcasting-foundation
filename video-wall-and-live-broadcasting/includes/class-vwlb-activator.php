<?php
defined( 'ABSPATH' ) || exit;
final class VWLB_Activator {
	const MIGRATION_LOCK = 'vwlb_schema_migration_lock';
	const MIGRATION_LOCK_TTL = 300;

	public static function activate() {
		if ( version_compare(PHP_VERSION,'8.3','<') ) { deactivate_plugins(plugin_basename(VWLB_FILE)); wp_die(esc_html__('File 10 requires PHP 8.3 or newer.',VWLB_TEXT_DOMAIN)); }
		if ( isset($GLOBALS['wp_version']) && version_compare((string)$GLOBALS['wp_version'],'7.0','<') ) { deactivate_plugins(plugin_basename(VWLB_FILE)); wp_die(esc_html__('File 10 requires WordPress 7.0 or newer.',VWLB_TEXT_DOMAIN)); }
		$migration=self::reconcile_schema();
		if(is_wp_error($migration)){deactivate_plugins(plugin_basename(VWLB_FILE));wp_die(esc_html($migration->get_error_message()));}
		self::capabilities(); self::pages(); self::schedules(); VWLB_Compatibility::migrate_legacy();
		update_option('vwlb_version',VWLB_VERSION,false); update_option('vwlb_safe_mode',0,false); flush_rewrite_rules(false);
	}

	/**
	 * Serialize base, extension and Future schema reconciliation. WordPress options have
	 * a unique key, so add_option() is the cross-request compare-and-set primitive here.
	 * A bounded stale-lock takeover prevents a crashed upgrader from wedging File 10.
	 */
	public static function reconcile_schema() {
		$acquired=add_option(self::MIGRATION_LOCK,time(),'no',false);
		if(!$acquired){
			$locked_at=absint(get_option(self::MIGRATION_LOCK,0));
			if($locked_at && (time()-$locked_at)>self::MIGRATION_LOCK_TTL){
				delete_option(self::MIGRATION_LOCK);
				$acquired=add_option(self::MIGRATION_LOCK,time(),'no',false);
			}
		}
		if(!$acquired)return VWLB_Helpers::error('vwlb_schema_migration_busy',__('File 10 schema migration is already in progress. Retry shortly.',VWLB_TEXT_DOMAIN),503);
		try{
			if(get_option('vwlb_schema_version')!==VWLB_SCHEMA_VERSION)VWLB_DB::install_schema();
			if(get_option(VWLB_Extensions::OPTION)!==VWLB_EXT_SCHEMA_VERSION)VWLB_Extensions::install_schema();
			if(get_option(VWLB_Future_Intelligence::OPTION)!==VWLB_FUTURE_SCHEMA_VERSION)VWLB_Future_Intelligence::install_schema();
			return true;
		}catch(Throwable $e){
			return VWLB_Helpers::error('vwlb_schema_migration_failed',__('File 10 schema migration failed safely.',VWLB_TEXT_DOMAIN),500,array('exception'=>get_class($e)));
		}finally{
			delete_option(self::MIGRATION_LOCK);
		}
	}

	public static function deactivate() { foreach(array('vwlb_process_jobs','vwlb_publish_outbox','vwlb_reconcile_states','vwlb_cleanup') as $hook){ wp_clear_scheduled_hook($hook); } flush_rewrite_rules(false); }
	private static function capabilities() {
		$roles=array('administrator'=>array(VWLB_Contracts::CAP_SUBMIT,VWLB_Contracts::CAP_PUBLISH,VWLB_Contracts::CAP_BROADCAST,VWLB_Contracts::CAP_MODERATE,VWLB_Contracts::CAP_REVIEW,VWLB_Contracts::CAP_OPERATE,VWLB_Contracts::CAP_MANAGE,VWLB_Contracts::CAP_DIAGNOSTICS));
		$roles=(array)apply_filters('vwlb_activation_role_capabilities',$roles);
		foreach($roles as $name=>$caps){$role=get_role($name);if($role){foreach($caps as $cap){$role->add_cap($cap);}}}
	}
	public static function schedules() {
		add_filter('cron_schedules',array(__CLASS__,'cron_schedules'));
		if(!wp_next_scheduled('vwlb_process_jobs'))wp_schedule_event(time()+60,'vwlb_five_minutes','vwlb_process_jobs');
		if(!wp_next_scheduled('vwlb_publish_outbox'))wp_schedule_event(time()+90,'vwlb_five_minutes','vwlb_publish_outbox');
		if(!wp_next_scheduled('vwlb_reconcile_states'))wp_schedule_event(time()+120,'hourly','vwlb_reconcile_states');
		if(!wp_next_scheduled('vwlb_cleanup'))wp_schedule_event(time()+300,'daily','vwlb_cleanup');
	}
	public static function cron_schedules($s){$s['vwlb_five_minutes']=array('interval'=>300,'display'=>'Every five minutes');return $s;}
	private static function pages() {
		$pages=array(
			'videos'=>array('title'=>__('Video Wall',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_wall]'),
			'video'=>array('title'=>__('Video',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_video]'),
			'live'=>array('title'=>__('Live',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_live]'),
			'channel'=>array('title'=>__('Channel',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_channel]'),
			'studio-video'=>array('title'=>__('Video Studio',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_studio_video]'),
			'studio-live'=>array('title'=>__('Live Studio',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_studio_live]'),
			'video-history'=>array('title'=>__('Video History',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_history]'),
			'podcasts'=>array('title'=>__('Podcasts',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_podcasts]')
		);
		$before=get_option('vwlb_page_map',array()); VWLB_DB::snapshot('activation_pages',$before);
		$map=array(); foreach($pages as $slug=>$data){$page=get_page_by_path($slug);if($page && strpos((string)$page->post_content,'[vwlb_')===false){$slug='file-10-'.$slug;$page=get_page_by_path($slug);}if(!$page){$id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$data['title'],'post_name'=>$slug,'post_content'=>$data['content']),true);if(!is_wp_error($id))$map[$slug]=(int)$id;}else{$map[$slug]=(int)$page->ID;}} update_option('vwlb_page_map',$map,false);
	}
}
