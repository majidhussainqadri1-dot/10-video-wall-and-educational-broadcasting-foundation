=== Video Wall and Live Broadcasting ===
Contributors: sabrihomeopathy
Requires at least: 7.0
Tested up to: 7.0.1
Requires PHP: 8.3
Stable tag: 1.2.5-rc1
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
* Provider webhooks require signature verification and provider-event deduplication.
* Private history/studio surfaces use no-store/noindex controls.
* Privacy erasure is bounded and retryable; retained safety/rights evidence is anonymized where required.
* Audit, outbox, rollback snapshots, bounded retries, dead-letter and reconciliation are included.

== Accessibility and localization ==

Keyboard-visible focus, approximate 44px controls, semantic status regions, captions, responsive layout, reduced-motion support, RTL-safe logical spacing and American English source strings are included. Urdu/Arabic translations may be supplied as standard WordPress language packs.

== Installation ==

1. Back up the approved staging environment and verify restoration.
2. Upload `video-wall-and-live-broadcasting-1.2.5-rc1.zip` to WordPress staging.
3. Activate the plugin; schema, capabilities, pages and cron jobs are reconciled idempotently.
4. Configure File 00 identity claims/capabilities and approved provider adapters.
5. Run Video & Live diagnostics.
6. Complete the current File 10 staging acceptance checklist before production.

== Upgrade ==

The activation migration reads supported legacy File 10 data when present, stores rollback evidence, verifies schema markers and keeps the legacy public owner disabled only after controlled cutover acceptance. Do not run duplicate public route owners after acceptance.

== External acceptance boundary ==

Source code, deterministic packaging and automated checks do not prove Hostinger/LiteSpeed runtime, real provider credentials, actual transcoding/live delivery, browser/device accessibility, high-concurrency streaming, backup restoration, rollback, staging acceptance, Founder acceptance or live deployment. Those remain separate evidence gates.

== Changelog ==

= 1.2.5-rc1 =
* Harden Future 24 REST outputs to opaque public contracts and reject internal-ID mutation inputs.
* Correct a cross-class private-method runtime fatal in simulcast orchestration.
* Make generated annotation persistence atomic and add processor-cancellation reconciliation for local track persistence failure.
* Bound Future list queries and live-poll option counts; preserve correct-answer alignment after option normalization.
* Make Future cleanup/consent reconciliation failures observable.
* Verify activation version/Safe Mode persistence and operational metrics/provider-health persistence.
* Bound privacy erasure batches and remove soft-404 success status from missing protected media routes.
* Synchronize WordPress/PHP/runtime release metadata.

= 1.2.4-rc1 =
* Third fresh 20-round corrective review cycle and exact-head release QA candidate.
