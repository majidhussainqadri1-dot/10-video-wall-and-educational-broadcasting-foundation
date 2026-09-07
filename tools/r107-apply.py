from pathlib import Path

# R107-F03: one reusable recursive raw-secret detector.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-helpers.php')
t=p.read_text()
needle="\tpublic static function text( $value, $max = 191 ) { return mb_substr( sanitize_text_field( (string) $value ), 0, $max ); }\n"
insert="""\tpublic static function contains_raw_secret( $value ) {\n\t\tif ( ! is_array( $value ) ) return false;\n\t\t$exact = array( 'secret','password','api_key','access_key','access_token','refresh_token','private_key','secret_key','signing_key','client_secret','webhook_secret','authorization','bearer','credential','credentials','stream_key','token' );\n\t\tforeach ( $value as $key => $child ) {\n\t\t\t$key = sanitize_key( (string) $key );\n\t\t\t$reference = str_ends_with( $key, '_ref' ) || str_ends_with( $key, '_id' );\n\t\t\tif ( ! $reference && ( in_array( $key, $exact, true ) || str_ends_with( $key, '_secret' ) || str_ends_with( $key, '_token' ) || str_ends_with( $key, '_password' ) ) ) return true;\n\t\t\tif ( is_array( $child ) && self::contains_raw_secret( $child ) ) return true;\n\t\t}\n\t\treturn false;\n\t}\n"""
if 'public static function contains_raw_secret' not in t:
    if needle not in t: raise SystemExit('helpers insertion matcher missing')
    t=t.replace(needle,needle+insert,1)
p.write_text(t)

# R107-F03: Future adapters delegate secret detection to the canonical helper.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-adapters.php')
t=p.read_text()
old="$deny=function($v)use(&$deny){if(!is_array($v))return false;foreach($v as $k=>$child){$key=sanitize_key((string)$k);if(!str_ends_with($key,'_ref')&&in_array($key,array('secret','password','api_key','access_token','refresh_token','private_key','token','stream_key'),true))return true;if(is_array($child)&&$deny($child))return true;}return false;};if($deny($safe))return VWLB_Helpers::error('vwlb_processor_secret_forbidden',__('Raw credentials cannot be sent in processor options.',VWLB_TEXT_DOMAIN),422);"
new="if(VWLB_Helpers::contains_raw_secret($safe))return VWLB_Helpers::error('vwlb_processor_secret_forbidden',__('Raw credentials cannot be sent in processor options.',VWLB_TEXT_DOMAIN),422);"
if old not in t: raise SystemExit('future adapters secret matcher missing')
p.write_text(t.replace(old,new,1))

# R107-F01/F03: final sequential overrides use opaque IDs; metadata detector delegates to helper.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-sequential-review-hardening.php')
t=p.read_text()
for old,new in [
    ("'/captions/(?P<id>[A-Za-z0-9_-]+)'","'/captions/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)'"),
    ("'/videos/(?P<id>[A-Za-z0-9_-]+)/annotations'","'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/annotations'"),
    ("'/live-events/(?P<id>[A-Za-z0-9_-]+)/kill'","'/live-events/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/kill'"),
]:
    if old not in t: raise SystemExit('sequential opaque matcher missing: '+old)
    t=t.replace(old,new,1)
start=t.find("\tprivate static function contains_raw_secret( $value ) {")
end=t.find("\n\t}\n\n\t/** R09",start)
if start<0 or end<0: raise SystemExit('sequential secret function matcher missing')
replacement="\tprivate static function contains_raw_secret( $value ) {\n\t\treturn VWLB_Helpers::contains_raw_secret( $value );\n"
t=t[:start]+replacement+t[end:]
p.write_text(t)

# R107-F01: Future Safety final overrides use opaque IDs and re-resolve the authorized video internally.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-safety.php')
t=p.read_text()
t=t.replace("[A-Za-z0-9_-]+","[a-z][a-z0-9]*_[a-z0-9]+")
old="public static function rest_playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$chapters=VWLB_Extensions::chapters('video',$payload['video']['id']??0);if(is_wp_error($chapters))return $chapters;$tracks=self::published_tracks('video',$payload['video']['id']??0);if(is_wp_error($tracks))return $tracks;$payload['chapters']=$chapters;$payload['media_tracks']=$tracks;$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=self::response($payload);if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}"
new="public static function rest_playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$video=VWLB_Repository::find('videos',$r['id']);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Playback enrichment state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$video)return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);$internal=(int)$video['id'];$chapters=VWLB_Extensions::chapters('video',$internal);if(is_wp_error($chapters))return $chapters;$tracks=self::published_tracks('video',$internal);if(is_wp_error($tracks))return $tracks;$payload['chapters']=$chapters;$payload['media_tracks']=$tracks;$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=self::response($payload);if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}"
if old not in t: raise SystemExit('future safety playback matcher missing')
p.write_text(t.replace(old,new,1))

# R107-F03: Future Intelligence uses canonical secret detector at every metadata/config boundary.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
t=p.read_text()
start=t.find("\tprivate static function contains_raw_secret( $value ) {")
end=t.find("\n\t}\n\n\t/** F10-FUT-001",start)
if start<0 or end<0: raise SystemExit('future intelligence secret function matcher missing')
replacement="\tprivate static function contains_raw_secret( $value ) {\n\t\treturn VWLB_Helpers::contains_raw_secret( $value );\n"
t=t[:start]+replacement+t[end:]
p.write_text(t)

# R107-F02: provider-side live creation compensation must never escape as a raw Throwable.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-live.php')
t=p.read_text()
old="\t\tdo_action('vwlb_provider_live_compensation_requested',$provider_id,$provider_result,$event,$reason);\n\t\t$result=apply_filters('vwlb_provider_delete_live_result',null,$provider_id,$provider_result,$event,$reason);\n\t\t$confirmed=true===$result||(is_array($result)&&in_array(sanitize_key((string)($result['status']??'')),array('deleted','cancelled','disabled','removed','not_found'),true));"
new="\t\ttry{do_action('vwlb_provider_live_compensation_requested',$provider_id,$provider_result,$event,$reason);$result=apply_filters('vwlb_provider_delete_live_result',null,$provider_id,$provider_result,$event,$reason);}catch(Throwable $e){do_action('vwlb_operational_failure','live','vwlb_provider_live_compensation_exception',array('provider'=>$provider_id,'provider_event_ref_hash'=>$provider_ref?hash('sha256',$provider_ref):'','exception'=>sanitize_key(get_class($e))));return false;}\n\t\t$confirmed=true===$result||(is_array($result)&&in_array(sanitize_key((string)($result['status']??'')),array('deleted','cancelled','disabled','removed','not_found'),true));"
if old not in t: raise SystemExit('live compensation matcher missing')
p.write_text(t.replace(old,new,1))

# R107-F02: stream-ingest compensation request action belongs inside the same Throwable boundary as confirmation.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-r46-stream-credential-durability.php')
t=p.read_text()
old="\t\tdo_action('vwlb_provider_ingest_compensation_requested',$context);\n\t\ttry{$result=apply_filters('vwlb_provider_revoke_ingest_result',null,$context);}catch(Throwable $e){$result=null;do_action('vwlb_operational_failure','live','vwlb_provider_ingest_compensation_exception',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));}"
new="\t\ttry{do_action('vwlb_provider_ingest_compensation_requested',$context);$result=apply_filters('vwlb_provider_revoke_ingest_result',null,$context);}catch(Throwable $e){$result=null;do_action('vwlb_operational_failure','live','vwlb_provider_ingest_compensation_exception',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider'],'exception'=>sanitize_key(get_class($e))));}"
if old not in t: raise SystemExit('r46 compensation matcher missing')
p.write_text(t.replace(old,new,1))

# R107-F04/F05: fresh DB truth for provider availability plus a separate evidence-grade readiness projection.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-observability.php')
t=p.read_text()
old="\tpublic static function provider_available($provider,$capability){\n\t\tglobal $wpdb;$provider=sanitize_key($provider);$capability=sanitize_key($capability);$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('provider_health').' WHERE provider=%s AND capability=%s',$provider,$capability),ARRAY_A);"
new="\tpublic static function provider_available($provider,$capability){\n\t\tglobal $wpdb;$provider=sanitize_key($provider);$capability=sanitize_key($capability);$wpdb->last_error='';$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('provider_health').' WHERE provider=%s AND capability=%s',$provider,$capability),ARRAY_A);"
if old not in t: raise SystemExit('observability availability matcher missing')
t=t.replace(old,new,1)
anchor="\tpublic static function snapshot(){\n"
method="""\tpublic static function provider_readiness($provider,$capability){\n\t\tglobal $wpdb;$provider=sanitize_key($provider);$capability=sanitize_key($capability);$wpdb->last_error='';$row=$wpdb->get_row($wpdb->prepare('SELECT state,circuit_open_until FROM '.VWLB_Helpers::table('provider_health').' WHERE provider=%s AND capability=%s',$provider,$capability),ARRAY_A);\n\t\tif(''!==(string)$wpdb->last_error){do_action('vwlb_operational_failure','provider_health','vwlb_provider_readiness_read_failed',array('provider'=>$provider,'capability'=>$capability));return 'unverified';}\n\t\tif(!$row)return 'unverified';if('down'===($row['state']??''))return 'unavailable';if(!empty($row['circuit_open_until'])&&strtotime($row['circuit_open_until'].' UTC')>time())return 'unavailable';if('healthy'===($row['state']??''))return 'ready';return 'unverified';\n\t}\n"""
if 'public static function provider_readiness' not in t:
    if anchor not in t: raise SystemExit('observability readiness anchor missing')
    t=t.replace(anchor,method+anchor,1)
p.write_text(t)

# R107-F05: explicit implementation-vs-runtime semantics and bounded provider readiness projection.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php')
t=p.read_text()
old="\tpublic function capabilities(){return $this->response(array('requirements'=>VWLB_Future_Intelligence::REQUIREMENTS,'capabilities'=>VWLB_Future_Intelligence::capabilities(array()),'schema'=>VWLB_Future_Intelligence::SCHEMA));}"
new="""\tprivate function runtime_readiness(){\n\t\t$provider_caps=array();$probe=array('upload','playback','processing','live','recording');\n\t\tforeach(VWLB_Providers::all() as $id=>$provider){$id=sanitize_key((string)$id);try{$caps=(array)$provider->capabilities();}catch(Throwable $e){$caps=array();do_action('vwlb_operational_failure','provider','vwlb_future_capability_probe_exception',array('provider'=>$id,'exception'=>sanitize_key(get_class($e))));}\n\t\t\tforeach($probe as $cap)$provider_caps[$id][$cap]=empty($caps[$cap])?'unavailable':VWLB_Observability::provider_readiness($id,$cap);\n\t\t}\n\t\t$implemented=VWLB_Future_Intelligence::capabilities(array());$features=array_fill_keys($implemented,'unverified');\n\t\ttry{$projected=apply_filters('vwlb_future_runtime_capability_readiness',$features,$provider_caps);}catch(Throwable $e){$projected=$features;do_action('vwlb_operational_failure','provider','vwlb_future_runtime_readiness_exception',array('exception'=>sanitize_key(get_class($e))));}\n\t\t$allowed=array('ready','unavailable','unverified');foreach($features as $name=>$default){$value=is_array($projected)?sanitize_key((string)($projected[$name]??$default)):$default;$features[$name]=in_array($value,$allowed,true)?$value:'unverified';}\n\t\treturn array('overall'=>'unverified','features'=>$features,'providers'=>$provider_caps,'proof_boundary'=>'ready requires current provider/runtime health evidence; implementation presence alone is not readiness');\n\t}\n\tpublic function capabilities(){return $this->response(array('requirements'=>VWLB_Future_Intelligence::REQUIREMENTS,'capabilities'=>VWLB_Future_Intelligence::capabilities(array()),'schema'=>VWLB_Future_Intelligence::SCHEMA,'capability_semantics'=>'implementation_presence_not_runtime_readiness','runtime_readiness'=>$this->runtime_readiness()));}"""
if old not in t: raise SystemExit('future rest capabilities matcher missing')
p.write_text(t.replace(old,new,1))

# R107 regression contracts.
p=Path('tests/file10-r101-r120-contracts.sh')
t=p.read_text(); marker="echo 'R101-R120 contracts PASS'"
block=r'''# R107 — provider/webhook/secrets/readiness boundaries.
grep -F "public static function contains_raw_secret" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "client_secret" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "str_ends_with( $key, '_secret' )" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "VWLB_Helpers::contains_raw_secret($safe)" "$P/includes/class-vwlb-future-adapters.php" >/dev/null
grep -F "return VWLB_Helpers::contains_raw_secret( $value );" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "return VWLB_Helpers::contains_raw_secret( $value );" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null
! grep -F "[A-Za-z0-9_-]+" "$P/includes/class-vwlb-future-safety.php" >/dev/null
! grep -E "'/((captions|videos|live-events)/[^']*)\[A-Za-z0-9_-\]\+" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null
grep -F "Playback enrichment state could not be verified safely." "$P/includes/class-vwlb-future-safety.php" >/dev/null
! grep -F "$payload['video']['id']" "$P/includes/class-vwlb-future-safety.php" >/dev/null
grep -F "vwlb_provider_live_compensation_exception" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "try{do_action('vwlb_provider_ingest_compensation_requested'" "$P/includes/class-vwlb-r46-stream-credential-durability.php" >/dev/null
grep -F "provider_readiness" "$P/includes/class-vwlb-observability.php" >/dev/null
grep -F "capability_semantics" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F "implementation_presence_not_runtime_readiness" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F "runtime_readiness" "$P/includes/class-vwlb-future-rest.php" >/dev/null
'''
if 'R107 — provider/webhook/secrets/readiness boundaries.' not in t:
    if marker not in t: raise SystemExit('R107 contract marker missing')
    t=t.replace(marker,block+'\n'+marker,1)
p.write_text(t)
