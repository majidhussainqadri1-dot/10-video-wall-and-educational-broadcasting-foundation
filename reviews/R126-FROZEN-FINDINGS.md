# File 10 — R126 Frozen Findings

Review mode: read-only review completed before correction.
Baseline reviewed: `87b01b006ac8163053dec8f0249ef522af615118`.
Live-First rule: exact deployed source was not available; deployed code remains unverified and this repository diagnosis is provisional.

## Frozen findings

### R126-F1 — Canonical Release QA still selects a drifting runner family
`.github/workflows/file10-release.yml` uses `runs-on: ubuntu-latest`. Although third-party Actions were pinned in R123, `ubuntu-latest` is a moving label. A later rerun of the same repository SHA may therefore execute on a different GitHub-hosted runner generation/toolchain.

### R126-F2 — Canonical package verification installs mutable archive tooling at run time
The canonical QA performs `sudo apt-get update && sudo apt-get install -y zip unzip`. That resolves packages from the runner's then-current APT indexes rather than a repository-frozen toolchain. `tools/build-package.sh` directly invokes `zip`, so package construction depends on this mutable external resolution path.

## Correction boundary
After this ledger is frozen, correction may: (1) replace the moving runner selector with an explicit Ubuntu runner release, (2) remove live APT package resolution from the canonical QA and fail closed if required preinstalled tools are absent, (3) record tool versions for execution provenance, and (4) add a regression contract preventing reintroduction of `*-latest` or live APT installation into the canonical File 10 release gate.

No product/runtime source defect was proven in R126 review. No product/runtime source was patched during this review phase.
