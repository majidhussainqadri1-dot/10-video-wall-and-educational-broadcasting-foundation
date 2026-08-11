# File 10 — Requirements Traceability — 1.1.0-rc1

Governing sources: `SSH-PMP-2026-v3.0` plus `SSH-F10-PLAN-2026-v1.0` including the later central harmonization appendix. This file maps source implementation only. Environment acceptance remains a separate gate.

| Requirement | Primary implementation | Automated/source evidence |
|---|---|---|
| F10-FR-001 Channel management | `VWLB_Videos`, `channels`, `channel_members`, channel route | state/static/plan contracts |
| F10-FR-002 Recorded ingest | `VWLB_Media`, `VWLB_Extensions::initiate_resumable/append_chunk/complete_resumable` | private resumable/offset/checksum contracts |
| F10-FR-003 Media processing | `VWLB_Jobs`, provider interface, scan gate, transcoding derivatives, retry/dead | processing/static contracts |
| F10-FR-004 Video metadata | `VWLB_Videos::create`, rights/consent/access metadata | validation/static contracts |
| F10-FR-005 Captions/transcripts | captions table/REST/player tracks; podcast reviewed transcript | caption/static/a11y contracts |
| F10-FR-006 Review/publish | publication gate, expected version, rights/consent/safety gate | state/security contracts |
| F10-FR-007 Playback | provider adapters, chapters, speed/quality/low-bandwidth, keyboard/PIP hooks | JS/a11y/provider contracts |
| F10-FR-008 Progress/history | playback sessions, clear history, export/erase, abuse-filtered public views | privacy/static contracts |
| F10-FR-009 Playlists | ordered unique membership + optimistic version | state/static contracts |
| F10-FR-010 Live scheduling | timezone-aware schedule, capacity, waiting room, reminders, policy | state/plan contracts |
| F10-FR-011 Stream authorization | one-time secret, hash-only persistence, expiry/rotation/revocation, step-up | security/static contracts |
| F10-FR-012 Live lifecycle | `VWLB_State_Machine`, expected-version transitions, provider proof | exhaustive state tests |
| F10-FR-013 Viewer authorization | `VWLB_Security::can_view`, private/entitled policies, secure grants | authorization contracts |
| F10-FR-014 Live moderation | reports, slow mode, Q&A moderation, kill switch, File 17 contextual bridge | authorization/plan contracts |
| F10-FR-015 Recording/replay | consent gate, finalize job, retry/dead, replay lineage | state/job/plan contracts |
| F10-FR-016 Provider abstraction | local/YouTube/Vimeo/custom, health/circuit, explicit failover, portable metadata | provider contracts |
| F10-FR-017 Reels source contract | `file10.reels-source.v1`, 60–600 second server duration, source/safety labels | File 11 fail-closed contract |
| F10-FR-018 Discovery/interactions | versioned events for File 19/21/26 + native like/dislike/save | event/interaction contracts |
| F10-FR-019 Copyright/takedown | takedown state/history + rights-aware download revocation | state/security contracts |
| F10-NFR-001 Authorization | File 00 claims fail-closed, object scope hook default false, IDOR-safe not-found | security contract |
| F10-NFR-002 Privacy | no-store/noindex, private ingest, progress, consent, export/erase, token revocation | privacy contract |
| F10-NFR-003 Reliability | idempotency, CAS upload compensation, outbox/inbox, retry/dead, reconciliation | static/state contracts |
| F10-NFR-004 Performance | cursor/limits, jobs, low-bandwidth derivatives, aggregate creator metrics | bounded-query contracts |
| F10-NFR-005 Accessibility | captions/transcript, 44px targets, focus, RTL, 320px reflow, reduced motion | CSS/JS contracts |
| F10-NFR-006 Observability | provider health, metrics, diagnostics, trace IDs, SLO target registry | observability contract |
| F10-NFR-007 Migration/rollback | base + extension `dbDelta`, compatibility namespace, snapshots, guarded repair | migration/static contracts |
| F10-NFR-008 Operability | System Check, queues, safe mode, repair, circuit reset, cleanup | diagnostics contracts |
| F10-NFR-009 Compatibility | WordPress 7.0+, PHP 8.3+, PHP 8.3/8.4 CI | metadata/CI |
| F10-NFR-010 Localization | English (US) base, translated strings, RTL, timezone-safe live schedule | localization/static contracts |

## Central-plan completion overlay

`F10-CEN-01` is implemented by one File 10 domain for recorded video, channels, podcasts/audio, live, replay, captions/transcripts and low-bandwidth renditions. `AJ-15` is represented by private ingest → scan gate → processor → caption/review/publish; `AJ-16` by schedule → reminder/waiting room → moderation/Q&A → recording consent → replay; `AJ-17` by the File 11 media contract rejecting 59 seconds and accepting only 60–600 seconds, returning source/safety fields and an autoplay-off policy.

Central catalogue ownership is frozen in `VWLB_Contracts::CENTRAL_TRACE`, including native File 10 items CV-107–118, CV-125, CV-127–128 and applicable security/privacy/accessibility/operations requirements; File 11, File 17, File 19/21/26 and File 20/24/25 remain bounded consumers/owners rather than duplicate backends.
