<?php
/** Bounded job, outbox, reconciliation and cleanup workers. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Jobs {
	public static function process($limit=5){
		global $wpdb;$table=VWLB_Helpers::table('processing_jobs');$worker=VWLB_Helpers::public_id('worker');$now=VWLB_Helpers::now();$stale=gmdate('Y-m-d H:i:s',time()-15*MINUTE_IN_SECONDS);
		$jobs=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE ((status IN ('pending','retry') AND available_at<=%s) OR (status='running' AND locked_at<%s)) ORDER BY priority ASC,id ASC LIMIT %d",$now,$stale,max(1,min(20,(int)$limit))),ARRAY_A);
		foreach($jobs as $job){
			$attempt=(int)$job['attempts']+1;$locked_at=VWLB_Helpers::now();
			$locked=$wpdb->query($wpdb->prepare("UPDATE $table SET status='running',locked_at=%s,locked_by=%s,attempts=%d,updated_at=%s WHERE id=%d AND status=%s AND attempts=%d",$locked_at,$worker,$attempt,$locked_at,$job['id'],$job['status'],$job['attempts']));
			if(1!==$locked)continue;$job['status']='running';$job['locked_at']=$locked_at;$job['locked_by']=$worker;$job['attempts']=$attempt;self::run_job($job);
		}
	}

	private static function run_job($job){
		global $wpdb;$table=VWLB_Helpers::table('processing_jobs');$asset=$job['asset_id']?VWLB_Repository::find('media_assets',$job['asset_id']):array();$result=null;
		if('verify_and_process'===$job['job_type']){
			if(!$asset||!VWLB_Media::verify_magic($asset))$result=VWLB_Helpers::error('vwlb_asset_validation_failed',__('Media validation failed.',VWLB_TEXT_DOMAIN),422);
			else{
				$claimed=$wpdb->update(VWLB_Helpers::table('media_assets'),array('status'=>'transcoding','scan_status'=>'passed','version'=>(int)$asset['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$asset['id'],'version'=>$asset['version']));
				if(1!==$claimed)$result=VWLB_Helpers::error('vwlb_asset_version_conflict',__('Media asset changed while processing was claimed.',VWLB_TEXT_DOMAIN),409);
				else{$asset['status']='transcoding';$asset['scan_status']='passed';$asset['version']=(int)$asset['version']+1;$provider=VWLB_Providers::get($asset['provider']);if(!$provider||!VWLB_Observability::provider_available($asset['provider'],'processing'))$result=VWLB_Helpers::error('vwlb_provider_degraded',__('Media processing provider is temporarily unavailable.',VWLB_TEXT_DOMAIN),503);else{$start=microtime(true);$result=$provider->process_asset($asset,$job);$ms=(int)round((microtime(true)-$start)*1000);VWLB_Observability::record_provider($asset['provider'],'processing',is_wp_error($result)?'degraded':'healthy',is_wp_error($result)?$result->get_error_code():'',$ms);}}
			}
		}elseif('finalize_live_recording'===$job['job_type'])$result=apply_filters('vwlb_finalize_live_recording',VWLB_Helpers::error('vwlb_recording_processor_unavailable',__('Recording finalizer is not configured.',VWLB_TEXT_DOMAIN),503),VWLB_Helpers::json($job['input_json']),$job);
		else $result=apply_filters('vwlb_process_job',VWLB_Helpers::error('vwlb_unknown_job',__('Unknown processing job.',VWLB_TEXT_DOMAIN),422),$job,$asset);
		if(is_wp_error($result)){self::fail_or_retry($job,$result);return;}
		$commit=VWLB_DB::transaction(function()use($wpdb,$table,$job,$asset,$result){
			if($asset){$derivatives=$result['derivatives']??VWLB_Helpers::json($asset['derivatives_json']);$status=$result['status']??'ready';$saved=$wpdb->update(VWLB_Helpers::table('media_assets'),array('status'=>$status,'scan_status'=>'passed','derivatives_json'=>VWLB_Helpers::json_encode($derivatives),'error_code'=>'','error_message'=>'','version'=>(int)$asset['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$asset['id'],'version'=>$asset['version']));if(1!==$saved)return VWLB_Helpers::error('vwlb_asset_finalize_conflict',__('Media asset changed before processing completion could be committed.',VWLB_TEXT_DOMAIN),409);}
			$finished=$wpdb->query($wpdb->prepare("UPDATE $table SET status='complete',output_json=%s,locked_at=NULL,locked_by=NULL,updated_at=%s WHERE id=%d AND status='running' AND attempts=%d AND locked_by=%s",VWLB_Helpers::json_encode($result),VWLB_Helpers::now(),$job['id'],$job['attempts'],$job['locked_by']));if(1!==$finished)return VWLB_Helpers::error('vwlb_job_lease_lost',__('Processing job lease was lost before completion.',VWLB_TEXT_DOMAIN),409);return true;
		});
		if(is_wp_error($commit))return;
		if($asset){$status=$result['status']??'ready';VWLB_Helpers::audit('asset',$asset['id'],'processing_complete',$asset['status'],$status);if('ready'===$status)VWLB_Helpers::outbox('MediaAssetReady','asset',$asset['id'],array('public_id'=>$asset['public_id']));}
	}

	private static function fail_or_retry($job,$error){
		global $wpdb;$attempt=max(1,(int)$job['attempts']);$retry=$attempt<(int)$job['max_attempts'];
		$changed=$wpdb->query($wpdb->prepare("UPDATE ".VWLB_Helpers::table('processing_jobs')." SET status=%s,available_at=%s,locked_at=NULL,locked_by=NULL,error_code=%s,error_message=%s,updated_at=%s WHERE id=%d AND status='running' AND attempts=%d AND locked_by=%s",$retry?'retry':'dead',gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,pow(2,$attempt)*60)),$error->get_error_code(),$error->get_error_message(),VWLB_Helpers::now(),$job['id'],$job['attempts'],$job['locked_by']));
		if(1!==$changed)return;
		if(!$retry&&$job['asset_id']){$asset=VWLB_Repository::find('media_assets',$job['asset_id']);if($asset)$wpdb->update(VWLB_Helpers::table('media_assets'),array('status'=>'failed','error_code'=>$error->get_error_code(),'error_message'=>$error->get_error_message(),'version'=>(int)$asset['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$asset['id'],'version'=>$asset['version']));}
		VWLB_Helpers::audit('job',$job['id'],$retry?'retry':'dead','running',$retry?'retry':'dead',$error->get_error_message(),array('code'=>$error->get_error_code(),'attempt'=>$attempt));
	}

	public static function publish_outbox($limit=20){
		global $wpdb;$table=VWLB_Helpers::table('outbox');$now=VWLB_Helpers::now();$stale=gmdate('Y-m-d H:i:s',time()-15*MINUTE_IN_SECONDS);
		$events=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE ((status IN ('pending','retry') AND available_at<=%s) OR (status='publishing' AND locked_at<%s)) ORDER BY id ASC LIMIT %d",$now,$stale,max(1,min(100,(int)$limit))),ARRAY_A);
		foreach($events as $event){$attempt=(int)$event['attempts']+1;$locked=$wpdb->query($wpdb->prepare("UPDATE $table SET status='publishing',attempts=%d,locked_at=%s,updated_at=%s WHERE id=%d AND status=%s AND attempts=%d",$attempt,$now,$now,$event['id'],$event['status'],$event['attempts']));if(1!==$locked)continue;
			try{do_action('vwlb_domain_event',$event['event_name'],VWLB_Helpers::json($event['payload_json']),$event);$published=$wpdb->query($wpdb->prepare("UPDATE $table SET status='published',published_at=%s,last_error='',updated_at=%s WHERE id=%d AND status='publishing' AND attempts=%d",VWLB_Helpers::now(),VWLB_Helpers::now(),$event['id'],$attempt));if(1!==$published)continue;}
			catch(Throwable $e){$wpdb->query($wpdb->prepare("UPDATE $table SET status=%s,available_at=%s,locked_at=NULL,last_error=%s,updated_at=%s WHERE id=%d AND status='publishing' AND attempts=%d",$attempt>=8?'dead':'retry',gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,pow(2,$attempt)*30)),mb_substr($e->getMessage(),0,1000),VWLB_Helpers::now(),$event['id'],$attempt));}
		}
	}

	public static function reconcile(){
		global $wpdb;$now=VWLB_Helpers::now();$videos=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('videos').' WHERE status=%s AND scheduled_at<=%s LIMIT 100','scheduled',$now),ARRAY_A);
		foreach($videos as $v){$ok=VWLB_Videos::publication_gate($v);if(true===$ok){$published=VWLB_Repository::update_versioned('videos',$v['id'],$v['version'],array('status'=>'published','published_at'=>$now));if(!is_wp_error($published))VWLB_Helpers::outbox('VideoPublished','video',$v['id'],array('scheduled'=>true));}}
		$events=$wpdb->get_results("SELECT * FROM ".VWLB_Helpers::table('live_events')." WHERE status IN ('scheduled','live','interrupted') LIMIT 100",ARRAY_A);
		foreach($events as $event){$provider=VWLB_Providers::get($event['provider']);if(!$provider)continue;$start=microtime(true);$state=$provider->reconcile('live',$event);$ms=(int)round((microtime(true)-$start)*1000);VWLB_Observability::record_provider($event['provider'],'live',is_array($state)?'healthy':'degraded',is_wp_error($state)?$state->get_error_code():'',$ms);if(!is_array($state))continue;
			$fresh=VWLB_Repository::find('live_events',$event['id']);if(!$fresh||$fresh['provider']!==$event['provider']||!in_array($fresh['status'],array('scheduled','live','interrupted'),true))continue;$safe=array_intersect_key($state,array_flip(array('provider_event_ref','status','degraded','region','health_code')));$merged=array_merge(VWLB_Helpers::json($fresh['provider_state_json']),$safe);VWLB_Repository::update_versioned('live_events',$fresh['id'],$fresh['version'],array('provider_state_json'=>VWLB_Helpers::json_encode($merged)));
		}
	}

	public static function cleanup(){
		global $wpdb;$now=VWLB_Helpers::now();
		foreach(array('idempotency'=>'expires_at','rate_limits'=>'window_ends_at','playback_sessions'=>'expires_at','rollback_snapshots'=>'expires_at') as $table=>$column){$wpdb->query($wpdb->prepare('DELETE FROM '.VWLB_Helpers::table($table)." WHERE $column IS NOT NULL AND $column<%s LIMIT 1000",$now));}
		$wpdb->query($wpdb->prepare('UPDATE '.VWLB_Helpers::table('stream_credentials')." SET status='expired' WHERE status='active' AND expires_at IS NOT NULL AND expires_at<%s",$now));
	}
}
