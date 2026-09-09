# File 10 — R127 Frozen Findings

## Read-only review boundary

- Baseline reviewed: `4669f3746daa28c26005a2bc4420e9d73df25915`.
- R126 was first verified Green on File 10 Release QA run `34308771351` before R127 began.
- The entire R127 review phase was read-only. No patch was applied before this findings freeze.

## Proven defect

### R127-F1 — PHP interpreter patch level is still mutable in canonical exact-head QA

The canonical workflow pins the setup-php Action by immutable commit SHA, but its matrix requests only `8.3` and `8.4`. That leaves the resolved PHP patch release mutable across reruns of the same repository commit.

The verified R126 run resolved those selectors to PHP `8.3.33` and PHP `8.4.25`. A later rerun of the identical repository SHA can therefore execute a newer `8.3.x` or `8.4.x` interpreter without any repository change, weakening exact-head QA reproducibility.

Impact: source identity remains exact, but interpreter identity is not exact. A Green result for one run is valid evidence for that run, but cannot be represented as bit-for-bit reproducible interpreter evidence for arbitrary later reruns while major/minor selectors float.

## Residual environment note

`runs-on: ubuntu-24.04` bounds the hosted runner generation but does not freeze a specific GitHub-hosted image build. R126 already emits observed runner/toolchain versions. R127 does not falsely claim that the hosted image is immutable; the proven correctable defect in this round is the PHP patch selector.

## Frozen correction set

Only after this freeze:

1. Pin the canonical PHP matrix to the exact patch versions verified by the R126 Green baseline: `8.3.33` and `8.4.25`.
2. Add an explicit runtime assertion that `PHP_VERSION` equals the requested matrix version.
3. Add an R127 regression contract that rejects major/minor-only PHP selectors and requires the exact patch pins plus runtime assertion.
4. Wire the R127 contract into canonical File 10 Release QA.
5. Run full regression/retest and exact-head QA; do not begin R128 unless the final R127 correction head is Green.

## Live-First rule

Exact deployed source/code is unavailable and therefore unverified. Repository, CI, package, and artifact evidence remain provisional with respect to the live deployed state and are not treated as proof of deployment or operation.
