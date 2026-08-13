<?php
/** Composition root. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Plugin {
	private static $instance;
	public static function instance(){if(!self::$instance)self::$instance=new self();return self::$instance;}
	public function run(){
		load_plugin_textdomain(VWLB_TEXT_DOMAIN,false,dirname(plugin_basename(VWLB_FILE)).'/languages');
		VWLB_Providers::register_defaults();
		$migration=VWLB_Activator::reconcile_schema();
		if(is_wp_error($migration)){
			add_action('admin_notices',function()use($migration){if(current_user_can('manage_options'))echo '<div class="notice notice-error"><p>'.esc_html($migration->get_error_message()).'</p></div>';});
			return;
		}
		VWLB_Extensions::register();
		VWLB_Observability::register();
		VWLB_Future_Intelligence::register();
		VWLB_Future_Safety::register();
		VWLB_Review_Hardening::register();
		VWLB_R3_Playback::register();
		add_filter('rest_request_before_callbacks',array('VWLB_Security','rest_mutation_before'),10,3);
		add_filter('rest_request_after_callbacks',array('VWLB_Security','rest_mutation_after'),10,3);

		$frontend=new VWLB_Frontend();$future_frontend=new VWLB_Future_Frontend();$admin=new VWLB_Admin();$privacy=new VWLB_Privacy();$integrations=new VWLB_Integrations();
		$rest=new VWLB_REST();$extended=new VWLB_Extended_REST();$future_rest=new VWLB_Future_REST();$seo=new VWLB_SEO();
		$frontend->register();$future_frontend->register();$integrations->register();
		add_action('wp_enqueue_scripts',array($frontend,'assets'));add_action('admin_enqueue_scripts',array($admin,'enqueue'));
		add_action('admin_menu',array($admin,'register'));add_action('rest_api_init',array($rest,'register'));add_action('rest_api_init',array($extended,'register'));add_action('rest_api_init',array($future_rest,'register'));
		add_filter('rest_pre_serve_request',array($this,'serve_raw_caption'),10,4);add_action('wp_head',array($seo,'output'));
		add_filter('wp_privacy_personal_data_exporters',array($privacy,'exporters'));add_filter('wp_privacy_personal_data_erasers',array($privacy,'erasers'));add_filter('wp_get_default_privacy_policy_content',array($privacy,'policy'));
		add_action('template_redirect',array($privacy,'private_headers'));add_action('admin_notices',array('VWLB_Compatibility','legacy_notice'));
		add_filter('cron_schedules',array($this,'cron_schedules'));add_action('vwlb_process_jobs',array('VWLB_Jobs','process'));add_action('vwlb_publish_outbox',array('VWLB_Jobs','publish_outbox'));
		add_action('vwlb_reconcile_states',array('VWLB_Jobs','reconcile'));add_action('vwlb_cleanup',array('VWLB_Jobs','cleanup'));
		add_action('init',array($this,'rewrite'));add_filter('query_vars',array($this,'query_vars'));add_filter('template_include',array($this,'route_template'));add_action('wp_enqueue_scripts',array($this,'enqueue_route_assets'),20);
	}
	public function cron_schedules($s){$s['vwlb_five_minutes']=array('interval'=>300,'display'=>__('Every five minutes',VWLB_TEXT_DOMAIN));return $s;}
	public function rewrite(){
		add_rewrite_rule('^videos/?$','index.php?vwlb_route=wall','top');
		add_rewrite_rule('^video/([A-Za-z0-9_-]+)/([^/]+)/?$','index.php?vwlb_video_id=$matches[1]','top');
		add_rewrite_rule('^live/([A-Za-z0-9_-]+)/?$','index.php?vwlb_live_id=$matches[1]','top');
		add_rewrite_rule('^channel/([A-Za-z0-9_-]+)/?$','index.php?vwlb_channel_slug=$matches[1]','top');
		add_rewrite_rule('^studio/video/?$','index.php?vwlb_route=studio_video','top');
		add_rewrite_rule('^studio/live/?$','index.php?vwlb_route=studio_live','top');
		add_rewrite_rule('^podcast/([A-Za-z0-9_-]+)/?$','index.php?vwlb_podcast_id=$matches[1]','top');
	}
	public function query_vars($vars){foreach(array('vwlb_video_id','vwlb_live_id','vwlb_channel_slug','vwlb_podcast_id','vwlb_route') as $v)$vars[]=$v;return $vars;}
	public function serve_raw_caption($served,$result,$request,$server){
		$route=$request->get_route();$matches=false;foreach(VWLB_Contracts::namespaces() as $n){if(str_starts_with($route,'/'.$n.'/captions/')){$matches=true;break;}}
		if(!$matches){foreach(VWLB_Contracts::namespaces() as $n){if(preg_match('#^/'.preg_quote($n,'#').'/podcasts/series/[^/]+/rss$#',$route)){$matches=true;break;}}}
		if($matches&&$result instanceof WP_REST_Response&&!is_array($result->get_data())){echo (string)$result->get_data();return true;}return $served;
	}
	public function enqueue_route_assets(){if(get_query_var('vwlb_video_id')||get_query_var('vwlb_live_id')||get_query_var('vwlb_channel_slug')||get_query_var('vwlb_podcast_id')||get_query_var('vwlb_route')){wp_enqueue_style('vwlb');wp_enqueue_script('vwlb');}}
	private function route_visible(){
		if($id=get_query_var('vwlb_video_id')){$row=VWLB_Repository::find('videos',$id);return $row&&VWLB_Security::can_view($row);}
		if($id=get_query_var('vwlb_live_id')){$row=VWLB_Repository::find('live_events',$id);return $row&&VWLB_Security::can_view($row);}
		if($slug=get_query_var('vwlb_channel_slug')){global $wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('channels').' WHERE slug=%s LIMIT 1',VWLB_Helpers::text($slug,191)),ARRAY_A);return $row&&'active'===($row['status']??'')&&('public'===($row['visibility']??'')||VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$row,'channel_route'));}
		if($id=get_query_var('vwlb_podcast_id'))return (bool)VWLB_Podcasts::public_episode_dto($id);
		return true;
	}
	public function route_template($template){if(get_query_var('vwlb_video_id')||get_query_var('vwlb_live_id')||get_query_var('vwlb_channel_slug')||get_query_var('vwlb_podcast_id')||get_query_var('vwlb_route')){if(!$this->route_visible()){status_header(404);nocache_headers();}else{status_header(200);}return VWLB_DIR.'templates/route.php';}return $template;}
}
