from pathlib import Path
import json

OLD='1.2.21-rc1'
NEW='1.2.22-rc1'

root=Path('.')

def read(p): return (root/p).read_text()
def write(p,s): (root/p).write_text(s)
def replace_once(p,old,new):
    s=read(p); n=s.count(old)
    if n != 1: raise SystemExit(f'{p}: expected exactly one occurrence of {old!r}, found {n}')
    write(p,s.replace(old,new,1))

def replace_all_if_present(p,old,new):
    s=read(p)
    if old in s: write(p,s.replace(old,new))

# R111 product correction: a late pre-dispatch guard owns the three frozen public-read boundaries.
guard = r'''<?php
/** R111: fail-closed public read/enrichment integrity. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R111_Public_Read_Integrity {
    public static function register(){ add_filter('rest_pre_dispatch',array(__CLASS__,'pre'),8,3); }
    private static function in_namespace($route){ foreach(VWLB_Contracts::namespaces() as $n){ if(str_starts_with($route,'/'.$n.'/'))return $n; } return ''; }
    private static function response($value,$status=200){ if(is_wp_error($value))return $value; $r=rest_ensure_response($value); $r->set_status($status); $r->header('X-Sabri-File','10'); $r->header('X-VWLB-Version',VWLB_VERSION); $r->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE); return $r; }
    public static function pre($result,$server,$request){
        if(null!==$result || !($request instanceof WP_REST_Request) || 'GET'!==strtoupper($request->get_method()))return $result;
        $route=$request->get_route(); $ns=self::in_namespace($route); if(!$ns)return $result; $q=preg_quote($ns,'#');
        if($route==='/'.$ns.'/live-events')return self::browse_live($request);
        if(preg_match('#^/'.$q.'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)$#',$route,$m))return self::video_detail($m['id']);
        if(preg_match('#^/'.$q.'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/chapters$#',$route,$m))return self::video_chapters($m['id']);
        return $result;
    }
    private static function browse_live($request){
        global $wpdb; $limit=max(1,min(24,absint($request['per_page']?:12)));
        $query=$wpdb->prepare("SELECT * FROM ".VWLB_Helpers::table('live_events')." WHERE visibility='public' AND status IN ('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published') AND deleted_at IS NULL ORDER BY scheduled_start DESC,id DESC LIMIT %d",$limit);
        $rows=VWLB_DB::read_results($query,'r111_public_live_browse'); if(is_wp_error($rows))return $rows;
        $items=array(); foreach($rows as $row){$dto=VWLB_Repository::public_live_dto($row);if($dto)$items[]=$dto;} return self::response(array('items'=>$items));
    }
    private static function video_detail($id){
        VWLB_Repository::reset_read_failure(); $bundle=VWLB_Repository::video_bundle($id);
        if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Video state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
        $dto=VWLB_Repository::public_video_dto($bundle); if(!$dto)return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);
        $chapters=VWLB_Extensions::chapters('video',$bundle['id']); if(is_wp_error($chapters))return $chapters; $dto['chapters']=$chapters;
        $response=self::response($dto); if(!is_wp_error($response)&&'public'!==($bundle['visibility']??'private'))$response->header('Cache-Control','private, no-store'); return $response;
    }
    private static function video_chapters($id){
        VWLB_Repository::reset_read_failure(); $video=VWLB_Repository::find('videos',$id);
        if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Video state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
        if(!$video||!VWLB_Security::can_view($video))return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);
        $chapters=VWLB_Extensions::chapters('video',$video['id']); if(is_wp_error($chapters))return $chapters; return self::response(array('items'=>$chapters));
    }
}
'''
write('video-wall-and-live-broadcasting/includes/class-vwlb-r111-public-read-integrity.php',guard)

# Wire the frozen correction after prior guards without editing the reviewed methods in place.
p='video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'
s=read(p)
anchor="'class-vwlb-r109-rights-consent-replay-guard.php','class-vwlb-r3-playback.php'"
if anchor not in s: raise SystemExit('plugin autoload anchor missing')
s=s.replace(anchor,"'class-vwlb-r109-rights-consent-replay-guard.php','class-vwlb-r111-public-read-integrity.php','class-vwlb-r3-playback.php'",1)
anchor2='VWLB_R109_Rights_Consent_Replay_Guard::register();}'
if anchor2 not in s: raise SystemExit('plugin register anchor missing')
s=s.replace(anchor2,'VWLB_R109_Rights_Consent_Replay_Guard::register();VWLB_R111_Public_Read_Integrity::register();}',1)
write(p,s)

# Fresh immutable candidate identity for materially changed deployable source/package.
identity_files=[
 'video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php',
 'video-wall-and-live-broadcasting/readme.txt',
 'README.md','MANIFEST.md','STATUS.md',
 '.github/workflows/file10-release.yml','tools/build-package.sh',
 'tests/run-all.sh','tests/static-contracts.sh','tests/plan-completion-contracts.sh','tests/file10-r101-r120-contracts.sh'
]
for p in identity_files: replace_all_if_present(p,OLD,NEW)

old_sbom=root/'SBOM-1.2.21-rc1.json'; new_sbom=root/'SBOM-1.2.22-rc1.json'
obj=json.loads(old_sbom.read_text());
def bump(v):
    if isinstance(v,str): return v.replace(OLD,NEW)
    if isinstance(v,list): return [bump(x) for x in v]
    if isinstance(v,dict): return {k:bump(x) for k,x in v.items()}
    return v
new_sbom.write_text(json.dumps(bump(obj),indent=2,ensure_ascii=False)+'\n')

# Append current-state evidence without rewriting historical round records.
with (root/'STATUS.md').open('a') as f:
    f.write("\n- R110 exact-head QA: `d7ed00cbaf76093fd1ccadfa4fbcf405ecde2fb3`, File 10 Release QA run `34174866764`, PHP 8.3/8.4 Green with complete suite, R101–R120 gate, package/checksum/archive and source/package parity.\n- R111 review: completed read-only from the R110 Green baseline; three findings frozen in `docs/FILE-10-R111-FROZEN-FINDINGS-2026-09-08.md`. Correction candidate: `1.2.22-rc1`; R112 remains blocked until exact-head QA is Green.\n")
with (root/'MANIFEST.md').open('a') as f:
    f.write("\n- R110 exact-head QA Green baseline for R111: `d7ed00cbaf76093fd1ccadfa4fbcf405ecde2fb3`, run `34174866764`.\n- R111 frozen findings: fail-open public live browse DB read; nested chapter-read failure in video detail; nested chapter-read failure in the dedicated chapters endpoint. Correction candidate: `1.2.22-rc1`; exact-head release QA is required before R112.\n")
with (root/'README.md').open('a') as f:
    f.write("\n### R111 correction candidate\nR111 completed its read-only public REST read-integrity review from the exact R110 Green baseline. Three findings were frozen in `docs/FILE-10-R111-FROZEN-FINDINGS-2026-09-08.md`. The `1.2.22-rc1` correction fails closed on public live-list DB errors and promotes chapter enrichment/read failures to top-level REST errors. Exact-head QA must be Green before R112 begins.\n")

# R111 regression assertions.
test=root/'tests/file10-r101-r120-contracts.sh'
s=test.read_text()
marker="printf '%s\\n' 'R101-R120 contracts PASS'"
checks=r'''# R111 — public read/enrichment failures must never become successful empty/partial payloads.
need "class-vwlb-r111-public-read-integrity.php" "$P/video-wall-and-live-broadcasting.php" r111-autoload
need "VWLB_R111_Public_Read_Integrity::register" "$P/video-wall-and-live-broadcasting.php" r111-register
need "VWLB_DB::read_results(\$query,'r111_public_live_browse')" "$P/includes/class-vwlb-r111-public-read-integrity.php" r111-live-read-wrapper
need "if(is_wp_error(\$chapters))return \$chapters" "$P/includes/class-vwlb-r111-public-read-integrity.php" r111-chapter-error-promotion
need "vwlb_database_read_failed" "$P/includes/class-vwlb-r111-public-read-integrity.php" r111-parent-read-fail-closed
'''
if marker not in s: raise SystemExit('R101-R120 PASS marker missing')
s=s.replace(marker,checks+marker,1); test.write_text(s)
print('R111 correction applicator completed')
