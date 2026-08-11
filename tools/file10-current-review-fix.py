from pathlib import Path
import re

intel=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
adapters=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-adapters.php')
reg=Path('tests/fresh-40-review-contracts.sh')
t=intel.read_text(); a=adapters.read_text()

replacement=r'''	public static function configure_live( $live_id, $data, $expected_version=0 ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_live_config'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot configure this broadcast.',VWLB_TEXT_DOMAIN),403);
		$latency=VWLB_Helpers::enum($data['latency_mode']??'standard',array('standard','low','ultra_low'),'standard');
		$dvr=max(0,min(6*HOUR_IN_SECONDS,(int)($data['dvr_window_seconds']??0)));$protocols=array_values(array_unique(array_intersect(array_map('strtolower',(array)($data['protocols']??array('rtmp'))),array('rtmp','srt','webrtc'))));if(!$protocols)$protocols=array('rtmp');
		$provider_caps=(array)apply_filters('vwlb_provider_future_capabilities',array(),$event['provider'],$event);foreach($protocols as $p){if('rtmp'!==$p&&!in_array($p,(array)($provider_caps['ingest_protocols']??array()),true))return VWLB_Helpers::error('vwlb_protocol_unavailable',__('Requested ingest protocol is not configured for this provider.',VWLB_TEXT_DOMAIN),503,array('protocol'=>$p));}
		if('standard'!==$latency&&!in_array($latency,(array)($provider_caps['latency_modes']??array()),true))return VWLB_Helpers::error('vwlb_latency_mode_unavailable',__('Requested latency mode is not declared by the configured provider adapter.',VWLB_TEXT_DOMAIN),503,array('latency_mode'=>$latency));
		if($dvr>0){$max_dvr=max(0,(int)($provider_caps['dvr_max_seconds']??0));if($max_dvr<=0||$dvr>$max_dvr)return VWLB_Helpers::error('vwlb_dvr_unavailable',__('Requested DVR window is not declared by the configured provider adapter.',VWLB_TEXT_DOMAIN),503,array('dvr_window_seconds'=>$dvr));}
		$backup=VWLB_Helpers::text($data['backup_provider']??'',64);if($backup&&$backup===$event['provider'])return VWLB_Helpers::error('vwlb_backup_provider_invalid',__('Backup provider must differ from the primary provider.',VWLB_TEXT_DOMAIN),422);if($backup){$bp=VWLB_Providers::get($backup);$bc=$bp?$bp->capabilities():array();if(!$bp||empty($bc['live']))return VWLB_Helpers::error('vwlb_backup_provider_unavailable',__('Backup provider is not a configured live provider.',VWLB_TEXT_DOMAIN),503);}
		$redundant=!empty($data['redundant_recording']);if($redundant&&!$backup)return VWLB_Helpers::error('vwlb_backup_provider_required',__('Redundant recording requires a distinct configured backup provider.',VWLB_TEXT_DOMAIN),422);
		$languages=array_values(array_unique(array_filter(array_map(function($v){$v=VWLB_Helpers::text($v,32);return preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/',$v)?$v:'';},(array)($data['translation_languages']??array())))));
		global $wpdb;$table=VWLB_Helpers::table('future_live_config');$current=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d",$event['id']),ARRAY_A);$now=VWLB_Helpers::now();
		$row=array('latency_mode'=>$latency,'dvr_window_seconds'=>$dvr,'backup_provider'=>$backup,'multicam_enabled'=>!empty($data['multicam_enabled'])?1:0,'simulcast_enabled'=>!empty($data['simulcast_enabled'])?1:0,'redundant_recording'=>$redundant?1:0,'protocols_json'=>VWLB_Helpers::json_encode($protocols),'translation_languages_json'=>VWLB_Helpers::json_encode($languages),'updated_by'=>get_current_user_id(),'updated_at'=>$now);
		if($current){if(!$expected_version||(int)$current['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Broadcast configuration changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Broadcast configuration changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);$id=(int)$current['id'];}
		else{$row+=array('live_event_id'=>(int)$event['id'],'version'=>1,'created_at'=>$now);if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Broadcast configuration could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		VWLB_Helpers::audit('live',$event['id'],'future_live_config_saved',$event['status'],$event['status'],'',array('latency_mode'=>$latency,'dvr_window_seconds'=>$dvr,'protocols'=>$protocols,'backup_provider'=>$backup,'redundant_recording'=>$redundant));return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
	}
'''
pat=re.compile(r"\tpublic static function configure_live\(.*?\n\t}\n",re.S);m=pat.search(t)
if not m: raise SystemExit('R07 configure_live not found')
t=t[:m.start()]+replacement+t[m.end():];intel.write_text(t)

old="$safe=array('accepted'=>true,'provider'=>$event['provider'],'mode'=>VWLB_Helpers::text($result['mode']??$config['latency_mode'],32),'dvr_window_seconds'=>max(0,(int)($result['dvr_window_seconds']??$config['dvr_window_seconds'])),'protocol'=>VWLB_Helpers::text($result['protocol']??'',32),'backup_ready'=>!empty($result['backup_ready']),'recording_redundant'=>!empty($result['recording_redundant']));"
new="$safe=array('accepted'=>true,'provider'=>$event['provider'],'mode'=>VWLB_Helpers::enum($result['mode']??$config['latency_mode'],array('standard','low','ultra_low'),$config['latency_mode']),'dvr_window_seconds'=>max(0,min(6*HOUR_IN_SECONDS,(int)($result['dvr_window_seconds']??$config['dvr_window_seconds']))),'protocol'=>VWLB_Helpers::enum($result['protocol']??'',array('rtmp','srt','webrtc'),''),'backup_ready'=>!empty($result['backup_ready']),'recording_redundant'=>!empty($result['recording_redundant']));"
if old in a:a=a.replace(old,new,1)
elif new not in a:raise SystemExit('R07 adapter safe response pattern missing')
adapters.write_text(a)

r=reg.read_text(); marker="""# R07 — provider-dependent DVR/latency/backup policy is capability-declared, version-safe and truthfully normalized.\nneed \"vwlb_latency_mode_unavailable\" \"$P/includes/class-vwlb-future-intelligence.php\" r07-latency-capability\nneed \"vwlb_dvr_unavailable\" \"$P/includes/class-vwlb-future-intelligence.php\" r07-dvr-capability\nneed \"vwlb_backup_provider_required\" \"$P/includes/class-vwlb-future-intelligence.php\" r07-backup-required\nneed \"submit its current version\" \"$P/includes/class-vwlb-future-intelligence.php\" r07-config-cas\nneed \"min(6*HOUR_IN_SECONDS\" \"$P/includes/class-vwlb-future-adapters.php\" r07-provider-result-bound\n"""
if '# R07 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
