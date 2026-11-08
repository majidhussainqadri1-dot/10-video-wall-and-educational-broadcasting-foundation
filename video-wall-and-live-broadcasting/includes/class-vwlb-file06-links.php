<?php
/**
 * File 10 → File 06: independently approved, canonical educational-video links.
 * File 10 owns video publication, licensing, consent and current access.
 */
defined('ABSPATH') || exit;

final class VWLB_File06_Links {
	const PREFIX='vwlb_file06_approved_v1_';

	public static function hooks() {
		add_filter('he_v12_canonical_related_resources',array(__CLASS__,'collect'),20,2);
		add_filter('he_v12_related_resource_owner_attested_v1',array(__CLASS__,'attest'),20,3);
		add_action('rest_api_init',array(__CLASS__,'routes'));
	}

	private static function key($id) { return self::PREFIX.hash('sha256',strtolower((string)$id)); }
	private static function uuid($v) { return is_string($v)&&(bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',$v); }

	private static function safe_video($public_id,$version) {
		if(!VWLB_Helpers::is_public_id((string)$public_id))return null;
		VWLB_Repository::reset_read_failure();
		$video=VWLB_Repository::find('videos',(string)$public_id);
		if(VWLB_Repository::read_failed()||!is_array($video))return null;
		if('published'!==($video['status']??'')||'public'!==($video['visibility']??'')
			||!empty($video['deleted_at'])||(int)($video['version']??0)!==(int)$version
			||'verified'!==($video['rights_status']??'')
			||!VWLB_Security::can_view($video,'file06_scholarly_link')
			||(class_exists('VWLB_R109_Rights_Consent_Replay_Guard')
				&&VWLB_R109_Rights_Consent_Replay_Guard::policy_read_failed()))return null;
		$dto=VWLB_Repository::public_video_dto($video);
		if(!is_array($dto)||VWLB_Repository::read_failed()||empty($dto['url']))return null;
		return array('video'=>$video,'dto'=>$dto);
	}

	private static function approved_link($row,$dto) {
		if(!is_array($row)||!is_array($dto)||!self::uuid((string)($dto['id']??'')))return null;
		if(true!==($row['approved']??false)
			||!hash_equals(strtolower((string)$dto['id']),(string)($row['concept_public_id']??''))
			||(int)($row['concept_version']??0)!==(int)($dto['version']??0)
			||!preg_match('/^[A-Za-z0-9._:-]{8,120}$/',(string)($row['rights_evidence_ref']??''))
			||absint($row['reviewer_id']??0)<1)return null;
		$video=self::safe_video((string)($row['video_public_id']??''),absint($row['video_version']??0));
		if(!$video||absint($row['reviewer_id'])===absint($video['video']['owner_id']??0))return null;
		$u=(string)$video['dto']['url'];if(!wp_http_validate_url($u))return null;
		return array(
			'owner_file'=>'file-10','owner_id'=>(string)$video['video']['public_id'],
			'owner_version'=>(int)$video['video']['version'],
			'source_version'=>(int)$dto['version'],'eligible'=>true,'rights_ok'=>true,
			'url'=>$u,'label'=>sanitize_text_field((string)$video['video']['title']),
		);
	}

	public static function collect($links,$dto) {
		$links=is_array($links)?$links:array();
		if(!is_array($dto)||!self::uuid((string)($dto['id']??''))||empty($dto['version']))return $links;
		$rows=get_option(self::key($dto['id']),array());
		foreach(array_slice(is_array($rows)?$rows:array(),0,12) as $row) {
			$link=self::approved_link($row,$dto);if($link)$links[]=$link;
		}
		return $links;
	}

	public static function attest($value,$link,$dto) {
		if(!is_array($link)||'file-10'!==($link['owner_file']??''))return $value;
		if(!is_array($dto)||!self::uuid((string)($dto['id']??'')))return false;
		$rows=get_option(self::key($dto['id']),array());
		foreach(array_slice(is_array($rows)?$rows:array(),0,12) as $row) {
			$approved=self::approved_link($row,$dto);
			if($approved&&hash_equals((string)$approved['owner_id'],(string)($link['owner_id']??''))
				&&hash_equals((string)$approved['url'],(string)($link['url']??''))
				&&(int)$approved['owner_version']===(int)($link['owner_version']??0))return true;
		}
		return false;
	}

	public static function routes() {
		register_rest_route('vwlb/v1','/file06/video/(?P<id>vid_[A-Za-z0-9]+)/approve-link',array(
			'methods'=>'POST','callback'=>array(__CLASS__,'approve'),
			'permission_callback'=>static function(){ return VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,null,'file06_scholarly_link_review'); },
		));
	}

	public static function approve($request) {
		if(!wp_verify_nonce((string)$request->get_header('X-WP-Nonce'),'wp_rest'))return VWLB_Helpers::error('vwlb_f06_nonce','Valid session nonce required',403);
		$public_id=sanitize_text_field((string)$request['id']);
		$data=(array)$request->get_json_params();$uuid=strtolower(sanitize_text_field((string)($data['concept_public_id']??'')));
		$version=absint($data['concept_version']??0);$video_version=absint($data['video_version']??0);
		$rights=sanitize_text_field((string)($data['rights_evidence_ref']??''));
		$actor=get_current_user_id();
		$video=self::safe_video($public_id,$video_version);
		if(!$actor||!$video||$actor===absint($video['video']['owner_id']??0)
			||!self::uuid($uuid)||!$version||!preg_match('/^[A-Za-z0-9._:-]{8,120}$/',$rights)) {
			return VWLB_Helpers::error('vwlb_f06_review_denied','Independent reviewer, current public media and rights proof required',403);
		}
		$concept=apply_filters('he_v12_file06_concept_assertion_v1',null,$uuid,$version);
		if(!is_array($concept)||true!==($concept['published']??false)
			||'file-06'!==($concept['owner_file']??'')
			||!hash_equals($uuid,(string)($concept['public_id']??''))) {
			return VWLB_Helpers::error('vwlb_f06_canonical_unavailable','Current File06 concept not verified',503);
		}
		$key=self::key($uuid);$rows=get_option($key,array());$rows=is_array($rows)?$rows:array();
		$record=array('approved'=>true,'concept_public_id'=>$uuid,'concept_version'=>$version,
			'video_public_id'=>$public_id,'video_version'=>$video_version,'rights_evidence_ref'=>$rights,
			'reviewer_id'=>$actor,'approved_at_utc'=>gmdate('c'));
		$rows=array_values(array_filter($rows,static function($r)use($public_id){return is_array($r)&&($r['video_public_id']??'')!==$public_id;}));
		if(count($rows)>=12)return VWLB_Helpers::error('vwlb_f06_link_limit','Canonical relation has reached its owner-review limit',409);
		$rows[]=$record;
		if(false===update_option($key,$rows,false)&&get_option($key)!==$rows) {
			return VWLB_Helpers::error('vwlb_f06_link_store','Link approval could not be saved',503);
		}
		return rest_ensure_response(array('approved'=>true,'owner_file'=>'file-10',
			'concept_public_id'=>$uuid,'source_version'=>$version,'video_public_id'=>$public_id));
	}
}
