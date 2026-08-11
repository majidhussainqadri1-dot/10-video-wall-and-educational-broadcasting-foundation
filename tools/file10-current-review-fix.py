from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-jobs.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="$wpdb->update($table,array('status'=>'publishing','attempts'=>(int)$event['attempts']+1,'locked_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()),array('id'=>$event['id'],'status'=>$event['status']));\n\t\t\ttry{do_action('vwlb_domain_event'"
new="$locked=$wpdb->update($table,array('status'=>'publishing','attempts'=>(int)$event['attempts']+1,'locked_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()),array('id'=>$event['id'],'status'=>$event['status']));if(1!==$locked)continue;\n\t\t\ttry{do_action('vwlb_domain_event'"
if old not in t:raise SystemExit('R25 outbox lock pattern missing')
t=t.replace(old,new,1)
old="foreach($videos as $v){$ok=VWLB_Videos::publication_gate($v);if(true===$ok){VWLB_Repository::update_versioned('videos',$v['id'],$v['version'],array('status'=>'published','published_at'=>$now));VWLB_Helpers::outbox('VideoPublished','video',$v['id'],array('scheduled'=>true));}}"
new="foreach($videos as $v){$ok=VWLB_Videos::publication_gate($v);if(true===$ok){$published=VWLB_Repository::update_versioned('videos',$v['id'],$v['version'],array('status'=>'published','published_at'=>$now));if(!is_wp_error($published))VWLB_Helpers::outbox('VideoPublished','video',$v['id'],array('scheduled'=>true));}}"
if old not in t:raise SystemExit('R25 scheduled publish pattern missing')
t=t.replace(old,new,1)
old="if(is_array($state))$wpdb->update(VWLB_Helpers::table('live_events'),array('provider_state_json'=>VWLB_Helpers::json_encode(array_merge(VWLB_Helpers::json($event['provider_state_json']),$state)),'updated_at'=>$now),array('id'=>$event['id']));"
new="if(is_array($state)){$safe=array_intersect_key($state,array_flip(array('provider_event_ref','status','degraded','region','health_code')));$wpdb->update(VWLB_Helpers::table('live_events'),array('provider_state_json'=>VWLB_Helpers::json_encode(array_merge(VWLB_Helpers::json($event['provider_state_json']),$safe)),'updated_at'=>$now),array('id'=>$event['id']));}"
if old not in t:raise SystemExit('R25 provider reconcile pattern missing')
t=t.replace(old,new,1)
old="$wpdb->update($table,array('status'=>'complete','output_json'=>VWLB_Helpers::json_encode($result),'locked_at'=>null,'locked_by'=>null,'updated_at'=>VWLB_Helpers::now()),array('id'=>$job['id']));"
new="$finished=$wpdb->update($table,array('status'=>'complete','output_json'=>VWLB_Helpers::json_encode($result),'locked_at'=>null,'locked_by'=>null,'updated_at'=>VWLB_Helpers::now()),array('id'=>$job['id'],'status'=>'running'));if(1!==$finished)return;"
if old not in t:raise SystemExit('R25 job completion pattern missing')
t=t.replace(old,new,1)
p.write_text(t)
r=reg.read_text();marker="""# R25 — outbox/job workers honor CAS locks and reconciliation never emits publication events after a failed state transition.\nneed \"if(1!==\$locked)continue\" \"$P/includes/class-vwlb-jobs.php\" r25-outbox-lock\nneed \"if(!is_wp_error(\$published))\" \"$P/includes/class-vwlb-jobs.php\" r25-scheduled-cas\nneed \"array_intersect_key(\$state\" \"$P/includes/class-vwlb-jobs.php\" r25-provider-redaction\nneed \"'status'=>'running'\" \"$P/includes/class-vwlb-jobs.php\" r25-job-finalize-cas\n"""
if '# R25 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
