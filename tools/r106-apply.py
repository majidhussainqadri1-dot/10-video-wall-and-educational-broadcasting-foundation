from pathlib import Path

# R106-F01: expiry cleanup coordinates with canonical upload writer lock.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-r76-cleanup-durability.php')
t=p.read_text()
old="if(is_file($path)&&!@unlink($path)){self::signal('vwlb_upload_cleanup_file_delete_failed',array('session_id'=>(int)$row['id']));continue;}"
new="if(is_file($path)){$fp=@fopen($path,'rb');if(!$fp){self::signal('vwlb_upload_cleanup_file_open_failed',array('session_id'=>(int)$row['id']));continue;}if(!flock($fp,LOCK_EX|LOCK_NB)){fclose($fp);self::signal('vwlb_upload_cleanup_file_busy',array('session_id'=>(int)$row['id']));continue;}$deleted=@unlink($path);flock($fp,LOCK_UN);fclose($fp);if(!$deleted&&file_exists($path)){self::signal('vwlb_upload_cleanup_file_delete_failed',array('session_id'=>(int)$row['id']));continue;}}"
if old not in t:
    raise SystemExit('R106 F01 matcher missing')
p.write_text(t.replace(old,new,1))

# R106-F02a: validation callbacks/hash failures fail closed instead of escaping the worker.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php')
t=p.read_text()
old="$sha=hash_file('sha256',$path);if(!empty($asset['checksum'])&&!hash_equals(strtolower((string)$asset['checksum']),$sha))return false;"
new="$sha=hash_file('sha256',$path);if(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/',$sha)){do_action('vwlb_operational_failure','upload','vwlb_asset_checksum_unreadable',array('asset_public_id'=>$asset['public_id']??''));return false;}if(!empty($asset['checksum'])&&!hash_equals(strtolower((string)$asset['checksum']),$sha))return false;"
if old not in t:
    raise SystemExit('R106 F02 hash matcher missing')
t=t.replace(old,new,1)
old="$scan=apply_filters('vwlb_malware_scan_result',null,$path,$asset);\n\t\t\tif(true===$scan)return true;"
new="try{$scan=apply_filters('vwlb_malware_scan_result',null,$path,$asset);}catch(Throwable $e){do_action('vwlb_operational_failure','upload','vwlb_malware_scanner_exception',array('asset_public_id'=>$asset['public_id']??'','exception'=>sanitize_key(get_class($e))));return false;}\n\t\t\tif(true===$scan)return true;"
if old not in t:
    raise SystemExit('R106 F02 scanner matcher missing')
t=t.replace(old,new,1)
old="$external=apply_filters('vwlb_external_media_validation',null,$asset);\n\t\tif(true===$external||is_array($external)&&in_array(($external['status']??''),array('clean','passed','provider_verified'),true))return true;"
new="try{$external=apply_filters('vwlb_external_media_validation',null,$asset);}catch(Throwable $e){do_action('vwlb_operational_failure','upload','vwlb_external_media_validation_exception',array('asset_public_id'=>$asset['public_id']??'','exception'=>sanitize_key(get_class($e))));return false;}\n\t\tif(true===$external||is_array($external)&&in_array(($external['status']??''),array('clean','passed','provider_verified'),true))return true;"
if old not in t:
    raise SystemExit('R106 F02 external matcher missing')
p.write_text(t.replace(old,new,1))

# R106-F02b: contain any technical-validation extension throwable at worker boundary.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-jobs.php')
t=p.read_text()
old="if('verify_and_process'==$job['job_type']){\n\t\t\tif(!$asset||!VWLB_Media::verify_magic($asset))$result=VWLB_Helpers::error('vwlb_asset_validation_failed',__('Media validation failed.',VWLB_TEXT_DOMAIN),422);"
new="if('verify_and_process'==$job['job_type']){\n\t\t\ttry{$valid=$asset?VWLB_Media::verify_magic($asset):false;}catch(Throwable $e){do_action('vwlb_operational_failure','upload','vwlb_asset_validation_exception',array('asset_public_id'=>$asset['public_id']??'','exception'=>sanitize_key(get_class($e))));$result=VWLB_Helpers::error('vwlb_asset_validation_failed',__('Media validation failed safely and will follow the normal retry policy.',VWLB_TEXT_DOMAIN),503,array('exception'=>sanitize_key(get_class($e))));$valid=false;}\n\t\t\tif(null===$result&&!$valid)$result=VWLB_Helpers::error('vwlb_asset_validation_failed',__('Media validation failed.',VWLB_TEXT_DOMAIN),422);"
if old not in t:
    raise SystemExit('R106 F02 jobs matcher missing')
p.write_text(t.replace(old,new,1))

# R106-F03: late completion override must use canonical opaque public-id grammar.
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-sequential-review-hardening.php')
t=p.read_text()
old="'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete',"
new="'/media/resumable/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/complete',"
if old not in t:
    raise SystemExit('R106 F03 route matcher missing')
p.write_text(t.replace(old,new,1))

# R106 regression gate.
p=Path('tests/file10-r101-r120-contracts.sh')
t=p.read_text()
marker="echo 'R101-R120 contracts PASS'"
block='''# R106 — upload/scan/transcode safety.\ngrep -F "LOCK_EX|LOCK_NB" "$P/includes/class-vwlb-r76-cleanup-durability.php" >/dev/null\ngrep -F "vwlb_upload_cleanup_file_busy" "$P/includes/class-vwlb-r76-cleanup-durability.php" >/dev/null\ngrep -F "vwlb_asset_checksum_unreadable" "$P/includes/class-vwlb-extensions.php" >/dev/null\ngrep -F "vwlb_malware_scanner_exception" "$P/includes/class-vwlb-extensions.php" >/dev/null\ngrep -F "vwlb_external_media_validation_exception" "$P/includes/class-vwlb-extensions.php" >/dev/null\ngrep -F "vwlb_asset_validation_exception" "$P/includes/class-vwlb-jobs.php" >/dev/null\ngrep -F "Media validation failed safely and will follow the normal retry policy." "$P/includes/class-vwlb-jobs.php" >/dev/null\ngrep -F "'/media/resumable/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/complete'" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null\n! grep -F "'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete'" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null\n'''
if 'R106 — upload/scan/transcode safety.' not in t:
    if marker not in t:
        raise SystemExit('R106 contract terminal marker missing')
    t=t.replace(marker,block+'\n'+marker,1)
p.write_text(t)
