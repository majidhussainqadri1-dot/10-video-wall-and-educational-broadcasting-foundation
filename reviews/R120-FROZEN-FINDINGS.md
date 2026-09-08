# File 10 R120 — Frozen Findings

Baseline exact reviewed HEAD: `c2d48ed9bbd5cbc29f9fe186340d01acf4e857ce`

Review discipline: R120 was completed as a read-only review before this findings ledger was written. No product, test, workflow, manifest, status, SBOM or review-ledger patch was made during the review phase.

## Frozen proven findings

1. **STATUS.md sequential-cycle state is stale.** It still says R118 requires exact-head Green before R119 even though R118 and R119 have subsequently completed and R119 exact-head Release QA run `34259864776` is Green at `c2d48ed9bbd5cbc29f9fe186340d01acf4e857ce`.
2. **MANIFEST.md sequential-cycle boundary is stale.** It still describes R101–R117 as QA-closed and R118 as blocking R119, so its repository release-evidence boundary no longer matches the verified repository state.
3. **SBOM-1.2.25-rc1.json provenance is stale.** Its serial/review boundary still identifies an R118 correction candidate, its baseline points to the R117 Green head, and it states R119 is blocked, although R119 is already QA-closed.
4. **docs/FILE-10-R101-R120-REVIEW-2026-09-06.md is incomplete as the named R101–R120 cycle ledger.** The file currently ends with R105 material and therefore omits the completed R106–R119 lineage and the present R120 closure round.

## Correction gate

All four findings must be corrected together after this freeze. No new numbered round may begin until the corrected repository state has completed full regression/retest and exact-head File 10 Release QA Green.

## Live-First rule

Exact deployed source is unverified. Repository diagnosis and QA are provisional with respect to live deployment. GitHub source/package/CI evidence is not live-deployed or operational evidence.
