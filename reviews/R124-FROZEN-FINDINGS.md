# File 10 — R124 Frozen Findings

Review mode: read-only until this ledger was frozen.
Baseline: R123 exact-head Green at 722073a28dd1ea9d46c46d92b0c42fd8f9af817e (Release QA run 34296879056).
Live-First rule: exact deployed source is unavailable/unverified; repository diagnosis remains provisional and GitHub is not treated as live.

## Proven defects

### R124-F1 — Mutable third-party checkout action remains in write-privileged correction workflows
The authoritative release workflow was pinned in R123, but historical correction workflows that retain `permissions: contents: write` still invoke `actions/checkout@v4`. A future rerun of the same repository commit can therefore execute different upstream checkout code before scripts that commit and push repository mutations.

Affected reviewed workflows:
- `.github/workflows/r110-correction.yml`
- `.github/workflows/r111-correction.yml`
- `.github/workflows/r112-correction.yml`
- `.github/workflows/r113-correction.yml`
- `.github/workflows/r115-correction.yml`
- `.github/workflows/r116-correction.yml`
- `.github/workflows/r119-correction.yml`

Correction requirement: replace mutable checkout tags with the same verified immutable checkout commit already adopted by R123: `actions/checkout@11d5960a326750d5838078e36cf38b85af677262`.

### R124-F2 — R112 workflow identity/trigger mismatch can invoke R114 mutation logic from the wrong trigger
`.github/workflows/r112-correction.yml` is named on disk as R112, but declares `name: File 10 R114 Correction`, watches `tools/.r112-correction-trigger`, and runs `python3 tools/r114-finalize.py`, then commits an R114 correction batch. Because the workflow has `contents: write`, touching the R112 trigger can invoke R114 mutation logic.

Correction requirement: reconcile trigger identity with the executed R114 correction logic so an R112 trigger cannot launch R114 writes; preserve historical intent without altering runtime/product code.

## Freeze rule
No correction was applied before this file was committed. All R124 corrections must be applied only after this frozen ledger exists, then followed by full regression/retest and exact-head Release QA before R125 begins.
