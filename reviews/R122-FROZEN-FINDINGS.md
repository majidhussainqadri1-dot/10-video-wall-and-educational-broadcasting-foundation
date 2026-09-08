# File 10 — R122 Frozen Findings

## Review discipline

R122 was completed as a read-only review from the R121 exact-head Green baseline `d466a9020d38a49fd9dcee8085da58775cd26601`, Release QA run `34277370167`. No correction was made during the review phase. Both PHP 8.3 and PHP 8.4 completed successfully, including the complete automated suite, R101–R120 sequential regression gate, R118 provenance continuity gate, R119 fail-closed read-integrity gate, canonical package build, checksum/archive verification and source/package parity; the PHP 8.3 job also published the release-candidate artifact.

## Frozen proven defects

1. **Self-referential closure-state defect in STATUS.** `STATUS.md` encodes R121 exact-head QA as pending. After that exact head became Green, the statement became stale. Updating the file after QA would create a new commit/head that itself has not yet passed exact-head QA, reproducing the same defect.
2. **Self-referential closure-state defect in MANIFEST.** `MANIFEST.md` encodes R122 as blocked until R121 QA. The condition is now satisfied, but mutating the manifest to record that result would itself create a new exact head and restart the same closure loop.
3. **SBOM provenance identity is coupled to transient review state.** `SBOM-1.2.25-rc1.json` uses an R121-candidate serial/review boundary. This makes the single current SBOM stale whenever an exact-head QA succeeds. Provenance must distinguish immutable reviewed baseline evidence from mutable workflow outcome, rather than requiring a post-QA source mutation.
4. **Historical provenance regression contract hard-codes transient R121 candidate text.** `tests/file10-r118-contracts.sh` requires the SBOM serial and boundary to remain `file10-r121...` / `R121 ... correction candidate`, guaranteeing a false regression failure as soon as provenance is normalized for later rounds.

## Systemic correction boundary

The correction must remove the circular dependency between repository documentation and exact-head QA. Repository files may record immutable reviewed baselines and the rule for resolving closure, but must not require a post-QA commit merely to turn `pending` into `Green`. Exact-head closure is authoritative only when the GitHub Actions Release QA result is Green for that same commit SHA; subsequent source mutations require their own QA.

This is provenance/QA-contract infrastructure only. No runtime/product-source change is justified by R122.

After correction, full regression/retest and exact-head Release QA must be Green before R123 begins.

## Live-First exact-deployed-state rule

Exact deployed source/code, live DB/schema and migration state remain unverified. GitHub repository, package and CI evidence are not live-deployment or operational evidence; repository-based diagnosis remains provisional with respect to the deployed website.
