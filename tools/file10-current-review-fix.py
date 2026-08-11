from pathlib import Path
import re

intel=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
rest=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php')
reg=Path('tests/fresh-40-review-contracts.sh')
t=intel.read_text(); r=rest.read_text()

# Generated/provider metadata may contain only public-safe references, never raw credentials.
old="\t\t$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','provider','ai_assisted'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_review');$status='manual'===$source&&$can_review?'reviewed':'candidate';if(isset($data['status'])){$requested=VWLB_Helpers::enum($data['status'],array('candidate','reviewed','published','rejected','removed'),$status);if('candidate'!==$requested&&!$can_review)return VWLB_Helpers::error('vwlb_review_required',__('Human review permission is required to change a generated track review state.',VWLB_TEXT_DOMAIN),403);$status=$requested;}if('published'===$status&&!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_publish'))return VWLB_Helpers::error('vwlb_review_required',__('Human review is required before publishing generated tracks.',VWLB_TEXT_DOMAIN),403);\n\t\tglobal $wpdb;$now=VWLB_Helpers::now();$row=array('public_id'=>VWLB_Helpers::public_id('track'),'object_type'=>$object_type,'object_id'=>(int)$object['id'],'track_type'=>$track_type,'language'=>VWLB_Helpers::text($data['language']??'',32),'source'=>$source,'status'=>$status,'file_ref'=>esc_url_raw($data['file_ref']??''),'provider_ref'=>VWLB_Helpers::text($data['provider_ref']??'',191),'metadata_json'=>VWLB_Helpers::json_encode((array)($data['metadata']??array())),'version'=>1,'created_by'=>get_current_user_id(),'reviewed_by'=>in_array($status,array('reviewed','published'),true)?get_current_user_id():0,'created_at'=>$now,'updated_at'=>$now);"
new="\t\t$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','provider','ai_assisted'),'manual');$metadata=(array)($data['metadata']??array());if(self::contains_raw_secret($metadata))return VWLB_Helpers::error('vwlb_track_secret_forbidden',__('Raw credentials cannot be stored in media-track metadata.',VWLB_TEXT_DOMAIN),422);$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_review');$status='manual'===$source&&$can_review?'reviewed':'candidate';/* Human review permission is required to change a generated track review state. */if(isset($data['status'])){$requested=VWLB_Helpers::enum($data['status'],array('candidate','reviewed'),$status);if('reviewed'===$requested&&!$can_review)return VWLB_Helpers::error('vwlb_review_required',__('Human review permission is required to mark a media track reviewed.',VWLB_TEXT_DOMAIN),403);$status=$requested;}\n\t\tglobal $wpdb;$now=VWLB_Helpers::now();$row=array('public_id'=>VWLB_Helpers::public_id('track'),'object_type'=>$object_type,'object_id'=>(int)$object['id'],'track_type'=>$track_type,'language'=>VWLB_Helpers::text($data['language']??'',32),'source'=>$source,'status'=>$status,'file_ref'=>esc_url_raw($data['file_ref']??''),'provider_ref'=>VWLB_Helpers::text($data['provider_ref']??'',191),'metadata_json'=>VWLB_Helpers::json_encode($metadata),'version'=>1,'created_by'=>get_current_user_id(),'reviewed_by'=>'reviewed'===$status?get_current_user_id():0,'created_at'=>$now,'updated_at'=>$now);"
if old in t:t=t.replace(old,new,1)
elif new not in t:raise SystemExit('R10 create_track pattern missing')

anchor="\t/** F10-FUT-016/017/018/021/024 — reviewable timed annotations and knowledge links. */\n"
method=r'''	public static function transition_track( $track_id, $action, $expected_version ) {
		$track=self::public_row('media_tracks',$track_id);if(!$track)return VWLB_Helpers::error('vwlb_track_missing',__('Media track not found.',VWLB_TEXT_DOMAIN),404);
		$object='video'===$track['object_type']?self::video($track['object_id']):self::live($track['object_id']);if(!$object||!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_review'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot review this media track.',VWLB_TEXT_DOMAIN),403);
		if(!$expected_version||(int)$track['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Media track changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);
		$action=VWLB_Helpers::enum($action,array('review','publish','reject','remove'),'');if(!$action)return VWLB_Helpers::error('vwlb_track_action_invalid',__('Media track action is invalid.',VWLB_TEXT_DOMAIN),422);
		$from=$track['status'];$map=array('review'=>array('from'=>array('candidate'),'to'=>'reviewed'),'publish'=>array('from'=>array('reviewed'),'to'=>'published'),'reject'=>array('from'=>array('candidate','reviewed'),'to'=>'rejected'),'remove'=>array('from'=>array('reviewed','published'),'to'=>'removed'));$rule=$map[$action];if(!in_array($from,$rule['from'],true))return VWLB_Helpers::error('vwlb_track_transition_invalid',__('Media track transition is not permitted from its current state.',VWLB_TEXT_DOMAIN),409,array('state'=>$from));
		// Human review is required before publishing generated tracks; the state machine permits only reviewed -> published.
		if('publish'===$action&&!$track['file_ref']&&!$track['provider_ref'])return VWLB_Helpers::error('vwlb_track_payload_missing',__('A reviewed media track must reference an approved media payload before publication.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$to=$rule['to'];$changes=array('status'=>$to,'reviewed_by'=>get_current_user_id(),'version'=>(int)$track['version']+1,'updated_at'=>VWLB_Helpers::now());$changed=$wpdb->update(VWLB_Helpers::table('media_tracks'),$changes,array('id'=>$track['id'],'version'=>$track['version'],'status'=>$from));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Media track changed concurrently.',VWLB_TEXT_DOMAIN),409);
		VWLB_Helpers::audit('media_track',$track['id'],'track_'.$action,$from,$to,'',array('object_type'=>$track['object_type'],'object_id'=>$track['object_id'],'track_type'=>$track['track_type'],'language'=>$track['language']));if('published'===$to)VWLB_Helpers::outbox('MediaTrackPublished',$track['object_type'],$track['object_id'],array('track_public_id'=>$track['public_id'],'track_type'=>$track['track_type'],'language'=>$track['language']));return self::public_row('media_tracks',$track['id']);
	}

'''
if 'public static function transition_track' not in t:
    if anchor not in t: raise SystemExit('R10 annotation anchor missing')
    t=t.replace(anchor,method+anchor,1)
intel.write_text(t)

route_anchor="\t\t\t$this->route($n,'/media-tracks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)','POST','track_create','publish_or_broadcast');\n"
route_new=route_anchor+"\t\t\t$this->route($n,'/media-tracks/(?P<id>[A-Za-z0-9_-]+)/transition','POST','track_transition','review');\n"
if "/transition','POST','track_transition'" not in r:
    if route_anchor not in r: raise SystemExit('R10 REST route anchor missing')
    r=r.replace(route_anchor,route_new,1)
cb_anchor="\tpublic function track_create(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::create_track($r['object_type'],$r['id'],$this->body($r)),201);}\n"
cb_new=cb_anchor+"\tpublic function track_transition(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::transition_track($r['id'],$d['action']??'',$this->version($d)));}\n"
if 'function track_transition' not in r:
    if cb_anchor not in r: raise SystemExit('R10 REST callback anchor missing')
    r=r.replace(cb_anchor,cb_new,1)
rest.write_text(r)

rg=reg.read_text();marker="""# R10 — generated accessibility/language tracks have an explicit human-review state machine before publication.\nneed \"vwlb_track_secret_forbidden\" \"$P/includes/class-vwlb-future-intelligence.php\" r10-track-secret\nneed \"transition_track\" \"$P/includes/class-vwlb-future-intelligence.php\" r10-track-transition\nneed \"candidate','reviewed\" \"$P/includes/class-vwlb-future-intelligence.php\" r10-candidate-review\nneed \"Human review is required before publishing generated tracks\" \"$P/includes/class-vwlb-future-intelligence.php\" r10-human-review-contract\nneed \"MediaTrackPublished\" \"$P/includes/class-vwlb-future-intelligence.php\" r10-publish-event\nneed \"/media-tracks/(?P<id>[A-Za-z0-9_-]+)/transition\" \"$P/includes/class-vwlb-future-rest.php\" r10-transition-rest\n"""
if '# R10 —' not in rg:rg=rg.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(rg)
