from pathlib import Path
import re
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'); reg=Path('tests/fresh-40-review-contracts.sh'); t=p.read_text()
replacement=r'''	public static function record_health( $live_id, $sample ) {
		$event=self::live($live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_OPERATE,$event,'future_health_sample'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot record broadcast health.',VWLB_TEXT_DOMAIN),403);
		$source_public_id=VWLB_Helpers::text($sample['source_public_id']??'',64);if($source_public_id){$source=self::public_row('production_sources',$source_public_id);if(!$source||(int)$source['live_event_id']!==(int)$event['id']||'removed'===$source['state'])return VWLB_Helpers::error('vwlb_health_source_invalid',__('Health telemetry source must belong to this live event.',VWLB_TEXT_DOMAIN),422);}
		$peak=null;if(isset($sample['audio_peak_db'])){$candidate=(float)$sample['audio_peak_db'];if(is_finite($candidate))$peak=max(-120,min(12,$candidate));}
		global $wpdb;$row=array('live_event_id'=>(int)$event['id'],'source_public_id'=>$source_public_id,'bitrate_kbps'=>max(0,min(200000,(int)($sample['bitrate_kbps']??0))),'packet_loss_bp'=>max(0,min(10000,(int)($sample['packet_loss_bp']??0))),'dropped_frames'=>max(0,min(1000000000,(int)($sample['dropped_frames']??0))),'jitter_ms'=>max(0,min(60000,(int)($sample['jitter_ms']??0))),'latency_ms'=>max(0,min(300000,(int)($sample['latency_ms']??0))),'audio_peak_db'=>$peak,'state'=>VWLB_Helpers::enum($sample['state']??'unknown',array('healthy','warning','degraded','failed','unknown'),'unknown'),'captured_at'=>VWLB_Helpers::now());
		if(!$wpdb->insert(VWLB_Helpers::table('broadcast_health_samples'),$row))return VWLB_Helpers::error('vwlb_database_error',__('Health sample could not be recorded.',VWLB_TEXT_DOMAIN),500);return $row;
	}
'''
pat=re.compile(r"\tpublic static function record_health\(.*?\n\t}\n",re.S);m=pat.search(t)
if not m: raise SystemExit('R09 record_health missing')
t=t[:m.start()]+replacement+t[m.end():];p.write_text(t)
r=reg.read_text();marker="""# R09 — broadcaster health telemetry is bounded and cannot fabricate cross-event source identity.\nneed \"vwlb_health_source_invalid\" \"$P/includes/class-vwlb-future-intelligence.php\" r09-source-scope\nneed \"is_finite(\$candidate)\" \"$P/includes/class-vwlb-future-intelligence.php\" r09-finite-audio\nneed \"min(1000000000\" \"$P/includes/class-vwlb-future-intelligence.php\" r09-dropped-frame-bound\n"""
if '# R09 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
