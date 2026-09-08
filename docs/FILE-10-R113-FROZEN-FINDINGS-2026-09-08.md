# File 10 R113 Frozen Findings — 2026-09-08

Baseline exact HEAD reviewed read-only: `76945ba49ac01432c9d1e12a1fb07a15f646964c` (`1.2.23-rc1`).

Mandatory method observed: the complete R113 review was performed read-only before this ledger was written. No product correction was applied during the review phase.

## R113-01 — Opaque public-reference resolver DB failures can collapse into false client/not-found errors

`VWLB_Extended_REST::entity_id()` calls `VWLB_Repository::find()` and returns `0` when no row is returned, but it does not distinguish a repository read failure from a verified missing object. Callers therefore convert an unreadable channel/media-asset/caption/video reference into ordinary `404`/`422` responses. The R112 pre-dispatch protection only covers the podcast-series resolver and does not cover these other public-reference resolvers.

Affected mutation boundaries include podcast series creation (`channel_public_id`), podcast episode creation (`asset_public_id`, `transcript_caption_public_id`) and premiere creation (`video_public_id`).

Required correction: fail closed with a 503-class database/read-integrity error whenever public-reference state cannot be verified; only return missing/not-found after a successful read proves absence.

## R113-02 — Podcast mutation preflight reads can convert database failure into false not-found/validation state

`VWLB_Podcasts::episode()` performs a direct `$wpdb->get_row()` and returns `null` on both verified absence and SQL failure. Mutation paths such as `publish_episode()` therefore can emit `vwlb_not_found` on an unreadable database. `publish_series()` similarly performs a direct series read and converts failure to `vwlb_not_found`. `create_episode()` also relies on repository asset state without explicitly preserving read-failure truth at the mutation boundary.

Required correction: mutation preflight reads must distinguish verified absence from database failure and must stop publication/creation with a fail-closed 503 response when authoritative state is unreadable.

## R113-03 — Live mutation entry reads do not consistently preserve repository read-failure truth

Several live mutation commands begin with `VWLB_Repository::find('live_events', ...)` and immediately return `vwlb_live_missing` when the result is false. Unlike the later provider-reconciliation path, these entry points do not consistently test `VWLB_Repository::read_failed()` first. A transient/real database failure can therefore be represented as a missing live event, which is unsafe for credential issuance, lifecycle transition and emergency-control mutation semantics.

Required correction: authoritative live mutation preflight must fail closed on repository read failure and reserve `404` only for successfully verified absence.

## Frozen scope

These three findings constitute the R113 correction ledger. No additional R113 product finding may be silently added during correction; any newly discovered independent defect belongs to a later numbered review round unless it is strictly necessary to make one of the frozen corrections safe and regression-complete.
