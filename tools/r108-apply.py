from pathlib import Path

OLD='1.2.18-rc1'
NEW='1.2.19-rc1'


def replace_once(path, old, new, label):
    p=Path(path); t=p.read_text()
    if old not in t:
        raise SystemExit(f'{label} matcher missing: {path}')
    p.write_text(t.replace(old,new,1))

# R108-F01/F02/F03 — canonical live lifecycle/provider reconciliation.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-live.php')
t=p.read_text()

start=t.find('\tpublic static function transition(')
end=t.find('\n\tprivate static function event_for_state', start)
if start < 0 or end < 0:
    raise SystemExit('live transition block matcher missing')
transition=r'''\tpublic static function transition($live_id,$to,$expected_version,$note='',$provider_proof=array()){
\t\t$event=VWLB_Repository::find('live_events',$live_id);if(!$event)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);if(!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'transition_live'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot control this broadcast.',VWLB_TEXT_DOMAIN),403);if($provider_proof)return VWLB_Helpers::error('vwlb_provider_proof_forbidden',__('Provider lifecycle evidence cannot be supplied by a public lifecycle request.',VWLB_TEXT_DOMAIN),422);$to=VWLB_Helpers::enum($to,VWLB_Contracts::LIVE_STATES,'');if(!$to)return VWLB_Helpers::error('vwlb_live_state_invalid',__('Invalid live state.',VWLB_TEXT_DOMAIN));$assert=VWLB_State_Machine::assert('live',$event['status'],$to);if(is_wp_error($assert))return $assert;if(in_array($to,array('live','ended','restricted'),true)){$step=VWLB_Security::require_step_up('transition_live_'.$to);if(is_wp_error($step))return $step;}if('live'===$to){$active=self::active_credential($event['id']);if(is_wp_error($active))return $active;if(!$active)return VWLB_Helpers::error('vwlb_stream_credential_required',__('An active stream credential is required.',VWLB_TEXT_DOMAIN),422);}$changes=array('status'=>$to);if('live'===$to)$changes['actual_start']=VWLB_Helpers::now();if('ended'===$to)$changes['actual_end']=VWLB_Helpers::now();$updated=VWLB_DB::transaction(function()use($event,$expected_version,$changes,$to){$changed=VWLB_Repository::update_versioned('live_events',$event['id'],$expected_version,$changes);if(is_wp_error($changed))return $changed;if('ended'===$to){$queued=self::queue_recording($changed);if(is_wp_error($queued))return $queued;}return $changed;});if(is_wp_error($updated))return $updated;VWLB_Helpers::audit('live',$event['id'],'transition',$event['status'],$to,$note);VWLB_Helpers::outbox(self::event_for_state($to),'live',$event['id'],array('public_id'=>$event['public_id'],'from'=>$event['status'],'to'=>$to));return $updated;}
'''
t=t[:start]+transition+t[end:]

anchor="\tprivate static function active_credential($event_id){"
if anchor not in t:
    raise SystemExit('live reconcile insertion anchor missing')
reconcile=r'''\tpublic static function reconcile_provider_observation($live_id,$observation,$source='provider_reconcile'){
\t\t$event=VWLB_Repository::find('live_events',$live_id);if(!$event){if(method_exists('VWLB_Repository','read_failed')&&VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_live_reconcile_read_failed',__('Live provider reconciliation state could not be read safely.',VWLB_TEXT_DOMAIN),503);return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);}if(!is_array($observation))return VWLB_Helpers::error('vwlb_provider_observation_invalid',__('Provider lifecycle observation is invalid.',VWLB_TEXT_DOMAIN),422);$source=VWLB_Helpers::enum($source,array('provider_reconcile','verified_webhook','emergency_end_confirmation'),'provider_reconcile');$provider=VWLB_Helpers::text($observation['provider']??'',64);if($provider&&$provider!==$event['provider'])return VWLB_Helpers::error('vwlb_provider_observation_mismatch',__('Provider lifecycle observation does not belong to this live event.',VWLB_TEXT_DOMAIN),409);
\t\t$target=VWLB_Helpers::enum($observation['canonical_status']??($observation['state']??''),array('ready','live','interrupted','ended'),'');$safe=array_intersect_key($observation,array_flip(array('provider_event_ref','status','degraded','region','health_code')));if($target&&!isset($safe['status']))$safe['status']=$target;$changes=array();if($safe)$changes['provider_state_json']=VWLB_Helpers::json_encode(array_merge(VWLB_Helpers::json($event['provider_state_json']),$safe));
\t\tif(!$target||$target===$event['status']){if(!$changes)return $event;$updated=VWLB_Repository::update_versioned('live_events',$event['id'],$event['version'],$changes);if(is_wp_error($updated)){do_action('vwlb_operational_failure','live','vwlb_provider_state_reconcile_write_failed',array('live_public_id'=>$event['public_id'],'source'=>$source,'error_code'=>$updated->get_error_code()));return $updated;}return $updated;}
\t\tif(!VWLB_State_Machine::allowed('live',$event['status'],$target)){do_action('vwlb_operational_failure','live','vwlb_provider_state_reconcile_transition_invalid',array('live_public_id'=>$event['public_id'],'from'=>$event['status'],'to'=>$target,'source'=>$source));return VWLB_Helpers::error('vwlb_provider_state_reconcile_transition_invalid',__('Verified provider lifecycle state cannot be applied safely from the current canonical state.',VWLB_TEXT_DOMAIN),409,array('from'=>$event['status'],'to'=>$target));}
\t\t$changes['status']=$target;if('live'===$target&&empty($event['actual_start']))$changes['actual_start']=VWLB_Helpers::now();if('ended'===$target)$changes['actual_end']=VWLB_Helpers::now();$updated=VWLB_DB::transaction(function()use($event,$changes,$target){$changed=VWLB_Repository::update_versioned('live_events',$event['id'],$event['version'],$changes);if(is_wp_error($changed))return $changed;if('ended'===$target){$queued=self::queue_recording($changed);if(is_wp_error($queued))return $queued;}VWLB_Helpers::audit('live',$event['id'],'provider_reconcile',$event['status'],$target,'Verified provider lifecycle observation applied.',array('source'=>'provider_reconcile'));VWLB_Helpers::outbox(self::event_for_state($target),'live',$event['id'],array('public_id'=>$event['public_id'],'from'=>$event['status'],'to'=>$target,'provider_verified'=>true));return $changed;});if(is_wp_error($updated)){do_action('vwlb_operational_failure','live','vwlb_provider_state_reconcile_write_failed',array('live_public_id'=>$event['public_id'],'source'=>$source,'error_code'=>$updated->get_error_code()));return $updated;}return $updated;
\t}

'''
t=t.replace(anchor,reconcile+anchor,1)

start=t.find('\tpublic static function kill(')
end=t.find('\n\tprivate static function queue_recording', start)
if start < 0 or end < 0:
    raise SystemExit('live kill block matcher missing')
kill=r'''\tprivate static function confirm_emergency_provider_end($event,$reason){
\t\ttry{do_action('vwlb_provider_emergency_end',$event,$reason);$result=apply_filters('vwlb_provider_emergency_end_result',null,$event,$reason);}catch(Throwable $e){do_action('vwlb_operational_failure','live','vwlb_provider_emergency_end_exception',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider'],'exception'=>sanitize_key(get_class($e))));return VWLB_Helpers::error('vwlb_provider_emergency_end_reconcile_required',__('Emergency provider termination ended with an unverified outcome. Reconciliation is required.',VWLB_TEXT_DOMAIN),503,array('reconcile_required'=>true));}if(is_wp_error($result)){do_action('vwlb_operational_failure','live','vwlb_provider_emergency_end_unconfirmed',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider'],'provider_error'=>$result->get_error_code()));return VWLB_Helpers::error('vwlb_provider_emergency_end_reconcile_required',__('Emergency provider termination was not confirmed. Reconciliation is required.',VWLB_TEXT_DOMAIN),503,array('provider_error'=>$result->get_error_code(),'reconcile_required'=>true));}$status=is_array($result)?sanitize_key((string)($result['status']??'')):'';$confirmed=true===$result||in_array($status,array('ended','stopped','terminated','disabled','not_found'),true);if(!$confirmed){do_action('vwlb_operational_failure','live','vwlb_provider_emergency_end_unconfirmed',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));return VWLB_Helpers::error('vwlb_provider_emergency_end_reconcile_required',__('Emergency provider termination was not positively confirmed. Reconciliation is required.',VWLB_TEXT_DOMAIN),503,array('reconcile_required'=>true));}return is_array($result)?array_intersect_key($result,array_flip(array('status','provider_event_ref','region','health_code'))):array('status'=>'ended');
\t}
\tpublic static function kill($live_id,$expected_version,$reason){$event=VWLB_Repository::find('live_events',$live_id);if(!$event)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);if(!in_array($event['status'],array('rehearsal','ready','live','interrupted'),true))return VWLB_Helpers::error('vwlb_live_state_invalid',__('Emergency end is not available in this state.',VWLB_TEXT_DOMAIN),409);if(!VWLB_Security::can(VWLB_Contracts::CAP_MODERATE,$event,'emergency_end'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot end this broadcast.',VWLB_TEXT_DOMAIN),403);$step=VWLB_Security::require_step_up('emergency_end');if(is_wp_error($step))return $step;$provider_end=self::confirm_emergency_provider_end($event,$reason);if(is_wp_error($provider_end))return $provider_end;global $wpdb;$updated=VWLB_DB::transaction(function()use($event,$expected_version,$reason,$provider_end,$wpdb){$provider_state=array_merge(VWLB_Helpers::json($event['provider_state_json']),array('status'=>'ended','emergency_end_confirmed'=>true),$provider_end);$changed=VWLB_Repository::update_versioned('live_events',$event['id'],$expected_version,array('status'=>'ended','kill_switch'=>1,'actual_end'=>VWLB_Helpers::now(),'provider_state_json'=>VWLB_Helpers::json_encode($provider_state)));if(is_wp_error($changed))return $changed;$revoked=$wpdb->update(VWLB_Helpers::table('stream_credentials'),array('status'=>'revoked','revoked_at'=>VWLB_Helpers::now()),array('live_event_id'=>$event['id'],'status'=>'active'));if(false===$revoked)return VWLB_Helpers::error('vwlb_database_error',__('Active stream credentials could not be revoked.',VWLB_TEXT_DOMAIN),500);VWLB_Helpers::audit('live',$event['id'],'emergency_end',$event['status'],'ended',$reason,array('provider_confirmed'=>true));VWLB_Helpers::outbox('LiveBroadcastEnded','live',$event['id'],array('emergency'=>true,'reason_category'=>sanitize_key($reason),'provider_confirmed'=>true));$queued=self::queue_recording($changed);if(is_wp_error($queued))return $queued;return $changed;});if(is_wp_error($updated)){do_action('vwlb_operational_failure','live','vwlb_provider_emergency_end_reconcile_required',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider'],'local_error'=>$updated->get_error_code(),'provider_confirmed'=>true));return VWLB_Helpers::error('vwlb_provider_emergency_end_reconcile_required',__('The provider confirmed emergency termination but File 10 could not commit matching local state. Reconciliation is required.',VWLB_TEXT_DOMAIN),503,array('local_error'=>$updated->get_error_code(),'provider_confirmed'=>true,'reconcile_required'=>true));}return $updated;}
'''
t=t[:start]+kill+t[end:]
p.write_text(t)

# R108-F01 — public REST explicitly refuses provider proof.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-rest.php')
t=p.read_text()
old="\tpublic function transition_live(WP_REST_Request $r){$d=$this->body($r);$v=VWLB_Live::transition($r['id'],$d['status']??'',absint($d['version']??0),$d['note']??'',$d['provider_proof']??array());return $this->response(is_wp_error($v)?$v:VWLB_Repository::live_mutation_dto($v));}"
new="\tpublic function transition_live(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('provider_proof',$d))return VWLB_Helpers::error('vwlb_provider_proof_forbidden',__('Provider lifecycle evidence cannot be supplied by a public lifecycle request.',VWLB_TEXT_DOMAIN),422);$v=VWLB_Live::transition($r['id'],$d['status']??'',absint($d['version']??0),$d['note']??'');return $this->response(is_wp_error($v)?$v:VWLB_Repository::live_mutation_dto($v));}"
if old not in t: raise SystemExit('REST transition matcher missing')
p.write_text(t.replace(old,new,1))

# R108-F02/F04 — fair, durable canonical provider reconciliation.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-jobs.php')
t=p.read_text()
if 'const LIVE_RECONCILE_CURSOR_OPTION' not in t:
    t=t.replace("final class VWLB_Jobs {\n","final class VWLB_Jobs {\n\tconst LIVE_RECONCILE_CURSOR_OPTION='vwlb_r108_live_reconcile_cursor';\n",1)
anchor='\tpublic static function reconcile(){\n'
helper=r'''\tprivate static function persist_live_reconcile_cursor($id){$id=max(0,(int)$id);if(0===$id){$deleted=delete_option(self::LIVE_RECONCILE_CURSOR_OPTION);if(!$deleted&&false!==get_option(self::LIVE_RECONCILE_CURSOR_OPTION,false)){do_action('vwlb_operational_failure','live_reconcile','vwlb_live_reconcile_cursor_failed',array('phase'=>'reset'));return false;}return true;}$saved=update_option(self::LIVE_RECONCILE_CURSOR_OPTION,$id,false);if(!$saved&&(int)get_option(self::LIVE_RECONCILE_CURSOR_OPTION,0)!==$id){do_action('vwlb_operational_failure','live_reconcile','vwlb_live_reconcile_cursor_failed',array('phase'=>'advance'));return false;}return true;}
'''
if 'private static function persist_live_reconcile_cursor' not in t:
    if anchor not in t: raise SystemExit('jobs reconcile anchor missing')
    t=t.replace(anchor,helper+anchor,1)
start=t.find("\t\t$wpdb->last_error='';$events=", t.find(anchor))
end=t.find("\n\t}\n\n\tpublic static function cleanup()", start)
if start < 0 or end < 0: raise SystemExit('jobs live reconcile block matcher missing')
block=r'''\t\t$after=absint(get_option(self::LIVE_RECONCILE_CURSOR_OPTION,0));$wpdb->last_error='';$events=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".VWLB_Helpers::table('live_events')." WHERE status IN ('scheduled','rehearsal','ready','live','interrupted') AND id>%d ORDER BY id ASC LIMIT 100",$after),ARRAY_A);
\t\tif(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','live_reconcile','vwlb_live_reconcile_queue_read_failed',array());return;}$events=is_array($events)?$events:array();if(!$events){if($after)self::persist_live_reconcile_cursor(0);return;}$last=$after;
\t\tforeach($events as $event){$last=max($last,(int)$event['id']);$provider=VWLB_Providers::get($event['provider']);if(!$provider){do_action('vwlb_operational_failure','live_reconcile','vwlb_provider_missing',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));continue;}$start=microtime(true);$state=$provider->reconcile('live',$event);$ms=(int)round((microtime(true)-$start)*1000);VWLB_Observability::record_provider($event['provider'],'live',is_array($state)?'healthy':'degraded',is_wp_error($state)?$state->get_error_code():'',$ms);if(is_wp_error($state)){do_action('vwlb_operational_failure','live_reconcile',$state->get_error_code(),array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));continue;}if(!is_array($state)){do_action('vwlb_operational_failure','live_reconcile','vwlb_provider_reconcile_invalid',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));continue;}try{$observation=apply_filters('vwlb_provider_live_reconcile_observation',$state,$event);}catch(Throwable $e){do_action('vwlb_operational_failure','live_reconcile','vwlb_provider_reconcile_observation_exception',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider'],'exception'=>sanitize_key(get_class($e))));continue;}if(!is_array($observation)){do_action('vwlb_operational_failure','live_reconcile','vwlb_provider_reconcile_observation_invalid',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));continue;}$observation['provider']=$event['provider'];$reconciled=VWLB_Live::reconcile_provider_observation($event['public_id'],$observation,'provider_reconcile');if(is_wp_error($reconciled))do_action('vwlb_operational_failure','live_reconcile',$reconciled->get_error_code(),array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));}
\t\tself::persist_live_reconcile_cursor(count($events)<100?0:$last);'''
t=t[:start]+block+t[end:]
p.write_text(t)

# R108-F02 — verified webhook gets a canonical provider-observation normalization path.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-integrations.php')
t=p.read_text()
old="\tpublic function process_webhook($provider,$data,$webhook_id){do_action('vwlb_provider_webhook_'.$provider,$data,$webhook_id);}"
new=r'''\tpublic function process_webhook($provider,$data,$webhook_id){$observation=apply_filters('vwlb_provider_live_webhook_observation',null,$provider,$data,$webhook_id);if(is_wp_error($observation))throw new RuntimeException('Verified provider lifecycle observation normalization failed.');if(is_array($observation)&&!empty($observation['live_public_id'])){$observation['provider']=sanitize_key((string)$provider);$applied=VWLB_Live::reconcile_provider_observation($observation['live_public_id'],$observation,'verified_webhook');if(is_wp_error($applied)){do_action('vwlb_operational_failure','webhook','vwlb_verified_live_reconcile_failed',array('provider'=>sanitize_key((string)$provider),'webhook_id_hash'=>hash('sha256',(string)$webhook_id),'error_code'=>$applied->get_error_code()));throw new RuntimeException('Verified provider lifecycle observation could not be reconciled.');}}do_action('vwlb_provider_webhook_'.$provider,$data,$webhook_id);}'''
if old not in t: raise SystemExit('integration webhook matcher missing')
p.write_text(t.replace(old,new,1))

# R108-F05 — fair, fail-closed, Throwable-contained Future redundancy reconciliation.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
t=p.read_text()
if 'const REDUNDANCY_RECONCILE_CURSOR_OPTION' not in t:
    t=t.replace("\tconst SCHEMA = '1.2.0';\n","\tconst SCHEMA = '1.2.0';\n\tconst REDUNDANCY_RECONCILE_CURSOR_OPTION = 'vwlb_r108_redundancy_reconcile_cursor';\n",1)
old="\tpublic static function reconcile_live_redundancy() {\n\t\tglobal $wpdb;$cfg=VWLB_Helpers::table('future_live_config');$live=VWLB_Helpers::table('live_events');$rows=$wpdb->get_results(\"SELECT c.*,l.public_id,l.provider,l.status FROM $cfg c INNER JOIN $live l ON l.id=c.live_event_id WHERE l.status IN ('ready','live','interrupted') AND (c.backup_provider<>'' OR c.redundant_recording=1) LIMIT 100\",ARRAY_A);foreach($rows as $row){do_action('vwlb_redundancy_reconcile',$row);if(!empty($row['redundant_recording']))do_action('vwlb_redundant_recording_required',$row);}\n\t}"
new=r'''\tprivate static function persist_redundancy_cursor($id){$id=max(0,(int)$id);if(0===$id){$deleted=delete_option(self::REDUNDANCY_RECONCILE_CURSOR_OPTION);if(!$deleted&&false!==get_option(self::REDUNDANCY_RECONCILE_CURSOR_OPTION,false)){do_action('vwlb_operational_failure','redundancy','vwlb_redundancy_reconcile_cursor_failed',array('phase'=>'reset'));return false;}return true;}$saved=update_option(self::REDUNDANCY_RECONCILE_CURSOR_OPTION,$id,false);if(!$saved&&(int)get_option(self::REDUNDANCY_RECONCILE_CURSOR_OPTION,0)!==$id){do_action('vwlb_operational_failure','redundancy','vwlb_redundancy_reconcile_cursor_failed',array('phase'=>'advance'));return false;}return true;}
\tpublic static function reconcile_live_redundancy() {
\t\tglobal $wpdb;$cfg=VWLB_Helpers::table('future_live_config');$live=VWLB_Helpers::table('live_events');$after=absint(get_option(self::REDUNDANCY_RECONCILE_CURSOR_OPTION,0));$wpdb->last_error='';$rows=$wpdb->get_results($wpdb->prepare("SELECT c.*,l.public_id,l.provider,l.status FROM $cfg c INNER JOIN $live l ON l.id=c.live_event_id WHERE l.status IN ('ready','live','interrupted') AND (c.backup_provider<>'' OR c.redundant_recording=1) AND c.id>%d ORDER BY c.id ASC LIMIT 100",$after),ARRAY_A);if(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','redundancy','vwlb_redundancy_reconcile_read_failed',array());return;}$rows=is_array($rows)?$rows:array();if(!$rows){if($after)self::persist_redundancy_cursor(0);return;}$last=$after;foreach($rows as $row){$last=max($last,(int)($row['id']??0));try{do_action('vwlb_redundancy_reconcile',$row);if(!empty($row['redundant_recording']))do_action('vwlb_redundant_recording_required',$row);}catch(Throwable $e){do_action('vwlb_operational_failure','redundancy','vwlb_redundancy_reconcile_exception',array('live_public_id'=>$row['public_id']??'','provider'=>$row['provider']??'','exception'=>sanitize_key(get_class($e))));}}self::persist_redundancy_cursor(count($rows)<100?0:$last);
\t}'''
if old not in t: raise SystemExit('future redundancy matcher missing')
p.write_text(t.replace(old,new,1))

# R108-F06 — normalize all effective late REST override object IDs.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-review-hardening.php')
t=p.read_text()
if '[A-Za-z0-9_-]+' not in t: raise SystemExit('review-hardening permissive route matcher missing')
t=t.replace('[A-Za-z0-9_-]+','[a-z][a-z0-9]*_[a-z0-9]+')
p.write_text(t)

# Keep lifecycle interceptor route grammar consistent too.
for path in [
    'video-wall-and-live-broadcasting/includes/class-vwlb-r94-live-external-uncertainty-guard.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-r46-stream-credential-durability.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-r73-recording-consent-guard.php',
]:
    p=Path(path); t=p.read_text(); t=t.replace('[A-Za-z0-9_-]+','[a-z][a-z0-9]*_[a-z0-9]+'); p.write_text(t)

# Cursor purge hygiene.
p=Path('video-wall-and-live-broadcasting/uninstall.php')
t=p.read_text()
old="'vwlb_r76_upload_cleanup_cursor','vwlb_allow_purge'"
new="'vwlb_r76_upload_cleanup_cursor','vwlb_r108_live_reconcile_cursor','vwlb_r108_redundancy_reconcile_cursor','vwlb_allow_purge'"
if old not in t: raise SystemExit('uninstall cursor matcher missing')
p.write_text(t.replace(old,new,1))

# R108 regression gate.
p=Path('tests/file10-r101-r120-contracts.sh')
t=p.read_text(); marker="echo 'R101-R120 contracts PASS'"
block=r'''# R108 — live lifecycle/emergency/reconciliation/concurrency/idempotency.
grep -F "vwlb_provider_proof_forbidden" "$P/includes/class-vwlb-rest.php" >/dev/null
! grep -F "d['provider_proof']??array()" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "reconcile_provider_observation" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "vwlb_provider_live_reconcile_observation" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "vwlb_provider_live_webhook_observation" "$P/includes/class-vwlb-integrations.php" >/dev/null
grep -F "vwlb_verified_live_reconcile_failed" "$P/includes/class-vwlb-integrations.php" >/dev/null
grep -F "vwlb_provider_emergency_end_result" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "positively confirmed" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "provider_confirmed'=>true" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "LIVE_RECONCILE_CURSOR_OPTION='vwlb_r108_live_reconcile_cursor'" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "id>%d ORDER BY id ASC LIMIT 100" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "vwlb_live_reconcile_cursor_failed" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "REDUNDANCY_RECONCILE_CURSOR_OPTION = 'vwlb_r108_redundancy_reconcile_cursor'" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "ORDER BY c.id ASC LIMIT 100" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "vwlb_redundancy_reconcile_read_failed" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "vwlb_redundancy_reconcile_exception" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
! grep -F "[A-Za-z0-9_-]+" "$P/includes/class-vwlb-review-hardening.php" >/dev/null
grep -F "vwlb_r108_live_reconcile_cursor" "$P/uninstall.php" >/dev/null
grep -F "vwlb_r108_redundancy_reconcile_cursor" "$P/uninstall.php" >/dev/null

'''
if '# R108 — live lifecycle/emergency/reconciliation/concurrency/idempotency.' not in t:
    if marker not in t: raise SystemExit('R108 test marker missing')
    t=t.replace(marker,block+marker,1)
p.write_text(t)

# Fresh immutable candidate identity for deployable R108 source corrections.
for path in ['video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php','tests/run-all.sh']:
    p=Path(path); t=p.read_text()
    if OLD not in t: raise SystemExit('version matcher missing: '+path)
    p.write_text(t.replace(OLD,NEW))

p=Path('video-wall-and-live-broadcasting/readme.txt'); t=p.read_text()
if 'Stable tag: '+OLD not in t: raise SystemExit('readme stable matcher missing')
t=t.replace('Stable tag: '+OLD,'Stable tag: '+NEW,1).replace('video-wall-and-live-broadcasting-'+OLD+'.zip','video-wall-and-live-broadcasting-'+NEW+'.zip',1)
marker='= '+OLD+' =\n'
section=('= '+NEW+' =\n'
         '* R108: remove public caller authority over provider lifecycle evidence and add a canonical verified provider/webhook reconciliation path.\n'
         '* Require positive provider confirmation for emergency end before committing matching local state; uncertain provider/local outcomes remain reconciliation-guarded.\n'
         '* Make core live and Future redundancy reconciliation DB-failure-aware, Throwable-contained and cursor-fair, and normalize late REST override IDs to the canonical opaque grammar.\n\n')
if section not in t:
    if marker not in t: raise SystemExit('readme changelog matcher missing')
    t=t.replace(marker,section+marker,1)
p.write_text(t)

p=Path('README.md'); t=p.read_text(); t=t.replace('- Runtime: `'+OLD+'`','- Runtime: `'+NEW+'`',1)
t += '\n\n### R108 correction candidate\nR108 completed its read-only live-lifecycle/reconciliation review before correction. Six findings were frozen in `docs/FILE-10-R108-FROZEN-FINDINGS-2026-09-07.md`. The correction candidate is `'+NEW+'`; exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive, source/package parity and artifact publication must be green before R109 begins.\n'
p.write_text(t)

p=Path('MANIFEST.md'); t=p.read_text(); t=t.replace(OLD,NEW)
t += '\n- R108 frozen findings: public provider-proof injection; missing canonical verified provider lifecycle reconciliation; unconfirmed emergency provider end; unfair/silent core live reconciliation; unsafe/unfair Future redundancy reconciliation; permissive effective late REST override grammar.\n- R108 correction candidate identity: `'+NEW+'`; exact-head release QA is required before R109.\n'
p.write_text(t)

p=Path('STATUS.md'); t=p.read_text(); t=t.replace('# File 10 Status — '+OLD,'# File 10 Status — '+NEW,1).replace('Coded/reviewed candidate: `'+OLD+'`','Coded/reviewed candidate: `'+NEW+'`',1)
t=t.replace('- Current evidence-recording head must itself pass the same release QA before R108 begins.','- R107 final evidence-recording head `0de65d9956cf3a4c03e3e0c315c09bb70ca8c82f`, run `34087545859`, is Green on PHP 8.3/8.4; R108 began only after that gate.',1)
t=t.replace('- Automated-QA Green: R101–R107 source/package gates established; evidence-recording-head revalidation is the final gate before R108.','- Automated-QA Green: R101–R107 established. R108 correction is coded as `'+NEW+'`; its exact-head release QA is pending and R109 remains blocked.',1)
t += '\n- R108 review: completed read-only from R107 Green baseline; six findings frozen in `docs/FILE-10-R108-FROZEN-FINDINGS-2026-09-07.md`.\n- R108 correction: applied as `'+NEW+'`; exact-head release QA is pending before R109.\n'
p.write_text(t)

Path('SBOM-'+NEW+'.json').write_text('''{\n  "bomFormat": "Sabri-Public-SBOM",\n  "specVersion": "1.0",\n  "component": {"name": "video-wall-and-live-broadcasting", "version": "'''+NEW+'''", "type": "wordpress-plugin"},\n  "runtime": {"wordpress": ">=7.0", "php": ">=8.3"},\n  "schemas": {"base": "1.1.0", "extension": "1.1.0", "future": "1.2.0"},\n  "bundledThirdPartyRuntimeLibraries": [],\n  "externalServiceAdapters": ["local", "youtube", "vimeo", "custom"],\n  "notes": "R108 correction candidate: verified provider lifecycle reconciliation, positive emergency-end confirmation, cursor-fair and failure-visible live/redundancy reconciliation, and canonical late REST opaque-ID grammar. Repository/package QA remains distinct from staging/live/operational acceptance."\n}\n''')
