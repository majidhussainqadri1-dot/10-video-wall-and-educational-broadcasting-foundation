from pathlib import Path
import json

OLD='1.2.22-rc1'
NEW='1.2.23-rc1'

def read(p): return Path(p).read_text()
def write(p,t): Path(p).write_text(t)
def replace_once(p,old,new):
    t=read(p); n=t.count(old)
    if n < 1: raise SystemExit(f'{p}: missing {old!r}')
    write(p,t.replace(old,new,1))

def replace_all(p,old,new):
    t=read(p)
    if old not in t: raise SystemExit(f'{p}: missing {old!r}')
    write(p,t.replace(old,new))

# Current immutable release identity. build-package.sh derives VWLB_VERSION dynamically.
replace_once('tests/run-all.sh',"CURRENT_VERSION='1.2.22-rc1'","CURRENT_VERSION='1.2.23-rc1'")
replace_all('.github/workflows/file10-release.yml',OLD,NEW)
replace_once('video-wall-and-live-broadcasting/readme.txt','Stable tag: 1.2.22-rc1','Stable tag: 1.2.23-rc1')

# Manifest current fields only; preserve historical R111 identity mentions.
p='MANIFEST.md'; t=read(p)
t=t.replace('# File 10 Release Candidate Manifest — 1.2.22-rc1','# File 10 Release Candidate Manifest — 1.2.23-rc1',1)
t=t.replace('- Plugin version: `1.2.22-rc1`','- Plugin version: `1.2.23-rc1`',1)
t=t.replace('- Package target: `packages/video-wall-and-live-broadcasting-1.2.22-rc1.zip`','- Package target: `packages/video-wall-and-live-broadcasting-1.2.23-rc1.zip`',1)
t=t.replace('- SBOM: `SBOM-1.2.22-rc1.json`','- SBOM: `SBOM-1.2.23-rc1.json`',1)
if 'R112 frozen findings:' not in t:
    t += "\n- R111 exact-head QA Green: `4194d1daf1f6b7ef835f11c0448211c6714c464d`, run `34178262832`, PHP 8.3/8.4 complete suite/package/parity Green; artifact `10037991198`, digest `sha256:c8c90f910d94c7fdb4b5ae591f4edecc1ded063be30fe6887d5e588df294b044`.\n- R112 frozen findings: authenticated Creator Studio fail-open reads/nested errors; observability false-zero/empty DB failure semantics; podcast series resolver DB-failure-to-404 collapse; privileged admin operational views rendering DB failure as zero/no records. Correction candidate: `1.2.23-rc1`; exact-head QA is required before R113.\n"
write(p,t)

p='README.md'; t=read(p)
t=t.replace('- Runtime: `1.2.22-rc1`','- Runtime: `1.2.23-rc1`',1)
if '### R112 correction candidate' not in t:
    t += "\n### R112 correction candidate\nR112 completed its full read-only authenticated creator/operational read-integrity review from the exact R111 Green baseline `4194d1daf1f6b7ef835f11c0448211c6714c464d`. Four findings were frozen in `docs/FILE-10-R112-FROZEN-FINDINGS-2026-09-08.md`. The `1.2.23-rc1` correction fails closed on Creator Studio, observability, podcast series resolution and privileged admin operational reads. Exact-head QA must be Green before R113 begins.\n"
write(p,t)

p='STATUS.md'; t=read(p)
t=t.replace('# File 10 Status — 1.2.22-rc1','# File 10 Status — 1.2.23-rc1',1)
t=t.replace('- Coded/reviewed candidate: `1.2.22-rc1`','- Coded/reviewed candidate: `1.2.23-rc1`',1)
if 'R111 exact-head QA:' not in t:
    t += "\n- R111 exact-head QA: `4194d1daf1f6b7ef835f11c0448211c6714c464d`, File 10 Release QA run `34178262832`, PHP 8.3/8.4 Green with complete suite, R101–R120 gate, package/checksum/archive, source/package parity and artifact publication. Artifact ID `10037991198`; digest `sha256:c8c90f910d94c7fdb4b5ae591f4edecc1ded063be30fe6887d5e588df294b044`.\n- R112 review: completed read-only from the R111 Green baseline; four findings frozen in `docs/FILE-10-R112-FROZEN-FINDINGS-2026-09-08.md`. Correction candidate: `1.2.23-rc1`; R113 remains blocked until exact-head QA is Green.\n"
write(p,t)

# R112 static regression gate.
p='tests/file10-r101-r120-contracts.sh'; t=read(p)
block='''\n# R112 — authenticated creator/operational reads must fail closed.\ngrep -F "class-vwlb-r112-operational-read-integrity.php" "$P/video-wall-and-live-broadcasting.php" >/dev/null\ngrep -F "VWLB_R112_Operational_Read_Integrity::register" "$P/video-wall-and-live-broadcasting.php" >/dev/null\ngrep -F "r112_creator_videos" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null\ngrep -F "r112_observability_dead_jobs" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null\ngrep -F "r112_podcast_series_resolver" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null\ngrep -F "r112_admin_preflight_" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null\n'''
if 'R112 — authenticated creator/operational reads' not in t:
    marker="echo 'R101-R120 contracts PASS'"
    if marker not in t: raise SystemExit('R101 gate marker missing')
    t=t.replace(marker,block+marker,1)
write(p,t)

# Fresh SBOM identity based on prior candidate while preserving history file.
src=Path('SBOM-1.2.22-rc1.json')
if not src.exists(): raise SystemExit('prior SBOM missing')
data=json.loads(src.read_text())
data['serialNumber']='urn:uuid:file10-r112-1.2.23-rc1'
data.setdefault('metadata',{}).setdefault('component',{})['version']=NEW
props=data['metadata'].setdefault('properties',[])
props.append({'name':'review_boundary','value':'R112 authenticated operational read-integrity correction candidate; R113 blocked until exact-head Green'})
Path('SBOM-1.2.23-rc1.json').write_text(json.dumps(data,indent=2)+"\n")
print('R112 finalizer completed')
