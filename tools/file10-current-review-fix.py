from pathlib import Path
import re

intel=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
adapters=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-adapters.php')
rest=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php')
reg=Path('tests/fresh-40-review-contracts.sh')
t=intel.read_text(); a=adapters.read_text(); rr=rest.read_text()

sim=r'''	public static function upsert_simulcast_target( $live_id, $data ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_simulcast'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot manage simulcast targets.',VWLB_TEXT_DOMAIN),403);
		$platform=VWLB_Helpers::text($data['platform']??'',64);$target=VWLB_Helpers::text($data['provider_target_ref']??'',191);$credential=VWLB_Helpers::text($data['credential_ref']??'',191);if(!$platform||!$target)return VWLB_Helpers::error('vwlb_simulcast_fields_required',__('Platform and provider target reference are required.',VWLB_TEXT_DOMAIN),422);
		$config=(array)($data['config']??array());if(!empty($data['stream_key'])||!empty($data['secret'])||self::contains_raw_secret($config))return VWLB_Helpers::error('vwlb_simulcast_secret_forbidden',__('Raw destination secrets cannot be stored in File 10.',VWLB_TEXT_DOMAIN),422);
		$status=VWLB_Helpers::enum($data['status']??'disabled',array('disabled','ready'),'disabled');global $wpdb;$table=VWLB_Helpers::table('simulcast_targets');$id=absint($data['id']??0);$now=VWLB_Helpers::now();
		$row=array('platform'=>$platform,'provider_target_ref'=>$target,'credential_ref'=>$credential,'status'=>$status,'config_json'=>VWLB_Helpers::json_encode($config),'updated_at'=>$now);
		if($id){$current=self::public_row('simulcast_targets',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_simulcast_missing',__('Simulcast target not found.',VWLB_TEXT_DOMAIN),404);if('transitioning'===$current['status'])return VWLB_Helpers::error('vwlb_simulcast_busy',__('Simulcast target is changing state. Retry after reconciliation.',VWLB_TEXT_DOMAIN),409);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}
		else{$row+=array('public_id'=>VWLB_Helpers::public_id('sim'),'live_event_id'=>(int)$event['id'],'last_state_json'=>'{}','version'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now);if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Simulcast target could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		do_action('vwlb_simulcast_target_changed',$event,self::public_row('simulcast_targets',$id));return self::public_row('simulcast_targets',$id);
	}
'''
pat=re.compile(r"\tpublic static function upsert_simulcast_target\(.*?\n\t}\n",re.S);m=pat.search(t)
if not m: raise SystemExit('R08 simulcast target method missing')
t=t[:m.start()]+sim+t[m.end():];intel.write_text(t)

transition=r'''	public static function transition_simulcast($live_id,$target_id,$action,$expected_version=0) {
		$event=VWLB_Future_Intelligence::live($live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_OPERATE,$event,'future_simulcast_transition'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot operate simulcast.',VWLB_TEXT_DOMAIN),403);
		$target=VWLB_Future_Intelligence::public_row('simulcast_targets',$target_id);if(!$target||(int)$target['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_simulcast_missing',__('Simulcast target not found.',VWLB_TEXT_DOMAIN),404);$action=VWLB_Helpers::enum($action,array('start','stop','retry'),'');if(!$action)return VWLB_Helpers::error('vwlb_simulcast_action_invalid',__('Simulcast action is invalid.',VWLB_TEXT_DOMAIN),422);if(!$expected_version||(int)$target['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);if('transitioning'===$target['status'])return VWLB_Helpers::error('vwlb_simulcast_busy',__('Simulcast target is already changing state.',VWLB_TEXT_DOMAIN),409);
		global $wpdb;$table=VWLB_Helpers::table('simulcast_targets');$reserved=$wpdb->update($table,array('status'=>'transitioning','version'=>(int)$target['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$target['version']));if(1!==$reserved)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed before the operation could start.',VWLB_TEXT_DOMAIN),409);$reserved_version=(int)$target['version']+1;
		$result=apply_filters('vwlb_simulcast_adapter_transition',null,$event,$target,$action,array('credential_ref'=>$target['credential_ref'],'provider_target_ref'=>$target['provider_target_ref']));
		if(is_wp_error($result)){$wpdb->update($table,array('status'=>'failed','version'=>$reserved_version+1,'last_state_json'=>VWLB_Helpers::json_encode(array('status'=>'failed','provider_code'=>$result->get_error_code(),'updated_at'=>gmdate('c'))),'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version));return $result;}
		if(!is_array($result)||empty($result['accepted'])){$wpdb->update($table,array('status'=>'failed','version'=>$reserved_version+1,'last_state_json'=>VWLB_Helpers::json_encode(array('status'=>'failed','provider_code'=>'unavailable','updated_at'=>gmdate('c'))),'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version));return VWLB_Helpers::error('vwlb_simulcast_adapter_unavailable',__('Simulcast provider adapter is unavailable.',VWLB_TEXT_DOMAIN),503);}
		$default='stop'===$action?'ready':'active';$allowed='stop'===$action?array('ready','disabled'):array('active','degraded');$status=VWLB_Helpers::enum($result['status']??$default,$allowed,$default);$safe=array('status'=>$status,'provider_code'=>VWLB_Helpers::text($result['provider_code']??'',64),'message'=>VWLB_Helpers::text($result['message']??'',500),'updated_at'=>gmdate('c'));
		$ok=$wpdb->update($table,array('status'=>$status,'last_state_json'=>VWLB_Helpers::json_encode($safe),'version'=>$reserved_version+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version,'status'=>'transitioning'));if(1!==$ok)return VWLB_Helpers::error('vwlb_simulcast_reconcile_required',__('Provider accepted the action but local state could not be finalized; reconciliation is required.',VWLB_TEXT_DOMAIN),503);
		VWLB_Helpers::audit('live',$event['id'],'simulcast_'.$action,$event['status'],$event['status'],'',array('target_public_id'=>$target['public_id'],'result'=>$safe));return VWLB_Future_Intelligence::public_row('simulcast_targets',$target['id']);
	}
'''
pat2=re.compile(r"\tpublic static function transition_simulcast\(.*?\n\t}\n",re.S);m2=pat2.search(a)
if not m2: raise SystemExit('R08 adapter transition method missing')
a=a[:m2.start()]+transition+a[m2.end():];adapters.write_text(a)

old="\tpublic function simulcast_transition(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Adapters::transition_simulcast($r['id'],$r['target'],$d['action']??''));}\n"
new="\tpublic function simulcast_transition(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Adapters::transition_simulcast($r['id'],$r['target'],$d['action']??'',$this->version($d)));}\n"
if old in rr: rr=rr.replace(old,new,1)
elif new not in rr: raise SystemExit('R08 REST transition callback missing')
rest.write_text(rr)

rg=reg.read_text();marker="""# R08 — simulcast secrets are recursively rejected and provider transitions reserve a CAS state before external side effects.\nneed \"self::contains_raw_secret(\$config)\" \"$P/includes/class-vwlb-future-intelligence.php\" r08-nested-secret\nneed \"array('disabled','ready')\" \"$P/includes/class-vwlb-future-intelligence.php\" r08-no-client-active\nneed \"status'=>'transitioning'\" \"$P/includes/class-vwlb-future-adapters.php\" r08-transition-reservation\nneed \"submit its current version\" \"$P/includes/class-vwlb-future-adapters.php\" r08-transition-version\nneed \"reconciliation is required\" \"$P/includes/class-vwlb-future-adapters.php\" r08-provider-local-divergence\n"""
if '# R08 —' not in rg:rg=rg.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(rg)
