#!/usr/bin/env python3
from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
OLD='1.2.12-rc1'; NEW='1.2.13-rc1'
def repl(path,old,new,minimum=1):
    p=ROOT/path;t=p.read_text();c=t.count(old)
    if c<minimum: raise SystemExit(f'{path}: expected >= {minimum} of {old!r}, got {c}')
    p.write_text(t.replace(old,new))

# Runtime and current-suite immutable identity.
repl('video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php',OLD,NEW,2)
# Stable/install identifiers only; retain the historical 1.2.12 changelog entry.
p=ROOT/'video-wall-and-live-broadcasting/readme.txt'; t=p.read_text()
t=t.replace(f'Stable tag: {OLD}',f'Stable tag: {NEW}',1)
t=t.replace(f'video-wall-and-live-broadcasting-{OLD}.zip',f'video-wall-and-live-broadcasting-{NEW}.zip',1)
marker=f'= {OLD} =\n'
entry=(f'= {NEW} =\n'
       '* R102: enforce opaque public identifiers across File 10 REST paths and foreign references.\n'
       '* Reject raw/native database identifiers at public mutation boundaries and remove caption/podcast numeric-ID leakage.\n'
       '* Re-resolve the authorized playback object internally before loading chapters and media tracks.\n\n')
if marker not in t: raise SystemExit('readme changelog marker missing')
t=t.replace(marker,entry+marker,1);p.write_text(t)
repl('tests/run-all.sh',"CURRENT_VERSION='1.2.12-rc1'","CURRENT_VERSION='1.2.13-rc1'",1)
# tests/static-contracts.sh intentionally derives the current version from the plugin entrypoint.

# Manifest/status truth.
p=ROOT/'MANIFEST.md'; t=p.read_text(); t=t.replace(OLD,NEW)
t=t.replace('- Current review boundary: R101–R120 sequential cycle; R101 corrected the missing reload-safe production-studio state/read path and progressive production-state UI.',
'''- Current review boundary: R101–R120 sequential cycle; R101 corrected reload-safe production-studio state/read reachability; R102 corrects the public opaque-ID/IDOR boundary and removes native-ID DTO leakage.\n- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009` — PHP 8.3/8.4 green before R102 began.''')
p.write_text(t)

p=ROOT/'STATUS.md'; t=p.read_text(); t=t.replace('# File 10 Status — 1.2.12-rc1','# File 10 Status — 1.2.13-rc1',1)
t=t.replace('- Coded/reviewed candidate: `1.2.12-rc1` on `fix/file10-r101-r120-sequential-2026-09-06` after R101 correction.',
'''- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, File 10 Release QA run `34045547009`, PHP 8.3/8.4 green before R102 began.\n- R102 frozen finding: public route/body boundaries still admitted native numeric identifiers in several object and foreign-reference paths, public caption/podcast DTOs leaked native IDs, and playback enrichment used a redacted DTO ID and therefore queried chapters/tracks with object ID zero.\n- R102 correction: require prefixed opaque IDs on public paths, resolve public foreign references internally, reject raw ID fields, redact caption/podcast native IDs, and re-resolve playback internals before enrichment.\n- Coded/reviewed candidate: `1.2.13-rc1` on `fix/file10-r101-r120-sequential-2026-09-06` after the R102 correction.''',1)
t=t.replace('- Automated-QA Green: must be established by the exact-head workflow after the R101 correction; not preclaimed here.',
'- Automated-QA Green: R101 established; R102 exact-head QA must be established after the correction and is not preclaimed here.',1)
p.write_text(t)

sbom={
 'bomFormat':'Sabri-Public-SBOM','specVersion':'1.0',
 'component':{'name':'video-wall-and-live-broadcasting','version':NEW,'type':'wordpress-plugin'},
 'runtime':{'wordpress':'>=7.0','php':'>=8.3'},
 'schemas':{'base':'1.1.0','extension':'1.1.0','future':'1.2.0'},
 'bundledThirdPartyRuntimeLibraries':[],
 'externalServiceAdapters':['local','youtube','vimeo','custom'],
 'notes':'R102 correction candidate: strict opaque public identifiers across REST paths and foreign references; native IDs rejected/redacted; playback enrichment re-resolves the authorized internal object. Repository/package QA remains distinct from staging, live deployment and operational acceptance.'
}
(ROOT/'SBOM-1.2.13-rc1.json').write_text(json.dumps(sbom,indent=2)+"\n")
print('R102 immutable candidate advanced to',NEW)
