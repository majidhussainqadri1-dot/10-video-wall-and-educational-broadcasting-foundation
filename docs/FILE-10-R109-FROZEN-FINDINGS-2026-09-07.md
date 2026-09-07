# File 10 — R109 Frozen Findings — 2026-09-07

## Review discipline

R109 was completed as a read-only review against exact baseline `762361ae9f1b1c8392e0fbce224a15051432952c` (`1.2.19-rc1`). No product correction was started while this review was in progress. This file freezes the complete R109 finding set before correction.

## Review lens

Rights/copyright/takedown, public delivery rights rechecks, patient-case consent expiry/withdrawal, recording consent, recording finalization, replay publication/lineage, restoration blockers, evidence/history and reconciliation durability.

Plan basis includes F10-FR-006, F10-FR-015, F10-FR-019, F10-NFR-002/003, AJ-16, F10-FUT-022, CV-114 and the central rights-policy invariant requiring owner/license/territory/expiry/access/revocation to be checked consistently by download/render/share.

## Frozen defects

### R109-01 — Current rights policy is not enforced consistently at render/playback time

`VWLB_Videos::publication_gate()` checks rights at publication and download paths recheck rights, but `VWLB_Security::can_view()` / normal video playback do not reject a video whose current `rights_status` is no longer `declared|verified`, nor do they enforce a current `rights_json` revocation/expiry condition. A once-published object can therefore remain renderable/playable after rights truth changes unless a separate moderation transition also changes its publication state.

This violates the central invariant that download/render/share use the same current rights policy and makes rights revocation/expiry propagation incomplete.

### R109-02 — Patient-case consent expiry is not request-time fail-closed

The Future-22 implementation relies on scheduled reconciliation to convert an expired active `consent_links` row into `expired` and restrict the video. Public `can_view()`, detail, caption and playback paths do not verify that a current consent link has not already expired or been withdrawn.

If cron is delayed or unavailable, a stale public copy can continue to be served. F10-FUT-022 explicitly requires stale public copy to fail closed.

### R109-03 — Effective consent-expiry reconciliation can fail silently and starve later expirations

The effective runtime reconciler is `VWLB_Future_Safety::reconcile_consent_expiry()`. Its initial candidate query uses a direct `$wpdb->get_results(...)` without a read-error check, so a database read failure can be interpreted as “nothing to do”. It also repeatedly scans the first 100 expired active rows with no persisted cursor. Permanently failing rows (for example an irreconcilable/missing linked video) can occupy that first page and starve all later consent expirations.

This violates fail-closed reconciliation, bounded fair progress and F10-NFR-003.

### R109-04 — Consent withdrawal/expiry history can be rewritten instead of remaining immutable

`VWLB_Future_Intelligence::upsert_consent_link()` permits an existing `withdrawn` or `expired` consent row to be updated back to `active`, and resets `withdrawn_at` to `NULL` whenever the submitted status is not `withdrawn`. The consent-row status transition itself is not audited and emits no consent lifecycle outbox fact.

A renewed consent must be represented by a new/superseding consent reference, not by rewriting terminal historical evidence. F10-FUT-022 requires versioned/revocable consent with audit/outbox evidence and the platform audit law requires durable change history.

### R109-05 — Replay publication does not prove recording authorization, consent finalization or media lineage

`VWLB_Live::publish_replay()` verifies permissions, live-state transition and that the chosen video is already published, but it does not verify:

- the live event recording policy authorized recording and replay publication;
- required recording-consent proof remained valid for the finalized recording;
- the selected replay video is derived from the live event’s canonical recording asset / recording lineage.

As written, an unrelated published video can be attached as the canonical replay of an ended live event, including an event whose recording/replay policy did not authorize that outcome. This violates F10-FR-015 metadata lineage/canonical replay integrity, CV-114 and AJ-16.

## Existing controls confirmed, not defects

- R73 requires explicit current recording-consent text version and fails finalization closed on unreadable/incomplete attendee consent state.
- R74 requires a verified active File 00 identity for public takedown filing.
- R11 blocks restoration while other moderation/takedown blockers or expired/withdrawn consent blockers remain.
- R71 rechecks rights/access and requires a secure short-lived grant for private download storage.
- Takedown target mutations are transactionally revalidated and use optimistic version/state checks.

## Correction gate

R109 correction must fix all five frozen defects together, add dedicated regression contracts, run the full File 10 automated suite, PHP 8.3/8.4 exact-head release QA, deterministic package build, checksum/archive verification and source/package parity. Only an exact-head Green candidate may start R110.

No R110 review may begin before that gate is satisfied.
