# File 10 — R108 Frozen Findings — 2026-09-07

## Review discipline

R108 was completed as an uninterrupted read-only review from the R107 exact-head Green baseline `0de65d9956cf3a4c03e3e0c315c09bb70ca8c82f` (`1.2.18-rc1`). No R108 source correction was started during the review. The review traversed the canonical live state machine and command service, core/extended/Future REST surfaces, global and external-effect idempotency guards, provider exception boundaries, verified-webhook durability, live/provider reconciliation workers, Future redundancy reconciliation, waiting-room/premiere/recording-consent lifecycle, and the late REST override layer.

## Frozen findings

### R108-F01 — Public lifecycle transition accepts caller-supplied provider truth

`VWLB_REST::transition_live()` forwards request-body `provider_proof` into `VWLB_Live::transition()`. The command service allowlists fields such as `provider_event_ref`, `status`, `degraded`, `region` and `health_code` and merges them into canonical `provider_state_json`. A broadcast-authorized browser/API caller therefore remains able to author provider-derived operational evidence. Provider truth must originate from a verified server-side adapter/webhook/reconciliation boundary, not from an ordinary public lifecycle request.

### R108-F02 — Verified provider observations cannot durably reconcile canonical live lifecycle state

`VWLB_Jobs::reconcile()` calls the provider reconciliation adapter but only merges a bounded provider-state payload; it does not apply a verified provider observation to File 10's canonical live state machine. The verified-webhook pipeline durably dispatches `vwlb_verified_webhook`, but the File 10 integration layer only forwards a dynamic provider action and exposes no canonical, authorization-independent server-side lifecycle reconciliation command. Consequently an externally interrupted/ended broadcast can remain canonically `live` unless external code bypasses File 10 ownership or a human performs a separate public transition. This conflicts with File 10's canonical live-truth ownership and reconciliation requirement.

### R108-F03 — Emergency end can report local success without confirmed provider termination

`VWLB_Live::kill()` updates local state, revokes credentials and invokes `do_action('vwlb_provider_emergency_end', ...)`, but an action has no return/confirmation contract. With no handler, or with a handler that silently fails without throwing, File 10 can commit `ended` while the provider broadcast remains active. The existing R60/R94 uncertainty guards correctly protect failed/throwing attempts, but they cannot detect a silent no-op because the emergency provider effect is never positively confirmed.

### R108-F04 — Core live reconciliation can starve rows and can silently lose provider-state reconciliation writes

The core live reconciliation query processes at most 100 rows with no stable ordering/cursor. Long-lived low-ID/high-frequency populations can therefore repeatedly occupy the bounded batch while later rows receive no reconciliation opportunity. After a provider observation, the final `update_versioned()` result is ignored, so a version conflict or database failure can discard provider-state reconciliation without a durable operational signal.

### R108-F05 — Future redundant-live reconciliation is not fail-closed or exception-contained and is not fair beyond its first 100 rows

`VWLB_Future_Intelligence::reconcile_live_redundancy()` uses a direct bounded DB read without resetting/checking `$wpdb->last_error`, has no stable cursor/order, and executes `vwlb_redundancy_reconcile` / `vwlb_redundant_recording_required` actions without a `Throwable` boundary. A database failure can be misread as an empty queue, one integration exception can abort the reconciliation action chain, and a population above 100 eligible configurations can starve later rows.

### R108-F06 — Effective late REST override layer still contains permissive object-ID route grammars

The higher-priority `VWLB_Review_Hardening` REST override layer still registers multiple object routes with `[A-Za-z0-9_-]+`, including live credential and replay routes. R107 corrected later override classes, but this earlier effective override layer was not normalized. Numeric IDs are separately rejected by `rest_pre_dispatch`, so this is not being classified as a fresh numeric-ID disclosure; it is nevertheless a real canonical-contract gap because malformed/non-prefixed public identifiers remain route-valid contrary to File 10's single prefixed opaque-ID grammar.

## Correction boundary

Only the six findings above are frozen for the R108 correction phase. R108 correction must not broaden into unrelated cleanup. After all six are corrected together, a dedicated R108 regression gate and the complete File 10 release suite must pass on PHP 8.3 and 8.4, with deterministic package/checksum/archive/source-package parity and a fresh immutable candidate identity, before R109 begins.
