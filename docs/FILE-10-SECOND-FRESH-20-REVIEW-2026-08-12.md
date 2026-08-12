# File 10 — Second Fresh Sequential 20-Review Corrective Record

Date: 2026-08-12 (Asia/Karachi)
Repository: `majidhussainqadri1-dot/10-video-wall-and-educational-broadcasting-foundation`
Branch: `fix/file-10-fresh-20-review-v1.2.3-rc1`
Frozen starting source HEAD: `38b3705d4947037e5e4407fffaf7f5904a0af46c`
Governing basis: Consolidated Central Master Plan v3.0 + File 10 v1.1 Future Video & Broadcasting Intelligence 24 amendment.

Sequential law: each round reviews only the corrected state produced by the immediately preceding round. A supported defect is fixed and the full File 10 automated suite must pass before the next review begins. This ledger is repository/source evidence, not staging/live evidence.

## R01 — DEFECT FIXED
Database transaction wrappers could report domain success after `START TRANSACTION` or `COMMIT` storage failure, while rollback snapshots returned an insert ID without proving persistence. Transaction start/commit and snapshot persistence now fail closed with stable errors before a caller can treat the operation as durable.

## R02 — DEFECT FIXED
Activation ignored rollback-snapshot failure, tolerated individual page-creation failures, and did not verify page-map persistence. Page setup now requires a durable pre-mutation snapshot, fails closed on page or mapping persistence failure, compensates pages created by the failed activation attempt, and propagates the error to activation before scheduling/version success. The first R02 regression-gate draft itself expanded a shell variable inside the grep pattern; that QA-only defect was corrected within R02 before product changes were accepted.

## R03 — DEFECT FIXED
The migration lock stored only a timestamp. After TTL expiry a second upgrader could take over, while the first upgrader's unconditional `finally` deletion could then remove the new owner's lock and permit overlapping schema work. The lock now carries a unique owner token, stale takeover uses an exact value compare-and-delete, and release removes only the lock owned by the current upgrader. A historical whitespace-sensitive static assertion was updated within R03 after it rejected the semantically stronger fail-closed code.

## R04 — DEFECT FIXED
Multi-camera production source and scene edit APIs performed a server-side CAS, but they re-read the newest row and applied the caller's stale payload without requiring the caller's expected version. A stale operator screen could therefore overwrite a newer operator change. Existing-row edits now require the caller's current version and use that exact version in the conditional update; missing/stale versions fail with 409 before mutation. The first R04 static assertion accidentally expanded a shell variable under `set -u`; that QA-only defect was corrected within R04 before accepting the product change.

## R05 — DEFECT FIXED
Simulcast target edits had the same stale-client overwrite class as production source/scene edits: the server refreshed the latest row and then applied the caller payload without proving the caller had edited that version. Existing target edits now require the submitted current version and use it as the conditional update token; stale/missing versions fail before mutation.

## R06 — DEFECT FIXED
The simulcast transition reserved local state as `transitioning`, but both provider-error branches ignored whether the subsequent local `failed` state write succeeded. A database/CAS race could therefore strand the target in `transitioning` while the caller saw only the provider error. Failure-state writes are now version/status-bound and verified; if File 10 cannot persist the provider failure truth, the API returns an explicit reconciliation-required error instead of masking local divergence.

## R07 — DEFECT FIXED
The public live-poll answer path accepted either the opaque option `public_id` or a guessed numeric database primary key. Public DTOs intentionally hide internal IDs, so accepting them reintroduced a guessable identifier path and weakened the object-identity boundary. Poll answers now resolve only the option public ID within the current poll; internal numeric option keys remain server-side implementation details.

## R08 — DEFECT FIXED
Future translation, dubbing, audio-description and sign-language records could be created, reviewed and published, but neither the recorded-video playback response nor the live viewer state exposed any approved track delivery contract. Published tracks therefore had no canonical viewer handoff. File 10 now exposes only `published` auxiliary tracks through a minimized DTO, never leaks provider references/metadata, permits a provider adapter to resolve a public/signed track reference, and adds the safe track set to recorded playback and live state responses.

## R09 — DEFECT FIXED
The public annotation read path exposed both `reviewed` and `published` records. Review completion is not publication, so a citation, correction, overlay or knowledge-link could become externally visible before its explicit publish transition. Public annotation reads now return only `published` records; reviewers may still request candidate/reviewed states through the authorized review path.

## R10 — DEFECT FIXED
The public poll read contract checked event visibility but did not check the poll lifecycle state. Anyone who obtained an opaque poll identifier could therefore read a `draft` poll before the broadcaster explicitly opened it. Viewer reads are now limited to `open` or `closed` polls; only an authorized broadcaster may preview another state.

## R11 — DEFECT FIXED
Consent-link updates locked the latest database row and used a server-side CAS, but did not require the caller to prove which version it had reviewed. A stale reviewer screen could therefore overwrite a newer consent decision. Existing consent records now require the submitted current version and reject stale/missing versions before mutation. The first R11 regression assertion expanded a shell variable under `set -u`; that QA-only defect was corrected inside R11 before the product correction was accepted.

## R12 — DEFECT FIXED
An `active` consent link could be saved with an expiry timestamp already in the past, leaving the video available until a later reconciliation run. Active consent now requires a future expiry; explicitly expired/withdrawn states retain immediate restriction semantics.

## R13 — DEFECT FIXED
Creating a timestamp correction emitted `VideoTimestampCorrectionPublished` while the new annotation was only `reviewed`. Downstream consumers could therefore receive a false publication fact. The correction-specific event now fires only when the annotation actually transitions to `published`.

## R14 — DEFECT FIXED
Public annotation responses decoded and returned arbitrary `metadata_json`, although that field is not a public schema and is only secret-scanned. Internal provenance/provider/workflow details could leak. Metadata is now returned only to an authorized reviewer request; public DTOs contain only explicit public annotation fields.

## R15 — DEFECT FIXED
Published auxiliary-track DTOs used the stored `file_ref` itself as the default public URL. Without a delivery adapter, a storage/provider reference could be returned directly. The resolver now defaults to an empty value and must explicitly return a viewer-safe public/signed URL; otherwise the track remains unavailable rather than leaking its stored reference.

