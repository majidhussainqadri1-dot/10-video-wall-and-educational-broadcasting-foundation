# File 10 — R115 Frozen Findings — 2026-09-08

Baseline exact reviewed HEAD: `3d70059ea64825a736c0ed3398c6ee7cdf81c291` (`1.2.25-rc1`).

Method: complete read-only R115 review first; no product correction was applied during review. Findings below are frozen before correction.

## R115-01 — Idempotency state reads can collapse database uncertainty into normal replay state

`VWLB_Security::idempotency_begin()` performs authoritative reads of the `idempotency` table through direct `$wpdb->get_row()` calls without clearing/checking `$wpdb->last_error` or using the fail-closed `VWLB_DB::read_row()` boundary. This occurs on the initial key/scope lookup, after expired-row cleanup, and on the duplicate-insert race lookup. A failed read can therefore be interpreted as absence or as a normal in-progress conflict rather than an explicit unverifiable-state 503. Because this boundary decides whether a mutation may proceed or be replayed, database uncertainty must fail closed.

Required correction: route all authoritative idempotency reads through a verified read helper (or equivalent explicit `$wpdb->last_error` verification) and propagate database-read failure as a 503 without proceeding to mutation/replay decisions.

## R115-02 — Transaction rollback outcome is not verified

`VWLB_DB::transaction()` issues `ROLLBACK` when a callback returns `WP_Error`, after a failed `COMMIT`, and after a caught `Throwable`, but ignores the return value of `ROLLBACK`. If rollback itself fails, the helper returns the original application/commit/exception error even though transaction state is no longer verified. That obscures a durability boundary failure and can leave the caller unable to distinguish a clean rollback from an indeterminate database transaction state.

Required correction: centralize rollback in a verified helper. If rollback fails, emit an operational-failure signal and return a distinct fail-closed transaction-rollback error indicating indeterminate durability. Preserve the original error code/class as bounded diagnostic context only.

## Frozen disposition

Both findings are proven and require correction in one R115 correction batch. No R116 review may begin until correction, full regression, and exact-head release QA are Green.
