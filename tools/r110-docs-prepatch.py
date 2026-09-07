from pathlib import Path

def exact(path, old, new):
    p=Path(path); t=p.read_text()
    if t.count(old)!=1: raise SystemExit(f'{path}: expected one occurrence, got {t.count(old)} for {old!r}')
    p.write_text(t.replace(old,new,1))

# MANIFEST: advance only current release identity; preserve historical R109 identity.
exact('MANIFEST.md','# File 10 Release Candidate Manifest — 1.2.20-rc1','# File 10 Release Candidate Manifest — 1.2.21-rc1')
exact('MANIFEST.md','- Plugin version: `1.2.20-rc1`','- Plugin version: `1.2.21-rc1`')
exact('MANIFEST.md','- Package target: `packages/video-wall-and-live-broadcasting-1.2.20-rc1.zip`','- Package target: `packages/video-wall-and-live-broadcasting-1.2.21-rc1.zip`')
exact('MANIFEST.md','- SBOM: `SBOM-1.2.20-rc1.json`','- SBOM: `SBOM-1.2.21-rc1.json`')
p=Path('MANIFEST.md'); t=p.read_text(); note='''\n- R109 exact-head QA: `779952fe108a3d4b87192c87305a7ff48ea7ec20`, run `34113080172`, PHP 8.3/8.4 Green before R110 review began.\n- R110 frozen findings: canonical public-ID drift on late delivery routes; stale browse rights/consent policy; podcast/video consent-ID collision; fail-open/incomplete late delivery revalidation; non-public/unlisted media-contract and download derivative exposure.\n- R110 correction candidate identity: `1.2.21-rc1`; final exact-head release QA remains required before R110 may be called Green.\n'''
if 'R110 correction candidate identity' not in t: p.write_text(t.rstrip()+note+'\n')

# README current candidate and current R109/R110 truth.
exact('README.md','- Runtime: `1.2.20-rc1`','- Runtime: `1.2.21-rc1`')
p=Path('README.md'); t=p.read_text(); section='''\n\n### R110 correction candidate\nR110 completed its full read-only public-delivery/cache/secure-grant review only after R109 exact-head QA run `34113080172` was Green at `779952fe108a3d4b87192c87305a7ff48ea7ec20`. Five finding groups were frozen in `docs/FILE-10-R110-FROZEN-FINDINGS-2026-09-07.md` before correction began. The correction candidate is `1.2.21-rc1`; it restores the canonical opaque public-ID grammar on late delivery routes, applies current rights/consent policy during public browse, isolates video consent-link history from podcast IDs, makes security-sensitive revalidation fail closed with complete authorization projections, and requires secure grants for all non-public media contracts/downloads including unlisted media. R110 is not Green until exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive, source/package parity and artifact publication pass.\n'''
if '### R110 correction candidate' not in t: p.write_text(t.rstrip()+section+'\n')

# STATUS current candidate and R109-closed/R110-pending state.
exact('STATUS.md','# File 10 Status — 1.2.20-rc1','# File 10 Status — 1.2.21-rc1')
exact('STATUS.md','- Coded/reviewed candidate: `1.2.20-rc1` on `fix/file10-r101-r120-sequential-2026-09-06`.','- Coded/reviewed candidate: `1.2.21-rc1` on `fix/file10-r101-r120-sequential-2026-09-06`.')
exact('STATUS.md','- Automated-QA Green: R101–R108 established. R108 exact-head `762361ae9f1b1c8392e0fbce224a15051432952c`, run `34100057505`, is green on PHP 8.3/8.4 with complete suite/package/parity/artifact gates. R109 correction is coded as `1.2.20-rc1`; its exact-head release QA is pending and R110 remains blocked.','- Automated-QA Green: R101–R109 established. R109 exact-head `779952fe108a3d4b87192c87305a7ff48ea7ec20`, run `34113080172`, is green on PHP 8.3/8.4 with complete suite/package/parity/artifact gates. R110 correction is coded as `1.2.21-rc1`; its final exact-head release QA is pending.')
exact('STATUS.md','- R109 correction: current rights/consent delivery, consent-expiry fairness/history and replay-lineage gates applied as `1.2.20-rc1`; exact-head release QA is pending before R110.','- R109 correction: current rights/consent delivery, consent-expiry fairness/history and replay-lineage gates applied as `1.2.20-rc1`; exact-head release QA passed at `779952fe108a3d4b87192c87305a7ff48ea7ec20`, run `34113080172`, before R110 began.')
p=Path('STATUS.md'); t=p.read_text(); note='''\n- R110 review: completed fully read-only from the exact R109 Green baseline; five finding groups frozen in `docs/FILE-10-R110-FROZEN-FINDINGS-2026-09-07.md` before any R110 product correction.\n- R110 correction: staged as `1.2.21-rc1`; canonical opaque IDs, current-policy browse filtering, video-only consent-link history, fail-closed delivery revalidation, and secure-grant-only non-public delivery have been corrected. R110 remains pending final exact-head release QA and is not yet classified Green.\n'''
if '- R110 review:' not in t: p.write_text(t.rstrip()+note+'\n')
