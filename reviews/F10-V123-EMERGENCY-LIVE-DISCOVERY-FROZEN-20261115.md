# File10 V12.3 — Emergency-ended Live Wall Discovery, Source Finding Freeze

Date 2026-11-15. Exact inspected parent SHA `b6161fc76360523689988fcfcacdcca56afbe5f3` (Draft PR #36, NOT Live).

## Requirement
F10-FR-012/014, F10-NFR-001/003, F10-FUT-022, V12.3 UX-12 and QA-22. An emergency kill must not leave a public discovery card labeled `Live now`.

## F10-FE-04 — emergency-killed stream still eligible for public Wall (high source finding)

`VWLB_Frontend::wall()` queries `live_events` by `visibility='public'`, `status IN ('scheduled','live')` and `deleted_at IS NULL`, without excluding `kill_switch=1`. The canonical live row has a `kill_switch` column (verified in `VWLB_DB::schema_sql()`), and `VWLB_File26_Connector::eligible()` already denies killed live events. Thus the video wall can show an emergency-ended event as `Live now` until status transition completes.

This is a source-level defect finding; no claim of a real Live incident or current deployed version.

## Minimal mitigation

- Add server-side `kill_switch=0` predicate to the public upcoming/live listing before any HTML is emitted; retain the existing public/status/deleted and result cap filters.
- Prove test-first that an emergency-killed row would otherwise be surfaced; verify an eligible scheduled/live row remains. A literal query-only test must not be described as real provider delivery proof.
- Verify on actual schema, avoid introducing a nonexistent column; no new DB migrations.
- No change to live-event termination state machine or provider kill adapter: those still require real Staging.

## Blockers and boundaries

Issue #13 Live origin HTTP 530, Issue #22 ruleset coverage, Issue #26 durable File26 deletion/consumer ACK/CDN purge remain open. Real provider authorization, staging and live verification unproven. No merge or deploy.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
