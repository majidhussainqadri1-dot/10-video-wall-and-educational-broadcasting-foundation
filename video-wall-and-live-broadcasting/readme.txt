=== Video Wall and Live Broadcasting ===
Contributors: sabrihomeopathy
Requires at least: 7.0
Tested up to: 7.0.1
Requires PHP: 8.3
Stable tag: 1.2.23-rc1
License: GPLv2 or later

File 10 canonical owner for recorded video and live broadcasting in the Sabri Social Homeopathy Platform.

== Description ==

This release candidate implements the approved File 10 domain: channels, playlists, bounded media ingest, processing jobs, recorded-video lifecycle, captions/transcripts, visibility-aware playback, private progress/history, Like/Dislike/Save, live scheduling, step-up protected stream credentials, live lifecycle, emergency end, recording/replay orchestration, provider adapters, moderation, copyright/takedown, audit/outbox/inbox, privacy export/erasure, diagnostics, repair, migration and Future Video & Broadcasting Intelligence 24 capabilities.

File 10 keeps canonical truth for recorded/live media. It exposes versioned contracts to companion modules and does not create a duplicate messaging backend, Reels feed, global shell, notification transport or profile system.

== Provider configuration ==

Local, YouTube, Vimeo and custom adapters are registered. External provider credentials must not be committed to GitHub. Configure approved provider secrets outside the plugin repository and expose them only through the documented adapter boundary.

== Security and privacy ==

* Object, field, state and ownership checks are repeated in command services.
* Public APIs use opaque identifiers; internal database primary keys are not public contracts.
* Stream keys are displayed once and stored only as password hashes.
* Step-up authorization is required for high-risk broadcast actions.
* Public/private DTO allowlists prevent row, secret and provider-state disclosure.
* Mutations use authorization, validation, rate limiting and idempotency controls.
* Provider webhooks require signature verification and content-bound provider-event deduplication.
* External provider-effect retries remain blocked when the provider outcome is unverified until explicit reconciliation.
* Private history/studio surfaces use no-store/noindex controls.
* Privacy erasure is bounded and retryable; private resumable-upload files are removed before their database rows and retained safety/rights evidence is anonymized where required.
* Forensic watermark grants require a verified playback/live viewer session and remain private/no-store.
* Audit, outbox, rollback snapshots, bounded retries, dead-letter and reconciliation are included.

== Accessibility and localization ==

Keyboard-visible focus, approximate 44px controls, semantic status regions, captions, responsive layout, reduced-motion support, RTL-safe logical spacing and American English source strings are included. Urdu/Arabic translations may be supplied as standard WordPress language packs.

== Installation ==

1. Back up the approved staging environment and verify restoration.
2. Upload `video-wall-and-live-broadcasting-1.2.22-rc1.zip` to WordPress staging.
3. Activate the plugin; schema, capabilities, pages and cron jobs are reconciled with activation compensation evidence.
4. Configure File 00 identity claims/capabilities and approved provider adapters.
5. Run Video & Live diagnostics.
6. Complete the current File 10 staging acceptance checklist before production.

== Upgrade ==

The activation migration reads supported legacy File 10 data when present, stores rollback evidence, verifies schema markers and keeps the legacy public owner disabled only after controlled cutover acceptance. Encrypted-evidence migration markers are revalidated with DB-error-aware reads before trusting completion. Do not run duplicate public route owners after acceptance.

== External acceptance boundary ==

Source code, deterministic packaging and automated checks do not prove Hostinger/LiteSpeed runtime, real provider credentials, actual transcoding/live delivery, browser/device accessibility, high-concurrency streaming, backup restoration, rollback, staging acceptance, Founder acceptance or live deployment. Those remain separate evidence gates.

== Changelog ==

= 1.2.22-rc1 =
* R110: restore canonical opaque IDs on late delivery routes and re-apply current rights/consent policy during public discovery.
* Fail closed on security-sensitive delivery revalidation reads and use complete podcast authorization projections.
* Treat every non-public visibility, including unlisted, as secure-grant-only for playback, downloads and media contracts.

= 1.2.20-rc1 =
* R109: enforce current rights/expiry/revocation and patient-case consent blockers consistently at delivery/publication time.
* Replace cron-only consent expiry with DB-failure-aware cursor-fair reconciliation and preserve terminal consent history with audit/outbox evidence.
* Require recording policy, current consent proof and canonical recording-asset lineage before a live replay can be published.

= 1.2.19-rc1 =
* R108: remove public caller authority over provider lifecycle evidence and add a canonical verified provider/webhook reconciliation path.
* Require positive provider confirmation for emergency end before committing matching local state; uncertain provider/local outcomes remain reconciliation-guarded.
* Make core live and Future redundancy reconciliation DB-failure-aware, Throwable-contained and cursor-fair, and normalize late REST override IDs to the canonical opaque grammar.

= 1.2.18-rc1 =
* R107: enforce canonical opaque IDs across late REST overrides and re-resolve authorized playback state before enrichment.
* Contain provider compensation callbacks inside Throwable boundaries and centralize recursive raw-credential detection across Future metadata/configuration surfaces.
* Separate implementation presence from evidence-backed runtime/provider readiness and reset provider-health DB error state before availability decisions.

= 1.2.17-rc1 =
* R106: coordinate expired private-upload cleanup with the canonical upload writer lock so cleanup cannot race an in-flight chunk write.
* Contain checksum/scanner/external-validation failures at the upload worker boundary and fail closed through the normal retry/dead-letter path.
* Restore the canonical prefixed opaque public-ID grammar on the late resumable-completion route override.

= 1.2.16-rc1 =
* R105: complete privacy-safe attribution export coverage for File 10 subject-attributable stores while excluding secret/internal fields.
* Anonymize eligible subject identifiers in retained canonical audit/outbox evidence and encrypted R30 fallbacks with bounded cursor-safe propagation.
* Add an explicit File10PrivacyLegalHold.v1 scope/basis/reference/expiry contract and distinguish ordinary retained evidence from validated legal-hold retention.

= 1.2.15-rc1 =
* R104: propagate immediate database-read failures on frontend/cross-file read helpers instead of rendering empty/404 partial state.
* Enforce the normal object-level unlisted authorization policy on podcast feed/RSS series and episode projections while preserving private/no-store delivery.
* Correction validation confirmed the pre-existing R78 caption-cache guard already makes non-public captions private/no-store; no duplicate cache patch was stacked.

= 1.2.14-rc1 =
* R103: verify required schema columns/indexes after dbDelta before trusting schema markers.
* Migrate the complete legacy video corpus in checkpointed 500-row batches without a 10,000-row silent truncation boundary.
* Enforce opaque public IDs on frontend rewrite/query/shortcode paths; repair podcast public DB projection and Video Wall thumbnail DTO consumption.

= 1.2.13-rc1 =
* R102: enforce opaque public identifiers across File 10 REST paths and foreign references.
* Reject raw/native database identifiers at public mutation boundaries and remove caption/podcast numeric-ID leakage.
* Re-resolve the authorized playback object internally before loading chapters and media tracks.

= 1.2.12-rc1 =
* R101: add reload-safe, owner-scoped production-studio state retrieval through opaque public identifiers.
* Fail closed when production state cannot be verified, keep the response private/no-store, and exclude native IDs and credential references.
* Bind the production studio UI to an authorized live event and progressively render current sources, scenes, guests, simulcast targets and latency state.

= 1.2.11-rc1 =
* Complete the sequential R81-R100 source-review cycle under Review → Findings Freeze → batch correction → full retest discipline.
* Correct canonical-owner duplication, DB-failure route truth, privacy anonymization uniqueness, signed unlisted delivery, emergency-end provider uncertainty and private-upload erasure propagation.
* Close final candidate identity/traceability drift with a fresh immutable package identity while retaining the 1.2.10-rc1 R61-R80 evidence as historical.

= 1.2.10-rc1 =
* Complete the fresh sequential R61-R80 source-review cycle under Review → Findings Freeze → batch correction → full retest discipline.
* Harden activation rollback, provider/processor exception containment, repository/direct-DB failure truth, playback and privacy read failures, webhook fairness, resumable completion, private downloads, podcast delivery, recording consent, takedown identity, cleanup fairness, bounded poll integrity and private public-delivery boundaries.
* Bind forensic watermark grants to verified playback/live viewer session and fail closed when session proof is unavailable.
* Assign a fresh immutable package identity after deployable corrections and re-enable final exact-head release artifact publication.

= 1.2.9-rc1 =
* Complete sequential review round R60 and close the R41-R60 source-review cycle.
* Make encrypted fallback and legacy migration reads DB-error-aware and re-prove the old migration completion marker.
* Add whole-activation rollback evidence and provider-effect retry/reconciliation guards.
* Fail public catalogue rendering/REST reads closed on database failure and guard interaction recount repair.

= 1.2.8-rc1 =
* R41-R59 sequential corrective-review candidate before final R60 closure.

= 1.2.7-rc1 =
* Close R40 of the sequential R21-R40 review cycle and synchronize destructive purge/release hygiene.

= 1.2.6-rc1 =
* R21-R39 interim sequential corrective review candidate.

= 1.2.5-rc1 =
* Harden Future 24 REST outputs, provider orchestration, bounded Future lists and release metadata.

= 1.2.4-rc1 =
* Third fresh 20-round corrective review cycle and exact-head release QA candidate.