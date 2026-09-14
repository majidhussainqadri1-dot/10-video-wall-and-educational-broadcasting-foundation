# R143 Frozen Findings

Review mode: read-only until this ledger was frozen.

Reviewed baseline: `85a89d7710d134522bd5bb9f82f74b51870dd45a` (R142 exact-head Release QA Green, run `34885898530`).

Scope included recorded-video creation/publication, metadata and scan gates, captions, rights/consent delivery, consent-expiry reconciliation, replay lineage, playback/session isolation, secure non-public delivery, downloads, interactions/progress, and command-level idempotency durability.

## Frozen finding R143-01 — create-video idempotency completion/abort failures are ignored

`VWLB_Videos::create()` starts its own durable `create_video` idempotency record, but after the transactional create it calls `idempotency_finish()` without checking for `WP_Error`. On transaction failure it also calls `idempotency_abort()` without checking its result. If durable replay-state finalization or abort fails, the command can return the business result/error while its idempotency truth is unverified or remains stuck in `processing`. That permits replay ambiguity or a long-lived false `in progress` state, contrary to F10-NFR-003.

## Frozen finding R143-02 — consent-expiry row-lock read can silently collapse a DB failure into “unchanged”

`VWLB_R109_Rights_Consent_Replay_Guard::reconcile_consent_expiry()` performs the per-record `SELECT ... FOR UPDATE` using raw `$wpdb->get_row()` without checking `$wpdb->last_error`. A database read failure therefore becomes a null row and is returned as `changed => false`; the worker produces no typed failure/audit evidence for that candidate. Delivery still fails closed elsewhere, but reconciliation evidence/degraded behavior is incomplete and can silently skip work during an outage.

## Authorized correction

After this review is frozen:
1. In `VWLB_Videos::create()`, verify both `idempotency_abort()` and `idempotency_finish()`; surface their typed errors when durable replay state cannot be safely finalized.
2. In consent-expiry reconciliation, replace the raw locked-row read with the fail-closed database read helper (or equivalent explicit `last_error` verification) and propagate the typed error through the transaction.
3. Extend a permanent regression contract to cover both invariants.
4. Run the complete exact-head Release QA before R144.

R131 repository-governance finding remains separately open and is not treated as corrected by these product fixes.

Live-First rule remains in force: exact deployed source was not available for this review; exact deployed code is unverified, and repository-based diagnosis is provisional.
