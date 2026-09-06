#!/usr/bin/env python3
from pathlib import Path
import json, re

ROOT = Path(__file__).resolve().parents[1]
OLD = '1.2.13-rc1'
NEW = '1.2.14-rc1'

def p(rel): return ROOT / rel

def read(rel): return p(rel).read_text()

def write(rel, text): p(rel).write_text(text)

def rep(rel, old, new, count=1):
    text = read(rel)
    found = text.count(old)
    if found != count:
        raise SystemExit(f'{rel}: expected {count} occurrences, found {found}: {old[:120]!r}')
    write(rel, text.replace(old, new, count))

def regex_rep(rel, pattern, replacement, count=1, flags=0):
    text = read(rel)
    new, n = re.subn(pattern, replacement, text, count=count, flags=flags)
    if n != count:
        raise SystemExit(f'{rel}: expected {count} regex replacements, got {n}: {pattern!r}')
    write(rel, new)

# ---------------------------------------------------------------------------
# R103-1: schema reconciliation must prove required columns and indexes, not
# merely table existence, before a schema-version marker can be trusted.
# ---------------------------------------------------------------------------
db = 'video-wall-and-live-broadcasting/includes/class-vwlb-db.php'
old_verify = "\tpublic static function verify_schema_sql($sql){global $wpdb;foreach((array)$sql as $statement){if(!preg_match('/CREATE\\s+TABLE\\s+([^\\s(]+)/i',(string)$statement,$m))continue;$table=trim($m[1],'`');$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));if($found!==$table)return VWLB_Helpers::error('vwlb_schema_incomplete',__('A required File 10 database table is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));}return true;}\n"
new_verify = r'''\tpublic static function verify_schema_sql($sql){
		global $wpdb;
		$split=function($body){$parts=array();$buf='';$depth=0;$len=strlen((string)$body);for($i=0;$i<$len;$i++){$ch=$body[$i];if('('===$ch)$depth++;elseif(')'===$ch&&$depth>0)$depth--;if(','===$ch&&0===$depth){$parts[]=trim($buf);$buf='';continue;}$buf.=$ch;}if(''!==trim($buf))$parts[]=trim($buf);return $parts;};
		$key_columns=function($list){$out=array();foreach(explode(',',(string)$list) as $raw){$column=trim($raw," `\t\n\r\0\x0B");$column=preg_replace('/\\s+(ASC|DESC)$/i','',$column);$column=preg_replace('/\\([0-9]+\\)$/','',$column);$column=trim($column,'` ');if($column)$out[]=$column;}return $out;};
		foreach((array)$sql as $statement){
			$statement=(string)$statement;if(!preg_match('/CREATE\\s+TABLE\\s+([^\\s(]+)/i',$statement,$m))continue;$table=preg_replace('/[^A-Za-z0-9_]/','',trim($m[1],'`'));if(!$table)return VWLB_Helpers::error('vwlb_schema_contract_invalid',__('A File 10 schema contract is invalid.',VWLB_TEXT_DOMAIN),500);
			$wpdb->last_error='';$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_schema_probe_failed',__('File 10 could not verify a required database table.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));if($found!==$table)return VWLB_Helpers::error('vwlb_schema_incomplete',__('A required File 10 database table is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));
			$open=strpos($statement,'(');$close=strrpos($statement,')');if(false===$open||false===$close||$close<=$open)return VWLB_Helpers::error('vwlb_schema_contract_invalid',__('A File 10 schema contract could not be parsed.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));
			$columns=array();$indexes=array();foreach($split(substr($statement,$open+1,$close-$open-1)) as $definition){$definition=trim($definition);if(preg_match('/^PRIMARY\\s+KEY\\s*\\(([^)]+)\\)/i',$definition,$km)){$indexes['PRIMARY']=$key_columns($km[1]);continue;}if(preg_match('/^(?:UNIQUE\\s+|FULLTEXT\\s+)?KEY\\s+`?([A-Za-z0-9_]+)`?\\s*\\(([^)]+)\\)/i',$definition,$km)){$indexes[$km[1]]=$key_columns($km[2]);continue;}if(preg_match('/^`?([A-Za-z0-9_]+)`?\\s+/',$definition,$cm)&&!in_array(strtoupper($cm[1]),array('PRIMARY','UNIQUE','KEY','FULLTEXT','CONSTRAINT','FOREIGN','CHECK'),true))$columns[]=$cm[1];}
			$wpdb->last_error='';$actual_columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);if(''!==(string)$wpdb->last_error||!is_array($actual_columns))return VWLB_Helpers::error('vwlb_schema_columns_unreadable',__('File 10 could not verify required database columns.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));foreach($columns as $column)if(!in_array($column,$actual_columns,true))return VWLB_Helpers::error('vwlb_schema_column_missing',__('A required File 10 database column is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'column'=>$column));
			$wpdb->last_error='';$rows=$wpdb->get_results("SHOW INDEX FROM `{$table}`",ARRAY_A);if(''!==(string)$wpdb->last_error||!is_array($rows))return VWLB_Helpers::error('vwlb_schema_indexes_unreadable',__('File 10 could not verify required database indexes.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));$actual=array();foreach($rows as $row){$name=(string)($row['Key_name']??'');$seq=(int)($row['Seq_in_index']??0);if($name&&$seq>0)$actual[$name][$seq]=(string)($row['Column_name']??'');}foreach($actual as &$cols){ksort($cols);$cols=array_values($cols);}unset($cols);foreach($indexes as $name=>$expected){if(empty($actual[$name]))return VWLB_Helpers::error('vwlb_schema_index_missing',__('A required File 10 database index is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'index'=>$name));if(array_values($expected)!==$actual[$name])return VWLB_Helpers::error('vwlb_schema_index_mismatch',__('A required File 10 database index has the wrong column order.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'index'=>$name));}
		}
		return true;
	}
'''.replace('\\t','\t')
rep(db, old_verify, new_verify, 1)

# ---------------------------------------------------------------------------
# R103-2: migrate the complete legacy corpus in bounded, checkpointed batches.
# Every batch commits before its cursor checkpoint is advanced; a later retry
# resumes after the last proven source row and never writes the completion
# marker until an empty source page proves exhaustion.
# ---------------------------------------------------------------------------
compat = 'video-wall-and-live-broadcasting/includes/class-vwlb-compatibility.php'
new_migrate = r'''\tpublic static function migrate_legacy(){
		if(get_option('vwlb_legacy_migration_complete'))return true;
		global $wpdb;$legacy=$wpdb->prefix.'svw_videos';
		$wpdb->last_error='';$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$legacy));
		if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_legacy_probe_failed',__('Legacy migration source state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
		if($exists===$legacy){
			$cursor=absint(get_option('vwlb_legacy_migration_cursor',0));
			if(0===$cursor){$snapshot=VWLB_DB::snapshot('legacy_options',array('svw_page_map'=>get_option('svw_page_map'),'svw_version'=>get_option('svw_version')));if(is_wp_error($snapshot))return $snapshot;}
			for(;;){
				$result=VWLB_DB::transaction(function()use($wpdb,$legacy,$cursor){
					$wpdb->last_error='';$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $legacy WHERE id>%d ORDER BY id ASC LIMIT 500",$cursor),ARRAY_A);
					if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_legacy_read_failed',__('Legacy videos could not be read safely.',VWLB_TEXT_DOMAIN),500);
					$last=$cursor;foreach((array)$rows as $row){$source_id=absint($row['id']??0);if($source_id<=$last)return VWLB_Helpers::error('vwlb_legacy_cursor_invalid',__('Legacy migration source order could not be verified safely.',VWLB_TEXT_DOMAIN),500);$last=$source_id;
						$wpdb->last_error='';$already=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('videos').' WHERE source_url=%s AND title=%s LIMIT 1',$row['video_url']??'',$row['title']??''));
						if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_legacy_dedupe_read_failed',__('Legacy video deduplication state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if($already)continue;
						$public=VWLB_Helpers::public_id('vid');$saved=$wpdb->insert(VWLB_Helpers::table('videos'),array('public_id'=>$public,'owner_id'=>absint($row['author_id']??0),'title'=>VWLB_Helpers::text($row['title']??__('Legacy video',VWLB_TEXT_DOMAIN)),'slug'=>sanitize_title(($row['title']??'legacy-video').'-'.substr($public,-6)),'description'=>VWLB_Helpers::textarea($row['description']??''),'provider'=>VWLB_Helpers::enum($row['provider']??'local',array('local','youtube','vimeo'),'local'),'source_url'=>esc_url_raw($row['video_url']??''),'visibility'=>'public','status'=>('publish'===($row['status']??''))?'published':'review','published_at'=>!empty($row['published_at'])?$row['published_at']:null,'rights_status'=>'declared','consent_status'=>'not_patient_case','created_at'=>$row['created_at']??VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()));
						if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_legacy_insert_failed',__('A legacy video could not be migrated safely.',VWLB_TEXT_DOMAIN),500);
					}
					return array('last_id'=>$last,'count'=>count((array)$rows));
				});
				if(is_wp_error($result))return $result;if(0===(int)$result['count'])break;$cursor=(int)$result['last_id'];$saved=update_option('vwlb_legacy_migration_cursor',$cursor,false);if(!$saved&&(int)get_option('vwlb_legacy_migration_cursor',0)!==$cursor)return VWLB_Helpers::error('vwlb_legacy_cursor_persist_failed',__('Legacy migration checkpoint could not be recorded durably.',VWLB_TEXT_DOMAIN),500);
			}
		}else{delete_option('vwlb_legacy_migration_cursor');}
		delete_option('vwlb_legacy_migration_cursor');$stamp=VWLB_Helpers::now();$saved=update_option('vwlb_legacy_migration_complete',$stamp,false);if(!$saved&&get_option('vwlb_legacy_migration_complete')!==$stamp)return VWLB_Helpers::error('vwlb_legacy_marker_failed',__('Legacy migration completion could not be recorded durably.',VWLB_TEXT_DOMAIN),500);return true;
	}
'''.replace('\\t','\t')
regex_rep(compat, r"\tpublic static function migrate_legacy\(\)\{.*?\n\t\}\n(?=\tpublic static function legacy_notice)", new_migrate, 1, re.S)

# Purge the resumable legacy migration cursor only during explicit destructive purge.
uninstall='video-wall-and-live-broadcasting/uninstall.php'
rep(uninstall,"'vwlb_legacy_migration_complete'","'vwlb_legacy_migration_complete','vwlb_legacy_migration_cursor'",1)

# ---------------------------------------------------------------------------
# R103-3: opaque IDs must hold on WordPress rewrite/query-var/shortcode public
# entry points too; REST hardening alone is insufficient.
# ---------------------------------------------------------------------------
plugin='video-wall-and-live-broadcasting/includes/class-vwlb-plugin.php'
for old,new in [
("^video/([A-Za-z0-9_-]+)/([^/]+)/?$","^video/([a-z][a-z0-9]*_[a-z0-9]+)/([^/]+)/?$"),
("^live/([A-Za-z0-9_-]+)/?$","^live/([a-z][a-z0-9]*_[a-z0-9]+)/?$"),
("^podcast/([A-Za-z0-9_-]+)/?$","^podcast/([a-z][a-z0-9]*_[a-z0-9]+)/?$"),
]: rep(plugin,old,new,1)
rep(plugin,
"\t\tif($id=get_query_var('vwlb_video_id')){$row=VWLB_Repository::find('videos',$id);",
"\t\tif($id=get_query_var('vwlb_video_id')){if(!VWLB_Helpers::is_public_id((string)$id))return false;$row=VWLB_Repository::find('videos',$id);",1)
rep(plugin,
"\t\tif($id=get_query_var('vwlb_live_id')){$row=VWLB_Repository::find('live_events',$id);",
"\t\tif($id=get_query_var('vwlb_live_id')){if(!VWLB_Helpers::is_public_id((string)$id))return false;$row=VWLB_Repository::find('live_events',$id);",1)
rep(plugin,
"\t\tif($id=get_query_var('vwlb_podcast_id')){$dto=VWLB_Podcasts::public_episode_dto($id);",
"\t\tif($id=get_query_var('vwlb_podcast_id')){if(!VWLB_Helpers::is_public_id((string)$id))return false;$dto=VWLB_Podcasts::public_episode_dto($id);",1)

front='video-wall-and-live-broadcasting/includes/class-vwlb-frontend.php'
rep(front,
"\t\t$this->enqueue();$id=get_query_var('vwlb_video_id');if(!$id&&isset($_GET['video']))$id=sanitize_text_field(wp_unslash($_GET['video']));\n\t\t$payload=$id?VWLB_Videos::playback($id):null;",
"\t\t$this->enqueue();$id=get_query_var('vwlb_video_id');if(!$id&&isset($_GET['video']))$id=sanitize_text_field(wp_unslash($_GET['video']));if($id&&!VWLB_Helpers::is_public_id((string)$id))$id='';\n\t\t$payload=$id?VWLB_Videos::playback($id):null;",1)
rep(front,
"\t\t$this->enqueue();$id=get_query_var('vwlb_live_id');if(!$id&&isset($_GET['live']))$id=sanitize_text_field(wp_unslash($_GET['live']));\n\t\t$event=$id?VWLB_Live::state($id):null;",
"\t\t$this->enqueue();$id=get_query_var('vwlb_live_id');if(!$id&&isset($_GET['live']))$id=sanitize_text_field(wp_unslash($_GET['live']));if($id&&!VWLB_Helpers::is_public_id((string)$id))$id='';\n\t\t$event=$id?VWLB_Live::state($id):null;",1)
rep(front,
"\t\t$this->enqueue();$id=get_query_var('vwlb_podcast_id');$ep=VWLB_Podcasts::public_episode_dto($id);",
"\t\t$this->enqueue();$id=get_query_var('vwlb_podcast_id');if($id&&!VWLB_Helpers::is_public_id((string)$id))$id='';$ep=$id?VWLB_Podcasts::public_episode_dto($id):null;",1)

# ---------------------------------------------------------------------------
# R103-4: public podcast DTO needs its DB handle and itself enforces opaque ID.
# ---------------------------------------------------------------------------
pod='video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'
rep(pod,
"\tpublic static function public_episode_dto($id){\n\t\t$ep=self::episode($id,false);",
"\tpublic static function public_episode_dto($id){\n\t\tglobal $wpdb;if(!VWLB_Helpers::is_public_id((string)$id))return null;$ep=self::episode($id,false);",1)

# ---------------------------------------------------------------------------
# R103-5: public wall template must consume the redacted thumbnail URL, never a
# native attachment ID that browse_videos intentionally removes.
# ---------------------------------------------------------------------------
rep(front,
"<?php if($item['thumbnail_id']):echo wp_get_attachment_image($item['thumbnail_id'],'medium_large',false,array('loading'=>'lazy'));else:?><span aria-hidden=\"true\">▶</span><?php endif;?>",
"<?php if(!empty($item['thumbnail_url'])):?><img src=\"<?php echo esc_url($item['thumbnail_url']);?>\" alt=\"<?php echo esc_attr($item['title']);?>\" loading=\"lazy\"><?php else:?><span aria-hidden=\"true\">▶</span><?php endif;?>",1)

# ---------------------------------------------------------------------------
# R103 regression gate.
# ---------------------------------------------------------------------------
test='tests/file10-r101-r120-contracts.sh'
text=read(test)
marker="\necho 'R101-R120 contracts PASS'\n"
if marker not in text: raise SystemExit('R101-R120 test marker missing')
r103=r'''
# R103 — schema/migration completeness and non-REST public opaque-ID boundary.
grep -F 'SHOW COLUMNS FROM' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'SHOW INDEX FROM' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_schema_column_missing' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_schema_index_missing' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_legacy_migration_cursor' "$P/includes/class-vwlb-compatibility.php" >/dev/null
grep -F 'WHERE id>%d ORDER BY id ASC LIMIT 500' "$P/includes/class-vwlb-compatibility.php" >/dev/null
! grep -F 'ORDER BY id ASC LIMIT 10000' "$P/includes/class-vwlb-compatibility.php" >/dev/null
grep -F '^video/([a-z][a-z0-9]*_[a-z0-9]+)/([^/]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F '^live/([a-z][a-z0-9]*_[a-z0-9]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F '^podcast/([a-z][a-z0-9]*_[a-z0-9]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F "is_public_id((string)\$id)" "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F "is_public_id((string)\$id)" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F 'global $wpdb;if(!VWLB_Helpers::is_public_id' "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "item['thumbnail_url']" "$P/includes/class-vwlb-frontend.php" >/dev/null
! grep -F "item['thumbnail_id']" "$P/includes/class-vwlb-frontend.php" >/dev/null
'''
write(test,text.replace(marker,'\n'+r103+marker,1))

# ---------------------------------------------------------------------------
# Immutable candidate identity: 1.2.14-rc1.
# ---------------------------------------------------------------------------
main='video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'
text=read(main)
if text.count(OLD)!=2: raise SystemExit(f'main version count expected 2, got {text.count(OLD)}')
write(main,text.replace(OLD,NEW))

readme='video-wall-and-live-broadcasting/readme.txt'
text=read(readme)
for old,new in [(f'Stable tag: {OLD}',f'Stable tag: {NEW}'),(f'video-wall-and-live-broadcasting-{OLD}.zip',f'video-wall-and-live-broadcasting-{NEW}.zip')]:
    if old not in text: raise SystemExit(f'readme identity missing: {old}')
    text=text.replace(old,new,1)
changelog=f'''= {NEW} =\n* R103: verify required schema columns/indexes after dbDelta before trusting schema markers.\n* Migrate the complete legacy video corpus in checkpointed 500-row batches without a 10,000-row silent truncation boundary.\n* Enforce opaque public IDs on frontend rewrite/query/shortcode paths; repair podcast public DB projection and Video Wall thumbnail DTO consumption.\n\n'''
needle=f'= {OLD} =\n'
if needle not in text: raise SystemExit('readme changelog insertion point missing')
text=text.replace(needle,changelog+needle,1);write(readme,text)

runall='tests/run-all.sh';rep(runall,"CURRENT_VERSION='1.2.13-rc1'","CURRENT_VERSION='1.2.14-rc1'",1)
workflow='.github/workflows/file10-release.yml';text=read(workflow);c=text.count(OLD)
if c<4: raise SystemExit(f'workflow expected >=4 old version refs, got {c}')
write(workflow,text.replace(OLD,NEW))

# Root README truth.
rootread='README.md';text=read(rootread)
text=text.replace('- Runtime: `1.2.13-rc1`','- Runtime: `1.2.14-rc1`',1)
r102="R102 found that several public route/body boundaries still admitted native numeric identifiers, some caption/podcast responses leaked native IDs, and playback enrichment attempted to use an internal ID after public DTO redaction. The R102 correction requires prefixed opaque public IDs at public boundaries, resolves public foreign references internally, rejects raw/native ID fields, removes the identified DTO leakage and re-resolves the authorized playback object internally before chapter/track enrichment."
if r102 not in text: raise SystemExit('README R102 paragraph missing')
text=text.replace(r102,r102+" Exact-head File 10 Release QA run `34064117765` was green on PHP 8.3/8.4 at `895c2d66a35a7b9430379a8eff8bc65aaf2d340c` before R103 began.\n\nR103 found incomplete schema-proofing (table existence without required column/index proof), a one-page 10,000-row legacy migration ceiling, non-REST numeric-ID public entry paths, a podcast public DTO database-handle failure and a Video Wall thumbnail DTO/template mismatch. The `1.2.14-rc1` correction candidate closes those five frozen findings; exact-head QA is required before R104 begins.",1)
oldhist="`1.2.11-rc1` remains the historical R81–R100 candidate. `1.2.12-rc1` is the historical R101 correction candidate. `1.2.13-rc1` is the current R102 correction candidate and must not be treated as Automated-QA Green until its exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive verification and package/source parity all pass."
newhist="`1.2.11-rc1` remains the historical R81–R100 candidate. `1.2.12-rc1` is the historical R101 correction candidate. `1.2.13-rc1` is the exact-head-green R102 correction candidate. `1.2.14-rc1` is the current R103 correction candidate and must not be treated as Automated-QA Green until its exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive verification and package/source parity all pass."
if oldhist not in text: raise SystemExit('README historical identity paragraph missing')
write(rootread,text.replace(oldhist,newhist,1))

status='STATUS.md';text=read(status)
text=text.replace('# File 10 Status — 1.2.13-rc1','# File 10 Status — 1.2.14-rc1',1)
text=text.replace('- Coded/reviewed candidate: `1.2.13-rc1` on `fix/file10-r101-r120-sequential-2026-09-06` after the R102 correction.','- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, File 10 Release QA run `34064117765`, PHP 8.3/8.4 green before R103 began.\n- R103 frozen findings: incomplete base/extension/Future column/index schema proof; 10,000-row legacy migration truncation; non-REST native-ID public entry paths; podcast public DTO undefined DB handle; and Video Wall thumbnail DTO/template mismatch.\n- R103 correction: strict schema column/index proof, checkpointed complete legacy migration, opaque-ID frontend/query/shortcode guards, podcast public DTO repair and thumbnail URL consumption.\n- Coded/reviewed candidate: `1.2.14-rc1` on `fix/file10-r101-r120-sequential-2026-09-06` after the R103 correction.',1)
text=text.replace('- Automated-QA Green: R101 established; R102 exact-head QA must be established after the correction and is not preclaimed here.','- Automated-QA Green: R101 and R102 established; R103 exact-head QA must be established after the correction and is not preclaimed here.',1)
write(status,text)

manifest='MANIFEST.md';text=read(manifest)
text=text.replace('# File 10 Release Candidate Manifest — 1.2.13-rc1','# File 10 Release Candidate Manifest — 1.2.14-rc1',1)
text=text.replace('- Plugin version: `1.2.13-rc1`','- Plugin version: `1.2.14-rc1`',1)
text=text.replace('- Package target: `packages/video-wall-and-live-broadcasting-1.2.13-rc1.zip`','- Package target: `packages/video-wall-and-live-broadcasting-1.2.14-rc1.zip`',1)
text=text.replace('- SBOM: `SBOM-1.2.13-rc1.json`','- SBOM: `SBOM-1.2.14-rc1.json`',1)
text=text.replace('- Current review boundary: R101–R120 sequential cycle; R101 corrected reload-safe production-studio state/read reachability; R102 corrects the public opaque-ID/IDOR boundary and removes native-ID DTO leakage.','- Current review boundary: R101–R120 sequential cycle; R101 corrected reload-safe production-studio state/read reachability; R102 corrected the public REST opaque-ID/IDOR boundary and native-ID DTO leakage; R103 corrects strict schema proof, complete checkpointed legacy migration and non-REST opaque-ID/public-runtime consistency.',1)
text=text.replace('- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009` — PHP 8.3/8.4 green before R102 began.','- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009` — PHP 8.3/8.4 green before R102 began.\n- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, run `34064117765` — PHP 8.3/8.4 green before R103 began.',1)
write(manifest,text)

sbom={
  'bomFormat':'Sabri-Public-SBOM','specVersion':'1.0',
  'component':{'name':'video-wall-and-live-broadcasting','version':NEW,'type':'wordpress-plugin'},
  'runtime':{'wordpress':'>=7.0','php':'>=8.3'},
  'schemas':{'base':'1.1.0','extension':'1.1.0','future':'1.2.0'},
  'bundledThirdPartyRuntimeLibraries':[],
  'externalServiceAdapters':['local','youtube','vimeo','custom'],
  'notes':'R103 correction candidate: strict schema column/index verification; complete checkpointed legacy migration; opaque non-REST public IDs; podcast DTO and Video Wall thumbnail public-projection repairs. Repository/package QA remains distinct from staging, live deployment and operational acceptance.'
}
write('SBOM-1.2.14-rc1.json',json.dumps(sbom,indent=2)+'\n')

print('R103 correction applied; candidate',NEW)
