<?php
/** Upload/ingest and processing queue. Large direct uploads use VWLB_Extensions resumable private ingest. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Media {
	public static function initiate( $data ) {
		if(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'initiate_upload'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot upload media.',VWLB_TEXT_DOMAIN),403);
		$rate=VWLB_Security::rate_limit('initiate_upload',20,HOUR_IN_SECONDS);if(is_wp_error($rate))return $rate;
		$media_class=VWLB_Helpers::enum($data['media_class']??'video',VWLB_Contracts::MEDIA_CLASSES,'video');
		$provider=VWLB_Helpers::enum($data['provider']??'local',array_keys(VWLB_Providers::all()),'local');
		$mime=VWLB_Helpers::text($data['mime']??'',128);$bytes=max(0,(int)($data['bytes']??0));
		$max=(int)apply_filters('vwlb_max_upload_bytes',1024*1024*1024,$media_class);if($bytes>$max)return VWLB_Helpers::error('vwlb_file_too_large',__('The file exceeds the allowed size.',VWLB_TEXT_DOMAIN),413);
		$allowed=(array)apply_filters('vwlb_allowed_mimes',array('video/mp4','video/webm','video/ogg','application/vnd.apple.mpegurl','audio/mpeg','audio/mp4','audio/ogg','image/jpeg','image/png','text/vtt'),$media_class);
		if($mime&&!in_array($mime,$allowed,true))return VWLB_Helpers::error('vwlb_mime_not_allowed',__('This file type is not allowed.',VWLB_TEXT_DOMAIN),415);
		$checksum=strtolower(VWLB_Helpers::text($data['checksum']??'',128));if($checksum&&!preg_match('/^[a-f0-9]{64}$/',$checksum))return VWLB_Helpers::error('vwlb_checksum_invalid',__('Checksum must be SHA-256.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$now=VWLB_Helpers::now();$public=VWLB_Helpers::public_id('asset');
		$wpdb->insert(VWLB_Helpers::table('media_assets'),array(
			'public_id'=>$public,'owner_id'=>get_current_user_id(),'source_object_type'=>sanitize_key($data['source_object_type']??'video'),
			'source_object_id'=>absint($data['source_object_id']??0),'media_class'=>$media_class,'provider'=>$provider,'checksum'=>$checksum,
			'mime'=>$mime,'bytes'=>$bytes,'status'=>'initiated','scan_status'=>'pending',
			'rights_status'=>VWLB_Helpers::enum($data['rights_status']??'declared',array('declared','verified','disputed','restricted'),'declared'),
			'storage_json'=>VWLB_Helpers::json_encode(array('upload_session'=>VWLB_Helpers::public_id('upload'),'expires_at'=>gmdate('c',time()+HOUR_IN_SECONDS))),
			'derivatives_json'=>'{}','created_at'=>$now,'updated_at'=>$now
		));
		$id=(int)$wpdb->insert_id;if(!$id)return VWLB_Helpers::error('vwlb_database_error',__('Media asset could not be initialized.',VWLB_TEXT_DOMAIN),500);
		VWLB_Helpers::audit('asset',$id,'initiate','','initiated','Upload session initiated');
		return array('id'=>$id,'public_id'=>$public,'status'=>'initiated','upload'=>apply_filters('vwlb_upload_grant',array('method'=>'resumable-private','endpoint'=>rest_url(VWLB_Contracts::CANONICAL_API_NAMESPACE.'/media/resumable'),'expires_in'=>HOUR_IN_SECONDS),$id,$data));
	}
	public static function complete( $asset_id, $data, $expected_version=1 ) {
		$asset=VWLB_Repository::find('media_assets',$asset_id);if(!$asset)return VWLB_Helpers::error('vwlb_asset_missing',__('Asset not found.',VWLB_TEXT_DOMAIN),404);
		if(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,$asset,'complete_upload'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot update this asset.',VWLB_TEXT_DOMAIN),403);
		if('initiated'!==$asset['status'])return VWLB_Helpers::error('vwlb_bad_asset_state',__('The upload is not awaiting completion.',VWLB_TEXT_DOMAIN),409);
		$url=VWLB_Helpers::remote_url($data['source_url']??'');$attachment=absint($data['attachment_id']??0);
		if(!$url&&!$attachment)return VWLB_Helpers::error('vwlb_source_required',__('A source URL or attachment is required. Use the resumable endpoint for private chunked uploads.',VWLB_TEXT_DOMAIN));
		if($attachment&&!current_user_can('read_post',$attachment))return VWLB_Helpers::error('vwlb_attachment_forbidden',__('The attachment is not accessible.',VWLB_TEXT_DOMAIN),403);
		if($attachment&&!apply_filters('vwlb_allow_legacy_attachment_ingest',false,$attachment,$asset))return VWLB_Helpers::error('vwlb_private_ingest_required',__('Raw media must use the private resumable ingest path; public Media Library ingest is disabled by default.',VWLB_TEXT_DOMAIN),422);
		$checksum=strtolower(VWLB_Helpers::text($data['checksum']??$asset['checksum'],128));if($checksum&&!preg_match('/^[a-f0-9]{64}$/',$checksum))return VWLB_Helpers::error('vwlb_checksum_invalid',__('Checksum must be SHA-256.',VWLB_TEXT_DOMAIN),422);
		// R25: state transition and processing-queue persistence are one transaction. Never strand an uploaded asset without its required job.
		$result=VWLB_DB::transaction(function()use($asset,$expected_version,$url,$attachment,$checksum,$data){
			$updated=VWLB_Repository::update_versioned('media_assets',$asset['id'],$expected_version,array('source_url'=>$url,'attachment_id'=>$attachment,'status'=>'uploaded','scan_status'=>'pending','checksum'=>$checksum,'duration_seconds'=>VWLB_Helpers::duration_seconds($data['duration']??0)),array('%s','%d','%s','%s','%s','%d'));
			if(is_wp_error($updated))return $updated;
			$job=self::enqueue($asset['id'],'verify_and_process',array('required_derivatives'=>array('hls','mp4_high','mp4_low','audio_only','poster','storyboard','transcript_draft')));
			if(!$job)return VWLB_Helpers::error('vwlb_processing_queue_failed',__('Media completion was rolled back because processing could not be queued. Retry safely.',VWLB_TEXT_DOMAIN),503);
			return $updated;
		});
		if(is_wp_error($result))return $result;
		VWLB_Helpers::audit('asset',$asset['id'],'complete','initiated','uploaded','Upload completed; validation, scan and transcoding queued');return $result;
	}
	public static function enqueue($asset_id,$type,$input=array(),$priority=100){
		global $wpdb;$asset=VWLB_Repository::find('media_assets',$asset_id);$provider=$asset?sanitize_key($asset['provider']):'local';$saved=$wpdb->insert(VWLB_Helpers::table('processing_jobs'),array('public_id'=>VWLB_Helpers::public_id('job'),'asset_id'=>absint($asset_id),'job_type'=>sanitize_key($type),'provider'=>$provider,'status'=>'pending','priority'=>(int)$priority,'attempts'=>0,'max_attempts'=>5,'available_at'=>VWLB_Helpers::now(),'input_json'=>VWLB_Helpers::json_encode($input),'output_json'=>'{}','created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()));return $saved?(int)$wpdb->insert_id:0;
	}
	public static function verify_magic( $asset ) {
		if(!is_array($asset))return false;
		if(!in_array($asset['media_class']??'',VWLB_Contracts::MEDIA_CLASSES,true))return false;
		if((int)($asset['bytes']??0)<0)return false;
		return (bool)apply_filters('vwlb_asset_technical_validation',true,$asset);
	}
}
