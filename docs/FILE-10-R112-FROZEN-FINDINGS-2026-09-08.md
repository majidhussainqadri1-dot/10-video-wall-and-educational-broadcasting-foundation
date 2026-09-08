# File 10 — R112 Frozen Findings — 2026-09-08

## Governance

R112 was completed as a read-only review from the exact R111 Green baseline `4194d1daf1f6b7ef835f11c0448211c6714c464d`, File 10 Release QA run `34178262832`. No product-source correction was applied while the review was in progress. This document freezes the complete R112 finding set before correction.

## Scope

R112 reviewed authenticated creator, diagnostics/observability, administrative queue/moderation, and extended REST identifier-resolution read surfaces. The audit emphasized fail-closed database semantics, top-level error propagation, and preventing operational database failures from being represented as legitimate empty/healthy state.

## Frozen findings

### R112-01 — Creator Studio can collapse several database failures into partial/empty HTTP 200 state

`VWLB_Extensions::creator_studio()` performs raw `$wpdb->get_results()` reads for owner videos, live events, processing jobs and takedown/copyright cases. A failed query may return `null`, yet the function continues and returns a nominal Creator Studio payload. Target public-ID resolution also does not promote repository read failure, and `creator_insights()` can return `WP_Error` nested inside an otherwise successful array. The authenticated `/creator/studio` endpoint can therefore present unavailable data as empty/partial data.

### R112-02 — Operational observability can report false-zero/empty health on database failure

`VWLB_Observability::snapshot()` casts raw `get_var()` results to integers and raw `get_results()` results to arrays without checking `$wpdb->last_error`. A database outage/error can therefore be reported as `dead_jobs=0`, `dead_outbox=0`, and an empty provider list. Because `/operations/observability` is an operational diagnostics surface, this is materially misleading fail-open health semantics.

### R112-03 — Podcast series public-ID resolution can translate a database failure into a false 404

`VWLB_Extended_REST::series_id()` performs a raw `get_var()` and converts the result directly to integer. During podcast episode creation, a database read failure is indistinguishable from a genuinely missing series and is returned as `vwlb_not_found`. This loses the established database-read failure contract at an authenticated write boundary.

### R112-04 — Private admin dashboard/queue/moderation views can represent database failure as zero/no records

`VWLB_Admin::dashboard()`, `jobs()`, and `moderation()` use raw database reads without a fail-closed read-failure projection. Table/count failures can therefore render `0` or “No records” inside the operational command center. These are privileged UI surfaces rather than public API surfaces, but they are specifically intended for operational truth and must not silently convert unavailable state into healthy/empty state.

## Correction requirements

1. Route Creator Studio list reads through durable database-read wrappers and promote any list, target-resolution, or insights error to the top level.
2. Make observability snapshot fail closed when dead-queue or provider-health reads cannot be verified; never emit false-zero/false-empty health.
3. Make podcast series public-ID resolution distinguish database failure from absence and propagate a service/database error at the REST boundary.
4. Make admin dashboard, processing queue and moderation queue visibly fail closed on database read failure instead of rendering zero/no-record state.
5. Add R112 regression assertions for each boundary and advance the materially changed candidate to a fresh immutable release identity.
6. Run complete regression, R101–R120 gate, PHP 8.3/8.4 exact-head source/package QA, deterministic package/checksum/archive, source/package parity, and artifact publication before R113 begins.

## Live-first boundary

Exact deployed source remains unverified. Repository/source conclusions are provisional with respect to the live website; GitHub source/package evidence is not Live-Deployed or Operational evidence.
