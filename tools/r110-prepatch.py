from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php')
t=p.read_text()
old="$asset='video'===$row['object_type']?($object['asset']??array()):VWLB_Repository::find('media_assets',$object['asset_id']??0);$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$url=$derivatives['download']??$derivatives['mp4_high']??$derivatives['mp4']??$derivatives['audio_only']??'';\n\t\tif(!$url)$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);$url=esc_url_raw($url);if(!$url)return VWLB_Helpers::error('vwlb_download_unavailable',__('Download derivative is not ready.',VWLB_TEXT_DOMAIN),503);"
new="$asset='video'===$row['object_type']?($object['asset']??array()):VWLB_Repository::find('media_assets',$object['asset_id']??0);$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$url=$derivatives['download']??$derivatives['mp4_high']??$derivatives['mp4']??$derivatives['audio_only']??'';if(!$url){$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);$url=is_string($url)?$url:'';}\n\t\t$url=esc_url_raw($url);if(!$url)return VWLB_Helpers::error('vwlb_download_unavailable',__('Download derivative is not ready.',VWLB_TEXT_DOMAIN),503);"
if t.count(old)!=1: raise SystemExit(f'prepatch source anchor count={t.count(old)}')
p.write_text(t.replace(old,new,1))
