# Release Notes — File 10 1.0.0-rc1

This candidate supersedes the limited `0.2.0` foundation for future implementation review while preserving the original packages and audit history.

## Delivered code scope

- Canonical `VWLB_` namespace, plugin folder and text domain.
- Channels and role-aware channel ownership.
- Ordered playlists with optimistic concurrency.
- Bounded media ingest, validation, processing jobs, retry/dead-letter and derivatives.
- Recorded videos, publication gates, scheduling, captions/transcripts and visibility-aware playback.
- Private progress/history, Like, Dislike and Save.
- Live event scheduling, time zones, rehearsal/readiness/live/interruption/end/replay state machine.
- One-time stream credential display, hash-only persistence, rotation, expiry, revocation and step-up authorization.
- Emergency end, live moderation, recording finalization and canonical replay linking.
- Local, YouTube, Vimeo and custom HMAC provider adapters.
- Copyright/takedown/appeal workflow, privacy export/erasure, audit, outbox/inbox and webhook deduplication.
- Diagnostics, safe mode, reversible repair, legacy migration and controlled cutover warning.
- Responsive, green, RTL-safe and accessibility-oriented public/admin UI.
- Requirements traceability, two review/fix rounds, deterministic package and PHP 8.1/8.3 CI.

## Required before production

Hostinger staging install/upgrade, real provider credentials, real processing/storage/CDN, cross-file integration, browser/device/accessibility, performance, backup/restore, rollback rehearsal and Founder acceptance remain mandatory.
