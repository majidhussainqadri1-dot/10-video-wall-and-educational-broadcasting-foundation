<?php
/** R111: fail-closed public read/enrichment integrity. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R111_Public_Read_Integrity {
    public static function register(){ add_filter('rest_pre_dispatch',array(__CLASS__,'pre'),8,3); }
    private static function in_namespace($route){ foreach(VWLB_Contracts::namespaces() as $n){ if(str_starts_with($route,'/'.$n.'/'))return $n; } return ''; }
    private static function response($value,$status=200){ if(is_wp_error($value))return $value; $r=rest_ensure_response($value); $r->set_status($status); $r->header('X-Sabri-File','10'); $r->header('X-VWLB-Version',VWLB_VERSION); $r->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE); return $r; }
    public static function pre($result,$server,$request){
        if(null!==$result || !($request instanceof WP_REST_Request) || 'GET'!==strtoupper($request->get_method()))return $result;
        $route=$request->get_route(); $ns=self::in_namespace($route); if(!$ns)return $result; $q=preg_quote($ns,'#');
        if($route==='/'.$ns.'/live-events')return self::browse_live($request);
        if(preg_match('#^/'.$q.'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)$#',$route,$m))return self::video_detail($m['id']);
        if(preg_match('#^/'.$q.'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/chapters$#',$route,$m))return self::video_chapters($m['id']);
        return $result;
    }
    private static function browse_live($request){
        global $wpdb; $limit=max(1,min(24,absint($request['per_page']?:12)));
        $query=$wpdb->prepare("SELECT * FROM ".VWLB_Helpers::table('live_events')." WHERE visibility='public' AND status IN ('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published') AND deleted_at IS NULL ORDER BY scheduled_start DESC,id DESC LIMIT %d",$limit);
        $rows=VWLB_DB::read_results($query,'r111_public_live_browse'); if(is_wp_error($rows))return $rows;
        $items=array(); foreach($rows as $row){$dto=VWLB_Repository::public_live_dto($row);if($dto)$items[]=$dto;} return self::response(array('items'=>$items));
    }
    private static function video_detail($id){
        VWLB_Repository::reset_read_failure(); $bundle=VWLB_Repository::video_bundle($id);
        if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Video state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
        $dto=VWLB_Repository::public_video_dto($bundle); if(!$dto)return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);
        $chapters=VWLB_Extensions::chapters('video',$bundle['id']); if(is_wp_error($chapters))return $chapters; $dto['chapters']=$chapters;
        $response=self::response($dto); if(!is_wp_error($response)&&'public'!==($bundle['visibility']??'private'))$response->header('Cache-Control','private, no-store'); return $response;
    }
    private static function video_chapters($id){
        VWLB_Repository::reset_read_failure(); $video=VWLB_Repository::find('videos',$id);
        if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Video state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
        if(!$video||!VWLB_Security::can_view($video))return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);
        $chapters=VWLB_Extensions::chapters('video',$video['id']); if(is_wp_error($chapters))return $chapters; return self::response(array('items'=>$chapters));
    }
}
