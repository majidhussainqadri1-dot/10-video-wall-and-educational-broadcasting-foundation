from pathlib import Path
import re
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-integrations.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="\tpublic function publish_event($name,$payload,$event){\n\t\tdo_action('sabri_event_bus_publish',$name,$payload,array('owner'=>'File 10','event_id'=>$event['public_id'],'contract_version'=>1,'privacy'=>'event-specific'));\n\t}\n"
new=r'''	public function publish_event($name,$payload,$event){
		if(!in_array($name,VWLB_Contracts::PUBLISHED_EVENTS,true))return;
		$safe=array();foreach((array)$payload as $key=>$value){$key=sanitize_key((string)$key);if((str_ends_with($key,'_public_id')||in_array($key,array('public_id','status','reason','scheduled','language','consented','minutes','capacity','waiting_room'),true))&&is_scalar($value))$safe[$key]=VWLB_Helpers::text($value,191);}
		$safe=(array)apply_filters('vwlb_cross_file_event_payload',$safe,$name,$event);
		do_action('sabri_event_bus_publish',$name,$safe,array('owner'=>'File 10','event_id'=>$event['public_id'],'contract_version'=>1,'privacy'=>'public-safe-event-projection'));
	}
'''
if old not in t:raise SystemExit('R31 publish_event pattern missing')
t=t.replace(old,new,1)
pat=re.compile(r"\tpublic function consume\(\$event_id,\$event_name,\$payload\)\{.*?\n\t}\n\tpublic function file11_media_source",re.S);m=pat.search(t)
if not m:raise SystemExit('R31 consume method missing')
consume=r'''	public function consume($event_id,$event_name,$payload){
		$event_id=VWLB_Helpers::text($event_id,100);$event_name=(string)$event_name;if(!$event_id)return VWLB_Helpers::error('vwlb_event_id_required',__('Inbound event ID is required.',VWLB_TEXT_DOMAIN),422);if(!in_array($event_name,VWLB_Contracts::CONSUMED_EVENTS,true))return array('ignored'=>true);
		global $wpdb;$table=VWLB_Helpers::table('inbox');$hash=hash('sha256',VWLB_Helpers::json_encode($payload));$inserted=$wpdb->insert($table,array('event_id'=>$event_id,'event_name'=>VWLB_Helpers::text($event_name,100),'payload_hash'=>$hash,'status'=>'processing','received_at'=>VWLB_Helpers::now()));
		if(false===$inserted){$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE event_id=%s",$event_id),ARRAY_A);if(!$existing)return VWLB_Helpers::error('vwlb_inbox_persist_failed',__('Inbound event could not be persisted.',VWLB_TEXT_DOMAIN),503);if(!hash_equals((string)$existing['payload_hash'],$hash)||$existing['event_name']!==$event_name)return VWLB_Helpers::error('vwlb_inbox_event_conflict',__('The same inbound event ID was received with different content.',VWLB_TEXT_DOMAIN),409);return array('duplicate'=>true,'status'=>$existing['status']);}
		try{
			if('MembershipEntitlementChanged.v1'===$event_name){do_action('vwlb_membership_entitlement_changed',$payload);}
			elseif('MessageUserBlocked.v1'===$event_name){do_action('vwlb_context_user_blocked',$payload);}
			elseif('CopyrightReportFiled.v1'===$event_name){do_action('vwlb_external_copyright_report',$payload);}
			do_action('vwlb_consume_event',$event_name,$payload);
		}catch(Throwable $e){$wpdb->update($table,array('status'=>'failed','processed_at'=>VWLB_Helpers::now()),array('event_id'=>$event_id,'status'=>'processing'));return VWLB_Helpers::error('vwlb_inbound_handler_failed',__('Inbound event handling failed safely.',VWLB_TEXT_DOMAIN),503,array('exception'=>get_class($e)));}
		$done=$wpdb->update($table,array('status'=>'processed','processed_at'=>VWLB_Helpers::now()),array('event_id'=>$event_id,'status'=>'processing'));if(1!==$done)return VWLB_Helpers::error('vwlb_inbox_finalize_failed',__('Inbound event was handled but durable completion could not be recorded; reconciliation is required.',VWLB_TEXT_DOMAIN),503);return array('processed'=>true);
	}
	public function file11_media_source'''
t=t[:m.start()]+consume+t[m.end():];p.write_text(t)
r=reg.read_text();marker="""# R31 — only declared public-safe File10 events leave the module; inbox persistence conflicts are not misclassified as duplicates.\nneed \"in_array(\$name,VWLB_Contracts::PUBLISHED_EVENTS,true)\" \"$P/includes/class-vwlb-integrations.php\" r31-event-allowlist\nneed \"public-safe-event-projection\" \"$P/includes/class-vwlb-integrations.php\" r31-payload-boundary\nneed \"vwlb_inbox_persist_failed\" \"$P/includes/class-vwlb-integrations.php\" r31-inbox-db\nneed \"vwlb_inbox_event_conflict\" \"$P/includes/class-vwlb-integrations.php\" r31-event-conflict\nneed \"vwlb_inbox_finalize_failed\" \"$P/includes/class-vwlb-integrations.php\" r31-finalize\n"""
if '# R31 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
