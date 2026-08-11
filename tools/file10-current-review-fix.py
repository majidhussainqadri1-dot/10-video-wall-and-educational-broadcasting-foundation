from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-live.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="$changes=array('status'=>$to,'provider_state_json'=>VWLB_Helpers::json_encode(array_merge(VWLB_Helpers::json($event['provider_state_json']),$provider_proof)));"
new="$safe_proof=array_intersect_key((array)$provider_proof,array_flip(array('provider_event_ref','status','degraded','region','health_code')));$changes=array('status'=>$to,'provider_state_json'=>VWLB_Helpers::json_encode(array_merge(VWLB_Helpers::json($event['provider_state_json']),$safe_proof)));"
if old not in t:raise SystemExit('R22 provider proof pattern missing')
t=t.replace(old,new,1)
old="$wpdb->update(VWLB_Helpers::table('stream_credentials'),array('status'=>'revoked','revoked_at'=>VWLB_Helpers::now()),array('live_event_id'=>$event['id'],'status'=>'active'));do_action('vwlb_provider_emergency_end',$event,$reason);"
new="$revoked=$wpdb->update(VWLB_Helpers::table('stream_credentials'),array('status'=>'revoked','revoked_at'=>VWLB_Helpers::now()),array('live_event_id'=>$event['id'],'status'=>'active'));if(false===$revoked)return VWLB_Helpers::error('vwlb_database_error',__('Active stream credentials could not be revoked.',VWLB_TEXT_DOMAIN),500);do_action('vwlb_provider_emergency_end',$event,$reason);"
if old not in t:raise SystemExit('R22 kill pattern missing')
t=t.replace(old,new,1)
old="$wpdb->update(VWLB_Helpers::table('live_events'),array('status'=>'ended','actual_end'=>VWLB_Helpers::now(),'version'=>(int)$event['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$event['id'],'version'=>$event['version']));VWLB_Helpers::audit('live',$event['id'],'request_time_reconcile','scheduled','ended','Scheduled window elapsed');"
new="$changed=$wpdb->update(VWLB_Helpers::table('live_events'),array('status'=>'ended','actual_end'=>VWLB_Helpers::now(),'version'=>(int)$event['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$event['id'],'version'=>$event['version']));if(1===$changed){VWLB_Helpers::audit('live',$event['id'],'request_time_reconcile','scheduled','ended','Scheduled window elapsed');VWLB_Helpers::outbox('LiveBroadcastEnded','live',$event['id'],array('reason'=>'scheduled_window_elapsed'));}"
if old not in t:raise SystemExit('R22 time reconcile pattern missing')
t=t.replace(old,new,1)
old="if(!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$event,'publish_replay'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot publish this replay.',VWLB_TEXT_DOMAIN),403);if('published'!==$video['status'])"
new="if(!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'publish_replay')||!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$video,'publish_replay'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot publish this replay.',VWLB_TEXT_DOMAIN),403);if('published'!==$video['status'])"
if old not in t:raise SystemExit('R22 replay auth pattern missing')
t=t.replace(old,new,1)
p.write_text(t)
r=reg.read_text();marker="""# R22 — live transitions store bounded provider proof, emergency end checks credential revocation, and replay linking authorizes both event and video.\nneed \"safe_proof=array_intersect_key\" \"$P/includes/class-vwlb-live.php\" r22-proof-redaction\nneed \"Active stream credentials could not be revoked\" \"$P/includes/class-vwlb-live.php\" r22-kill-revoke\nneed \"scheduled_window_elapsed\" \"$P/includes/class-vwlb-live.php\" r22-reconcile-event\nneed \"CAP_BROADCAST,\$event,'publish_replay'\" \"$P/includes/class-vwlb-live.php\" r22-event-auth\nneed \"CAP_PUBLISH,\$video,'publish_replay'\" \"$P/includes/class-vwlb-live.php\" r22-video-auth\n"""
if '# R22 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
