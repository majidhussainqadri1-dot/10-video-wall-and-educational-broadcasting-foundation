# File 10 -> File 26: current-source event/revocation mapping review (FROZEN BEFORE CODE) — 2026-11-13

## Immutable scope
- Reviewed advanced File10 PR #21 exact SHA `e668f39a6dc2549e49f98274f6416fe76b75220e`.
- Governing Master V12.1: `F10-FR-018`, `F10-NFR-001/002/003`, `F10-FUT-022/024`, `V12.1-STO-09/10`; separate File26 native event/tombstone gate.
- Deployed WordPress code, DB, wp_options, migration and active runtime unverified (WordPress Jetpack disconnected; WPVibe site connection unavailable). No allegation of Live bug.

## Source findings and acceptance gap
**REC-001 (High; missing event-to-tombstone owner contract):** `VWLB_File26_Connector` exposes `list_batch`/`fetch_object`/`can_view`, but no deterministic translation of native recorded/live outbox events to a privacy-safe, owner-versioned index/tombstone proposal. Current manifest explicitly says `owner_outbox_tombstone_gate_pending`. File26 cannot be promoted without it. The immediate task is to implement a **dormant proposal method only**, not automatic consumer delivery.

**REC-002 (High; source integrity risk if guessed):** File10 `VWLB_Helpers::outbox()` persists internal numeric `object_id`, which is not a public identifier. The public `payload.public_id` is the only safe source reference; missing rows cannot provide authoritative `object_version` for a tombstone. Must **reject** numeric id fallback, not fabricate a tombstone or treat row deletion as resolved.

**REC-003 (High; false-deletion risk on operational read failure):** File10 repository and rights/consent guards fail closed on database failure; a consumer must distinguish **unverifiable source** from **verified currently restricted**. Never turn a transient read/consent error into a durable deletion tombstone.

## Corrective scope
- Add a pure, non-registered `propose_outbox_reconciliation` method on File10's currently dormant File26 connector.
- Bound supported video/live event-name-to-entity mapping. Read canonical **current** version/state and choose an upsert or tombstone proposal only when authoritative current row is verified.
- Strict public IDs and public event IDs; no internal numeric IDs, no stream keys/secrets, no raw patient material, no direct File26 writes.
- Reject missing/deleted source when tombstone version cannot be verified, and rights/consent read uncertainty.
- Independent PHP fault injection tests in canonical `tests/run-all.sh`; exact-head PHP 8.3/8.4 QA must pass.
- Preserve `status=proposed`, `event_contract=...pending-file26-reconciliation`, `owner_outbox_tombstone_gate_pending` and no runtime autoload. True event delivery, idempotent File26 consumption, rollback and live re-test remain unverified gates.

## Review ordering
This immutable ledger must be committed before source edits. Failures reproduced by executable fixtures before or alongside corrective implementation; no retrospective root-cause invention.
