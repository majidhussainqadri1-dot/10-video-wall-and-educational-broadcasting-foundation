# File10 V12.3 GOV-001 — Source-side closure, ruleset admin still OPEN

**Date:** 2026-11-14. **Frozen finding:** `reviews/F10-V12-3-GOV-001-STABLE-CI-ROOT-CAUSE-FROZEN.md`.

## Change after root-cause freeze

- Kept complete File10 PHP `8.3.36` / `8.4.27` matrix, all existing security/regression, deterministic package and artifact gates.
- Added `file10-stable-source-gate` with permanent GitHub check name **`File10 Source Aggregate Gate`**, `needs: [source-package-qa]`, and an `always()` guarded `MATRIX_RESULT == success` post-matrix check.
- Added source contract `tests/file10-v12-3-stable-check-contract.sh`; it validates the dependency, immutable label and success-only condition.
- No File10 application plugin runtime PHP, DB, WordPress or existing live system was changed.

## Exact source proof

- Source SHA: `80231fce2c2ca6f333c63dc47031430c4775b7fc`.
- File10 Release QA Push run `43616009564`: **success**.
- File10 Release QA Pull Request run `43616016649`: **success**.
- Previous exact-head version `8b21a5367a92f245b8ff0a213ca3b216b2681ce2` produced three Green jobs: `source-package-qa (8.3.36)`, `source-package-qa (8.4.27)`, `File10 Source Aggregate Gate`.
- This proves the aggregate **success path** on source CI only. Actual fail-path is source checked via job `needs` + outcome comparison, not falsely described as a live GitHub failure drill.

## Residual operational risk and required admin action

**Issue #22 remains OPEN**: active ruleset `25451533` still pins old volatile checks `source-package-qa (8.3.35)` and `(8.4.26)` and does not select the modern PR32 branch. A repository administrator must (i) choose the approved release-protection branch/ref pattern, (ii) include the reviewed branch or approved final release branch, (iii) set required check to **`File10 Source Aggregate Gate`**, (iv) maintain non-fast-forward/deletion/review controls, and (v) re-read the effective ruleset and confirm same-source-SHA CI on the protected target. This action is **not** performed in PR32.

**No Staging / Production / Live assertion.** This source-side fix reduces future check-name drift; it does not establish live artifact parity, DB migration health, File26 durable ack or Founder release authorization.
