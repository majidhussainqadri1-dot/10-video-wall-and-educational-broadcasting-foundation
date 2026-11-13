# File10 -> File26 direct owner fetch: read-integrity review (FROZEN) — 2026-11-13

## Immutable baseline
File10 PR #23 exact source SHA `696aa862a67701a68cf076cf734dfb09ed40452b`, after File26 proposal event contract. Governing V12.1 F10-NFR-001/002/003/008, F10-FR-018, F10-FUT-022/024 and STO-09/10. Live WordPress/DB/deployed source unknown; source-only diagnosis.

## Proven source defect F26-FETCH-001 (HIGH)
`VWLB_File26_Connector::fetch_object()` directly called `VWLB_Repository::find()`, then returned `null` both on *legitimate missing/private media* and on *repository read failure*. Modern `VWLB_Repository::find()` explicitly preserves a `read_failed()` signal, but this new connector did not inspect it. A File26 rebuild/consumer might interpret transient owner database failure as deleted or absent media and incorrectly change its derivative index. Exact current owner state is a required precondition for any durable delete decision.

## Proven source defect F26-FETCH-002 (HIGH)
The proposed direct owner fetch had no `self::ready()` check. During File10 safe-mode/migration unavailability, `project()` returned null; degraded runtime could appear indistinguishable from a legitimate missing document. Separately, a rights/consent lookup failure can be recorded by `VWLB_R109_Rights_Consent_Replay_Guard::policy_read_failed()`, but the connector returned null instead of a typed operational error.

## Minimal approved source-only correction
- Distinguish current *legitimate* missing/private (null) from owner DB/policy/migration/safe-mode failures (typed WP_Error).
- Reset and inspect existing repository `read_failed()` around the canonical owner read; catch thrown read/policy exceptions without private diagnostic leakage.
- Recheck `policy_read_failed` after `project`; never turn an operational failure into a deletion verdict.
- Extend current `tests/file10-file26-public-projection.php` with absent, private, DB failure, consent failure and safe-mode cases.
- Exact-head GitHub QA mandatory. No runtime adapter registration, File26 event delivery, new DB tables, merge to main or deployment.

## Status condition
Until real WordPress/File26 staging and owner-versioned tombstone delivery/replay are accepted by Founder, the File26 manifest stays `proposed` and `owner_outbox_tombstone_gate_pending`. File26 PR #25 disposable tests are separate from real staging/Live.
