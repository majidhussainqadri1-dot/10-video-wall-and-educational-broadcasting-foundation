from pathlib import Path

OLD='1.2.23-rc1'; NEW='1.2.24-rc1'

def read(p): return Path(p).read_text()
def write(p,t): Path(p).write_text(t)
def once(p,a,b):
    t=read(p); n=t.count(a)
    if n!=1: raise SystemExit(f'{p}: expected one occurrence, found {n}: {a[:100]!r}')
    write(p,t.replace(a,b,1))

# R113-01: fail-closed public-reference read preflight at REST boundary.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r113-mutation-read-integrity.php'
write(p,'''<?php
/** R113: fail-closed mutation/preflight authoritative read integrity. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R113_Mutation_Read_Integrity {
    public static function register(){ add_filter('rest_pre_dispatch',array(__CLASS__,'pre'),6,3); }
    private static function ns($route){foreach(VWLB_Contracts::namespaces() as $n)if(str_starts_with($route,'/'.$n.'/'))return $n;return '';}
    private static function verify_public($entity,$public_id,$context){
        if(!VWLB_Helpers::is_public_id((string)$public_id))return true;
        global $wpdb;$value=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table($entity).' WHERE public_id=%s LIMIT 1',VWLB_Helpers::text($public_id,100)),$context);
        if(is_wp_error($value))return $value;return true;
    }
    public static function pre($result,$server,$request){
        if(null!==$result||!($request instanceof WP_REST_Request)||'POST'!==strtoupper($request->get_method()))return $result;
        $route=$request->get_route();$ns=self::ns($route);if(!$ns)return $result;$d=$request->get_json_params();$d=is_array($d)?$d:array();
        $checks=array();
        if($route==='/'.$ns.'/podcasts/series'&&!empty($d['channel_public_id']))$checks[]=array('channels',$d['channel_public_id'],'r113_channel_public_resolver');
        if($route==='/'.$ns.'/podcasts/episodes'){
            if(!empty($d['asset_public_id']))$checks[]=array('media_assets',$d['asset_public_id'],'r113_asset_public_resolver');
            if(!empty($d['transcript_caption_public_id']))$checks[]=array('captions',$d['transcript_caption_public_id'],'r113_caption_public_resolver');
        }
        if($route==='/'.$ns.'/premieres'&&!empty($d['video_public_id']))$checks[]=array('videos',$d['video_public_id'],'r113_video_public_resolver');
        foreach($checks as $c){$ok=self::verify_public($c[0],$c[1],$c[2]);if(is_wp_error($ok))return $ok;}
        return $result;
    }
}
''')

# R113-02: podcast mutation reads distinguish SQL failure from verified absence.
p='video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'
once(p,"$title=VWLB_Helpers::text($data['title']??'',255);$asset=VWLB_Repository::find('media_assets',$data['asset_id']??0);", "$title=VWLB_Helpers::text($data['title']??'',255);VWLB_Repository::reset_read_failure();$asset=VWLB_Repository::find('media_assets',$data['asset_id']??0);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast asset state could not be verified safely.',VWLB_TEXT_DOMAIN),503);")
once(p,"$ep=self::episode($id,true);if(!$ep)return VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);", "$wpdb->last_error='';$ep=self::episode($id,true);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast episode state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$ep)return VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);")
once(p,"global $wpdb;$series=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE (id=%d OR public_id=%s) AND deleted_at IS NULL LIMIT 1',absint($id),VWLB_Helpers::text($id,64)),ARRAY_A);", "global $wpdb;$series=VWLB_DB::read_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE (id=%d OR public_id=%s) AND deleted_at IS NULL LIMIT 1',absint($id),VWLB_Helpers::text($id,64)),'r113_publish_series');if(is_wp_error($series))return $series;")

# R113-03: canonical live mutation entry reads fail closed.
p='video-wall-and-live-broadcasting/includes/class-vwlb-live.php'
marker="\tpublic static function issue_credential($live_id,$ttl=21600){"
helper="\tprivate static function mutation_event($live_id){VWLB_Repository::reset_read_failure();$event=VWLB_Repository::find('live_events',$live_id);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_live_mutation_read_failed',__('Live event state could not be verified safely.',VWLB_TEXT_DOMAIN),503);return $event;}\n"
once(p,marker,helper+marker)
once(p,"public static function issue_credential($live_id,$ttl=21600){$event=VWLB_Repository::find('live_events',$live_id);if(!$event)","public static function issue_credential($live_id,$ttl=21600){$event=self::mutation_event($live_id);if(is_wp_error($event))return $event;if(!$event)")
once(p,"\t\t$event=VWLB_Repository::find('live_events',$live_id);if(!$event)return VWLB_Helpers::error('vwlb_live_missing'", "\t\t$event=self::mutation_event($live_id);if(is_wp_error($event))return $event;if(!$event)return VWLB_Helpers::error('vwlb_live_missing'")
once(p,"public static function kill($live_id,$expected_version,$reason){$event=VWLB_Repository::find('live_events',$live_id);if(!$event)","public static function kill($live_id,$expected_version,$reason){$event=self::mutation_event($live_id);if(is_wp_error($event))return $event;if(!$event)")

# Autoload/register R113 and advance immutable candidate identity.
p='video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'
once(p,"'class-vwlb-r112-operational-read-integrity.php','class-vwlb-r3-playback.php'","'class-vwlb-r112-operational-read-integrity.php','class-vwlb-r113-mutation-read-integrity.php','class-vwlb-r3-playback.php'")
once(p,"VWLB_R112_Operational_Read_Integrity::register();}","VWLB_R112_Operational_Read_Integrity::register();VWLB_R113_Mutation_Read_Integrity::register();}")

for p in ['video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php','video-wall-and-live-broadcasting/readme.txt','README.md','MANIFEST.md','STATUS.md','tests/run-all.sh','tools/build-package.sh']:
    t=read(p)
    if OLD not in t: raise SystemExit(f'{p}: old version missing')
    write(p,t.replace(OLD,NEW))

# Regression contracts for the frozen findings.
p='tests/file10-r101-r120-contracts.sh'; t=read(p)
block='''\n# R113 mutation read-integrity correction\nneed "class-vwlb-r113-mutation-read-integrity.php" "$P/video-wall-and-live-broadcasting.php" r113-autoload\nneed "VWLB_R113_Mutation_Read_Integrity::register" "$P/video-wall-and-live-broadcasting.php" r113-register\nneed "r113_asset_public_resolver" "$P/includes/class-vwlb-r113-mutation-read-integrity.php" r113-public-resolver\nneed "r113_publish_series" "$P/includes/class-vwlb-podcasts.php" r113-podcast-series-read\nneed "Podcast episode state could not be verified safely" "$P/includes/class-vwlb-podcasts.php" r113-podcast-episode-read\nneed "vwlb_live_mutation_read_failed" "$P/includes/class-vwlb-live.php" r113-live-mutation-read\n'''
if 'r113-live-mutation-read' not in t: write(p,t+block)

# New SBOM from prior candidate, preserving history.
src=Path('SBOM-1.2.23-rc1.json'); dst=Path('SBOM-1.2.24-rc1.json')
text=src.read_text().replace(OLD,NEW)
dst.write_text(text)

for p in ['MANIFEST.md','STATUS.md']:
    t=read(p)
    note='\n- R113: read-only mutation/preflight integrity review completed and three findings frozen in `docs/FILE-10-R113-FROZEN-FINDINGS-2026-09-08.md`; correction candidate `1.2.24-rc1` requires exact-head Green before R114.\n'
    if 'R113:' not in t: write(p,t+note)
print('R113 correction applicator completed')
