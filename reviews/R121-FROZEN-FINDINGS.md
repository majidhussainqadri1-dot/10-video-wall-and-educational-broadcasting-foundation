# File 10 — R121 Frozen Findings

## Review discipline

R121 was completed as a read-only review from the R120 exact-head Green baseline `4094a9a861eae9a47222cc7a28959eaff87c6c76`. No correction was made during the review phase. The R120 Release QA run `34271494386` completed successfully on both PHP 8.3 and PHP 8.4, including the complete automated suite, R101–R120 sequential regression gate, R118 provenance gate, R119 fail-closed read-integrity gate, canonical package build, checksum/archive verification and source/package parity.

## Frozen proven defects

1. **STATUS closure state is stale.** `STATUS.md` still says R120 remains unclosed/pending and that automated QA is Green only through R119, although R120 exact-head QA is now fully Green.
2. **MANIFEST closure state is stale.** `MANIFEST.md` still says R101–R119 are QA-closed and R120 requires exact-head QA before cycle closure, although that gate has passed.
3. **SBOM provenance baseline is stale.** `SBOM-1.2.25-rc1.json` still identifies an R120 correction candidate with R119 (`c2d48ed...`) as `baseline_exact_head`; after R120 exact-head Green, the repository provenance baseline must advance to the exact R120 Green head `4094a9a...` while preserving historical R119 evidence.
4. **Named R101–R120 cycle ledger closure is stale.** `docs/FILE-10-R101-R120-REVIEW-2026-09-06.md` still states that R120 remains open and that no R121 may begin before R120 Green, despite that condition now being satisfied. It also has not recorded the final ten-round batch result: R111–R120 all found defects, yielding 0/10 clean rounds.

## Correction boundary

All four defects must be corrected together only after this findings freeze. The correction is provenance/closure-only; no runtime/product-source change is justified by this round.

After correction, full regression/retest and exact-head Release QA must be Green before R122 begins.

## Live-First exact-deployed-state rule

Exact deployed source/code, live DB/schema and migration state remain unverified. GitHub repository, package and CI evidence are not live-deployment or operational evidence; repository-based diagnosis remains provisional with respect to the deployed website.
