<?php
/** Bounded job, outbox, reconciliation and cleanup workers. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Jobs {
	public static function process($limit=5){
		global $wpdb;$table=VWLB_Helpers::table('processing_jobs');$worker=VWLB_Helpers::public_id('worker');$now=VWLB_Helpers::now();$stale=gmdate('Y-m-d H:i:s',time()-15*MINUTE_IN_SECONDS);
		$wpdb->last_error='';$jobs=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE ((status IN ('pending','retry') AND available_at<=%s) OR (status='running' AND locked_at<%s)) ORDER BY priority ASC,id ASC LIMIT %d",$now,$stale,max(1,min(20,(int)$limit))),ARRAY_A);
		if(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','jobs','vwlb_processing_queue_read_failed',array());return;}
		foreach($jobs as $job){
			$attempt=(int)$job['attempts']+1;$locked_at=VWLB_Helpers::now();
			$locked=$wpdb->query($wpdb->prepare("UPDATE $table SET status='running',locked_at=%s,locked_by=%s,attempts=%d,updated_at=%s WHERE id=%d AND status=%s AND attempts=%d",$locked_at,$worker,$attempt,$locked_at,$job['id'],$job['status'],$job['attempts']));
			if(1!==$locked)continue;$job['status']='running';$job['locked_at']=$locked_at;$job['locked_by']=$worker;$job['attempts']=$attempt;self::run_job($job);
		}
	}

	private static function run_job($job){
		global $wpdb;$table=VWLB_Helpers::table('processing_jobs');$asset=$job['asset_id']?VWLB_Repository::find('media_assets',$job['asset_id']):array();$result=null;$input=VWLB_Helpers::json($job['input_json']??'{}');
		if('verify_and_process'===$job['job_type']){
			if(!$asset||!VWLB_Media::verify_magic($asset))$result=VWLB_Helpers::error('vwlb_asset_validation_failed',__('Media validation failed.',VWLB_TEXT_DOMAIN),422);
			else{
				$claimed=$wpdb->update(VWLB_Helpers::table('media_assets'),array('status'=>'transcoding','scan_status'=>'passed','version'=>(int)$asset['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$asset['id'],'version'=>$asset['version']));
				if(1!==$claimed)$result=VWLB_Helpers::error('vwlb_asset_version_conflict',__('Media asset changed while processing was claimed.',VWLB_TEXT_DOMAIN),409);
				else{$asset['status']='transcoding';$asset['scan_status']='passed';$asset['version']=(int)$asset['version']+1;$provider=VWLB_Providers::get($asset['provider']);if(!$provider||!VWLB_Observability::provider_available($asset['provider'],'processing'))$result=VWLB_Helpers::error('vwlb_provider_degraded',__('Media processing provider is temporarily unavailable.',VWLB_TEXT_DOMAIN),503);else{$start=microtime(true);$result=$provider->process_asset($asset,$job);$ms=(int)round((microtime(true)-$start)*1000);VWLB_Observability::record_provider($asset['provider'],'processing',is_wp_error($result)?'degraded':'healthy',is_wp_error($result)?$result->get_error_code():'',$ms);}}
			}
		}elseif('finalize_live_recording'===$job['job_type'])$result=apply_filters('vwlb_finalize_live_recording',VWLB_Helpers::error('vwlb_recording_processor_unavailable',__('Recording finalizer is not configured.',VWLB_TEXT_DOMAIN),503),$input,$job);
		else $result=apply_filters('vwlb_process_job',VWLB_Helpers::error('vwlb_unknown_job',__('Unknown processing job.',VWLB_TEXT_DOMAIN),422),$job,$asset);
		if(is_wp_error($result)){self::fail_or_retry($job,$result);return;}
		$commit=VWLB_DB::transaction(function()use($wpdb,$table,$job,$asset,$result,$input){
			if($asset){$derivatives=$result['derivatives']??VWLB_Helpers::json($asset['derivatives_json']);$status=$result['status']??'ready';$saved=$wpdb->update(VWLB_Helpers::table('media_assets'),array('status'=>$status,'scan_status'=>'passed','derivatives_json'=>VWLB_Helpers::json_encode($derivatives),'error_code'=>'','error_message'=>'','version'=>(int)$asset['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$asset['id'],'version'=>$asset['version']));if(1!==$saved)return VWLB_Helpers::error('vwlb_asset_finalize_conflict',__('Media asset changed before processing completion could be committed.',VWLB_TEXT_DOMAIN),409);}
			if('finalize_live_recording'===$job['job_type']){
				$live_id=absint($input['live_event_id']??0);$live=$live_id?VWLB_Repository::find('live_events',$live_id,true):null;if(!$live)return VWLB_Helpers::error('vwlb_recording_live_missing',__('Canonical live event could not be locked for recording completion.',VWLB_TEXT_DOMAIN),409);if('recording_processing'!==($live['status']??''))return VWLB_Helpers::error('vwlb_recording_state_invalid',__('Recording job no longer owns the canonical recording-processing state.',VWLB_TEXT_DOMAIN),409);
				$proof=VWLB_R73_Recording_Consent_Guard::finalization_proof($result,$input,$job);if(is_wp_error($proof))return $proof;
				$review=VWLB_Repository::update_versioned('live_events',$live['id'],$live['version'],array('status'=>'replay_review'));if(is_wp_error($review))return $review;VWLB_Helpers::audit('live',$live['id'],'recording_finalized','recording_processing','replay_review','Recording finalization and consent proof completed atomically with the job lease.');VWLB_Helpers::outbox('LiveReplayReadyForReview','live',$live['id'],array('public_id'=>$live['public_id']??''));
			}
			$finished=$wpdb->query($wpdb->prepare("UPDATE $table SET status='complete',output_json=%s,locked_at=NULL,locked_by=NULL,updated_at=%s WHERE id=%d AND status='running' AND attempts=%d AND locked_by=%s",VWLB_Helpers::json_encode($result),VWLB_Helpers::now(),$job['id'],$job['attempts'],$job['locked_by']));if(1!==$finished)return VWLB_Helpers::error('vwlb_job_lease_lost',__('Processing job lease was lost before completion.',VWLB_TEXT_DOMAIN),409);return true;
		});
		if(is_wp_error($commit)){self::fail_or_retry($job,$commit);return;}
		if($asset){$status=$result['status']??'ready';VWLB_Helpers::audit('asset',$asset['id'],'processing_complete',$asset['status'],$status);if('ready'===$status)VWLB_Helpers::outbox('MediaAssetReady','asset',$asset['id'],array('public_id'=>$asset['public_id']));}
	}

	private static function fail_recording_job($job,$error,$attempt,$retry){
		global $wpdb;$table=VWLB_Helpers::table('processing_jobs');$input=VWLB_Helpers::json($job['input_json']??'{}');
		$result=VWLB_DB::transaction(function()use($wpdb,$table,$job,$error,$attempt,$retry,$input){
			$changed=$wpdb->query($wpdb->prepare("UPDATE $table SET status=%s,available_at=%s,locked_at=NULL,locked_by=NULL,error_code=%s,error_message=%s,updated_at=%s WHERE id=%d AND status='running' AND attempts=%d AND locked_by=%s",$retry?'retry':'dead',gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,pow(2,$attempt)*60)),$error->get_error_code(),$error->get_error_message(),VWLB_Helpers::now(),$job['id'],$job['attempts'],$job['locked_by']));if(1!==$changed)return VWLB_Helpers::error('vwlb_recording_job_state_conflict',__('Recording job state changed before failure handling completed.',VWLB_TEXT_DOMAIN),409);
			if(!$retry){$live_id=absint($input['live_event_id']??0);$live=$live_id?VWLB_Repository::find('live_events',$live_id,true):null;if($live&&'recording_processing'===($live['status']??'')){$failed=VWLB_Repository::update_versioned('live_events',$live['id'],$live['version'],array('status'=>'failed'));if(is_wp_error($failed))return $failed;VWLB_Helpers::audit('live',$live['id'],'recording_failed','recording_processing','failed',$error->get_error_message(),array('code'=>$error->get_error_code(),'attempt'=>$attempt));VWLB_Helpers::outbox('LiveRecordingFailed','live',$live['id'],array('public_id'=>$live['public_id']??'','code'=>$error->get_error_code()));}elseif(!$live){do_action('vwlb_operational_failure','recording','vwlb_recording_live_missing',array('job_public_id'=>$job['public_id']??''));}}
			VWLB_Helpers::audit('job',$job['id'],$retry?'retry':'dead','running',$retry?'retry':'dead',$error->get_error_message(),array('code'=>$error->get_error_code(),'attempt'=>$attempt));return true;
		});
		if(is_wp_error($result))do_action('vwlb_operational_failure','recording','vwlb_recording_failure_commit_failed',array('job_public_id'=>$job['public_id']??'','code'=>$result->get_error_code()));
	}

	private static function fail_or_retry($job,$error){
		global $wpdb;$attempt=max(1,(int)$job['attempts']);$retry=$attempt<(int)$job['max_attempts'];if('finalize_live_recording'===($job['job_type']??'')){self::fail_recording_job($job,$error,$attempt,$retry);return;}
		$changed=$wpdb->query($wpdb->prepare("UPDATE ".VWLB_Helpers::table('processing_jobs')." SET status=%s,available_at=%s,locked_at=NULL,locked_by=NULL,error_code=%s,error_message=%s,updated_at=%s WHERE id=%d AND status='running' AND attempts=%d AND locked_by=%s",$retry?'retry':'dead',gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,pow(2,$attempt)*60)),$error->get_error_code(),$error->get_error_message(),VWLB_Helpers::now(),$job['id'],$job['attempts'],$job['locked_by']));
		if(1!==$changed)return;
		if(!$retry&&$job['asset_id']){$asset=VWLB_Repository::find('media_assets',$job['asset_id']);if($asset){$asset_changed=$wpdb->update(VWLB_Helpers::table('media_assets'),array('status'=>'failed','error_code'=>$error->get_error_code(),'error_message'=>$error->get_error_message(),'version'=>(int)$asset['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$asset['id'],'version'=>$asset['version']));if(1!==$asset_changed){$fresh=VWLB_Repository::find('media_assets',$asset['id']);if(!$fresh||'failed'!==($fresh['status']??'')){$reset=$wpdb->query($wpdb->prepare("UPDATE ".VWLB_Helpers::table('processing_jobs')." SET status='retry',available_at=%s,error_code=%s,error_message=%s,updated_at=%s WHERE id=%d AND status='dead' AND attempts=%d",gmdate('Y-m-d H:i:s',time()+5*MINUTE_IN_SECONDS),'vwlb_asset_dead_letter_reconcile_failed','Asset state could not be reconciled after dead-letter.',VWLB_Helpers::now(),$job['id'],$job['attempts']));VWLB_Helpers::audit('job',$job['id'],'dead_letter_reconcile_failed','dead',$reset===1?'retry':'dead','Asset failure state could not be persisted; reconciliation is required.');return;}}}}
		VWLB_Helpers::audit('job',$job['id'],$retry?'retry':'dead','running',$retry?'retry':'dead',$error->get_error_message(),array('code'=>$error->get_error_code(),'attempt'=>$attempt));
	}

	public static function publish_outbox($limit=20){
		global $wpdb;$table=VWLB_Helpers::table('outbox');$now=VWLB_Helpers::now();$stale=gmdate('Y-m-d H:i:s',time()-15*MINUTE_IN_SECONDS);
		$wpdb->last_error='';$events=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE ((status IN ('pending','retry') AND available_at<=%s) OR (status='publishing' AND locked_at<%s)) ORDER BY id ASC LIMIT %d",$now,$stale,max(1,min(100,(int)$limit))),ARRAY_A);
		if(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','outbox','vwlb_outbox_queue_read_failed',array());return;}
		foreach($events as $event){$attempt=(int)$event['attempts']+1;$locked=$wpdb->query($wpdb->prepare("UPDATE $table SET status='publishing',attempts=%d,locked_at=%s,updated_at=%s WHERE id=%d AND status=%s AND attempts=%d",$attempt,$now,$now,$event['id'],$event['status'],$event['attempts']));if(1!==$locked)continue;
			try{do_action('vwlb_domain_event',$event['event_name'],VWLB_Helpers::json($event['payload_json']),$event);$published=$wpdb->query($wpdb->prepare("UPDATE $table SET status='published',published_at=%s,last_error='',updated_at=%s WHERE id=%d AND status='publishing' AND attempts=%d",VWLB_Helpers::now(),VWLB_Helpers::now(),$event['id'],$attempt));if(1!==$published)continue;}
			catch(Throwable $e){$wpdb->query($wpdb->prepare("UPDATE $table SET status=%s,available_at=%s,locked_at=NULL,last_error=%s,updated_at=%s WHERE id=%d AND status='publishing' AND attempts=%d",$attempt>=8?'dead':'retry',gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,pow(2,$attempt)*30)),mb_substr($e->getMessage(),0,1000),VWLB_Helpers::now(),$event['id'],$attempt));}
		}
	}

	public static function reconcile(){
		global $wpdb;$now=VWLB_Helpers::now();$wpdb->last_error='';$videos=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('videos').' WHERE status=%s AND scheduled_at<=%s ORDER BY id ASC LIMIT 100','scheduled',$now),ARRAY_A);
		if(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','scheduled_publish','vwlb_scheduled_publish_queue_read_failed',array());$videos=array();}
		foreach((array)$videos as $candidate){
			// R36: scheduled publication, its mandatory audit, and outbox evidence are one transaction; revalidate under row lock.
			$result=VWLB_DB::transaction(function()use($candidate,$now){$current=VWLB_Repository::find('videos',$candidate['id'],true);if(!$current||'scheduled'!==($current['status']??'')||empty($current['scheduled_at'])||strtotime($current['scheduled_at'].' UTC')>time())return array('changed'=>false);$gate=VWLB_Videos::publication_gate($current);if(is_wp_error($gate))return $gate;$published=VWLB_Repository::update_versioned('videos',$current['id'],$current['version'],array('status'=>'published','published_at'=>$now));if(is_wp_error($published))return $published;VWLB_Helpers::audit('video',$current['id'],'scheduled_publish','scheduled','published','Scheduled publication gate revalidated at execution time');VWLB_Helpers::outbox('VideoPublished','video',$current['id'],array('public_id'=>$current['public_id'],'scheduled'=>true));return array('changed'=>true);});
			if(is_wp_error($result))do_action('vwlb_operational_failure','scheduled_publish',$result->get_error_code(),array('video_public_id'=>$candidate['public_id']??''));
		}
		$wpdb->last_error='';$events=$wpdb->get_results("SELECT * FROM ".VWLB_Helpers::table('live_events')." WHERE status IN ('scheduled','live','interrupted') LIMIT 100",ARRAY_A);
		if(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','live_reconcile','vwlb_live_reconcile_queue_read_failed',array());return;}
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
