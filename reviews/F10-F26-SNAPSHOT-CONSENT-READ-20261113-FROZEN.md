# File10 ↔ File26 source-batch snapshot fail-closed review (2026-11-13)

## Exact immutable starting point
Reviewed combined File10 PR #28 HEAD `0cbb8cf8de9dbb3b6fe4228d5176c41e5ef2efc6` (File26 proposed owner contracts, R109 consent cursor, read-only G01 collector); no Live/Staging plugin/DB access because Issue #13. V12.1 F10-NFR-002/003, F10-FUT-022/024, V12.1-STO-09.

## Proven source defect SNAP-001 (HIGH, owner snapshot read integrity)
`VWLB_File26_Connector::list_batch()` checks only the `$wpdb->last_error` produced by its initial `SELECT *`. For each row it calls `project()` → `eligible()` → `VWLB_Security::can_view()`; modern File10 R109 policy can query the `consent_links` table and record `policy_read_failed()` if that query errors. The current batch **silently filters the row and advances the cursor**, returning `done/next_cursor` normally. A rebuilding File26 consumer may then interpret an incomplete owner snapshot as correct, dropping rows or publishing partial index evidence.

This is not a confirmed Live incident. It is a reproducible source control-flow error. The existing `fetch_object()` read-integrity fix in PR #24 handles direct individual fetch but not batched indexing.

## Minimal fix and tests
- On any File10 R109 rights/consent `policy_read_failed()` after a projected row, stop the batch with typed `WP_Error`, never a successful empty/incomplete snapshot.
- Catch exceptions from row projection and return typed unavailable; no external File26 writes and no cursor advance; maintain legitimate private/unlisted filtering.
- Add an isolated consent read failure regression to `tests/file10-file26-public-projection.php` and preserve private row success behavior.
- Include regression in existing canonical runner; exact-HEAD PHP 8.3/8.4 full source/package QA mandatory.
- Follow with real disposable File26↔File10 WordPress/MariaDB consent table fault test on the **new pinned SHA**; no runtime Search activation.

## Non-bypassable gates
Proposed adapter still **not autoloaded**; `status=proposed`, tombstone/outbox consumer ACK pending (Issue #26), site/DB and plugin parity unverified (Issue #13), repository ruleset gate Issue #22 open. No Production deployment or Live resolution.
