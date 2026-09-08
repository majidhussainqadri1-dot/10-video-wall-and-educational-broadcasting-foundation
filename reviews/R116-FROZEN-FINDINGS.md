# File 10 — R116 Frozen Findings

Baseline exact head reviewed: `87dabca7d5f5295cf144aae8f16730bef5bc1786`

Review discipline: this round was completed read-only. No product patch was applied while the review was in progress. The findings below are frozen before correction begins.

## R116-F1 — Private storage protection write failures are suppressed and unverified

`VWLB_Extensions::ensure_private_dir()` creates `index.php`, `.htaccess`, and `web.config` with `@file_put_contents(...)` and does not verify the return value or re-read the resulting protection files before returning the directory as usable.

Impact: File 10 can report private media storage as ready even when one or more web-server protection files were not persisted. This is a security/integrity gap because failure of the protection layer is indistinguishable from successful hardening.

Required correction: fail closed when a required protection file cannot be written or verified; emit an operational failure signal and do not return the private directory as usable.

## R116-F2 — Remaining authoritative DB verification reads bypass the fail-closed read contract

`VWLB_Security::rate_limit()` and `VWLB_DB::idempotency_finish()` still perform direct `$wpdb->get_row(...)` verification reads instead of `VWLB_DB::read_row(...)`.

Impact: database read failures at these authoritative verification points bypass the standardized `vwlb_database_read_failed` operational-failure path and are conflated with ordinary missing/unverified state. These paths are fail-safe in outcome, but inconsistent with the repository's current authoritative-read integrity contract and weaken diagnostics/auditability.

Required correction: route these verification reads through `VWLB_DB::read_row(...)`, propagate `WP_Error`, and add regression contracts that prevent reintroduction of raw authoritative reads in these paths.

## Freeze

Frozen defect groups: **2** (`R116-F1`, `R116-F2`).

No additional product corrections are permitted under R116 except corrections necessary to close these frozen findings and any regression/tooling defect discovered only during that correction validation. R117 remains blocked until R116 correction, regression, and exact-head Release QA are green.
