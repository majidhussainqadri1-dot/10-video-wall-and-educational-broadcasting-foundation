from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="""\t\tif ( $id ) {\n\t\t\t$current = self::public_row( 'production_sources', $id );\n\t\t\tif ( ! $current || (int)$current['live_event_id'] !== (int)$event['id'] ) return VWLB_Helpers::error( 'vwlb_source_missing', __( 'Production source not found.', VWLB_TEXT_DOMAIN ), 404 );\n\t\t\t$row['version'] = (int)$current['version'] + 1;\n\t\t\t$ok = $wpdb->update( $table, $row, array( 'id'=>$id, 'version'=>(int)$current['version'] ) );\n\t\t\tif ( 1 !== $ok ) return VWLB_Helpers::error( 'vwlb_version_conflict', __( 'Production source changed. Refresh and try again.', VWLB_TEXT_DOMAIN ), 409 );\n\t\t} else {\n"""
new="""\t\tif ( $id ) {\n\t\t\t$current = self::public_row( 'production_sources', $id );\n\t\t\tif ( ! $current || (int)$current['live_event_id'] !== (int)$event['id'] ) return VWLB_Helpers::error( 'vwlb_source_missing', __( 'Production source not found.', VWLB_TEXT_DOMAIN ), 404 );\n\t\t\t$expected_version = absint( $data['version'] ?? 0 );\n\t\t\tif ( ! $expected_version || $expected_version !== (int) $current['version'] ) return VWLB_Helpers::error( 'vwlb_version_conflict', __( 'Production source changed. Refresh and submit its current version.', VWLB_TEXT_DOMAIN ), 409 );\n\t\t\t$row['version'] = $expected_version + 1;\n\t\t\t$ok = $wpdb->update( $table, $row, array( 'id'=>$id, 'version'=>$expected_version ) );\n\t\t\tif ( 1 !== $ok ) return VWLB_Helpers::error( 'vwlb_version_conflict', __( 'Production source changed. Refresh and try again.', VWLB_TEXT_DOMAIN ), 409 );\n\t\t} else {\n"""
if new not in s:
    if old not in s: raise SystemExit('R04 source anchor missing')
    s=s.replace(old,new,1)
old2="if($id){$current=self::public_row('production_scenes',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_scene_missing',__('Scene not found.',VWLB_TEXT_DOMAIN),404);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>(int)$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}"
new2="if($id){$current=self::public_row('production_scenes',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_scene_missing',__('Scene not found.',VWLB_TEXT_DOMAIN),404);$expected_version=absint($data['version']??0);if(!$expected_version||$expected_version!==(int)$current['version'])return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);$row['version']=$expected_version+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>$expected_version));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}"
if new2 not in s:
    if old2 not in s: raise SystemExit('R04 scene anchor missing')
    s=s.replace(old2,new2,1)
F.write_text(s)
checks="""\n# R04 — source/scene edits require the caller's current optimistic version.\nneed "Production source changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r04-source-client-version\nneed "Scene changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r04-scene-client-version\nneed 'version'=>$'expected_version' "$P/includes/class-vwlb-future-intelligence.php" r04-cas-version\n"""
# Build the literal PHP token without a shell-expanding $ in the generated test.
checks=checks.replace("'version'=>$'expected_version'", "'version'=>\\$expected_version")
ts=TEST.read_text()
if 'r04-cas-version' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry="""## R04 — DEFECT FIXED\nMulti-camera production source and scene edit APIs performed a server-side CAS, but they re-read the newest row and applied the caller's stale payload without requiring the caller's expected version. A stale operator screen could therefore overwrite a newer operator change. Existing-row edits now require the caller's current version and use that exact version in the conditional update; missing/stale versions fail with 409 before mutation. The first R04 static assertion accidentally expanded a shell variable under `set -u`; that QA-only defect was corrected within R04 before accepting the product change.\n\n"""
if '## R04 ' not in ls: LEDGER.write_text(ls+entry)
print('R04 correction prepared')
