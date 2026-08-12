# File 10 — Third Fresh Sequential 20-Review Corrective Record

Date: 2026-08-12 (Asia/Karachi)
Repository: `majidhussainqadri1-dot/10-video-wall-and-educational-broadcasting-foundation`
Branch: `fix/file-10-third-fresh-20-review-v1.2.4-rc1`
Frozen starting source HEAD: `308d102d2524a3b0ebbe14576dd1240e5d80f448`
Governing basis: Consolidated Central Master Plan v3.0 + File 10 v1.1 Future Video & Broadcasting Intelligence 24 amendment.

Sequential law: each round reviews only the corrected state produced by the immediately preceding round. A supported defect is fixed, a permanent regression gate is appended, and the complete File 10 automated suite must pass before the next round begins. This is repository/source evidence only; it is not staging/live evidence.

## R01 — DEFECT FIXED
Generic REST idempotency durably stored and replayed successful response bodies for one-time stream credentials, resumable upload tokens, download tokens/URLs and forensic watermark grants. Secret-bearing callbacks are now non-replayable: only a non-secret completion marker is stored and reuse of the same key fails closed instead of replaying the secret/grant.

## R02 — DEFECT FIXED
Ending or emergency-ending a recording-enabled broadcast could report success even when the recording-finalization processing job failed to persist. End transitions now bind state change and recording-job enqueue in one transaction; kill also checks enqueue success, and queue persistence failure returns a stable reconciliation-safe error.

## R03 — DEFECT FIXED
Public recorded-video browse/detail DTOs exposed the internal numeric channel primary key. Public output now replaces it with the channel opaque public identifier.

## R04 — DEFECT FIXED
Public live DTOs exposed the internal numeric replay-video primary key. Viewer output now exposes only `replay_video_public_id`.

## R05 — DEFECT FIXED
Several mutation endpoints returned raw database rows or internal IDs after publish/complete/live/takedown operations. Canonical minimized mutation DTOs now constrain returned fields and keep internal storage/provider metadata server-side.

## R06 — DEFECT FIXED
Live-question moderation used an explicitly numeric REST route and database primary key. Question submission/moderation/listing now use opaque `public_id` identifiers end-to-end.

## R07 — DEFECT FIXED
Additional extended/Future responses leaked internal IDs for chapters, waiting-room attendees, live resources and transcript-index rows. These responses now use public identifiers or stable semantic coordinates only.

## R08 — DEFECT FIXED
Resumable upload initiation created a media asset before private-file/session setup, but setup failure left the asset orphaned in `initiated`. Failed setup now compensates the asset deterministically and fails closed if compensation itself cannot persist.

## R09 — DEFECT FIXED
Expired private-upload cleanup ignored file-unlink and database-update failures and could claim expiration while bytes/state remained. Cleanup now verifies deletion and CAS-bound expiry persistence and audits failure without lying about completion.

## R10 — DEFECT FIXED
A broadcaster could attach a guessed/readable WordPress attachment to a live resource without a File 10 safety/scan authorization boundary. Attachment resources now require both WordPress read authorization and an explicit fail-closed File 10 attachment-safety adapter.

## R11 — DEFECT FIXED
A live resource declared with `rights_status=restricted` was still persisted as `published` and therefore visible. Restricted rights now force restricted resource state.

## R12 — DEFECT FIXED
Public premiere lookup accepted either an opaque ID or a guessed numeric database primary key. Public lookup now resolves only `public_id`.

## R13 — DEFECT FIXED
Creator aggregate writes returned success even when the database write failed, masking analytics/data-quality loss. Metric persistence failure is now explicit and emits a failure signal for operations.

## R14 — DEFECT FIXED
When a processing job entered dead-letter, failure to persist the asset's `failed` state was ignored, potentially leaving the asset stuck in processing while the job appeared terminal. The job now re-enters retry/reconciliation if the asset failure state cannot be proven.

## R15 — DEFECT FIXED
Guarded repair ignored rollback-snapshot failure and Safe Mode option persistence failure. Repair now requires a durable snapshot before mutation and verifies Safe Mode writes.

## R16 — DEFECT FIXED
Activation and repair scheduled critical processing/outbox/reconciliation/cleanup workers without checking WordPress cron scheduling results. All required schedules are now created in WP_Error mode, verified, and propagated fail-closed.

## R17 — DEFECT FIXED
Legacy migration ignored rollback-snapshot failure, per-video insert failure and migration-completion persistence, yet could still mark migration complete. Migration is now transactional for copied rows, snapshot-gated and marker-verified.

## R18 — DEFECT FIXED
Moderation/takedown filing accepted internal numeric target IDs (and could create cases for invalid targets), while moderation decision used a numeric report route. Public surfaces now require opaque verified target/report identifiers; contextual chat targets require a File 17 resolver.

## R19 — DEFECT FIXED
Moderation/copyright restore blindly forced videos to `published` and live events to `scheduled`, losing the actual pre-restriction state and allowing unsafe restoration semantics. Restriction provenance is now captured in the case and restore is case-bound and fail-closed when a safe prior state cannot be proven.

## R20 — DEFECT FIXED
Privacy erasure left durable REST idempotency rows whose scope encoded the user identity (and historical response bodies could predate secret-safe handling), then created a new privacy-audit record keyed by the erased user's raw ID. Erasure now removes matching idempotency rows, anonymizes additional retained reviewer/credential references and writes a non-identifying erasure receipt instead of re-linking the subject.
