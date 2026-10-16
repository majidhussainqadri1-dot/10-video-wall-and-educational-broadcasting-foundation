<?php
/** R50: privacy erasure completion must be proven with DB-error-aware reads. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R50_Privacy_Proof {
	public static function register(){add_filter('wp_privacy_personal_data_erasers',array(__CLASS__,'replace_eraser'),100);}
	public static function replace_eraser($erasers){$erasers['vwlb']=array('eraser_friendly_name'=>__('Video Wall and Live Broadcasting',VWLB_TEXT_DOMAIN),'callback'=>array(__CLASS__,'erase'));return $erasers;}
	private static function remaining($uid){
		global $wpdb;$checks=array(
			array('playback_sessions','user_id'),array('interactions','user_id'),array('download_tokens','user_id'),array('upload_sessions','owner_id'),array('live_poll_responses','user_id'),array('broadcast_guests','user_id'),array('live_attendees','user_id'),array('live_questions','user_id'),array('moderation','reporter_id'),array('audit','actor_id'),array('creator_metrics_daily','owner_id'),array('production_sources','owner_id'),array('production_scenes','owner_id'),array('simulcast_targets','created_by'),array('media_tracks','created_by'),array('media_tracks','reviewed_by'),array('video_annotations','created_by'),array('video_annotations','reviewed_by'),array('live_polls','created_by'),array('consent_links','created_by'),array('watermark_policies','updated_by'),array('broadcast_guests','invited_by'),array('captions','created_by'),array('captions','reviewed_by'),array('stream_credentials','created_by'),array('moderation','reviewer_id'),array('takedowns','claimant_id'),array('takedowns','reviewer_id')
		);
		foreach($checks as $spec){$table=VWLB_Helpers::table($spec[0]);$wpdb->last_error='';$found=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE {$spec[1]}=%d LIMIT 1",$uid));if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_privacy_completion_unverifiable',__('Privacy erasure completion could not be verified because File 10 database state was unreadable.',VWLB_TEXT_DOMAIN),503,array('table'=>$spec[0]));if(null!==$found)return array('remaining'=>true,'table'=>$spec[0]);}
		$table=VWLB_Helpers::table('idempotency');$scope='%'.$wpdb->esc_like(':u'.$uid).'%';$wpdb->last_error='';$found=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE scope LIKE %s LIMIT 1",$scope));if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_privacy_completion_unverifiable',__('Privacy erasure completion could not verify historical replay state.',VWLB_TEXT_DOMAIN),503,array('table'=>'idempotency'));return array('remaining'=>null!==$found,'table'=>$found?'idempotency':'');
	}
	public static function erase($email,$page=1){
		$privacy=new VWLB_Privacy();$result=$privacy->erase($email,$page);if(!is_array($result)||empty($result['done']))return $result;$user=get_user_by('email',$email);if(!$user)return $result;$proof=self::remaining((int)$user->ID);
		if(is_wp_error($proof)){do_action('vwlb_operational_failure','privacy',$proof->get_error_code(),array('phase'=>'r50-completion-proof'));return array('items_removed'=>!empty($result['items_removed']),'items_retained'=>true,'messages'=>array($proof->get_error_message()),'done'=>false);}
		if(!empty($proof['remaining'])){$messages=(array)($result['messages']??array());$messages[]=__('File 10 detected additional eligible data after the bounded erasure pass; another eraser page is required.',VWLB_TEXT_DOMAIN);return array('items_removed'=>!empty($result['items_removed']),'items_retained'=>true,'messages'=>$messages,'done'=>false);}
		return $result;
	}
}
