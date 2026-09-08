#!/usr/bin/env python3
from pathlib import Path
import runpy
p=Path(__file__).with_name('r119-apply.py')
s=p.read_text()
old="\"$d['series_id']=$this->series_id($d['series_public_id']??'');if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\",\n\"$d['series_id']=$this->series_id($d['series_public_id']??'');if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\","
new="\"$d['series_id']=$this->series_id($d['series_public_id']);if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\",\n\"$d['series_id']=$this->series_id($d['series_public_id']);if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\","
if old not in s:
    raise SystemExit('R119 applicator hotfix target not found')
p.write_text(s.replace(old,new,1))
runpy.run_path(str(p),run_name='__main__')

# F7 follow-through: upload_session() now returns WP_Error on DB failure, so all
# resumable mutation callers must propagate that error before array access/auth.
ext=Path(__file__).resolve().parents[1]/'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
t=ext.read_text()
old_call="$session = self::upload_session( $public_id );\n\t\t$auth = self::authorize_upload_session( $session, $token );"
new_call="$session = self::upload_session( $public_id );\n\t\tif ( is_wp_error( $session ) ) return $session;\n\t\t$auth = self::authorize_upload_session( $session, $token );"
count=t.count(old_call)
if count != 2:
    raise SystemExit(f'F7-upload-session-callers: expected 2 matches, found {count}')
t=t.replace(old_call,new_call)
ext.write_text(t)
print('R119 upload-session caller propagation applied')

# Historical R113 contract reconciliation: R119 moves the authoritative podcast
# episode read into VWLB_DB::read_row(), so callers now propagate WP_Error directly
# instead of inspecting $wpdb->last_error and recreating the same message. Preserve
# the semantic fail-closed requirement by asserting the new primitive/caller path.
# Use a single-quoted shell pattern so set -u does not expand the literal PHP $ep.
contract=Path(__file__).resolve().parents[1]/'tests/file10-r101-r120-contracts.sh'
c=contract.read_text()
old_contract='grep -F "Podcast episode state could not be verified safely" "$P/includes/class-vwlb-podcasts.php" >/dev/null'
new_contract='grep -F "r119_podcast_episode" "$P/includes/class-vwlb-podcasts.php" >/dev/null\ngrep -F \'if(is_wp_error($ep))return $ep\' "$P/includes/class-vwlb-podcasts.php" >/dev/null'
if old_contract not in c:
    raise SystemExit('R119 historical podcast contract target not found')
contract.write_text(c.replace(old_contract,new_contract,1))
print('R119 historical podcast contract reconciled')
