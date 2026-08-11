from pathlib import Path
import re
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-adapters.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
helper=r'''
	private static function safe_processor_context($object_type,$object){
		if(!is_array($object))return array();$base=array('object_type'=>$object_type,'public_id'=>VWLB_Helpers::text($object['public_id']??'',80),'language'=>VWLB_Helpers::text($object['language']??'',32));if('video'===$object_type){$base['duration_seconds']=max(0,(int)($object['duration_seconds']??0));$base['topics']=VWLB_Helpers::json($object['topics_json']??'[]');}else{$base['status']=VWLB_Helpers::enum($object['status']??'',VWLB_Contracts::LIVE_STATES,'');}return $base;
	}
	private static function safe_processor_options($options,$object_type,$object){$safe=(array)apply_filters('vwlb_media_track_generation_safe_options',array(),(array)$options,$object_type,self::safe_processor_context($object_type,$object));$encoded=VWLB_Helpers::json_encode($safe);if(strlen($encoded)>16384)return VWLB_Helpers::error('vwlb_processor_options_too_large',__('Processor options are too large.',VWLB_TEXT_DOMAIN),422);$deny=function($v)use(&$deny){if(!is_array($v))return false;foreach($v as $k=>$child){$key=sanitize_key((string)$k);if(!str_ends_with($key,'_ref')&&in_array($key,array('secret','password','api_key','access_token','refresh_token','private_key','token','stream_key'),true))return true;if(is_array($child)&&$deny($child))return true;}return false;};if($deny($safe))return VWLB_Helpers::error('vwlb_processor_secret_forbidden',__('Raw credentials cannot be sent in processor options.',VWLB_TEXT_DOMAIN),422);return $safe;}
'''
anchor="\tprivate static function config( $event_id ) {\n"
if 'private static function safe_processor_context' not in t:
    idx=t.find(anchor)
    if idx<0:raise SystemExit('R16 helper anchor missing')
    # insert before config function
    t=t[:idx]+helper+t[idx:]
old="$request=array('object_type'=>$object_type,'object_public_id'=>$object['public_id'],'track_type'=>$track_type,'language'=>VWLB_Helpers::text($language,32),'options'=>(array)$options,'require_human_review'=>true,'medical_interpretation_authority'=>false);\n\t\t$result=apply_filters('vwlb_media_track_generation_request',null,$request,$object);"
new="$safe_options=self::safe_processor_options($options,$object_type,$object);if(is_wp_error($safe_options))return $safe_options;$context=self::safe_processor_context($object_type,$object);$request=array('object_type'=>$object_type,'object_public_id'=>$object['public_id'],'track_type'=>$track_type,'language'=>VWLB_Helpers::text($language,32),'options'=>$safe_options,'require_human_review'=>true,'medical_interpretation_authority'=>false);\n\t\t$result=apply_filters('vwlb_media_track_generation_request',null,$request,$context);"
if old in t:t=t.replace(old,new,1)
elif new not in t:raise SystemExit('R16 track request pattern missing')
old2="$result=apply_filters('vwlb_video_intelligence_suggestions',null,array('video_public_id'=>$video['public_id'],'kinds'=>$kinds,'require_sources'=>true,'auto_publish'=>false,'clinical_authority'=>false),$video);"
new2="$context=self::safe_processor_context('video',$video);$result=apply_filters('vwlb_video_intelligence_suggestions',null,array('video_public_id'=>$video['public_id'],'kinds'=>$kinds,'require_sources'=>true,'auto_publish'=>false,'clinical_authority'=>false),$context);"
if old2 in t:t=t.replace(old2,new2,1)
elif new2 not in t:raise SystemExit('R16 suggestion context pattern missing')
p.write_text(t)
r=reg.read_text();marker="""# R16 — external processor/AI adapters receive minimized canonical context and only explicitly approved safe options.\nneed \"safe_processor_context\" \"$P/includes/class-vwlb-future-adapters.php\" r16-safe-context\nneed \"vwlb_media_track_generation_safe_options\" \"$P/includes/class-vwlb-future-adapters.php\" r16-option-allowlist\nneed \"vwlb_processor_secret_forbidden\" \"$P/includes/class-vwlb-future-adapters.php\" r16-secret\nneed \"16384\" \"$P/includes/class-vwlb-future-adapters.php\" r16-size-bound\nneed \"clinical_authority'=>false\" \"$P/includes/class-vwlb-future-adapters.php\" r16-ai-authority\n"""
if '# R16 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
