# File 10 — R104 Correction and Validation — 2026-09-07

## Frozen-review integrity
R104 remained read-only until `docs/FILE-10-R104-FROZEN-FINDINGS-2026-09-07.md` was committed. This correction phase did not begin R105.

## Correction-phase validation
1. **Direct DB-read truth — confirmed, narrowed.** REST already had R66 request-level `$wpdb->last_error` protection, but that only observes the final request error and does not protect non-REST frontend/cross-file helper reads. Immediate typed read helpers now return a 503-grade `WP_Error` at the failing query and the affected frontend/cross-file paths propagate it instead of rendering empty/404/partial state.
2. **Caption cache — frozen finding invalidated as a false positive.** `VWLB_R78_Public_Delivery_Guard::caption_cache()` was already registered and overwrites non-public caption responses with `Cache-Control: private, no-store`. The core caption callback's public header therefore was not the final composed response for access-controlled captions. Root-cause-first/no-patch-stacking requires retaining that single canonical guard rather than adding a duplicate cache patch. Regression coverage now makes this composition explicit.
3. **Podcast feed/RSS unlisted authorization — confirmed.** The R78 feed interceptor and canonical podcast feed both admitted unlisted rows without `VWLB_Security::can_view()`. Both series and episode projections now apply the normal object-level policy; the existing feed response remains no-store and private-storage delivery remains short-lived/provider-gated.

## Candidate
Material code changes receive runtime identity `1.2.15-rc1`. Exact-head PHP 8.3/8.4 release QA, deterministic package/checksum/archive and source/package parity are mandatory before R105.
