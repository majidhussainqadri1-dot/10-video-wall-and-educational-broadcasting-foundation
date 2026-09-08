<?php
/** R112: fail-closed authenticated creator, observability, resolver and admin read integrity. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R112_Operational_Read_Integrity {
    public static function register(){
        add_filter('rest_pre_dispatch',array(__CLASS__,'pre'),7,3);
        add_action('admin_init',array(__CLASS__,'admin_preflight'),1);
    }
    private static function namespace($route){foreach(VWLB_Contracts::namespaces() as $n){if(str_starts_with($route,'/'.$n.'/'))return $n;}return '';}
    private static function response($value,$status=200){if(is_wp_error($value))return $value;$r=rest_ensure_response($value);$r->set_status($status);$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);$r->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE);return $r;}
    public static function pre($result,$server,$request){
        if(null!==$result||!($request instanceof WP_REST_Request))return $result;
        $route=$request->get_route();$ns=self::namespace($route);if(!$ns)return $result;$method=strtoupper($request->get_method());
        if('GET'===$method&&$route==='/'.$ns.'/creator/studio')return self::creator_studio();
        if('GET'===$method&&$route==='/'.$ns.'/operations/observability')return self::observability();
        if('POST'===$method&&$route==='/'.$ns.'/podcasts/episodes'){
            $body=$request->get_json_params();$body=is_array($body)?$body:array();
            if(!empty($body['series_public_id'])){global $wpdb;$value=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',VWLB_Helpers::text($body['series_public_id'],100)),'r112_podcast_series_resolver');if(is_wp_error($value))return $value;}
        }
        return $result;
    }
    private static function creator_studio(){
        if(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'creator_studio'))return VWLB_Helpers::error('vwlb_forbidden',__('Creator Studio is restricted.',VWLB_TEXT_DOMAIN),403);
        global $wpdb;$uid=get_current_user_id();
        $videos=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,title,status,visibility,view_count,like_count,dislike_count,updated_at FROM '.VWLB_Helpers::table('videos').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),'r112_creator_videos');if(is_wp_error($videos))return $videos;
        $live=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,title,status,scheduled_start,visibility,updated_at FROM '.VWLB_Helpers::table('live_events').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),'r112_creator_live');if(is_wp_error($live))return $live;
        $jobs=VWLB_DB::read_results($wpdb->prepare('SELECT j.public_id,j.job_type,j.status,j.attempts,j.error_code,j.updated_at FROM '.VWLB_Helpers::table('processing_jobs').' j INNER JOIN '.VWLB_Helpers::table('media_assets').' a ON a.id=j.asset_id WHERE a.owner_id=%d ORDER BY j.id DESC LIMIT 100',$uid),'r112_creator_jobs');if(is_wp_error($jobs))return $jobs;
        $rights=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,target_type,target_id,status,rights_basis,decision_reason,updated_at FROM '.VWLB_Helpers::table('takedowns').' WHERE claimant_id=%d ORDER BY id DESC LIMIT 100',$uid),'r112_creator_rights');if(is_wp_error($rights))return $rights;
        foreach($rights as &$case){$entity='video'===($case['target_type']??'')?'videos':'live_events';VWLB_Repository::reset_read_failure();$target=VWLB_Repository::find($entity,(int)$case['target_id'],true);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Creator copyright target state could not be verified safely.',VWLB_TEXT_DOMAIN),503);$case['target_public_id']=$target['public_id']??'';unset($case['target_id']);}unset($case);
        $insights=VWLB_Extensions::creator_insights(30);if(is_wp_error($insights))return $insights;
        try{$comments=apply_filters('vwlb_creator_comment_projection',array(),$uid);}catch(Throwable $e){return VWLB_Helpers::error('vwlb_creator_projection_failed',__('Creator Studio comment projection failed safely.',VWLB_TEXT_DOMAIN),503);}
        return self::response(array('videos'=>$videos,'live'=>$live,'jobs'=>$jobs,'copyright'=>$rights,'insights'=>$insights,'comments'=>(array)$comments,'canonical_owner'=>'File 10','comments_owner'=>'File 21/shared interaction contract'));
    }
    private static function observability(){
        if(!VWLB_Security::can(VWLB_Contracts::CAP_DIAGNOSTICS,null,'observability'))return VWLB_Helpers::error('vwlb_forbidden',__('Operational observability is restricted.',VWLB_TEXT_DOMAIN),403);
        global $wpdb;
        $jobs=VWLB_DB::read_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('processing_jobs')." WHERE status='dead'",'r112_observability_dead_jobs');if(is_wp_error($jobs))return $jobs;
        $outbox=VWLB_DB::read_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('outbox')." WHERE status='dead'",'r112_observability_dead_outbox');if(is_wp_error($outbox))return $outbox;
        $providers=VWLB_DB::read_results('SELECT provider,capability,state,failures,last_latency_ms,circuit_open_until,last_error_code,checked_at FROM '.VWLB_Helpers::table('provider_health').' ORDER BY provider,capability LIMIT 200','r112_observability_providers');if(is_wp_error($providers))return $providers;
        return self::response(array('version'=>VWLB_VERSION,'schema'=>VWLB_SCHEMA_VERSION,'dead_jobs'=>(int)$jobs,'dead_outbox'=>(int)$outbox,'providers'=>$providers,'provider_list_truncated'=>count($providers)>=200,'extension'=>VWLB_Extensions::status(),'slo_targets'=>array('availability'=>'defined-per-provider','api_p95_ms'=>(int)apply_filters('vwlb_slo_api_p95_ms',750),'initial_rpo_hours'=>24,'initial_rto_hours'=>8),'evidence_boundary'=>'runtime measurements require staging/production traffic'));
    }
    public static function admin_preflight(){
        if(!is_admin())return;$page=sanitize_key($_GET['page']??'');if(!in_array($page,array('vwlb','vwlb-jobs','vwlb-moderation'),true))return;
        if('vwlb'===$page&&!current_user_can(VWLB_Contracts::CAP_MANAGE))return;if('vwlb-jobs'===$page&&!current_user_can(VWLB_Contracts::CAP_OPERATE)&&!current_user_can(VWLB_Contracts::CAP_MANAGE))return;if('vwlb-moderation'===$page&&!current_user_can(VWLB_Contracts::CAP_MODERATE))return;
        global $wpdb;$queries=array();
        if('vwlb'===$page){foreach(array('videos','live_events','media_assets','channels','playlists','podcast_episodes','premieres','moderation','takedowns') as $entity)$queries[]='SELECT COUNT(*) FROM '.VWLB_Helpers::table($entity);}
        elseif('vwlb-jobs'===$page)$queries[]='SELECT public_id FROM '.VWLB_Helpers::table('processing_jobs').' ORDER BY id DESC LIMIT 1';
        else $queries[]='SELECT public_id FROM '.VWLB_Helpers::table('moderation').' ORDER BY id DESC LIMIT 1';
        foreach($queries as $i=>$query){$value=VWLB_DB::read_var($query,'r112_admin_preflight_'.$i);if(is_wp_error($value))wp_die(esc_html__('Operational data could not be verified safely. Retry after database health is restored.',VWLB_TEXT_DOMAIN));}
    }
}
