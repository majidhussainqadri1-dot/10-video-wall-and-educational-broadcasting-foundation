from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
FI=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
REST=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-rest.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=FI.read_text()
marker="\t/** F10-FUT-016/017/018/021/024 — reviewable timed annotations and knowledge links. */\n"
method="""\t/** F10-FUT-012..015 — public-safe delivery contract for reviewed and published auxiliary tracks. */\n\tpublic static function published_tracks( $object_type, $object_id ) {\n\t\t$object_type = VWLB_Helpers::enum( $object_type, array( 'video', 'live' ), '' );\n\t\t$object = 'video' === $object_type ? self::video( $object_id ) : ( 'live' === $object_type ? self::live( $object_id ) : null );\n\t\tif ( ! $object || ! VWLB_Security::can_view( $object ) ) return array();\n\t\tglobal $wpdb;\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,track_type,language,source,file_ref,provider_ref,version FROM ' . VWLB_Helpers::table('media_tracks') . ' WHERE object_type=%s AND object_id=%d AND status=%s ORDER BY track_type ASC, language ASC, id ASC', $object_type, (int) $object['id'], 'published' ), ARRAY_A );\n\t\t$out = array();\n\t\tforeach ( $rows as $row ) {\n\t\t\t$resolved = apply_filters( 'vwlb_public_media_track_ref', (string) $row['file_ref'], $row, $object );\n\t\t\t$src = esc_url_raw( is_string( $resolved ) ? $resolved : '' );\n\t\t\t$out[] = array(\n\t\t\t\t'public_id' => $row['public_id'],\n\t\t\t\t'track_type' => $row['track_type'],\n\t\t\t\t'language' => $row['language'],\n\t\t\t\t'source' => $row['source'],\n\t\t\t\t'src' => $src,\n\t\t\t\t'available' => (bool) $src,\n\t\t\t\t'version' => (int) $row['version'],\n\t\t\t);\n\t\t}\n\t\treturn $out;\n\t}\n\n"""
if 'public static function published_tracks' not in s:
    if marker not in s: raise SystemExit('R08 track insertion marker missing')
    s=s.replace(marker,method+marker,1)
FI.write_text(s)
r=REST.read_text()
old="public function playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$payload['chapters']=VWLB_Extensions::chapters('video',$payload['video']['id']??0);$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=$this->response($payload);"
new="public function playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$payload['chapters']=VWLB_Extensions::chapters('video',$payload['video']['id']??0);$payload['media_tracks']=VWLB_Future_Intelligence::published_tracks('video',$payload['video']['id']??0);$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=$this->response($payload);"
if new not in r:
    if old not in r: raise SystemExit('R08 playback anchor missing')
    r=r.replace(old,new,1)
old2="public function get_live(WP_REST_Request $r){$state=VWLB_Live::state($r['id']);if(!is_wp_error($state)){$event=VWLB_Repository::find('live_events',$r['id']);$state['experience']=VWLB_Extensions::live_extras($event);}return $this->response($state);}"
new2="public function get_live(WP_REST_Request $r){$state=VWLB_Live::state($r['id']);if(!is_wp_error($state)){$event=VWLB_Repository::find('live_events',$r['id']);$state['experience']=VWLB_Extensions::live_extras($event);$state['media_tracks']=$event?VWLB_Future_Intelligence::published_tracks('live',$event['id']):array();}return $this->response($state);}"
if new2 not in r:
    if old2 not in r: raise SystemExit('R08 live anchor missing')
    r=r.replace(old2,new2,1)
REST.write_text(r)
ts=TEST.read_text()
checks='''\n# R08 — reviewed/published Future tracks have a public-safe viewer delivery contract.\nneed "public static function published_tracks" "$P/includes/class-vwlb-future-intelligence.php" r08-published-track-contract\nneed "AND status=%s" "$P/includes/class-vwlb-future-intelligence.php" r08-only-published\nneed "vwlb_public_media_track_ref" "$P/includes/class-vwlb-future-intelligence.php" r08-provider-resolution\nneed "media_tracks" "$P/includes/class-vwlb-rest.php" r08-rest-delivery\n'''
if 'r08-published-track-contract' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R08 — DEFECT FIXED\nFuture translation, dubbing, audio-description and sign-language records could be created, reviewed and published, but neither the recorded-video playback response nor the live viewer state exposed any approved track delivery contract. Published tracks therefore had no canonical viewer handoff. File 10 now exposes only `published` auxiliary tracks through a minimized DTO, never leaks provider references/metadata, permits a provider adapter to resolve a public/signed track reference, and adds the safe track set to recorded playback and live state responses.\n\n'''
if '## R08 ' not in ls: LEDGER.write_text(ls+entry)
print('R08 correction prepared')
