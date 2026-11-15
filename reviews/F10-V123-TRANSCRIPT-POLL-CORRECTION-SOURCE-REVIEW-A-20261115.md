# File10 V12.3 — source review A: transcript, educational poll, correction publication

**Date:** 2026-11-15. **Scope:** Draft source only. Original plan V12.3 `F10-FUT-019`, `F10-FUT-020`, `F10-FUT-021`, `F10-NFR-001`, `F10-NFR-003`, `F10-NFR-006`. Master Word SHA-256 `c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf`.

## Original first-pass findings, fixes, retests

| Source finding | Fault proven from exact advanced candidate | Candidate correction | Regression status |
|---|---|---|---|
| SRC-TP-001 | Search owner read failure appears as 404; failed transcript SQL appears as normal null items | Typed repository read-failure 503; `VWLB_DB::read_results`; preserve missing/empty | PASS |
| SRC-TP-002 | Index owner read failure appears as 403; reviewed media-track SQL read failure becomes 422 | Typed repository and track 503; reject unknown before INSERT | PASS |
| SRC-TP-003 | Poll root/owner read outage appears as normal missing/404 | Typed canonical poll `read_row` and live owner 503; preserve true missing | PASS |
| SRC-TP-004 | Multi-choice option SQL failure or unknown reference is silently skipped and accepts a partial vote | Read every option with typed `read_var`, refuse any unknown/error before transactional replacement | PASS |
| SRC-TP-005 | Actual registered `VWLB_R77_Poll_Integrity::guard()` poll-create route hides canonical event SQL failure as 403 | Owner query typed 503 before poll insert, preserve true denial | PASS |
| SRC-TP-006 | Creating correction in `reviewed` state falsely emits `VideoTimestampCorrectionPublished` event before publication | Remove false event from create; keep actual `reviewed->published` event | PASS |

## Test-first evidence

- Workflow `43664502199` (both PHP variants) red prior to source correction: `transcript owner SQL outage expecting 503 got vwlb_not_found`.
- Workflow `43664905917` red prior to R77 patch: `poll-creation owner SQL failure expecting 503 got vwlb_forbidden`.
- Workflow `43665122009` red prior to timestamp correction patch: `reviewed-only correction must not publish a correction event`.
- Workflow `43665185372` at source SHA `a68aae1cf98a7afb7c12b92257a3a73f34660e98`: **all 3 QA jobs successful** — PHP 8.3.36, PHP 8.4.27, and `File10 Source Aggregate Gate`.
- `tests/file10-v12-3-transcript-poll-negative.php`: **31** real class, isolated-mock assertions. `tests/file10-v12-3-correction-publication-events.php`: **11** isolated-mock assertions. Both run via `tests/run-all.sh`; existing 64 requirement source-evidence gate remains unchanged.
- Warning in CI that Node.js 20 checkout action is deprecated is an existing nonblocking toolchain warning, not a production WordPress error.

**Count:** 6 newly discovered source behavior defect groups; 6 corrected and source regression checked; 0 verified on Live. This pass does **not** satisfy the first independent File24/Staging release review.

## Mandatory next review

Adversarial fresh pass on generated corrections, provenance, preview, and real post-publish semantics (instead of repeating the same test logic). Inspect if AI/imported correction draft can bypass explicit review, and test against actual class if found. Hold any release on #13/#22/#26.
