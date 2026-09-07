# File 10 — R106/R107 closure evidence — 2026-09-07

This evidence record belongs to the R101–R120 sequential cycle and follows the mandatory discipline: complete each round read-only, freeze all findings, correct the frozen findings only after review completion, run the complete regression/release QA, and only then begin the next round.

## R106 — Upload, scan and transcode safety

**Review completed before correction.** R106 was performed only after the R105 evidence head was green. The private resumable-upload writer/cleanup path, file-signature/checksum validation, malware/technical validation, processing-worker failure handling, resumable completion override and canonical public-ID boundary were reviewed as one uninterrupted read-only round.

**Frozen findings:** three proven defects were frozen before correction: (1) expired private-upload cleanup could race an in-flight chunk writer; (2) checksum, scanner or external technical-validation exceptions/failures could escape the processing-worker boundary and strand a claimed job instead of following the normal retry/dead-letter path; and (3) a late resumable-completion route override retained permissive public-ID grammar.

**Correction:** private-upload cleanup now coordinates with the canonical writer lock; validation/checksum/scanner failures are contained at the worker boundary and fail closed through normal retry/dead-letter handling; the late resumable-completion route uses canonical prefixed opaque public IDs. The candidate identity advanced to `1.2.17-rc1`.

**Retest:** exact-head File 10 Release QA run `34085674761` was green on PHP 8.3 and PHP 8.4 at HEAD `a1507752f5dad87e7b22e7a4aa4cf714de4aefd3`. Complete suite, R101–R120 regression gate, deterministic package, checksum/archive, source/package parity and artifact publication were green before R107 began. Published artifact ID: `10005125569`.

## R107 — Provider/webhook/secrets/runtime-readiness boundaries

**Review completed before correction.** R107 began only after the R106 exact-head green gate. Provider adapters, webhook verification/integrity, provider compensation paths, stream-credential compensation, late REST overrides, Future playback enrichment, provider-health truth and Future capability/readiness reporting were traversed together without source modification during the review.

**Frozen findings:** five proven defects were frozen before correction: (1) late REST overrides still used permissive identifiers and Future Safety playback enrichment attempted to reuse a redacted/internal ID path; (2) provider live/ingest compensation hooks were not fully inside `Throwable` boundaries; (3) duplicated raw-secret detection missed credential-key variants such as client/suffix secret forms; (4) provider availability could inherit stale `$wpdb->last_error`; and (5) Future capabilities did not clearly separate implementation presence from evidence-backed provider/runtime readiness.

**Correction:** canonical opaque-ID grammar is now enforced on the late overrides; playback enrichment re-resolves the authorized object internally and fails closed on unreadable DB state; provider compensation callbacks are contained inside `Throwable` boundaries; a single recursive raw-secret detector covers common exact and suffix credential keys; provider-health reads reset DB error state and expose a separate readiness projection; Future capabilities explicitly state `implementation_presence_not_runtime_readiness` and return bounded runtime-readiness evidence. R107 received a fresh immutable candidate identity `1.2.18-rc1` rather than reusing the R106 artifact identity.

**Retest reconciliation:** the first post-cleanup exact-head run exposed only a historical R59 status assertion that still expected an obsolete review-boundary phrase. That historical compatibility assertion was rebased without weakening its substantive release-identity checks; this was a regression-harness reconciliation, not a new R108 review.

**Retest:** exact-head File 10 Release QA run `34087336458` completed successfully on PHP 8.3 and PHP 8.4 at HEAD `cdb5aaddb831f61070c0c62c49ee42cf0aa38e48`. The complete automated suite, R101–R120 regression gate, deterministic `1.2.18-rc1` package, checksum/archive verification and package/source parity were green. Artifact `file10-video-wall-live-1.2.18-rc1` was published as artifact ID `10005650563` with uploaded artifact digest `sha256:7607f7c28fb0900991046dfc84538869b9a8b2323fef06030b257cb0bfdfc6b9`.

## Evidence boundary

This record proves repository/source/package automated-QA state only. It does not establish staging acceptance, deployed/live parity or operational readiness. Exact deployed code, live DB/schema and migration state require a separate Live Reality Freeze.
