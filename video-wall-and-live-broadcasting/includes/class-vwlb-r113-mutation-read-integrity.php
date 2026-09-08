<?php
/** R113: fail-closed mutation/preflight authoritative read integrity. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R113_Mutation_Read_Integrity {
    public static function register(){ add_filter('rest_pre_dispatch',array(__CLASS__,'pre'),6,3); }
    private static function ns($route){foreach(VWLB_Contracts::namespaces() as $n)if(str_starts_with($route,'/'.$n.'/'))return $n;return '';}
    private static function verify_public($entity,$public_id,$context){
        if(!VWLB_Helpers::is_public_id((string)$public_id))return true;
        global $wpdb;$value=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table($entity).' WHERE public_id=%s LIMIT 1',VWLB_Helpers::text($public_id,100)),$context);
        if(is_wp_error($value))return $value;return true;
    }
    public static function pre($result,$server,$request){
        if(null!==$result||!($request instanceof WP_REST_Request)||'POST'!==strtoupper($request->get_method()))return $result;
        $route=$request->get_route();$ns=self::ns($route);if(!$ns)return $result;$d=$request->get_json_params();$d=is_array($d)?$d:array();
        $checks=array();
        if($route==='/'.$ns.'/podcasts/series'&&!empty($d['channel_public_id']))$checks[]=array('channels',$d['channel_public_id'],'r113_channel_public_resolver');
        if($route==='/'.$ns.'/podcasts/episodes'){
            if(!empty($d['asset_public_id']))$checks[]=array('media_assets',$d['asset_public_id'],'r113_asset_public_resolver');
            if(!empty($d['transcript_caption_public_id']))$checks[]=array('captions',$d['transcript_caption_public_id'],'r113_caption_public_resolver');
        }
        if($route==='/'.$ns.'/premieres'&&!empty($d['video_public_id']))$checks[]=array('videos',$d['video_public_id'],'r113_video_public_resolver');
        foreach($checks as $c){$ok=self::verify_public($c[0],$c[1],$c[2]);if(is_wp_error($ok))return $ok;}
        return $result;
    }
}
