=== Video Wall and Live Broadcasting ===
Contributors: sabrihomeopathy
Requires at least: 7.0
Tested up to: 7.0.1
Requires PHP: 8.3
Stable tag: 1.2.10-rc1
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
* Privacy erasure is bounded and retryable; retained safety/rights evidence is anonymized where required.
* Forensic watermark grants require a verified playback/live viewer session and remain private/no-store.
* Audit, outbox, rollback snapshots, bounded retries, dead-letter and reconciliation are included.

== Accessibility and localization ==

Keyboard-visible focus, approximate 44px controls, semantic status regions, captions, responsive layout, reduced-motion support, RTL-safe logical spacing and American English source strings are included. Urdu/Arabic translations may be supplied as standard WordPress language packs.

== Installation ==

1. Back up the approved staging environment and verify restoration.
2. Upload `video-wall-and-live-broadcasting-1.2.10-rc1.zip` to WordPress staging.
3. Activate the plugin; schema, capabilities, pages and cron jobs are reconciled with activation compensation evidence.
4. Configure File 00 identity claims/capabilities and approved provider adapters.
5. Run Video & Live diagnostics.
6. Complete the current File 10 staging acceptance checklist before production.

== Upgrade ==

The activation migration reads supported legacy File 10 data when present, stores rollback evidence, verifies schema markers and keeps the legacy public owner disabled only after controlled cutover acceptance. Encrypted-evidence migration markers are revalidated with DB-error-aware reads before trusting completion. Do not run duplicate public route owners after acceptance.

== External acceptance boundary ==

Source code, deterministic packaging and automated checks do not prove Hostinger/LiteSpeed runtime, real provider credentials, actual transcoding/live delivery, browser/device accessibility, high-concurrency streaming, backup restoration, rollback, staging acceptance, Founder acceptance or live deployment. Those remain separate evidence gates.

== Changelog ==

= 1.2.10-rc1 =
* Complete the fresh sequential R61-R80 source-review cycle under Review → Findings Freeze → batch correction → full retest discipline.
* Harden activation rollback, provider/processor exception containment, repository/direct-DB failure truth, playback and privacy read failures, webhook fairness, resumable completion, private downloads, podcast delivery, recording consent, takedown identity, cleanup fairness, bounded poll integrity and private public-delivery boundaries.
* Bind forensic watermark grants to verified playback/live viewer sessions and fail closed when session proof is unavailable.
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
