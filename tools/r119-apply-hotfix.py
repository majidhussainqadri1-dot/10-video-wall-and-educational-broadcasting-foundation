#!/usr/bin/env python3
from pathlib import Path
import runpy

root = Path(__file__).resolve().parents[1]
p = Path(__file__).with_name('r119-apply.py')
pod = root / 'video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'
ext = root / 'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
contract = root / 'tests/file10-r101-r120-contracts.sh'

# The correction workflow may be rerun after the product correction has already
# been committed. Make the applicator idempotent: apply the frozen product batch
# only when the R119 podcast primitive is not already present.
pod_text = pod.read_text()
if "r119_podcast_episode" not in pod_text:
    s = p.read_text()
    old = "\"$d['series_id']=$this->series_id($d['series_public_id']??'');if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\",\n\"$d['series_id']=$this->series_id($d['series_public_id']??'');if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\","
    new = "\"$d['series_id']=$this->series_id($d['series_public_id']);if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\",\n\"$d['series_id']=$this->series_id($d['series_public_id']);if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\","
    if old not in s and new not in s:
        raise SystemExit('R119 applicator hotfix target not found')
    if old in s:
        p.write_text(s.replace(old, new, 1))
    runpy.run_path(str(p), run_name='__main__')
    print('R119 frozen product correction applied')
else:
    print('R119 frozen product correction already present; product reapply skipped')

# F7 follow-through: upload_session() returns WP_Error on DB failure. Propagate
# it before array access/auth. Accept either pre-fix or already-fixed state.
t = ext.read_text()
old_call = "$session = self::upload_session( $public_id );\n\t\t$auth = self::authorize_upload_session( $session, $token );"
new_call = "$session = self::upload_session( $public_id );\n\t\tif ( is_wp_error( $session ) ) return $session;\n\t\t$auth = self::authorize_upload_session( $session, $token );"
old_count = t.count(old_call)
new_count = t.count(new_call)
if old_count == 2:
    ext.write_text(t.replace(old_call, new_call))
    print('R119 upload-session caller propagation applied')
elif old_count == 0 and new_count >= 2:
    print('R119 upload-session caller propagation already present')
else:
    raise SystemExit(f'F7-upload-session-callers: unexpected state old={old_count} new={new_count}')

# Historical R113 contract reconciliation: R119 moves the authoritative podcast
# episode read into VWLB_DB::read_row(), so preserve the semantic fail-closed
# requirement without pinning the obsolete message-based implementation.
c = contract.read_text()
old_contract = 'grep -F "Podcast episode state could not be verified safely" "$P/includes/class-vwlb-podcasts.php" >/dev/null'
new_contract = 'grep -F "r119_podcast_episode" "$P/includes/class-vwlb-podcasts.php" >/dev/null\ngrep -F \'if(is_wp_error($ep))return $ep\' "$P/includes/class-vwlb-podcasts.php" >/dev/null'
if old_contract in c:
    contract.write_text(c.replace(old_contract, new_contract, 1))
    print('R119 historical podcast contract reconciled')
elif new_contract in c:
    print('R119 historical podcast contract already reconciled')
else:
    raise SystemExit('R119 historical podcast contract target not found')
