# R119 Frozen Findings — Remaining Public/Mutation Read Integrity

Baseline exact repository head reviewed: `7e85c8498ab836cac41a58b91ac4e6e6a67c8a51`.

Review discipline: the complete R119 review was performed read-only. No product, test, workflow, or repository metadata was modified during the review. Findings below are frozen before correction.

## Proven defect group R119-1 — public caption reads can misclassify database failure as 404

The canonical REST caption endpoint calls `VWLB_Repository::find('captions', ...)` and then `VWLB_Repository::find('videos', ...)`. `VWLB_Repository::find()` deliberately returns `null` on a database read error after setting its internal read-failure flag. The caption endpoint does not reset/check that flag, so an authoritative database failure can be returned to the caller as ordinary `vwlb_not_found` 404.

This violates the established fail-closed public-read boundary: database uncertainty must remain operational uncertainty (5xx), not verified absence.

## Proven defect group R119-2 — core opaque public-ID mutation resolvers can misclassify database failure as missing references

The canonical REST `entity_id()` helper uses `VWLB_Repository::find()` and collapses its `null` result to integer `0`. Core mutation routes then translate `0` to normal missing-reference errors such as asset/channel/video 404. Existing R113 preflight coverage protects selected podcast/premiere resolvers, but does not cover the core File 10 resolver paths, including create-video asset/channel references, create-playlist/schedule-live channel references, and playlist-item video references (PUT).

A database read failure in these authoritative preflight reads can therefore be misreported as a verified missing object and can alter mutation semantics.

## Correction contract

After this ledger is frozen, correction must:

- fail closed on caption/video authoritative reads for the public caption endpoint and return an operational 503 on database uncertainty;
- fail closed on core opaque public-ID mutation resolver reads before the underlying mutation callback executes;
- cover POST and PUT routes that depend on these resolvers, without changing verified-missing semantics when the database read succeeds;
- add deterministic R119 regression coverage;
- run the complete regression/retest suite and exact-head Release QA before R120 begins.

## Live-First rule

Exact deployed source was not available for comparison. Exact deployed code remains unverified; repository-based diagnosis is provisional and GitHub is not treated as live state.
