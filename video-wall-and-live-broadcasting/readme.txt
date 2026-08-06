=== Video Wall and Live Broadcasting ===
Contributors: sabrihomeopathy
Requires at least: 6.0
Tested up to: 7.0.1
Requires PHP: 8.1
Stable tag: 1.0.0-rc1
License: GPLv2 or later

File 10 canonical owner for recorded video and live broadcasting in the Sabri Social Homeopathy Platform.

== Description ==

This release-candidate implements the approved File 10 code baseline: channels, playlists, bounded media ingest, processing jobs, recorded-video lifecycle, captions/transcripts, visibility-aware playback, private progress/history, Like/Dislike/Save, live scheduling, step-up protected stream credentials, live lifecycle, emergency end, recording/replay orchestration, provider adapters, moderation, copyright/takedown, audit/outbox/inbox, privacy export/erasure, diagnostics, repair, migration and versioned integration contracts.

The plugin keeps canonical truth in File 10 and exposes versioned contracts to Files 00, 01, 11, 17, 19, 20, 21, 22, 23, 24, 25 and 26. It does not create a duplicate messaging backend, Reels feed, global shell, notification transport or profile system.

== Provider configuration ==

Local, YouTube, Vimeo and custom adapters are registered. External provider credentials must not be committed to GitHub. Configure custom provider secrets in wp-config.php:

`define( 'VWLB_CUSTOM_INGEST_BASE', 'https://ingest.example.test/' );`
`define( 'VWLB_CUSTOM_WEBHOOK_SECRET', 'replace-outside-source-control' );`

YouTube/Vimeo live creation, ingest and webhook verification are connected through explicit filters so production credentials remain outside the plugin repository.

== Security ==

* Object, field, state and ownership checks are repeated in command services.
* Stream keys are displayed once and stored only as password hashes.
* Step-up authorization is required for key issue, go-live, end and emergency kill.
* REST mutation permission cannot be changed by request parameters.
* Public/private DTO allowlists prevent row or credential disclosure.
* Upload size/MIME, URL host, state transition, version and idempotency controls are enforced.
* Provider webhooks require adapter signature verification and provider-event deduplication.
* Private history/studio surfaces use no-store/noindex controls.
* Audit, outbox, rollback snapshots, bounded retries, dead-letter and reconciliation are included.

== Accessibility and localization ==

Keyboard-visible focus, 44px controls, semantic status regions, captions, responsive layout, reduced-motion support, RTL-safe logical spacing and English (US) source strings are included. Urdu/Arabic translations may be supplied as standard WordPress language packs.

== Installation ==

1. Back up the approved staging environment and verify restoration.
2. Upload `video-wall-and-live-broadcasting-1.0.0-rc1.zip` to WordPress staging.
3. Activate the plugin; schema, capabilities, pages and cron jobs are created idempotently.
4. Configure File 00 claims/capabilities and provider adapters.
5. Run Video & Live > Diagnostics.
6. Complete every item in `docs/STAGING-ACCEPTANCE-1.0.0-rc1.md` before production.

== Upgrade ==

The activation migration reads a legacy `svw_videos` table when present, imports unmapped records once, stores rollback evidence and leaves the legacy plugin untouched until controlled cutover. Do not run both public route owners after acceptance.

== External acceptance boundary ==

Source code, deterministic packaging and automated static/unit checks do not prove Hostinger/LiteSpeed runtime, real provider credentials, actual transcoding, browser/device accessibility, high-concurrency streaming, backup restoration, rollback or Founder acceptance. Those are explicit staging gates and must not be represented as complete until evidence exists.

== Changelog ==

= 1.0.0-rc1 =
* Canonical File 10 architecture and namespace.
* Full recorded-video and live-broadcasting domain model.
* Channels, playlists, assets, jobs, captions, playback, history and interactions.
* Live schedule, credentials, lifecycle, moderation, recording/replay and provider adapters.
* Copyright/takedown, privacy, audit, outbox/inbox, diagnostics, repair and migration.
* Two code-review/fix rounds and deterministic package evidence.
