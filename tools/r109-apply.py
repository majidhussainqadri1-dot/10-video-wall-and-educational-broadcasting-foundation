from pathlib import Path
import re, json
ROOT=Path('.')
P=ROOT/'video-wall-and-live-broadcasting'
OLD='1.2.19-rc1'; NEW='1.2.20-rc1'

def replace_once(path, old, new):
    p=Path(path); t=p.read_text()
    if old not in t: raise SystemExit(f'missing pattern in {path}: {old[:120]}')
    p.write_text(t.replace(old,new,1))

def regex_once(path, pattern, repl):
    p=Path(path); t=p.read_text(); n=re.subn(pattern,repl,t,count=1,flags=re.S)
    if n[1]!=1: raise SystemExit(f'regex count {n[1]} in {path}: {pattern[:80]}')
    p.write_text(n[0])

# Security: every current delivery view rechecks R109 rights/consent policy before visibility is granted.
sec=P/'includes/class-vwlb-security.php'
old="\t\tif($is_live&&!in_array($object['status']??'',array('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published'),true))return self::can(VWLB_Contracts::CAP_BROADCAST,$object,$purpose);\n\t\t$visibility=$object['visibility']??'private';if('public'===$visibility)return true;"
new="\t\tif($is_live&&!in_array($object['status']??'',array('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published'),true))return self::can(VWLB_Contracts::CAP_BROADCAST,$object,$purpose);\n\t\tif(class_exists('VWLB_R109_Rights_Consent_Replay_Guard')&&!VWLB_R109_Rights_Consent_Replay_Guard::current_delivery_allowed($object,$purpose))return false;\n\t\t$visibility=$object['visibility']??'private';if('public'===$visibility)return true;"
replace_once(sec,old,new)

# Future consent command: terminal history cannot be reactivated, status writes are audited/outboxed, DB reads fail closed.
future=P/'includes/class-vwlb-future-intelligence.php'
replacement="""\tpublic static function upsert_consent_link( $video_id, $data ) {
\t\t$video=self::video($video_id);if(!$video||!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_consent_link'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot manage consent for this video.',VWLB_TEXT_DOMAIN),403);$ref=VWLB_Helpers::text($data['consent_ref']??'',191);if(!$ref)return VWLB_Helpers::error('vwlb_consent_ref_required',__('Consent reference is required.',VWLB_TEXT_DOMAIN),422);$status=VWLB_Helpers::enum($data['status']??'active',array('active','expired','withdrawn','superseded'),'active');$expires=!empty($data['expires_at'])?VWLB_Helpers::datetime_in_timezone($data['expires_at'],$data['timezone']??'UTC'):null;if(!empty($data['expires_at'])&&!$expires)return VWLB_Helpers::error('vwlb_consent_time_invalid',__('Consent expiry time is invalid.',VWLB_TEXT_DOMAIN),422);if('active'===$status&&$expires&&strtotime($expires.' UTC')<=time())return VWLB_Helpers::error('vwlb_consent_time_invalid',__('Active consent expiry must be in the future.',VWLB_TEXT_DOMAIN),422);$metadata=(array)($data['metadata']??array());if(self::contains_raw_secret($metadata))return VWLB_Helpers::error('vwlb_consent_secret_forbidden',__('Raw credentials cannot be stored in consent metadata.',VWLB_TEXT_DOMAIN),422);
\t\tglobal $wpdb;$table=VWLB_Helpers::table('consent_links');$result=VWLB_DB::transaction(function()use($wpdb,$table,$video,$ref,$status,$expires,$data,$metadata){$locked=VWLB_Repository::find('videos',$video['id'],true);if(!$locked)return VWLB_Helpers::error('vwlb_video_missing',__('Video not found.',VWLB_TEXT_DOMAIN),404);$wpdb->last_error='';$current=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE video_id=%d AND consent_ref=%s FOR UPDATE\",$locked['id'],$ref),ARRAY_A);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_consent_read_failed',__('Consent history could not be verified safely.',VWLB_TEXT_DOMAIN),503);$allowed=VWLB_R109_Rights_Consent_Replay_Guard::assert_consent_transition($current,$status);if(is_wp_error($allowed))return $allowed;$before=is_array($current)?sanitize_key((string)$current['status']):'';$row=array('subject_ref'=>VWLB_Helpers::text($data['subject_ref']??'',191),'status'=>$status,'expires_at'=>$expires,'withdrawn_at'=>VWLB_R109_Rights_Consent_Replay_Guard::withdrawn_at($current,$status),'metadata_json'=>VWLB_Helpers::json_encode($metadata),'updated_at'=>VWLB_Helpers::now());if($current){$expected=absint($data['version']??0);if(!$expected||$expected!==(int)$current['version'])return VWLB_Helpers::error('vwlb_version_conflict',__('Consent record changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);$row['version']=$expected+1;$changed=$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$expected));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Consent record changed concurrently.',VWLB_TEXT_DOMAIN),409);$id=(int)$current['id'];}else{$row+=array('video_id'=>(int)$locked['id'],'consent_ref'=>$ref,'version'=>1,'created_by'=>get_current_user_id(),'created_at'=>VWLB_Helpers::now());if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Consent link could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
\t\t$restricted=false;if(in_array($status,array('expired','withdrawn'),true)){$restricted=self::restrict_video_for_consent($locked,$status);if(is_wp_error($restricted))return $restricted;}VWLB_R109_Rights_Consent_Replay_Guard::record_consent_change($locked,$id,$before,$status,$ref);$wpdb->last_error='';$saved=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE id=%d\",$id),ARRAY_A);if(''!==(string)$wpdb->last_error||!$saved)return VWLB_Helpers::error('vwlb_consent_read_failed',__('Updated consent history could not be verified safely.',VWLB_TEXT_DOMAIN),503);return array('consent'=>$saved,'restricted'=>(bool)$restricted,'purge'=>in_array($status,array('expired','withdrawn'),true),'video_public_id'=>$locked['public_id'],'reason'=>$status);});if(is_wp_error($result))return $result;if(!empty($result['purge']))do_action('vwlb_purge_media_derivative_caches',$result['video_public_id'],'consent_'.$result['reason']);return $result['consent'];
\t}
"""
regex_once(future,r"\tpublic static function upsert_consent_link\( \$video_id, \$data \) \{.*?\n\t\}\n(?=\n\t/\*\* F10-FUT-023)",replacement)

# Replay publication: prove recording/replay authorization, current rights/consent and canonical recording-asset lineage.
live=P/'includes/class-vwlb-live.php'
regex_once(live,r"\tpublic static function publish_replay\(\$live_id,\$video_id,\$expected_version\)\{.*?\n\}","""\tpublic static function publish_replay($live_id,$video_id,$expected_version){$event=VWLB_Repository::find('live_events',$live_id);$video=VWLB_Repository::find('videos',$video_id);if(!$event||!$video)return VWLB_Helpers::error('vwlb_missing',__('Live event or replay video not found.',VWLB_TEXT_DOMAIN),404);if(!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'publish_replay')||!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$video,'publish_replay'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot publish this replay.',VWLB_TEXT_DOMAIN),403);if('published'!==$video['status'])return VWLB_Helpers::error('vwlb_replay_video_unpublished',__('Replay video must be published first.',VWLB_TEXT_DOMAIN),422);$gate=VWLB_R109_Rights_Consent_Replay_Guard::assert_replay($event,$video);if(is_wp_error($gate))return $gate;$assert=VWLB_State_Machine::assert('live',$event['status'],'replay_published');if(is_wp_error($assert))return $assert;$updated=VWLB_Repository::update_versioned('live_events',$event['id'],$expected_version,array('status'=>'replay_published','replay_video_id'=>$video['id']));if(is_wp_error($updated))return $updated;VWLB_Helpers::audit('live',$event['id'],'publish_replay',$event['status'],'replay_published','Canonical replay lineage and consent policy verified.',array('video_public_id'=>$video['public_id'],'recording_asset_public_id'=>(VWLB_Repository::find('media_assets',$event['recording_asset_id'])['public_id']??'')));VWLB_Helpers::outbox('LiveReplayPublished','live',$event['id'],array('video_public_id'=>$video['public_id'],'recording_lineage_verified'=>true));return $updated;}
}
""")

# Plugin loader/register + immutable candidate identity.
plugin=P/'video-wall-and-live-broadcasting.php'
pt=plugin.read_text().replace("Version: "+OLD,"Version: "+NEW,1).replace("define( 'VWLB_VERSION', '"+OLD+"' )","define( 'VWLB_VERSION', '"+NEW+"' )",1)
pt=pt.replace("'class-vwlb-r105-privacy-lifecycle.php','class-vwlb-r3-playback.php'","'class-vwlb-r105-privacy-lifecycle.php','class-vwlb-r109-rights-consent-replay-guard.php','class-vwlb-r3-playback.php'",1)
pt=pt.replace("VWLB_R105_Privacy_Lifecycle::register();}","VWLB_R105_Privacy_Lifecycle::register();VWLB_R109_Rights_Consent_Replay_Guard::register();}",1)
if 'VWLB_R109_Rights_Consent_Replay_Guard::register()' not in pt: raise SystemExit('plugin register patch failed')
plugin.write_text(pt)

# Uninstall cursor cleanup.
un=P/'uninstall.php'; ut=un.read_text(); ut=ut.replace("'vwlb_r76_upload_cleanup_cursor','vwlb_allow_purge'","'vwlb_r76_upload_cleanup_cursor','vwlb_r109_consent_expiry_cursor','vwlb_allow_purge'",1)
if 'vwlb_r109_consent_expiry_cursor' not in ut: raise SystemExit('uninstall patch failed')
un.write_text(ut)

# WordPress readme: identity/install and new changelog entry, preserving R108 history.
rd=P/'readme.txt'; t=rd.read_text(); t=t.replace('Stable tag: '+OLD,'Stable tag: '+NEW,1).replace('video-wall-and-live-broadcasting-'+OLD+'.zip','video-wall-and-live-broadcasting-'+NEW+'.zip',1)
marker='== Changelog ==\n\n'
entry="""= 1.2.20-rc1 =
* R109: enforce current rights/expiry/revocation and patient-case consent blockers consistently at delivery/publication time.
* Replace cron-only consent expiry with DB-failure-aware cursor-fair reconciliation and preserve terminal consent history with audit/outbox evidence.
* Require recording policy, current consent proof and canonical recording-asset lineage before a live replay can be published.

"""
if marker not in t: raise SystemExit('readme changelog marker missing')
t=t.replace(marker,marker+entry,1); rd.write_text(t)

# Current automated-suite identity.
run=ROOT/'tests/run-all.sh'; rt=run.read_text().replace("CURRENT_VERSION='"+OLD+"'","CURRENT_VERSION='"+NEW+"'",1); run.write_text(rt)

# R109 regression gate.
contracts=ROOT/'tests/file10-r101-r120-contracts.sh'; ct=contracts.read_text(); block="""

# R109 — rights/takedown/recording-consent/replay integrity.
grep -F "class VWLB_R109_Rights_Consent_Replay_Guard" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "VWLB_R109_Rights_Consent_Replay_Guard::register()" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "current_delivery_allowed($object,$purpose)" "$P/includes/class-vwlb-security.php" >/dev/null
grep -F "vwlb_rights_consent_unverifiable" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_rights_territory_authorized" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_consent_terminal_history" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "VideoConsentRestricted" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "CONSENT_CURSOR_OPTION='vwlb_r109_consent_expiry_cursor'" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "id>%d ORDER BY id ASC LIMIT %d" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_consent_expiry_read_failed" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "remove_action('vwlb_reconcile_states',array('VWLB_Future_Safety','reconcile_consent_expiry'),20)" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "assert_consent_transition($current,$status)" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "record_consent_change($locked,$id,$before,$status,$ref)" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "assert_replay($event,$video)" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "vwlb_replay_not_authorized" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_replay_lineage_invalid" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_r109_consent_expiry_cursor" "$P/uninstall.php" >/dev/null
"""
ct=ct.replace("\necho 'R101-R120 contracts PASS'",block+"\necho 'R101-R120 contracts PASS'",1); contracts.write_text(ct)

# Current candidate documentation, preserving historical R108 evidence.
readme=ROOT/'README.md'; x=readme.read_text().replace('- Runtime: `'+OLD+'`','- Runtime: `'+NEW+'`',1)
x += "\n### R109 correction candidate\nR109 completed its full read-only rights/takedown/recording-consent/replay review from the exact R108 Green baseline `762361ae9f1b1c8392e0fbce224a15051432952c`. Five findings were frozen in `docs/FILE-10-R109-FROZEN-FINDINGS-2026-09-07.md`. The correction candidate is `1.2.20-rc1`; exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive, source/package parity and artifact publication must be green before R110 begins.\n"
readme.write_text(x)
status=ROOT/'STATUS.md'; x=status.read_text().replace('# File 10 Status — '+OLD,'# File 10 Status — '+NEW,1).replace('- Coded/reviewed candidate: `'+OLD+'`','- Coded/reviewed candidate: `'+NEW+'`',1)
x=x.replace('- Automated-QA Green: R101–R107 established. R108 correction is coded as `1.2.19-rc1`; its exact-head release QA is pending and R109 remains blocked.','- Automated-QA Green: R101–R108 established. R108 exact-head `762361ae9f1b1c8392e0fbce224a15051432952c`, run `34100057505`, is green on PHP 8.3/8.4 with complete suite/package/parity/artifact gates. R109 correction is coded as `1.2.20-rc1`; its exact-head release QA is pending and R110 remains blocked.',1)
x += "\n- R109 review: completed read-only after R108 Green; five findings frozen in `docs/FILE-10-R109-FROZEN-FINDINGS-2026-09-07.md`.\n- R109 correction: current rights/consent delivery, consent-expiry fairness/history and replay-lineage gates applied as `1.2.20-rc1`; exact-head release QA is pending before R110.\n"
status.write_text(x)
man=ROOT/'MANIFEST.md'; x=man.read_text().replace('# File 10 Release Candidate Manifest — '+OLD,'# File 10 Release Candidate Manifest — '+NEW,1).replace('- Plugin version: `'+OLD+'`','- Plugin version: `'+NEW+'`',1).replace('packages/video-wall-and-live-broadcasting-'+OLD+'.zip','packages/video-wall-and-live-broadcasting-'+NEW+'.zip',1).replace('SBOM: `SBOM-'+OLD+'.json`','SBOM: `SBOM-'+NEW+'.json`',1)
x += "\n- R108 exact-head QA: `762361ae9f1b1c8392e0fbce224a15051432952c`, run `34100057505`, PHP 8.3/8.4 Green before R109.\n- R109 frozen findings: inconsistent current rights delivery; cron-only stale consent exposure; unsafe/unfair consent-expiry reconciliation; mutable terminal consent history without lifecycle evidence; replay policy/consent/lineage gap.\n- R109 correction candidate identity: `1.2.20-rc1`; exact-head release QA is required before R110.\n"
man.write_text(x)

sbom={
  'bomFormat':'CycloneDX','specVersion':'1.5','serialNumber':'urn:uuid:file10-r109-1.2.20-rc1','version':1,
  'metadata':{'component':{'type':'application','name':'Video Wall and Live Broadcasting','version':NEW},'properties':[{'name':'review_boundary','value':'R109 rights/consent/replay correction candidate; R110 blocked until exact-head Green'},{'name':'baseline_exact_head','value':'762361ae9f1b1c8392e0fbce224a15051432952c'},{'name':'plan','value':'SSH-F10-PLAN-2026-v1.1 Future24 amended + SSH-PMP-2026-v3.0'}]},
  'components':[{'type':'library','name':'WordPress','version':'7.0+'},{'type':'library','name':'PHP','version':'8.3+'}]
}
(ROOT/'SBOM-1.2.20-rc1.json').write_text(json.dumps(sbom,indent=2)+'\n')

print('R109 frozen correction batch applied')
