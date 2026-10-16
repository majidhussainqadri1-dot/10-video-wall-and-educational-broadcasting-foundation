# File 10 Requirements Traceability

| Requirement | Primary implementation | Automated evidence |
|---|---|---|
| F10-FR-001 Channel management | `VWLB_Videos::create_channel`, channels/member schema | static contract + PHP lint |
| F10-FR-002 Recorded ingest | `VWLB_Media::initiate/complete` | validators and route contract |
| F10-FR-003 Media processing | `VWLB_Jobs`, provider `process_asset` | job retry/dead-letter tests |
| F10-FR-004 Video metadata | `VWLB_Videos::create`, videos schema | DTO/field contract scan |
| F10-FR-005 Captions/transcripts | `VWLB_Videos::add_caption`, caption endpoint | VTT validation contract |
| F10-FR-006 Review/publish | publication gate + optimistic version | state-machine tests |
| F10-FR-007 Playback | provider adapters + player UI | provider/static/JS syntax |
| F10-FR-008 Progress/history | sessions, privacy export/reset | privacy contract scan |
| F10-FR-009 Playlists | ordered unique membership | transaction/version contract |
| F10-FR-010 Live scheduling | `VWLB_Live::schedule` | time/visibility/idempotency |
| F10-FR-011 Stream authorization | step-up, one-time hash-only secret | secret-storage contract |
| F10-FR-012 Live lifecycle | `VWLB_State_Machine`, `transition` | exhaustive state tests |
| F10-FR-013 Viewer authorization | `VWLB_Security::can_view` at playback | visibility contract |
| F10-FR-014 Live moderation | report/decision/kill/slow-mode fields | authorization contract |
| F10-FR-015 Recording/replay | recording job + canonical replay link | queue/lineage contract |
| F10-FR-016 Provider abstraction | interface + four adapters | provider-interface scan |
| F10-FR-017 Reels source contract | integration registry contract | exact contract string |
| F10-FR-018 Discovery/interactions | outbox + File 21/19 contracts | event/interaction scan |
| F10-FR-019 Copyright/takedown | takedown state machine/history | state and reason contract |
| F10-NFR-001 Authorization | native command authorization + IDOR-safe 404 | endpoint scan |
| F10-NFR-002 Privacy | no-store, export, erasure, consent gates | privacy scan |
| F10-NFR-003 Reliability | idempotency/outbox/inbox/retry/dead-letter | queue scan |
| F10-NFR-004 Performance | cursor pagination, limits, indexed schema | query-limit scan |
| F10-NFR-005 Accessibility | captions, keyboard, focus, reduced motion, RTL | CSS/JS scan |
| F10-NFR-006 Observability | audit, trace IDs, diagnostics/health | diagnostics scan |
| F10-NFR-007 Migration/rollback | dbDelta, legacy migration, snapshots | migration scan |
| F10-NFR-008 Operability | System Check, queue inspection, repair/safe mode | diagnostic routes |
| F10-NFR-009 Compatibility | WP/PHP headers and CI matrix | metadata check |
| F10-NFR-010 Localization | text domain, translatable strings, RTL/timezone | localization scan |
