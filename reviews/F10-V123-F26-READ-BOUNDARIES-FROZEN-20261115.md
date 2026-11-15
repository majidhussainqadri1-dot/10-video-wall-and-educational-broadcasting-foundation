# File10 V12.3 — File26 Owner Read Boundaries: Frozen Source Findings (2026-11-15)

## Scope and provenance

- Governing plan: `Sabri-Website-Master-Plan-V12.3.docx`, F10-FR-018/019, F10-NFR-001/003, F10-FUT-022/024, V12.1-STO-07/09/10.
- Inspected exact source: `e02b5ff026e1754b4eb7b2dfc7cc3b517f7af083` (Draft PR #34); not the legacy main branch and NOT a deployed plugin.
- Affected candidate: `video-wall-and-live-broadcasting/includes/class-vwlb-file26-connector.php`.
- The adapter remains deliberately `status=proposed` and is not registered in the runtime. This source audit does not authorize activation, merge or deployment.
- Live WordPress and Staging inaccessible (WPVibe HTTP 530); exact installed plugin, DB/schema, migration, logs and parity remain unverified.

## Findings — read-only source analysis before patch

1. **F10-F26-RB-01 (medium, defensive authorization):** `can_view()` directly calls `VWLB_Repository::find()` without an owner-read exception boundary and without checking `VWLB_Repository::read_failed()` before using an owner row. A thrown DB adapter error can escape a supposed boolean authorization callback; a returned row together with the repository's failure flag may be incorrectly treated as current public truth. It also performs a DB read despite safe-mode owner unavailability.
2. **F10-F26-RB-02 (high, direct fetch source integrity):** `fetch_object()` checks the repository read failure only immediately after the first `find()`. `project()` may run related security/consent/owner lookups; the method then checks only the R109 policy flag, not the repository's read-failure flag. A later read fault can be reported as successful public document or legitimate absence.
3. **F10-F26-RB-03 (high, nonactive reconciliation proposal):** `propose_outbox_reconciliation()` similarly checks the repository flag before `project()` but not after it. A later owner-side read failure can yield an upsert/tombstone proposal as if the current state were verified.

## Required minimal correction

- Add fail-closed readiness, exception and post-read checks for the boolean `can_view` callback, preserving return type bool.
- For direct document and outbox proposal, propagate *typed* `WP_Error` on late repository lookup failure instead of a false-success projection. Preserve true missing/private as `null` and legitimate tombstone proposal respectively.
- Add adversarial regressions that fail against this exact source: thrown canonical read, row returned with late failed flag, nested policy read flag, and safe-mode avoidance of needless lookups.
- Preserve proposed-only File26 manifest, no new schema, no remote service, no new privileges or production changes.
- Include source-only PHP tests in the established test runner. Exact-head CI must be evaluated before claiming source fix.

## Release and external blockers

- Issue #13 live connection HTTP 530; Issue #22 branch protection; Issue #26 durable File26 tombstone/ACK and CDN purge still unresolved.
- No owner/consumer ACK, storage provider, staging, live retest, rollback rehearsal or Founder acceptance; zero of 64 requirements independently Staging/Live accepted.
- **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
