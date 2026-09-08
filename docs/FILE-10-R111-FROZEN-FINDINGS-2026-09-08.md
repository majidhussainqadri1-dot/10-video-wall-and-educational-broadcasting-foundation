# File 10 — R111 Frozen Findings — 2026-09-08

## Governance

R111 was completed as a read-only review from the exact R110 Green baseline `d7ed00cbaf76093fd1ccadfa4fbcf405ecde2fb3`. No product-source correction was applied while the review was in progress. This document freezes the complete R111 finding set before correction.

## Scope

R111 reviewed the canonical and extended public REST read surfaces after the R110 public-delivery correction, with emphasis on database-read failure semantics, public DTO enrichment, and avoidance of partial-success responses.

## Frozen findings

### R111-01 — Public live browse can collapse a database failure into a successful empty list

`VWLB_REST::browse_live()` performs a direct `$wpdb->get_results()` query and immediately iterates the returned value without using the repository/DB read-failure contract. On a database read error WordPress can return `null`; the endpoint can therefore project an empty `items` response rather than a fail-closed service error. This violates the established File 10 public-read integrity rule and can make an unavailable catalogue appear legitimately empty.

### R111-02 — Video-detail chapter enrichment can embed a read failure inside HTTP 200

`VWLB_REST::get_video()` assigns `VWLB_Extensions::chapters()` directly to `$dto['chapters']`. `chapters()` is backed by `VWLB_DB::read_results()` and can return `WP_Error`. The caller does not promote that error to the top-level response, so a chapter-table read failure can become a nominally successful video DTO containing an error object/partial enrichment instead of a fail-closed response.

### R111-03 — Canonical `/videos/{id}/chapters` has the same nested-error/partial-success failure mode

`VWLB_Extended_REST::video_chapters()` wraps `VWLB_Extensions::chapters()` directly as `array('items'=>...)` without checking `is_wp_error()`. A chapter read failure can therefore be serialized as a successful wrapper rather than terminating the request with the canonical database-read failure.

## Correction requirements

1. Route public live listing through the durable DB read wrapper or explicitly detect/translate DB read failure before producing any list payload.
2. Promote chapter read failures to top-level REST errors in both video detail and the dedicated chapter endpoint.
3. Add R111 regression assertions that preserve these fail-closed boundaries.
4. Advance the materially changed correction candidate to a fresh immutable release identity before exact-head QA.
5. Run the complete regression suite, R101–R120 gate, PHP 8.3/8.4 source/package QA, deterministic package/checksum/archive, source/package parity, and artifact publication before R112 begins.

## Live-first boundary

Exact deployed source remains unverified. Repository/source conclusions are provisional with respect to the live website; GitHub source/package evidence is not Live-Deployed or Operational evidence.
